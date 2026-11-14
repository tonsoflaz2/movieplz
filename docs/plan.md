# MoviePlz — Implementation Plan

A one-shot, executable plan derived from `/Users/fluency/Code/movieplz/docs/spec.md`, Blade/HAM rules in `/Users/fluency/Code/movieplz/CLAUDE.md`, the HAM reference, and the framework source under `/Users/fluency/Apps/hypeapps/lib/src/`. Spec section §9 blockers are resolved first, then the rest.

---

## 1. Blocker resolutions (spec §9)

### 1a. Secure-storage device-token action — RESOLVED via session cookies, not headers

**The spec's design (`X-Client-Token` header + a HAM "store this in keychain" action) does not exist in the framework, and trying to invent it is unnecessary.** The framework already does the equivalent, automatically, using cookies.

Evidence from `/Users/fluency/Apps/hypeapps/lib/src/controls/network_manager.dart`:

- **Line 6**: `import 'package:flutter_secure_storage/flutter_secure_storage.dart';`
- **Lines 18, 21, 28–48**: Cookies are stored in memory (`_cookies`), serialized to JSON, persisted via `FlutterSecureStorage` under key `hype_session_cookies`, and **restored on first request after every cold launch**.
- **Line 105 (`get`)**, **125 (`post`)**, etc.: Every request first calls `_ensureCookiesRestored()` and then `_buildHeaders()`.
- **Lines 576–581 (`_buildHeaders`)**: When cookies are non-empty, the framework joins them as `name=value; name2=value2` and sets the standard `Cookie:` header.
- **Lines 593–613 (`_extractCookies`)**: Every response's `Set-Cookie` header is parsed and added to `_cookies`, then `_persistCookies()` writes back to secure storage.
- **Lines 680–687 (`clearCookies`)**: Wipes both memory and secure storage. Already exposed via `AuthManager.logout()` (`auth_manager.dart:80–84`).

There is **no HAM-level "store this value" action**, and **no way to set arbitrary custom headers from a Blade-rendered HAM template** — `_buildHeaders` is hard-coded. I searched the HAM reference (`/Users/fluency/Code/movieplz/docs/ham-reference.md`) for `header`, `cookie`, `token`, `storage`, `secure`, `persist`, `keychain`, `set:`, `store:` and found nothing relevant. The only `X-` headers present are framework-managed (`X-Device-Id`, `X-Device-Platform` at lines 558–574).

**Decision**: drop the "device token + custom header" model from the spec and use **standard Laravel session cookies**. Laravel 13's default session driver is already `database` (`/Users/fluency/Code/movieplz/config/session.php:21`), and the `sessions` table already exists (`/Users/fluency/Code/movieplz/database/migrations/0001_01_01_000000_create_users_table.php:30–37`).

The flow becomes:
1. Onboarding `POST /onboarding/name` creates the `clients` row and stores `session(['client_id' => $client->id])`.
2. Laravel sets the `movieplz_session` cookie in the response, the framework's `_extractCookies` captures it, persists it.
3. Every subsequent request from the same shell carries the cookie automatically; `RequireClient` middleware reads `session('client_id')` and resolves the `Client` model.
4. "Revoke" sets `clients.revoked_at`; the next request finds the client revoked, `forget('client_id')`, `regenerate()`, and renders the invite-entry template. The framework will pick up the new session cookie and forget the old one. No HAM-level cooperation needed.

We keep the `clients.device_token` column **only as a server-side audit/debug field** (a random 40-char string, not used for auth). It's a useful "this row was the one redeemed by that phone" tag if we ever add observability, but the auth itself rides on Laravel sessions.

This is, in effect, exactly the spec's intent — "an opaque secret stored on the device, sent on every request" — except the secret is the session ID and the storage is `flutter_secure_storage` (not a cookie jar in RAM). Behaviorally identical, vastly simpler.

**Trade-off accepted**: a session cookie has a TTL (`config/session.php` `lifetime` = 120 min by default). We bump this to **5 years** in `config/session.php` and rely on the host's "revoke" button, matching the spec's "no expiry, host revokes" semantics.

### 1b. HAM polling tag — RESOLVED, native support

Reference: `docs/ham-reference.md:1309` lists `every {n}s` as a valid `trigger=`. Confirmed in the framework source at `/Users/fluency/Apps/hypeapps/lib/src/controls/hypermedia_controls.dart:1735–1745` — when an element has `trigger="every Ns"` and `action=`, the framework calls `Timer.periodic` and refires the fetch every interval. Working example in showcase: `/Users/fluency/Apps/showcase_app/assets/html/showcase/demo_triggers.ham:127`:
```html
<box id="timer-2s" trigger="every 2s" action="file:/showcase/r_timer_tick.ham" style="...">
```

For MoviePlz we'll use server URLs:
```html
<box id="unread-badge" trigger="every 30s" action="/host/unread_count.ham" target="this" swap="inner">
  …badge HAM…
</box>
```

The framework caps minimum interval at 100ms (`demo_triggers.ham:156`) and **auto-cancels timers when the element is removed by an outer swap** (`demo_triggers.ham:157`, confirmed at `hypermedia_controls.dart:1888`). So we get cleanup for free when the user navigates away.

**Decision**: 30s polling interval (defensible compromise — short enough to feel near-real-time, long enough not to drain battery). This is a Risk item — see §8.

### 1c. Form → partial swap with status codes — RESOLVED with workaround

The framework **does not differentiate 422 from 500 from 404**. Evidence at `/Users/fluency/Apps/hypeapps/lib/src/controls/network_manager.dart:617–639` (`_handleResponse`):

```dart
if (response.statusCode >= 200 && response.statusCode < 300) {
  // ... return body as HAM
} else {
  return _handleError('HTTP ${response.statusCode}: ${response.reasonPhrase}');
}
```

`_handleError` (lines 643–645) returns a hard-coded red-icon error placeholder, **completely discarding the response body**. So a Laravel `ValidationException` (which Laravel renders as a 422 with a HAM body) would show the framework's generic error box. **Same problem if Laravel's debug page leaks**: lines 622–633 sniff for `<!DOCTYPE`/`<html>` even on 2xx responses and treat them as errors.

