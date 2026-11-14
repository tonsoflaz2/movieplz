<?php

namespace App\Console\Commands;

use App\Models\Host;
use App\Models\LibraryItem;
use App\Support\SortTitle;
use Illuminate\Console\Command;

class LibraryImport extends Command
{
    protected $signature = 'library:import
                            {file : Path to the CSV file}
                            {--host-id= : Host id to import under. Defaults to the single seeded host.}';

    protected $description = 'Import or upsert library items from a CSV (title, sort_title, type, year[, added_at]).';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_file($path) || ! is_readable($path)) {
            $this->error("File not found or not readable: {$path}");
            return self::FAILURE;
        }

        $hostId = $this->option('host-id');
        if ($hostId === null) {
            $host = Host::query()->orderBy('id')->first();
            if ($host === null) {
                $this->error('No host exists yet. Run HostSeeder or `php artisan host:mint-code` first.');
                return self::FAILURE;
            }
            $hostId = $host->id;
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            $this->error("Could not open file: {$path}");
            return self::FAILURE;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            $this->error('CSV is empty.');
            return self::FAILURE;
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $required = ['title', 'type', 'year'];
        foreach ($required as $col) {
            if (! in_array($col, $header, true)) {
                fclose($handle);
                $this->error("Missing required CSV column: {$col}");
                return self::FAILURE;
            }
        }

        $created = 0;
        $updated = 0;
        $rowNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            if ($row === [null] || $row === []) {
                continue;
            }

            $data = array_combine($header, array_pad($row, count($header), null));
            if ($data === false) {
                $this->warn("Row {$rowNumber}: column count mismatch, skipping.");
                continue;
            }

            $title = trim((string) ($data['title'] ?? ''));
            $type = strtolower(trim((string) ($data['type'] ?? '')));
            $yearRaw = trim((string) ($data['year'] ?? ''));

            if ($title === '' || ! in_array($type, ['film', 'series'], true)) {
                $this->warn("Row {$rowNumber}: invalid title/type, skipping.");
                continue;
            }

            $year = $yearRaw === '' ? null : (int) $yearRaw;
            $sortTitle = trim((string) ($data['sort_title'] ?? ''));
            if ($sortTitle === '') {
                $sortTitle = SortTitle::from($title);
            }

            $addedAt = null;
            if (isset($data['added_at']) && trim((string) $data['added_at']) !== '') {
                $addedAt = trim((string) $data['added_at']);
            }

            $attrs = [
                'sort_title' => $sortTitle,
                'type' => $type,
            ];
            if ($addedAt !== null) {
                $attrs['added_at'] = $addedAt;
            }

            $existing = LibraryItem::query()
                ->where('host_id', $hostId)
                ->where('title', $title)
                ->where('year', $year)
                ->first();

            if ($existing) {
                $existing->fill($attrs)->save();
                $updated++;
            } else {
                LibraryItem::create(array_merge([
                    'host_id' => $hostId,
                    'title' => $title,
                    'year' => $year,
                    'added_at' => $addedAt ?? now(),
                ], $attrs));
                $created++;
            }
        }

        fclose($handle);

        $this->info("Imported {$created} new items, updated {$updated} existing items.");
        return self::SUCCESS;
    }
}
