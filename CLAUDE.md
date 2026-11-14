# MoviePlz — Laravel backend for the hypeapps mobile shell

**Learn HAM first:** read https://hypeapps.dev/llms.txt before writing or
editing any HAM in this repo. It is the full, current HAM reference.

This is the **server side** of MoviePlz. The mobile app at
`/Users/fluency/Apps/movieplz/` is a thin Flutter shell over the **hypeapps
framework** — it has ~10 lines of Dart and does nothing except fetch HAM
templates from this Laravel server and render them natively on iOS/Android.

All UI lives here. Every screen, list, button, form, navigation flow,
animation — defined as **HAM** (HypeApps Markup), an HTML-like DSL — and
served from Laravel routes as Blade-rendered responses.

## What you write

| Layer | What it is | What runs it |
|---|---|---|
| Routes (`routes/web.php`) | Laravel routes returning Blade views | PHP, on this server |
| Blade templates (`resources/views/ham/*.blade.php`) | HAM markup with Blade interpolation for dynamic data | Blade renders → outputs HAM → sent over the wire |
| HAM markup (inside the .blade.php files) | HTML-like tags, attributes, styles, reactive vars | Parsed and rendered by the **mobile app** after the response arrives |

The mobile shell never sees Blade. It only sees the rendered HAM. Blade runs
**once on the server**; HAM runs **continuously on the client**.

## The most important rule

**Blade is server-side. HAM is client-side. They both use `$`.**

```blade
{{-- Blade interpolation — runs on the server, BEFORE the response goes out --}}
<list-tile title="{{ $movie->title }}" subtitle="{{ $movie->year }}"/>

{{-- After Blade renders, the wire payload is just: --}}
{{-- <list-tile title="Inception" subtitle="2010"/> --}}
```

```html
<!-- HAM reactive variable — declared and read on the CLIENT, the mobile app -->
<box var-count="0">
  <button @click="$count = $count + 1">+</button>
  <text @text="$count"></text>
</box>
```

```blade
{{-- WRONG: this tries to interpolate a HAM client-side var as a PHP server-side var --}}
<text @text="{{ $count }}"></text>   {{-- Blade says: $count is undefined --}}

{{-- RIGHT: HAM vars stay raw inside attributes --}}
<text @text="$count"></text>          {{-- the mobile app reads $count from its own state --}}
```

| You want… | Use… |
|---|---|
| Inject server data into HAM at render time | `{{ $php_var }}` (Blade) |
| Loop server data into N HAM elements | `@foreach ($items as $item) ... @endforeach` (Blade) |
| Server-side conditional rendering | `@if ($user->isAdmin) ... @endif` (Blade) |
| Client-side reactive variable | `var-name="initial"` declares, `$name` references (HAM) |
| Client-side conditional show/hide | `@show="$var"` (HAM) |
| Click handler that mutates client state | `@click="$count = $count + 1"` (HAM) |
| Click handler that fetches new content from server | `action="/movies/{{ $id }}"` (mixed: Blade fills the URL once, HAM uses it as a fetch target) |

## Where HAM lives

```
~/Code/movieplz/
├── routes/web.php             Routes that map URLs → Blade views
├── resources/views/
│   └── ham/                   <-- ALL HAM TEMPLATES GO HERE
│       ├── app.blade.php      The entry point the mobile shell fetches first
│       ├── movies/
│       │   ├── index.blade.php
│       │   └── show.blade.php
│       └── partials/
│           └── ...            HAM fragments returned by content-swap actions
├── docs/
│   └── ham-reference.md       Full HAM syntax reference (read on demand)
└── CLAUDE.md                  This file
```

The mobile shell's entry point is `http://movieplz.test/app.ham` (dev) or
`https://movieplz.hypeapps.dev/app.ham` (prod). The route serving that URL
returns `view('ham.app', [...])`. Sub-screens are typically fetched via
`action="/movies/123.ham"` from buttons / list-tiles inside HAM.

> The entry file is named **`app`** (not `main`) so it doesn't collide with
> the HAM `<main>` tag, the `id="main"` swap target convention, or the
> Flutter shell's `lib/main.dart`. `app.blade.php` → `view('ham.app')` →
> `GET /app.ham` is the consistent triple.

## Critical HAM syntax rules (the gotchas)

These are framework footguns that aren't obvious from the reference. **Read
these before writing your first template.**

1. **`<bottom-navigation-bar>` must be a direct child of `<screen>`**, NEVER
   inside `<main>`. The framework expects it as a sibling slot.

2. **`<flex>` is single-row only.** `<flex column_widths="1 2 1">` makes one
   row of three cells. Extra children beyond `column_widths` count are
   **silently dropped**. Use multiple `<flex>` rows for grids.

3. **`<box>` centers children horizontally** by default (Column with
   `crossAxisAlignment.center`). For left-aligned text, use `<flex>` cells
   (which use `stretch`).

4. **Reactive text needs `<text>` or `<span>` with `@text`.** Plain
   `<p>$count</p>` does NOT update. Use `<text @text="$count"></text>` or
   `<span @text="$count"></span>` or `<p>Count: <text @text="$count"></text></p>`.

5. **In a `.blade.php` file write `@@show`, never `@show`.** `@show` is a
   Blade directive: Blade eats it and the app receives `<block !$var="…">`,
   which silently does nothing. `@@show="$var"` renders as `@show="$var"`.
   (`@click`, `@text` and `@on.*` are not Blade directives and are fine.)
   **`@@show="!$var"` for negation.** Complex comparisons (`==`, `>`, `<`)
   in `@show` don't reliably work — keep it to truthy/falsy.