**Decision**: never let a non-2xx response reach the shell from a HAM route. All HAM controllers always return **HTTP 200** with HAM markup, including for validation errors.

Three implementation pieces:

1. **Custom validation pattern**. Don't use `$request->validate(...)` (which throws `ValidationException` → 422). Instead, use `Validator::make(...)` and on `->fails()`, return `view('ham.partials.invite_form_error', ['errors' => $validator->errors(), 'old' => ...])` with status 200. Or define a `respondWithErrors($errorsView, $errors)` helper on the base controller.

2. **Override Laravel's exception renderer for `*.ham` and HAM-content routes**. In `bootstrap/app.php` `withExceptions(...)`, register a renderer that, when the request matches `*.ham` or matches a path under `/onboarding`, `/host`, `/library`, `/requests`, returns `view('ham.partials.error', ['message' => …])` with status 200. This way, even unanticipated server errors render as a clean HAM error block, not a Laravel debug page.

3. **Inline HAM error template**. `resources/views/ham/partials/error.blade.php` that produces a small `<box>` with a red icon and error text. Looks like the spec's intent without the status-code dependency.

For inline form errors, the partial returned for the failure case is just the form re-rendered with an `<text style="text-red font-sm" @if($errors->has('code'))>{{ $errors->first('code') }}</text>` block (Blade conditional on errors collection, server-side, before the response goes out).

**Auxiliary note: client-side validation as a first pass.** The framework supports `<input required min-length="9" max-length="11" pattern="..." error="Invalid code format"/>` (`form_builders.dart:228–272`). For onboarding we do client-side format validation (regex `^[0-9A-HJ-NP-Z]{2}-[0-9A-HJ-NP-Z]{3}-[0-9A-HJ-NP-Z]{3}$`), and the server only validates "does this code exist + is it usable". So most of the time the user gets immediate feedback without a roundtrip.

### 1d. Remote poster image loading — RESOLVED, cheap

Reference: `docs/ham-reference.md:823–838` documents `<img>` / `<image>` with `src="https://example.com/logo.png"` and `loading="fade"` as the default for network sources. Image cache is built into the framework (`<config cache_max_age="..." cache_max_size="..." cache_medium_width="..." cache_small_width="...">` at `ham-reference.md:185–189`), and `image_cache_manager.dart` + `cached_image_provider.dart` exist in the framework source (`/Users/fluency/Apps/hypeapps/lib/src/controls/`). So poster URLs in `library_items.poster_url` would be rendered with native disk caching at zero implementation cost. **Stretch §8 row 1 is cheap on the framework side** — the only friction is the TMDB enrichment job, which is the actual deferred work.

### Summary of resolutions

| Blocker | Resolution |
|---|---|
| Secure storage / device token header | Use Laravel session cookies; framework already persists cookies via `FlutterSecureStorage`. Drop the `X-Client-Token` design entirely. |
| Polling tag | `trigger="every 30s"` on the badge `<box>`, native support since `hypermedia_controls.dart:1735`. |
| Form 422 + partial | Always return 200 from HAM routes; render error HAM inline. Override the global exception handler to return 200 HAM for HAM routes. |
| Poster image loading | Native `<img src="https://...">` works; image cache is built-in. v1 doesn't need this — stretch ready when TMDB enrichment is added. |

---

## 2. Library / dependency choices

| Decision | Choice | Why |
|---|---|---|
| Composer additions | **None** | Zero new packages. Laravel 12 + the existing dev-tools are enough. |
| Invite code format | 5 numeric digits (`00000`–`99999`), `str_pad(random_int(0, 99999), 5, '0', STR_PAD_LEFT)` | Short enough to read aloud over a phone call. 100K combinations combined with single-use + 10-minute expiry + rate limiting is safe. Uniqueness enforced by a DB unique constraint; regenerate on collision (rare). |
| Invite lifetime | Hard-coded 10 minutes | Long enough for a phone call / text, short enough to minimize the brute-force window. |
| Invite redemption rate limiting | `throttle:5,1` on `POST /onboarding/invite` | Laravel's built-in `ThrottleRequests`, 5 attempts/min/IP. Defends against guessing. |
| CSV parsing | `fgetcsv()` | Pure stdlib. Small CSVs; no streaming framework needed. Idempotent upsert via `LibraryItem::updateOrCreate(['host_id', 'title', 'year'], [...])`. |
| Device token | Random 40-char string via `Str::random(40)`, stored plaintext in `clients.device_token` | **Not used for auth** (sessions are). Audit field. |
| Auth gating | Three custom middleware classes: `RequireClient`, `RequireHost`, `RequireGuestClient`, registered in `bootstrap/app.php` `withMiddleware(...)` | No Sanctum — Laravel sessions handle the cookie. Middleware resolves `session('client_id')` to a model and branches on `role` and `revoked_at`. |
| Host bootstrap | `php artisan host:mint-code` | Creates an invite row with `grants_host=true`, outputs the 5-digit code. Errors if a host already exists. Same onboarding UX as clients — the flag just tells `NameController` to create the `hosts` row and set `role='host'`. Zero env vars. |
| Fake movie data | A hardcoded JSON fixture at `database/fixtures/library_seed.json` (~80 well-known titles with year/type) | Faker has no movie locale. Network-fetching at seed time is forbidden. Static list is simplest. |
| CSV sample fixture | `database/fixtures/library_sample.csv` | Lets the artisan import command be tested independently of the seeder. ~20 rows. |
| Test runner | Default `phpunit` | Spec says no Pest. |
| Routing helpers | Resource controllers where natural; closures for trivial routes | No magic. |

---

## 3. File inventory (full tree, grouped by phase)

All paths absolute under `/Users/fluency/Code/movieplz/`. New files unless noted as MODIFIED.

### Phase 0 — config + scaffold

```
config/session.php                                          MODIFIED — lifetime → 5y, cookie name → "movieplz_session"
config/app.php                                              MODIFIED — name → "MoviePlz"
bootstrap/app.php                                           MODIFIED — register middleware aliases + HAM exception renderer
.env                                                        MODIFIED — APP_NAME, SESSION_LIFETIME=2628000 (5y in min)
.env.example                                                MODIFIED — same
```

