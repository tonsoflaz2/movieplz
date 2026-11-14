# MoviePlz 1.0 — App Store release log

A step-by-step record of releasing MoviePlz to the App Store (and preparing
Google Play), for the how-to video. Times are local (PDT). Tools: the `hype`
CLI built from Shipyard's launch/platform branch, Laravel Forge, App Store
Connect. Nothing secret is written here.

## 1. Server-side prep (Oct 17–18)

1. **Privacy and support pages.** Added `/privacy` and `/support` (plain web
   pages, `resources/views/site/`). The App Store listing links to both. The
   support email comes from `MOVIEPLZ_SUPPORT_EMAIL` (default
   lazarus@hypeapps.dev).
2. **A review code for Apple's reviewer.** Normal invite codes expire in ten
   minutes, so reviewers get a permanent one, `MOVIEPLZ_REVIEW_CODE` (five
   digits, kept in `store/private.json`, outside git). It joins a demo
   list, "MoviePlz Demo", with 24 well-known titles, made by
   `php artisan movieplz:review-demo`.
3. **Store listing text** in `store/listing.json`: title, subtitle, description,
   keywords, promo text, and Play's short description. Leave "What's New" out
   of a first version: App Store Connect refuses it on 1.0.
4. **Review notes** in `store/review-notes.md`: what to paste into App Review
   Information, App Privacy answers, age rating, category.
5. Committed and pushed (`bac0bc8`).
6. **Forge:**
   - Environment: add `MOVIEPLZ_REVIEW_CODE` (five digits, kept in
   `store/private.json`, outside git).
   - Redeploy.
   - Commands: run `php artisan movieplz:review-demo` once.
7. **Check the review code like a reviewer would:** enter the review code → type a
   name → "You're in! MoviePlz Demo" → Library shows 24 titles with posters.
   Then delete the test person with "Leave this library".

## 2. Store kit: screenshots and art (Oct 18)

1. Opened the app in **Hype Preview** (a phone-sized window on the Mac),
   pointed at the dev server:
   `hype preview --url http://movieplz.test/app.ham --theme dark`
2. Signed in as a friend of the demo list, and added a few sample suggestions
   locally, so the screens aren't empty.
3. On each screen, `hype shot --store <name>` renders it at every size the
   stores need, into `store/screenshots/<size>/`:
   - 01 library (posters and descriptions from TMDB)
   - 02 inbox (the host's Suggestions: I'll watch it / Saw it! / Not for me)
   - 03 title (a title page: Dune)
   - 04 suggest (the suggest-a-title form)
   - 05 suggestions (a friend's list: Up next, Seen, Waiting)

   Sizes: iPhone 6.3" (1206×2622, required), iPhone 6.9" (1320×2868),
   iPad 13" (2064×2752, required because the app runs on iPad), and Play
   phone, 7" and 10".
4. Store art:
   - `store/app-store/icon-1024.png`: the App Store icon, no transparency.
   - `store/play/icon.png`: 512×512.
   - `store/play/feature.png`: Google Play's 1024×500 banner.
5. Checked what would change, without changing anything:
   `hype listing --dry-run --ios`
6. Committed and pushed (`2af283c`, `2882c7b`).

## 3. Build check (Oct 18)

`hype build ios --dry-run` stages the app, checks permissions, privacy
manifests and https, and says what it would sign with. It signs nothing:
- Removed an unused location permission (`location_always`) it flagged.
- Result: "the build checks out", signing as Apple Distribution, team
  3UZ67ZRK4R.

## 4. Real build and upload (Oct 19)

1. `hype build ios` → `build/MoviePlz-1.0-3.ipa`.
   - Build number 3: App Store Connect already had 1 and 2.
   - macOS asks to use the "Apple Distribution" key: Always Allow.
2. `hype publish ios build/MoviePlz-1.0-3.ipa` → **rejected** (07:13):
   "Invalid Code Signing … must be signed with the certificate that is
   contained in the provisioning profile (90179)".
   - **Why:** the App Store provisioning profile on the Mac, "iOS Team Store
     Provisioning Profile: com.hypeapps.movieplz", was made for an older
     Apple Distribution certificate. That certificate is no longer on the
     account. The keychain's certificate (SHA-1 E65E0F…, valid to
     2027-07-15) wasn't in it.
   - **Fix:** made a new App Store profile with the App Store Connect API,
     tied to the keychain's certificate: "MoviePlz App Store 2026-10-19",
     valid to 2027-07-15.
   - Installed it in `~/Library/Developer/Xcode/UserData/Provisioning
     Profiles/` (where hype looks). hype picks the profile that expires
     latest, so it chose the new one.
   - Shipyard follow-up: hype should check the profile contains the signing
     certificate before building, and offer to make a new one.
