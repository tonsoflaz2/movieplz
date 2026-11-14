<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('library:sync')->everyFiveMinutes()->withoutOverlapping();
// Posters and overviews for new titles, checked every 10 seconds (40 lookups ≈ 10 s at
// TMDB's pace); titles another library has are copied, not looked up. Imports also
// kick off a run straight away (LibrarySource::enrichSoon).
Schedule::command('tmdb:enrich --limit=40')->everyTenSeconds()->withoutOverlapping();
