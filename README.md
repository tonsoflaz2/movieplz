# MoviePlz

MoviePlz is a private app for suggesting movies and series to a friend.
One person, the host, has a library: the films and shows they own or can
watch. Friends join it with a five-digit code the host reads to them. They
browse the library, ask for something to watch, and suggest titles the host
doesn't have yet. The host sees every suggestion, marks it "up for it" or
"saw it", and the friend gets a notification.

There are no accounts, passwords or email addresses. A person is a name
inside one host's library.

MoviePlz is on the
[App Store](https://apps.apple.com/us/app/movieplz/id6762552708) and
[Google Play](https://play.google.com/store/apps/details?id=com.hypeapps.movieplz).

## How it works

This repository is the whole app. It's a Laravel server, and every screen
is written here in **HAM** (HypeApps Markup), an HTML-like markup for
native mobile UI. The apps in the stores are a thin native shell (the
[hypeapps](https://hypeapps.dev) engine) that fetches HAM from this server
and draws it with native iOS and Android widgets.

```
phone (native shell)  ── GET /app.ham ──▶  Laravel (this repo)
                      ◀── HAM markup ───   routes → controllers → Blade views
```

- Screens live in `resources/views/ham/` as Blade templates that output HAM.
- Blade runs on the server once per request; HAM's own reactive variables
  (`var-x`, `$x`, `@show`, `@click`) run on the phone.
- Buttons and forms fetch more HAM from server routes and swap it into the
  screen (`action="/host/tbw.ham" target="#main"`).

Because the screens come from the server, **a merged change is live in the
app as soon as it's deployed to movieplz.hypeapps.dev**. People get it the
next time a screen loads, with no store update.

### What needs a store release instead

Some things are compiled into the native shell, so changing them only
reaches people through a new App Store and Google Play version, which the
maintainer builds and submits:

- `hype.toml`: the app's name, bundle id, version, engine, Firebase
  settings and the permission strings in `[permissions]`;
- the app icon and splash (`store/icon.png`, `store/splash-logo.png`, the
  splash color);
- native permissions and capabilities (camera, location, notifications
  entitlements and so on);
- new HAM tags or engine behavior: those are in the hypeapps engine, not
  here.

The store listing (`store/listing.json`, screenshots) changes only when the
maintainer sends it.

## Running it locally

You need PHP 8.3+, Composer, and SQLite.

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed                      # a host, friends, a library and an invite code
php artisan serve                        # http://127.0.0.1:8000
```

Check a screen with curl: `curl http://127.0.0.1:8000/app.ham` prints the
HAM the phone would get.

To see the screens rendered, use one of these:

- **Shipyard**, the free hypeapps Mac app: open this folder and run it in a
  simulator or Hype Preview.
- **The `hype` CLI**: `hype preview` (a phone-sized window on a Mac) or
  `hype run` (an iOS Simulator or Android emulator). These commands are part
  of Hype Apps Pro.

`hype.toml`'s `[env] dev` is the URL the dev app loads. It's
`http://movieplz.test/app.ham` (Laravel Herd); point it at your own server
in your copy, and leave that change out of pull requests.

### The Movie Database

Posters and descriptions come from [TMDB](https://www.themoviedb.org/).
Leave `TMDB_READ` empty and the app works without them: titles have no
poster, and "Change poster" says The Movie Database couldn't be reached. To see
posters, put your own TMDB read access token in `TMDB_READ`. The tests never
call TMDB; they use `Http::fake()`.

### Notifications

Push needs Apple and Firebase keys that only production has. Locally,
without them, nothing is delivered; every notification still leaves one
log line saying it was sent, failed or skipped, and why.

## Tests

```sh
php artisan test
```

The suite runs on in-memory SQLite with every outside service faked. It
runs on each pull request, and it must pass before a change is merged.

## Where things are

| Path | What it is |
|---|---|
| `routes/web.php` | Every URL the app and the website use |
| `app/Http/Controllers/` | Host, friend (`Client`), onboarding and account screens |
| `resources/views/ham/` | The app's screens (HAM in Blade) |
| `resources/views/site/` | The website pages: `/privacy`, `/support` |
| `app/Services/PushService.php` | APNs and FCM notifications |
| `app/Support/` | Invite and reconnect codes, Google Sheet import, TMDB matching |
| `database/migrations/` | The schema |
| `docs/ham-reference.md` | The HAM reference: tags, attributes, styles, actions |
| `docs/spec.md`, `docs/plan.md` | The original design |
| `store/` | Store listing, screenshots, icons, review notes and the release log |
| `CLAUDE.md` | Notes for AI coding assistants, including HAM's gotchas |

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). By taking part you agree to the
[code of conduct](CODE_OF_CONDUCT.md).

## License

MIT. See [LICENSE](LICENSE).