3. Rebuilt (`hype build ios`), then `hype publish ios …` → **UPLOAD
   SUCCEEDED** (07:17). Delivery 8ed6439f-…, 32 MB in 10 s.
4. `hype listing --ios` → "App Store updated (7 changes)": subtitle,
   description, keywords, promo text, and 5 screenshots each for iPhone 6.3",
   iPhone 6.9" and iPad 13".

5. App Store Connect processed it: **build 3 is VALID** (shown a few
   minutes after the upload). It can now be picked for version 1.0 and goes
   to TestFlight.

## 5. Left for the person (in App Store Connect)

- When build 3 finishes processing, select it on the 1.0 version.
- App Privacy, age rating, category, the privacy policy URL and the
  App Review notes (sign-in: the review code). The answers are in
  `store/review-notes.md`.
- Submit for Review.

## Google Play (prepared, not uploaded)

- Upload key: `hype keys android` → `~/.hype/keys/upload.jks`, with its
  password in the keychain. **Back it up**: every Play update must be
  signed with it.
- `hype build android --build-number 1` → `build/MoviePlz-1.0-1.aab`, signed
  with the upload key (Java only, no Flutter).
- Known issues:
  - Android notifications: fixed since. The server sends through Firebase
    Cloud Messaging (`FCM_CREDENTIALS` in Forge's environment), and
    hype.toml's `[android.firebase]` has all four values (`8a1dd78`). Rebuild
    the .aab to pick them up.
  - The build declares unused foreground services (media, location), which
    Play asks about.
- The person's Play Console steps:
  - Create the app, then upload the first .aab by hand to internal testing.
  - Data safety, content rating, and App access (the review code).
  - A service-account key, so `hype keys google` can handle later uploads.

## 6. iOS build 4 and submission (2026-11-10)

- Why build 4: build 3's profile had no `aps-environment`, so iOS push
  couldn't work. Build 4 was built with Shipyard's fixed hype CLI (engine
  370595c, which also has the textarea Return-key fix).
  `codesign -d --entitlements` shows `aps-environment: production`, and
  `hype audit` shows ✓.
- Server: pushed 1f0a91e → 1009191 (120 tests pass). This includes the
  per-token APNs environment (`clients.push_environment`), so
  `APNS_ENVIRONMENT` is no longer used.
  - **Still to do on prod:** `php artisan migrate --force`, unless Forge's
    deploy script already runs it.
- On the user's go, the coordinating session:
  - Uploaded `build/MoviePlz-1.0-4.ipa` (delivery a0ec951c…).
  - Filled in App Store Connect from `store/review-notes.md`: category,
    content rights, privacy policy URL and privacy labels, age rating 4+,
    support/marketing URLs, review contact, review notes with the review code,
    price Free in all countries, and automatic release.
  - **Submitted for review at 16:37. Status: Waiting for Review.**
- Google Play: **MoviePlz has been live on Google Play since 2026-11-09.**

## 7. Build 4 pulled, build 5 resubmitted (2026-11-10 to 11-11)

- Build 4 crashed at launch on the user's iPhone (iOS 27.0.1): signal 5,
  no crash report. The hype device players had been built with Xcode 27.1
  (`iphoneos27.1`). The iOS 27 SDK requires the UIScene lifecycle, and the
  Flutter shell doesn't have it. Simulator runs passed because the simulator
  player was built with Xcode 26.6. The submission was pulled from review.
- Build 5: `build/MoviePlz-1.0-5.ipa` (sha256 d6548b28…), with players rebuilt
  on Xcode 26.6 (`DTSDKName iphoneos26.5`, `DTXcode 2660`) and
  `aps-environment: production`. Uploaded to TestFlight (delivery
  8dff0867…).
  - Device check: a development re-sign of the same binary launched on the
    user's iPhone. The TestFlight copy itself wasn't opened.
- **2026-11-11: build 5 submitted with `hype submit ios --build 5` (no
  browser). Status: Waiting for Review.** The metadata is unchanged from
  Nov 10.
- Next: build 6 (`build/MoviePlz-1.0-6.ipa`, sha256 c6992b9e…) goes in as
  1.0.1 after approval. It changes the launch screen to the icon's tile,
  120 pt, on #F00709 (`16e18a9`). It pairs with the server's
  `splash_color="#F00709"` (`4f0c655`).

## 8. iOS 1.0 live (2026-11-13)

- **MoviePlz 1.0 (build 5) was approved and released on the App Store at
  2026-11-13 03:08Z:** https://apps.apple.com/us/app/movieplz/id6762552708
- Both stores are now live: Google Play since 2026-11-09, the App Store
  since 2026-11-13.
- /support lists both store links as plain, crawlable links:
  - Google Play in `3df47d5`
  - the App Store added after release
- Next: 1.0.1 with build 6's launch screen. It can also have the logo
  animation (`splash_animation`, engine 70a1113) if the user chooses it.

## 9. Black screen report and first-load resilience (2026-11-13)

- **Report:** the user saw a black screen after `9369bbd` (disconnect and
  reconnect) went live. The coordinating session reverted it (`74f4ceb`), but
  the black screen stayed.
  - The server HAM rendered cleanly through engine 02e1444 in a headless
    Flutter widget test.
  - The likely cause was the user's network. The feature was re-landed as
    `dfdda4d`.
  - A console capture is still to do: the phone wasn't connected.
- **How engine 02e1444 handles a slow first load (builds 5 and 6):**
  - The native launch screen is held for up to 4 s.
  - Then comes the engine splash in the cached `splash_color`. Without a
    cache it's the default #04060E, near-black.
  - Behind it, `/app.ham` is tried 3 times. Each try waits up to 15 s to
    connect and 15 s to read, with 1 s and 2 s between tries. That's about
    48–93 s before the "App Load Failed" diagnostic screen (with Reload).
- **Idea, logged and not built (engine work for 5f/CORE):**
  - After about 5 s of no answer, show a plain "Can't reach MoviePlz. Try
    again" view over the splash, while the retries go on.
  - Use a person-facing message, not the developer diagnostic.
  - Consider starting from the lastGood copy of `/app.ham` when the network
    fails.

## 10. The missing "saw it" notification (2026-11-13)

- **Report:** the user, signed in on the App Store iPhone as a friend
  (client 29), got no notification when the host marked their suggestion
  "Saw it!".
- **What the log showed:** the iPhone's token registered on client 1 (the
  user's own host row) at 13:10 and 13:55, then on client 29 at 14:18. No
  APNs rejection or curl error was ever logged. That rules out the Apple
  key. A successful send didn't log anything, so success couldn't be told
  apart from never sending.
- **Now (this commit):** every notification leaves one INFO line:
  - `Push` with the client, the event (`saw-it`, `up-for-it`,
    `new-suggestion`, `test`), the platform, the result, the gateway, the
    HTTP status, and Apple's `apns-id` or Firebase's message name.
  - Or `Push skipped`, with the reason: no token, disconnected, revoked, or
    no such client.
  - Never the token.
  - Tests cover "saw it" and "up for it" from an Android host reaching the
    friend who suggested it on an iPhone. The suggester is always the
    suggestion's own client, never the host.
- **Note: iOS gives an app a push token even when alerts are denied.** A
  registered token doesn't mean the person will see anything, and Apple's
  200 only means Apple accepted it.
  - Ideally the engine would send the notification authorization status
    with the registration (authorized, denied, provisional or not
    determined), and the server would store it. Without it, the server
    should treat delivery as "unknown".
  - Engine work for 5f/CORE.
- **One phone, one person (built after):** one phone's token could sit on
  more than one row (here client 1, disconnected, and client 29).
  Registering a token now clears it from every other row in any library,
  so a phone is notified only for whoever is signed in on it. Each move is
  logged as `Push token moved {from_clients, to_client}`, ids only.
