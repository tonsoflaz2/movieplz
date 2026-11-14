<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Host;
use App\Support\LibrarySource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * The demo library App Review joins with MOVIEPLZ_REVIEW_CODE: a host named
 * MOVIEPLZ_REVIEW_HOST with a list of well-known titles (nobody's real list).
 * Safe to run again: it only adds what's missing.
 */
class ReviewDemo extends Command
{
    protected $signature = 'movieplz:review-demo';
    protected $description = 'Create or top up the demo library for App Review';

    private const TITLES = <<<'TXT'
The Shawshank Redemption (1994)
The Godfather (1972)
Spirited Away (2001)
Inception (2010)
Paddington 2 (2017)
Toy Story (1995)
Back to the Future (1985)
Jurassic Park (1993)
The Princess Bride (1987)
Amélie (2001)
Mad Max: Fury Road (2015)
Coco (2017)
Knives Out (2019)
The Grand Budapest Hotel (2014)
Everything Everywhere All at Once (2022)
Dune (2021)
Parasite (2019)
The Wild Robot (2024)
Ted Lasso, series (2020)
The Bear, series (2022)
Planet Earth, series (2006)
Stranger Things, series (2016)
Severance, series (2022)
Only Murders in the Building, series (2021)
TXT;

    public function handle(): int
    {
        $name = config('movieplz.review_host');
        $host = Host::firstOrCreate(['name' => $name]);

        if (! $host->hostClient()) {
            Client::create([
                'host_id' => $host->id,
                'role' => 'host',
                'display_name' => 'Demo host',
                'device_token' => Str::random(40),
                'last_seen_at' => now(),
            ]);
        }

        $new = LibrarySource::import($host, LibrarySource::parsePasted(self::TITLES), \App\Models\LibraryItem::ORIGIN_PASTE);
        Artisan::call('tmdb:enrich', ['--host-id' => $host->id, '--limit' => 100]);

        $this->info("\"{$name}\" (host {$host->id}): {$new} titles added, {$host->libraryItems()->count()} in all.");
        $this->line(config('movieplz.review_code')
            ? 'App Review code: '.config('movieplz.review_code')
            : 'Set MOVIEPLZ_REVIEW_CODE (5 digits) in .env to let reviewers join it.');

        return 0;
    }
}