### Phase 1 — data model + seed host + library import

```
database/migrations/2026_04_09_000001_create_hosts_table.php
database/migrations/2026_04_09_000002_create_invites_table.php
database/migrations/2026_04_09_000003_create_clients_table.php
database/migrations/2026_04_09_000004_create_library_items_table.php
database/migrations/2026_04_09_000005_create_requests_table.php

app/Models/Host.php                                         hasMany clients, invites, libraryItems, requests
app/Models/Invite.php                                       belongsTo host; hasMany clients; scopes: usable()
app/Models/Client.php                                       belongsTo host, invite; hasMany requests; scopes: active()
app/Models/LibraryItem.php                                  belongsTo host; full-text scope search()
app/Models/MediaRequest.php                                 belongsTo host, client, fulfilledLibraryItem (table = "requests")
                                                            (named MediaRequest to avoid clashing with Illuminate\Http\Request)

app/Support/InviteCode.php                                  generate(), normalize(), looksValid()
app/Support/SortTitle.php                                   from(string $title): string  ("The Matrix" → "Matrix, The")

database/seeders/DatabaseSeeder.php                         MODIFIED — calls HostSeeder and (in dev) DemoSeeder
database/seeders/HostSeeder.php                             one Host row + one default Invite + one always-on Host "client" record
database/seeders/DemoSeeder.php                             ~50 library_items, ~5 clients, ~10 requests; only runs in non-prod
database/factories/HostFactory.php
database/factories/InviteFactory.php
database/factories/ClientFactory.php
database/factories/LibraryItemFactory.php
database/factories/MediaRequestFactory.php

database/fixtures/library_seed.json                         hardcoded list of ~80 titles for the demo seeder
database/fixtures/library_sample.csv                        ~20-row sample CSV for testing the artisan command

app/Console/Commands/LibraryImport.php                      `php artisan library:import {csv} {--host-id=}` — idempotent upsert
app/Console/Commands/HostInviteIssue.php                    `php artisan host:invite [--max-uses=] [--expires=]` — emits a code on stdout
                                                            (the spec hints at "manual DB insert is fine"; this is the polished version)

routes/console.php                                          MODIFIED only if we want a closure command instead of full class
```

### Phase 2 — onboarding + auth middleware

```
app/Http/Middleware/RequireClient.php                       resolves session('client_id') → Client; aborts to onboarding/invite if missing/revoked
app/Http/Middleware/RequireHost.php                         resolves session('host_id') → Client (with role=host); aborts/403
app/Http/Middleware/RequireGuestClient.php                  inverse — for the onboarding routes themselves; if already authed, redirects to home
app/Providers/AppServiceProvider.php                        MODIFIED — Blade helper $current_client / $current_host via View::share

bootstrap/app.php                                           MODIFIED — register the three middleware aliases + HAM exception renderer

app/Http/Controllers/Onboarding/InviteController.php        show(), redeem() — POST /onboarding/invite
app/Http/Controllers/Onboarding/NameController.php          show(), submit() — POST /onboarding/name
app/Http/Controllers/MainController.php                     index() — GET /main.ham, branches on session

resources/views/ham/main.blade.php                          MODIFIED — branch logic; renders home or onboarding
resources/views/ham/onboarding/invite.blade.php             code-entry form
resources/views/ham/onboarding/name.blade.php               name-entry form (after invite verified)
resources/views/ham/partials/error.blade.php                generic HAM error box (small <box> with red icon)
resources/views/ham/partials/invite_form.blade.php          code form + inline error (used by both initial render and 422-as-200)

routes/web.php                                              MODIFIED — register all onboarding routes
```

### Phase 3 — client home, library browse, search

```
app/Http/Controllers/Client/HomeController.php              show() — /home.ham
app/Http/Controllers/Client/LibraryController.php           index(), search() — /library.ham, /library/search.ham
app/Http/Controllers/Client/RequestController.php           new(), store(), index() — /requests/new.ham, POST /requests, /requests.ham

resources/views/ham/client/home.blade.php
resources/views/ham/client/library.blade.php
resources/views/ham/client/_library_results.blade.php       partial returned by /library/search.ham?q=
resources/views/ham/client/request_form.blade.php
resources/views/ham/client/_request_confirmation.blade.php  partial returned after POST /requests
resources/views/ham/client/requests.blade.php
resources/views/ham/client/partials/_unread_banner.blade.php  shown on home; polled every 30s

routes/web.php                                              MODIFIED — register client routes under RequireClient
```

### Phase 4 — host home, requests, fulfillment, generate invite

```
app/Http/Controllers/Host/HomeController.php                show() — /host/home.ham
app/Http/Controllers/Host/RequestController.php             index(), fulfill(), decline()
app/Http/Controllers/Host/InviteCodeController.php          generate() — POST /host/invite-code (creates 5-digit ephemeral invite, returns big-code HAM partial)

resources/views/ham/host/home.blade.php
resources/views/ham/host/requests.blade.php
resources/views/ham/host/partials/_request_row.blade.php    used by both index and the partial swap after fulfill/decline
resources/views/ham/host/partials/_invite_code.blade.php    big-number display + countdown shown after POST /host/invite-code

routes/web.php                                              MODIFIED — host routes under RequireHost
```

### Phase 5 — host client revocation

```
app/Http/Controllers/Host/ClientController.php              index(), revoke()

resources/views/ham/host/clients.blade.php
resources/views/ham/host/partials/_client_row.blade.php
```

(No persistent invite admin screen — invites are ephemeral and generated one at a time from §4.)

### Phase 6 — polling + unread badges

```
(no new files; only adds <box trigger="every 30s" action="/host/unread_count.ham" .../>
 to the host home and similar for the client home)

app/Http/Controllers/Client/UnreadController.php            count() — /home/unread_count.ham (mirror of host's)
resources/views/ham/client/partials/_unread_count.blade.php
```

### Phase 7 — tests

