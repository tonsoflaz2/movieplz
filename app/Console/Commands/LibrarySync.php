<?php

namespace App\Console\Commands;

use App\Models\Host;
use App\Support\LibrarySource;
use Illuminate\Console\Command;

class LibrarySync extends Command
{
    protected $signature = 'library:sync {--host-id= : Sync a specific host (default: every host with a sheet)}';
    protected $description = "Sync each host's library from its own Google Sheet";

    public function handle(): int
    {
        $hosts = Host::query()
            ->whereNotNull('library_sheet_url')
            ->when($this->option('host-id'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        if ($hosts->isEmpty()) {
            $this->info('No hosts with a library sheet.');
            return 0;
        }

        $failed = 0;
        foreach ($hosts as $host) {
            $report = LibrarySource::syncReport($host);
            if ($report === null) {
                $failed++;
                $this->warn("Host {$host->id}: sheet could not be read.");
                continue;
            }
            $this->info("Host {$host->id}: {$report['new']} new titles"
                .($host->library_mirror ? ", {$report['removed']} removed" : '')
                .($report['held'] ? ", {$report['held']} removals held back" : '').'.');
        }

        return $failed === $hosts->count() ? 1 : 0;
    }
}