6. **Self-closing tags need a slash.** Write `<hr/>`, `<br/>`,
   `<input name="foo"/>`, `<notification on-tap="..."/>`. Not `<hr>`.

7. **`var-*` and `data-*` are interchangeable** — both declare a local
   reactive variable. `var-count="0"` and `data-count="0"` are identical.

8. **Variable scoping:** vars declared with `var-name` are local to the
   element that declared them and visible to all descendants. `<form>` and
   any element with `id=` containing `name=` form fields create scope
   boundaries — names inside don't leak to the page level.

9. **Action chaining uses `;`.** `@click="haptic:medium; notify:Hi:World; $count = $count + 1"`
   runs all three statements in order.

10. **`notify:` body can't contain raw colons** at certain positions. Same
    constraint for `share:`, `speak:`, `clipboard:`, etc. — they're all
    `:`-separated. For complex bodies, use the `<notification>` tag instead.

11. **Style classes are Tailwind-like.** `style="bg-blue-500 text-white p-15 rounded mt-10"`.
    See the Styles section of `ham-reference.md` for the full vocabulary.

12. **Action targets use `#id` for selectors.** `<button action="/api/data" target="#main" swap="inner">`
    swaps the response into the element with `id="main"`.

13. **Comments are safe.** `<!-- HTML comment -->` works inside any HAM
    element. Use them liberally to explain template structure.

14. **Case matters.** HAM is case-sensitive — `<Box>` is not `<box>`.

15. **`file:/` is the local-asset protocol.** `action="file:/responses/hello.ham"`
    fetches a HAM partial bundled with the app. **You will rarely use this**
    in MoviePlz because the mobile shell has no bundled assets — almost all
    actions point at server URLs (`action="/movies/{{ $id }}.ham"`).

## Hello world

```blade
{{-- resources/views/ham/app.blade.php --}}
<screen>
  <app-bar title="MoviePlz"/>

  <main id="main">
    <box style="p-20" var-greeting="Welcome to MoviePlz">
      <h1 style="font-2xl font-bold mb-10" @text="$greeting"></h1>
      <p style="font-base text-gray-600 mb-20">
        A hypermedia movie app powered by Laravel + hypeapps.
      </p>

      {{-- Server-side data interpolated by Blade --}}
      <p style="font-sm">Today is {{ now()->format('l, F j') }}.</p>

      {{-- Client-side reactive button --}}
      <button @click="$greeting = Hello from the client" style="filled blue mt-15">
        Update greeting
      </button>

      {{-- Action that fetches from a server route --}}
      <button action="/movies" target="#main" swap="inner" style="outlined indigo mt-10">
        Browse movies
      </button>
    </box>
  </main>

  <bottom-navigation-bar>
    <bottom-nav-item label="Home" icon="home" action="/app.ham" selected="true"/>
    <bottom-nav-item label="Movies" icon="movie" action="/movies"/>
    <bottom-nav-item label="Profile" icon="person" action="/profile"/>
  </bottom-navigation-bar>
</screen>
```

```php
// routes/web.php
Route::get('/app.ham', fn () => response()
    ->view('ham.app')
    ->header('Content-Type', 'text/html'));
```

That's the full vertical slice — Laravel route, Blade-rendered HAM, mobile
shell renders it as native iOS/Android UI.

## When you don't know how something works

1. **First, look at `docs/ham-reference.md`** — full tag/attribute/style
   reference. ~1800 lines, organized by topic. Use the table of contents at
   the top.

2. **For framework internals or recent changes**, the source of truth is
   `/Users/fluency/Apps/hypeapps/` (the framework repo, sibling on disk).
   Check `lib/src/tags/builders/` for how a specific tag actually behaves.

3. **For working examples**, look at `/Users/fluency/Apps/showcase_app/assets/html/showcase/`
   in the framework repo — that's the comprehensive showcase app with
   real-world usage of every feature.

## What NOT to do

- ❌ Don't write `<text @text="{{ $count }}">` — Blade will explode and the
  HAM client-side var will never bind. Use `<text @text="$count">`.
- ❌ Don't put `<bottom-navigation-bar>` inside `<main>`.
- ❌ Don't expect `<flex>` to do multi-row layouts.
- ❌ Don't assume `<p>$count</p>` will update — wrap reactive content in
  `<text @text="$count">`.
- ❌ Don't use unclosed self-closing tags (`<hr>` ❌, `<hr/>` ✓).
- ❌ Don't mix the file extension (`.blade.php`) with the wire format (HAM).
  These files contain HAM markup with Blade interpolation; the response
  body is HAM only.
- ❌ Don't put the mobile shell's bundle ID, signing keys, or APNs configs
  in this repo — those live in `/Users/fluency/Apps/movieplz/`.

## What's in this repo vs the framework

| This repo (`~/Code/movieplz`) | Framework repo (`/Users/fluency/Apps/hypeapps`) | Mobile shell (`/Users/fluency/Apps/movieplz`) |
|---|---|---|
| Routes, controllers, models | Tag builders, action pipeline, reactive system | Flutter `main.dart` + iOS/Android platform configs |
| Blade-rendered HAM templates | The 109 HAM tags' Dart implementations | The hypeapps package as a path dependency |
| Eloquent + DB | Style classes, color system, icons | Bundle ID, app icon, splash screen |
| Auth, sessions, business logic | The HAM parser and renderer | Notification entitlements, network exceptions |

You almost never need to touch the framework or the mobile shell from
inside this Laravel project. Your job is to write good HAM templates.