```
tests/Feature/MainEntryTest.php                             /main.ham branching
tests/Feature/Onboarding/InviteRedeemTest.php
tests/Feature/Onboarding/NameSubmitTest.php
tests/Feature/Onboarding/RevocationTest.php
tests/Feature/Library/ImportCommandTest.php                 idempotency test (run twice → no dupes)
tests/Feature/Library/SearchTest.php
tests/Feature/Requests/RequestLifecycleTest.php             pending → fulfilled → seen
tests/Feature/Host/InviteManagementTest.php                 max_uses, expiry, revoke
tests/Feature/Host/RequestFulfillmentTest.php
tests/Unit/Support/InviteCodeTest.php                       generation, normalization, format check
tests/Unit/Support/SortTitleTest.php                        article handling
tests/TestCase.php                                          MODIFIED — adds RefreshDatabase trait support helper
```

---

## 4. Phased build order

Each phase ends with a smoke test you can run by hand. The phases build on each other but each one leaves the app in a runnable, demoable state.

### Phase 0 — Config (~15 min)

**Touches**: `config/session.php`, `config/app.php`, `bootstrap/app.php`, `.env`, `.env.example`.

- Set `'lifetime' => 60 * 24 * 365 * 5` (≈5 years) and `'cookie' => 'movieplz_session'`.
- Set `APP_NAME=MoviePlz`.
- In `bootstrap/app.php` `withExceptions(...)`, install a renderer that returns a 200 HAM error block for any path matching `/main.ham`, `/onboarding/*`, `/host/*`, `/library*`, `/requests*`, `/home.ham`. (Or check `Accept` header for `text/html` and request paths matching `*.ham` or "non-welcome" routes — start with the path-list approach; precise enough for v1.)

**Smoke**: `php artisan config:clear && php artisan serve`, `curl -i http://localhost:8000/main.ham` — should still return the existing hello-world HAM.

### Phase 1 — Data model + library import + demo seeder (~2–3 hr)

**Touches**: all migrations, models, factories, `App\Support\InviteCode`, `App\Support\SortTitle`, `LibraryImport` artisan command, `HostInviteIssue` artisan command, `HostSeeder`, `DemoSeeder`, `database/fixtures/*`.

Order within the phase:
1. Migrations (5 of them).
2. Models with relationships.
3. `App\Support\InviteCode::generate()` and tests.
4. `App\Support\SortTitle::from()` and tests.
5. `HostSeeder` — creates one host row.
6. `LibraryImport` artisan command — reads CSV, calls `LibraryItem::updateOrCreate(['host_id' => $hostId, 'title' => $row['title'], 'year' => $row['year']], [...])`.
7. `database/fixtures/library_sample.csv` — 20 rows.
8. `DemoSeeder` — reads `library_seed.json`, creates ~50 library items, ~5 clients (with one invite each), ~10 requests in mixed states.
9. `HostInviteIssue` artisan command — convenience for issuing the host's first invite outside the UI.

**Smoke**:
```
php artisan migrate:fresh --seed
php artisan library:import database/fixtures/library_sample.csv
php artisan library:import database/fixtures/library_sample.csv   # second run — no duplicates
php artisan tinker
> App\Models\LibraryItem::count()    # ~70 (50 from DemoSeeder + 20 from sample)
> App\Models\Invite::first()->code   # something like "7F-K4M-9Q2"
> App\Models\Client::count()         # ~6 (host + 5 demo clients)
```

### Phase 2 — Onboarding flow + middleware (~3–4 hr) — THE HARDEST PHASE

**Touches**: `RequireClient`, `RequireHost`, `RequireGuestClient` middleware, `MainController`, `Onboarding\InviteController`, `Onboarding\NameController`, `AppServiceProvider`, `ham/main.blade.php`, `ham/onboarding/*`, `ham/partials/*`, `routes/web.php`.

Order within the phase:
1. The three middleware classes — see §5 of this document for the exact lifecycle they implement.
2. Register them as named middleware in `bootstrap/app.php`.
3. `AppServiceProvider::boot()` shares `$current_client` (or `null`) and `$current_host` (or `null`) to all views via `View::composer('ham.*', ...)`. This is the Blade-side `auth()` analogue.
4. `MainController::index()`: if there's a session client → return `view('ham.client.home', ...)`; else → `view('ham.onboarding.invite')`.
5. `ham/onboarding/invite.blade.php`: `<form>` containing `<input name="code" pattern="..." error="..."/>` and a submit `<button action="/onboarding/invite" method="POST" target="#main" swap="inner">Continue</button>`.
6. `Onboarding\InviteController::redeem()`: `Validator::make(...)` (200, returns invite_form.blade.php with errors); else looks up `Invite::where('code', $code)->first()`, checks usable, stashes `$invite->id` in `session('invite_id')`, returns `view('ham.onboarding.name', ['invite' => $invite])` (also a partial).
7. `ham/onboarding/name.blade.php`: another form, `<input name="display_name"/>`, button POSTs to `/onboarding/name`.
8. `Onboarding\NameController::submit()`: re-resolves `session('invite_id')`, validates name (1–40 chars), creates the `Client` row, increments invite `uses_count`, stores `session(['client_id' => $client->id])`, returns `view('ham.client.home', ['current_client' => $client])` (the home partial). The Laravel session cookie ride-along happens automatically.
9. Set up `RequireGuestClient` on the onboarding routes so that an already-authed client doesn't accidentally re-redeem.

**Smoke**:
- `php artisan migrate:fresh --seed`
- Note the invite code printed by `php artisan tinker -- App\Models\Invite::first()->code` (or use the `host:invite` command to issue a fresh one).
- Open a fresh shell (no cookies): `curl -c jar -i http://localhost:8000/main.ham` → should see the invite-entry HAM.
- `curl -b jar -c jar -d "code=7F-K4M-9Q2" -X POST http://localhost:8000/onboarding/invite` → name-entry HAM.
- `curl -b jar -c jar -d "display_name=Alice" -X POST http://localhost:8000/onboarding/name` → home HAM, plus a `Set-Cookie: movieplz_session=...` header.
- `curl -b jar -i http://localhost:8000/main.ham` → home HAM directly (bypasses onboarding).
- `tinker > App\Models\Client::find(N)->update(['revoked_at' => now()])` → next `curl -b jar` request goes back to onboarding.

