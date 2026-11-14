# Contributing to MoviePlz

Thanks for helping. MoviePlz is a live app with real people's libraries
in it, and a merged pull request is deployed to production straight away:
the app loads its screens from the server, so there's no store review in
between. These rules exist because of that.

## How a change gets in

1. Open an issue first for anything bigger than a fix, so we can agree on
   it before you build it.
2. Fork, branch, and open a pull request against `main`.
3. CI runs the test suite. It has to pass.
4. The maintainer reviews it. Nothing merges without their approval.
5. Merging to `main` deploys to movieplz.hypeapps.dev. People see the
   change the next time a screen loads. If the start screen breaks, the
   deploy rolls itself back (docs/deploy.md).

## The rules

- **Keep pull requests small.** One fix or one feature per pull request.
  A change that's easy to read is easy to approve.
- **Tests must pass**, and new behavior needs a test. Run `php artisan
  test` before you push. Feature tests in `tests/Feature/` show how
  screens are tested: request the `.ham` URL, then assert on the HAM.
- **Migrations never lose data.** A migration that drops a column or table,
  or rewrites existing rows, needs a working `down()` and a note in the
  pull request saying what happens to existing data. Adding columns and
  tables is fine.
- **No new third-party services.** No new APIs, analytics, tracking, ad
  SDKs or remote assets. MoviePlz talks to The Movie Database, Google Sheets
  (a sheet the host links), Apple Push Notification service and Firebase
  Cloud Messaging, and the privacy answers in the stores depend on that
  list. New Composer packages need a reason in the pull request.
- **No secrets.** Don't commit keys, tokens, `.env` files or real people's
  data. Tests fake every outside service (`Http::fake()`).
- **Don't change the native side in a server pull request.** `hype.toml`,
  icons, splash and permissions need a store release; see "What needs a
  store release" in the README. Open an issue for those.

The maintainer has to review some paths before they can be merged:
migrations, config, routes, `composer.json`, the CI workflow, `hype.toml`
and `store/` (see `.github/CODEOWNERS`).

## Writing screens

Screens are HAM inside Blade templates in `resources/views/ham/`. Read
`docs/ham-reference.md`, and the "gotchas" in `CLAUDE.md`, before your
first template. The ones that bite most often:

- Write `@@show`, not `@show`, in a `.blade.php` file. Blade eats `@show`.
- Blade's `{{ $x }}` runs on the server; HAM's `$x` runs on the phone.
  Don't mix them up in `@text` or `@click`.
- `<flex>` is one row; extra children are dropped.
- Self-closing tags need the slash: `<hr/>`, `<input …/>`.
- HAM routes always answer HTTP 200, errors included; the error is a HAM
  block the screen can show.

Keep the app's voice: short, plain sentences that say what happens. Use
American spelling.

## Reporting a security problem

Don't open a public issue. Email the address on
https://movieplz.hypeapps.dev/support instead.
