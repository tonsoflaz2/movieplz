# Deploying

A push to `main` deploys movieplz.hypeapps.dev through Laravel Forge. The
deploy script lives in Forge, not in this repository, so a pull request
can't change it. The copy below is the reference, and the maintainer pastes
it into Forge → movieplz.hypeapps.dev → Deployments → Deploy script.

What it does, in order:

1. Notes the commit that's serving now.
2. **Fetches `main` and moves to it exactly** (`git fetch` then
   `git reset --hard FETCH_HEAD`), rather than `git pull`. The server then
   always matches GitHub, even when the history it had is unrelated (the
   move from Bitbucket to a fresh GitHub history) or has diverged. Files
   git doesn't track, like `.env` and `storage/`, are left alone.
3. Runs `composer install`.
4. **Migrates only when there's something to migrate.** It asks Laravel
   (`migrate:status --pending`) and runs `migrate --force` only if a
   migration is pending.
5. Reloads PHP-FPM.
6. **Health check.** Signed out, `/up` must answer 200, and `/app.ham`'s
   body, trimmed, must start with `<app` and end with `</app>`. It tries 3
   times, 3 seconds apart. A broken start screen still answers 200 (HAM
   routes always do), but its body is the error block, so it fails.
   `tests/Feature/DeployHealthTest.php` keeps the app to this contract.
7. **On any failure:** it resets the code to the commit that was serving,
   reinstalls, reloads, checks again, and exits 1. Forge then marks the
   deploy failed and sends its failure notification.

What a rollback doesn't undo:

- **Migrations that already ran stay.** The previous code then runs on the
  new schema. That's fine for added columns and tables, which is why
  CONTRIBUTING.md asks for non-destructive migrations. A migration that
  failed halfway on MySQL (DDL isn't transactional) needs a look by hand.
  There are no automatic backups, so review migrations closely before
  merging.
- **`main` on GitHub still has the bad commit.** Revert it with a pull
  request, and the next deploy runs normally.

The script is written for Forge's standard (in-place) deploys. If the site
has **zero-downtime deployments** turned on, it needs adapting: the
rollback becomes "activate the previous release".

## The script

```bash
# MoviePlz deploy (docs/deploy.md). Matches the server to origin's branch,
# migrates only when a migration is pending, health-checks, rolls back.
set -uo pipefail
cd "$FORGE_SITE_PATH" || exit 1
URL="https://movieplz.hypeapps.dev"

reload_fpm() {
    ( flock -w 10 9 || exit 1
      echo 'Restarting FPM...'; sudo -S service "$FORGE_PHP_FPM" reload ) 9>/tmp/fpmlock
}

healthy() {
    for try in 1 2 3; do
        sleep 3
        if curl -fsS --max-time 15 -o /dev/null "$URL/up" &&
           curl -fsS --max-time 15 "$URL/app.ham" |
             $FORGE_PHP -r '$b = trim(stream_get_contents(STDIN)); exit(str_starts_with($b, "<app") && str_ends_with($b, "</app>") ? 0 : 1);'
        then
            return 0
        fi
        echo "Health check $try of 3 failed."
    done
    return 1
}

PREV=$(git rev-parse HEAD)

rollback() {
    echo "ROLLING BACK to $PREV: $1"
    git reset --hard "$PREV"
    $FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    reload_fpm
    if healthy; then
        echo "Rolled back: $PREV is serving and healthy."
    else
        echo "STILL UNHEALTHY after the rollback. Look now: storage/logs/laravel.log"
    fi
    exit 1
}

git fetch origin "$FORGE_SITE_BRANCH" || { echo "git fetch failed; nothing changed."; exit 1; }
git reset --hard FETCH_HEAD || rollback "git reset to the fetched commit failed"
NEW=$(git rev-parse HEAD)
echo "Deploying $PREV -> $NEW"

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader ||
    rollback "composer install failed"

$FORGE_PHP artisan migrate:status --pending=3 > /dev/null 2>&1
case $? in
    0) echo "No pending migrations: not migrating." ;;
    3) echo "Pending migrations: migrating."
       $FORGE_PHP artisan migrate --force || rollback "migrate failed; check the schema by hand" ;;
    *) rollback "migrate:status failed: is the database reachable?" ;;
esac

reload_fpm
healthy || rollback "/up or /app.ham failed the health check"
echo "Deployed $NEW: healthy."
```

## Checking it

- Run it once with nothing new to deploy, and you should see "No pending
  migrations" and "healthy".
- To see a rollback without breaking anything, temporarily change `URL` to
  a path that 404s, deploy, and watch it roll back. Then put `URL` back.