### Phase 3 — Client read paths (~2 hr)

**Touches**: `Client\HomeController`, `Client\LibraryController`, `Client\RequestController`, the corresponding HAM views, `routes/web.php`.

- `/home.ham`: search bar (a `<search-bar>` that triggers a `/library/search.ham?q=$q` action with `change` trigger and target `#library-results`), recent requests, unread banner.
- `/library.ham`: paginated grid using `<flex>` rows of 2–3 columns, falls back to `<list-tile>` for items without posters.
- `/library/search.ham?q=...`: returns `_library_results.blade.php` partial.
- `/requests/new.ham`: form prefilled if `?title=...` query is present.
- `/requests.ham`: client's history grouped by status with HAM `<expansion-panel>`s.

**Smoke**: with the seeded session cookie from Phase 2, `curl -b jar http://localhost:8000/library.ham` returns the seeded library, `curl -b jar "http://localhost:8000/library/search.ham?q=matrix"` returns matching titles only, `curl -b jar -d "title=Inception&type=film" -X POST http://localhost:8000/requests` creates a `requests` row.

### Phase 4 — Host inbox + fulfillment (~2 hr)

**Touches**: `Host\HomeController`, `Host\RequestController`, `Host\UnreadController`, the HAM views, `routes/web.php` under `RequireHost`.

- `/host/home.ham`: count of pending requests, preview of latest 5, library stats.
- `/host/requests.ham`: full list, filter chips for `pending|fulfilled|declined`.
- `POST /host/requests/{id}/fulfill`: marks fulfilled, optional `library_item_id`, returns the row partial.
- `POST /host/requests/{id}/decline`: same shape.

**How does the host get an authed session?** Run `php artisan host:mint-code`. It creates an `invites` row with `grants_host=true` and prints the 5-digit code. Host opens the app, types the code, enters their display name — the same onboarding flow clients use, except `NameController` sees `grants_host=true` and creates the `hosts` row + sets `role='host'` on the new client. See §5e.

**Smoke**: with the host session cookie, `curl -b host_jar http://localhost:8000/host/home.ham` shows the seeded pending requests; `curl -b host_jar -d "library_item_id=42" -X POST http://localhost:8000/host/requests/3/fulfill` flips one to fulfilled.

### Phase 5 — Host admin (invites, client management) (~1.5 hr)

**Touches**: `Host\InviteController`, `Host\ClientController`, the HAM views.

- `/host/invites.ham`: list current invites with `uses_count/max_uses`, `expires_at`, "Create new" button (form with `max_uses` and `expires_at`).
- `POST /host/invites`: creates one via `App\Support\InviteCode::generate()`.
- `POST /host/invites/{id}/revoke`: sets `revoked_at`.
- `/host/clients.ham`: list clients with their `display_name`, invite they came in through, `last_seen_at`, "revoke" button.
- `POST /host/clients/{id}/revoke`: sets `revoked_at`.

**Smoke**: revoke a client via the HAM POST → that client's next request bounces to onboarding (covered by Phase 2 smoke test). Generate a new invite, walk through onboarding with it.

### Phase 6 — Polling + unread (~30 min)

**Touches**: `Host\UnreadController`, `Client\UnreadController`, partial views for the badge, the host home and client home templates.

- Add `<box id="unread-badge" trigger="every 30s" action="/host/unread_count.ham" target="this" swap="inner">…initial badge HAM…</box>` to host home.
- Mirror on client home for fulfilled-but-unseen requests.
- The endpoints return tiny `<text>` partials with the count.

**Smoke**: open `/host/home.ham` in any HAM-rendering tool (or just `curl -b host_jar http://localhost:8000/host/unread_count.ham` to confirm the partial), watch the badge update after a new request comes in.

### Phase 7 — Tests (~2 hr)

**Touches**: `tests/Feature/...`, `tests/Unit/...`. See §7 for the test inventory.

**Smoke**: `php artisan test` — all green.

---

## 5. Auth lifecycle walkthrough

This is the spec's trickiest part. The cleanest path uses Laravel sessions and three middleware classes. No custom token, no custom header — the framework's automatic cookie handling does the legwork.

### Middleware definitions

```
RequireClient        // wraps client routes
RequireHost          // wraps host routes
RequireGuestClient   // wraps onboarding routes (rejects already-authed clients)
```

Aliased in `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->alias([
        'client.required' => \App\Http\Middleware\RequireClient::class,
        'host.required' => \App\Http\Middleware\RequireHost::class,
        'guest.client' => \App\Http\Middleware\RequireGuestClient::class,
    ]);
})
```

`AppServiceProvider::boot()` registers a view composer:
```php
View::composer('ham.*', function ($view) {
    $clientId = session('client_id');
    $client = $clientId ? Client::with('host')->find($clientId) : null;
    if ($client && $client->revoked_at !== null) {
        $client = null;
        session()->forget('client_id');
    }
    $view->with('current_client', $client);
    $view->with('current_host', $client?->role === 'host' ? $client : null);
});
```

In Blade you write `@if ($current_client) ... @endif` — this is the spec's "auth helper analogue".

### Scenario A — First launch (no cookie)

**Wire**: shell calls `GET /main.ham` with no `Cookie:` header (well, with `X-Device-Id` and `X-Device-Platform`, both ignored by Laravel).

**Path**:
1. Route hits `MainController::index()`. No middleware required on `/main.ham` itself — it's the universal entry.
2. View composer runs, sets `$current_client = null`.
3. Controller checks `$current_client === null` → returns `view('ham.onboarding.invite')`.
4. Laravel's `web` middleware group sets the `movieplz_session` cookie in the response (empty session, but the cookie exists). The framework's `_extractCookies` captures it.

**Response**: 200 OK + HAM body of the invite-entry screen + `Set-Cookie: movieplz_session=...`.

### Scenario B — Invite redeem and name submit

**Step B1**: shell POSTs `/onboarding/invite` with form body `code=7F-K4M-9Q2` and the cookie from step A.

1. Route: `POST /onboarding/invite` → `Onboarding\InviteController::redeem()`. Wrapped in `guest.client` middleware (a no-op here because we're not yet a client).
2. Controller validates with `Validator::make(['code' => $req->input('code')], ['code' => 'required|string|regex:/^[2-9A-HJ-NP-Z]{2}-[2-9A-HJ-NP-Z]{3}-[2-9A-HJ-NP-Z]{3}$/'])`. If fails → return 200 with `view('ham.partials.invite_form', ['errors' => $validator->errors()])`.
3. Look up `Invite::where('code', InviteCode::normalize($code))->first()`. If `null`, expired, revoked, or `uses_count >= max_uses`, return 200 with the same partial including a server error message.
4. On success: `session(['pending_invite_id' => $invite->id])`. Return 200 with `view('ham.onboarding.name', ['invite' => $invite])`.

**Step B2**: shell POSTs `/onboarding/name` with `display_name=Alice` and the same cookie.

1. Route: `POST /onboarding/name` → `Onboarding\NameController::submit()`.
2. Pull `session('pending_invite_id')`. If missing → return 200 redirect-shaped partial pointing back to invite entry.
3. Validate display name (1–40 chars).
4. Inside a DB transaction:
   - `$invite->lockForUpdate()`, re-check usable.
   - `$client = Client::create(['host_id' => $invite->host_id, 'invite_id' => $invite->id, 'display_name' => $name, 'device_token' => Str::random(40), 'role' => 'client', 'last_seen_at' => now()])`.
   - `$invite->increment('uses_count')`.
5. `session(['client_id' => $client->id])`, `session()->forget('pending_invite_id')`, `session()->regenerate()`.
6. Return 200 with `view('ham.client.home', [...])`.

**Wire effect**: the response carries the new session ID via `Set-Cookie`. The framework's `_extractCookies` captures it and persists it to secure storage. The shell now has a sticky credential.

The spec asked: "how does the response tell the mobile shell to persist the token?" — **the response doesn't need to**. The framework persists every cookie automatically without any HAM-side instruction.

### Scenario C — Authenticated request

Shell GETs `/library.ham` with cookie header `Cookie: movieplz_session=...`.

1. Route: `Route::get('/library.ham', [LibraryController::class, 'index'])->middleware('client.required')`.
2. Laravel session middleware reads the cookie, hydrates `session()`.
3. `RequireClient` middleware:
   - Gets `session('client_id')`. If null → `return redirect('/main.ham')` (which the framework treats as a normal swap-target redirect; actually we won't redirect — see note below — we'll return a 200 partial).
   - Loads `Client::find($id)` with `host`. If revoked or missing → forget session, return `view('ham.onboarding.invite')` with status 200 (so the next swap displays the invite-entry inside `#main`).
   - Touches `last_seen_at`.
   - Stores the resolved model on the request: `$request->attributes->set('client', $client)`.
   - Calls `$next($request)`.
4. View composer also picks up `current_client` from session for use in templates.
5. Controller renders.

**Note on redirects**: the framework's hypermedia layer doesn't follow HTTP 302 redirects in the "navigate to a new screen" sense — it just swaps response bodies into target elements. So **never use `redirect()`**. Always return the destination view directly with status 200. This matches the spec's "swap a partial into `#main`" pattern.

### Scenario D — Revoked client

The host taps "revoke" on a client in `/host/clients.ham`. That POST hits `Host\ClientController::revoke()` which calls `$client->update(['revoked_at' => now()])`.

The next time that phone hits any client route:
1. `RequireClient::handle()` runs.
2. It loads the `Client`, sees `revoked_at !== null`.
3. It calls `session()->forget('client_id')` and `session()->regenerate()` (the regenerate ensures the old session cookie is invalidated server-side).
4. It returns 200 with the invite-entry HAM partial **wrapped in a `<screen>` if the request is for `/main.ham`**, or just the partial swapped into `#main` for any other client URL.

The framework will see the new session cookie via `_extractCookies` and overwrite the persisted one. The user lands on the invite-entry screen, types a new code, gets a fresh client row.

The shell does NOT need to be told "clear your stored token" — the cookie just changes. From the user's perspective: they tap the app, see invite entry instead of home, type a new code, they're back.

### Scenario E — Host vs client routing

The spec says §5.4 host routes are gated by host middleware. Decision: **the host is a special row in the `clients` table** with `role='host'`, **not a separate model**. Reasons:
1. The host is conceptually the same kind of entity (one phone, one "user", one display name, one revocation state).
2. It avoids duplicate auth flows.
3. Same session-cookie mechanism, same middleware base, just a different role column.

So the `clients` migration has a `role` column: `enum('client', 'host')`, default `'client'`.

**How the host gets a token in the first place**:

`php artisan host:mint-code`:
1. Aborts if any `clients.role='host'` row already exists ("host already claimed, generate client codes from inside the app").
2. Creates an `invites` row with `host_id=null`, `grants_host=true`, `code=<5 digits>`, `expires_at=now()+10min`.
3. Prints the code to stdout.

Host opens the mobile app → types the code → enters their display name. The `NameController::submit()` flow:
- Resolves the invite. If `grants_host=true`:
  - Creates a new `hosts` row (`name = $displayName . "'s library"` or similar).
  - Creates a `clients` row with `role='host'`, `host_id = $newHost->id`, `invite_id = $invite->id`, `display_name = $name`.
  - Updates the invite: `host_id = $newHost->id`, `redeemed_at = now()`, `redeemed_by_client_id = $client->id`.
  - `session(['client_id' => $client->id])`, regenerate.
- If `grants_host=false` (normal client invite):
  - Creates a `clients` row with `role='client'`, `host_id = $invite->host_id` (already set by the generate-invite action).
  - Burns the invite (`redeemed_at`).
  - `session(['client_id' => $client->id])`, regenerate.

No env vars. No magic URL. Single code path, single onboarding flow, one extra boolean on the invite row.

`RequireHost` middleware:
```php
public function handle($request, $next) {
    $clientId = session('client_id');
    if (!$clientId) {
        return $this->bounceToOnboarding($request);
    }
    $client = Client::find($clientId);
    if (!$client || $client->revoked_at || $client->role !== 'host') {
        // Not the host — show 403-style HAM
        return response()->view('ham.partials.error', ['message' => 'Not authorized.'], 200)
            ->header('Content-Type', 'text/html');
    }
    $request->attributes->set('current_host', $client);
    return $next($request);
}
```

Host routes are then:
```php
Route::middleware('host.required')->prefix('host')->group(function () {
    Route::get('/home.ham', [Host\HomeController::class, 'show']);
    // ... etc
});
```

### Header / helper / blade summary (the answer the prompt asked for)

| Need | Mechanism |
|---|---|
| Header for the device token | None — Laravel's `Cookie:` header (set via `web` middleware group, sent automatically by the shell). Cookie name: `movieplz_session`. |
| Middleware on client routes | `client.required` (alias for `App\Http\Middleware\RequireClient`) |
| Middleware on host routes | `host.required` (alias for `App\Http\Middleware\RequireHost`) |
| Middleware on onboarding routes | `guest.client` (alias for `App\Http\Middleware\RequireGuestClient`) — no-op for fresh users, redirects authed users to home |
| Blade access to current client | `$current_client` (shared by `View::composer('ham.*', ...)` in `AppServiceProvider`); also `$request->attributes->get('client')` in controllers |
| Blade access to current host | `$current_host` (same composer) |
| `auth()` analogue | `$current_client` global Blade variable |

---

## 6. Fake data plan

### Source: `database/fixtures/library_seed.json`

Hardcoded array of 80 objects shaped like:
```json
[
  {"title": "The Matrix", "type": "film", "year": 1999},
  {"title": "Inception", "type": "film", "year": 2010},
  {"title": "Breaking Bad", "type": "series", "year": 2008},
  ...
]
```

These come from the agent's general knowledge (no network fetch needed). Aim for a mix of films and series, mostly recognizable titles, mixed decades to exercise the year-grouping UI. The agent picks the titles when writing the file.

### `database/fixtures/library_sample.csv`

Same shape, ~20 rows, **with `sort_title` precomputed** (one of the things the spec requires the importer to handle):
```csv
title,sort_title,type,year
The Matrix,"Matrix, The",film,1999
Inception,Inception,film,2010
A Beautiful Mind,"Beautiful Mind, A",film,2001
The Office (US),"Office (US), The",series,2005
...
```

Used for: testing the artisan command independent of the seeder, and as a starter import for new dev environments.

### `App\Console\Commands\LibraryImport`

```
Signature: library:import {file} {--host-id=1}
```

Algorithm:
1. Validate file exists.
2. `fopen($file, 'r')`, `fgetcsv()` for header row, sanity check headers `[title, sort_title, type, year]`.
3. Loop rows (`fgetcsv()`), each row:
   - Trim, normalize.
   - If `sort_title` is empty, derive via `App\Support\SortTitle::from($title)`.
   - `LibraryItem::updateOrCreate(['host_id' => $hostId, 'title' => $title, 'year' => $year], ['sort_title' => $sortTitle, 'type' => $type, 'added_at' => now()])`.
4. Print summary: `Imported X new items, updated Y existing items.`

Re-running with the same file is idempotent because the `(host_id, title, year)` triple is the upsert key. (Tests cover this — see §7.)

### `database/seeders/HostSeeder`

1. Create the single `hosts` row (`name = "Home library"`).
2. Create the host's `clients` row with `role='host'`, `display_name='Host'`.
3. In local dev only, create 5 fake client rows (each with a burned `invites` row pointing at the host) so the demo data has a realistic user list.

### `database/seeders/DemoSeeder` (only runs in non-prod)

1. Read `library_seed.json`, create ~50 library items for the seeded host.
2. Rely on `HostSeeder` having already created ~5 fake clients; use them.
3. Create ~10 requests in mixed states: 5 pending, 3 fulfilled (linked to a real library item), 2 declined.
4. Create one active (unredeemed, unexpired) client invite code so manual smoke testing has something to redeem on a second phone.

Request titles are sampled from the same `library_seed.json` (minus items already in the library), so they look like plausible "please add this" asks.

`DatabaseSeeder::run()`:
```php
$this->call(HostSeeder::class);
if (app()->environment(['local', 'testing'])) {
    $this->call(DemoSeeder::class);
}
```

---

## 7. Test strategy

Default Laravel PHPUnit. SQLite in-memory (already configured in `phpunit.xml`). `RefreshDatabase` trait on every feature test.

### Unit tests (small, fast, no DB)

| File | What it covers |
|---|---|
| `tests/Unit/Support/InviteCodeTest.php` | `generate()` returns matching format; `normalize()` strips whitespace and uppercases; `looksValid()` accepts canonical and rejects look-alikes (`0`, `O`, `1`, `I`). |
| `tests/Unit/Support/SortTitleTest.php` | `"The Matrix"` → `"Matrix, The"`; `"A Beautiful Mind"` → `"Beautiful Mind, A"`; `"An Education"` → `"Education, An"`; non-articled titles stay put; case insensitive. |

### Feature tests (DB, full HTTP cycle)

| File | What it covers |
|---|---|
| `MainEntryTest.php` | `GET /main.ham` with no session → onboarding markup. With valid session → home markup. With revoked client session → onboarding markup. |
| `Onboarding/InviteRedeemTest.php` | Valid code 200 + name form. Invalid code 200 + error inside the form. Revoked invite 200 + error. Expired invite 200 + error. Maxed-out invite 200 + error. |
| `Onboarding/NameSubmitTest.php` | Empty name 200 + error. Valid name 200 + creates client + sets session + increments invite uses_count. Concurrent redeem (two simultaneous POSTs against the same single-use invite) — only one succeeds. |
| `Onboarding/RevocationTest.php` | Authed client → host marks revoked → next request bounces to invite entry, session cleared. |
| `Library/ImportCommandTest.php` | First run inserts N rows. Second run on same file inserts 0 new + updates N existing. Run on a modified row updates `sort_title`/`type`. Missing file errors cleanly. Bad CSV (missing columns) errors cleanly. |
| `Library/SearchTest.php` | `GET /library/search.ham?q=matrix` returns only matching titles in the response body. Empty query returns paginated full list. |
| `Requests/RequestLifecycleTest.php` | Client POST `/requests` creates a pending row tied to current client. Host POST `/host/requests/{id}/fulfill` flips state. `seen_by_client_at` is set when client GET `/requests.ham` after fulfillment. |
| `Host/InviteManagementTest.php` | Create invite with `max_uses=2`; redeem twice → both succeed; third redemption fails. Create with `expires_at` past → redeem fails. Revoke active invite → next redeem fails. |
| `Host/RequestFulfillmentTest.php` | Fulfill with `library_item_id` links the row. Decline records `status='declined'`. |

### Manual smoke tests (no automation)

- All "smoke" steps from the phased build order in §4.
- Polling: open `/host/home.ham` in a browser, leave it open, in another tab POST a fake request, watch the badge update within 30s.

### Test counts (for sanity)

Roughly 11 feature test files, ~30–40 individual assertions; 2 unit test files, ~10 assertions. Total runtime under 3 seconds against in-memory SQLite. Targeted, not exhaustive.

---

## 8. Risks and open questions

These are gaps the spec doesn't address, in priority order. **Each is something to decide before or during execution; none is a blocker.**

1. **Polling interval**. Spec doesn't pick one. **Proposed: 30s.** Rationale: feels near-real-time without draining battery. Tunable later via env var.

2. **What happens when a client tries to redeem an invite while already authed.** Spec is silent. **Proposed**: `/onboarding/invite` is wrapped in `guest.client` middleware which redirects authed clients to home (rendering `ham.client.home` in the same `#main` swap). The user has to explicitly revoke their own session via a "switch account" button if they want to onboard fresh. We add such a button in `/home.ham`'s settings drawer — `<button action="/onboarding/switch" method="POST">Use a different invite</button>` which clears the session.

3. **`library_items` deletion.** Spec doesn't say if the importer should soft-delete items missing from the new CSV. **Proposed**: no deletion in v1. The artisan command is upsert-only. If the host removes a title from Plex, the host can manually delete the row via `tinker` or a future host UI. Document this in the command's help text.

4. **Library item `added_at` semantics on re-import.** Spec says "when the host added this to Plex (not when the row was created)". The CSV doesn't have this column in the current schema definition. **Proposed**: extend the importer to look for an optional `added_at` column. If absent, use `now()` on insert and leave existing rows untouched.

5. **Concurrent invite redemption**. With `max_uses=1`, two simultaneous POSTs could both think the invite is usable. **Proposed**: the redemption code path is wrapped in a DB transaction with `$invite->lockForUpdate()` (mentioned in §5 above). Tests cover this.

6. **Host onboarding flow**. Resolved via `php artisan host:mint-code` which issues an invite with `grants_host=true`. No env var. The host walks the normal onboarding flow; the `NameController` sees the flag and creates the `hosts` row + `role='host'` client on redemption. See §5e.

7. **Poster URL safety**. Spec's stretch poster section says `<img src="https://...">` and the framework caches these. But what if a CSV has a poster URL pointing at a malicious payload? **Proposed**: defer with stretch §8 row 1. v1 has `poster_url` nullable in the schema but never sets it.

8. **Session cookie `lifetime`**. Default Laravel is 120 minutes. **Proposed**: bump to 5 years in `config/session.php`. Document the trade-off (a stolen device retains access until host revokes).

9. **`role` enum on `clients`**. Adding to satisfy the host-as-client decision. Spec says "Connected clients screen lists every display_name that has redeemed an invite" — the host wouldn't appear in that list because they didn't redeem an invite. **Proposed**: filter `clients` index by `role='client'` so the host doesn't show up there.

10. **`MediaRequest` model name**. The `requests` table conflicts with `Illuminate\Http\Request` in PHP namespace land. **Proposed**: model is `App\Models\MediaRequest`, table is still `requests` (`protected $table = 'requests'`). Controllers use `MediaRequest::create(...)`.

11. **HAM `text/html` Content-Type**. The framework's `_handleResponse` accepts HAM as long as it doesn't start with `<!DOCTYPE`/`<html>`. Laravel's `view()->make()->render()` will return raw HAM since our Blade templates start with `<screen>` or `<box>`. **Proposed**: register a tiny route macro / middleware that sets `Content-Type: text/html` on every HAM route response. Or just put `->header('Content-Type', 'text/html')` on each return. The current `routes/web.php:30–32` does this manually for the welcome HAM.

12. **`server-rendered` Laravel exception page leakage**. If we forget the exception renderer override (Phase 0), an uncaught exception in dev mode shows the Laravel Whoops page, which `_handleResponse` will detect and render as a generic error. **Proposed**: implement the override in Phase 0, test with a deliberately broken route in Phase 7.

13. **Spec says invites have `expires_at` and `revoked_at` BUT clients also have `revoked_at` and no `expires_at`**. Asymmetry is fine — clients are intended to be permanent until manual revocation. Just calling it out so a future agent doesn't add an `expires_at` to clients out of consistency reflex.

14. **Search performance**. Spec says "full-text on (title, sort_title) for search". SQLite has FTS5 but it's a separate virtual table. **Proposed**: v1 uses `LIKE '%term%'` against `sort_title`; library size is small (< 5,000 rows for one host) so this is fine. Add a regular index on `sort_title`. Migrate to FTS5 if it ever matters.

15. **`fixSelfClosingCustomTags` blacklist**. The framework rewrites `<button .../>` and similar self-closing tags into open/close pairs (`network_manager.dart:649–677`). This means our HAM templates can use either form for those tags. Worth knowing when reading the source — not a risk.

---

### Critical Files for Implementation

The 5 most critical files for implementing this plan, in load-bearing order:

- /Users/fluency/Code/movieplz/app/Http/Middleware/RequireClient.php
- /Users/fluency/Code/movieplz/bootstrap/app.php
- /Users/fluency/Code/movieplz/routes/web.php
- /Users/fluency/Code/movieplz/app/Http/Controllers/Onboarding/NameController.php
- /Users/fluency/Code/movieplz/app/Console/Commands/LibraryImport.php

Honourable mention (the schema is the second-most foundational piece, but it's spread across 5 migration files): the `database/migrations/2026_04_09_000003_create_clients_table.php` migration adding the `role` column is the single change that makes the host-as-client decision physical.