<?php

namespace Tests\Feature\Library;

use App\Models\Host;
use App\Models\LibraryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportCommandTest extends TestCase
{
    use RefreshDatabase;

    private function writeCsv(string $body): string
    {
        $path = tempnam(sys_get_temp_dir(), 'library_').'.csv';
        file_put_contents($path, $body);
        return $path;
    }

    public function test_first_run_inserts_rows(): void
    {
        $host = Host::factory()->create();

        $csv = $this->writeCsv(<<<CSV
title,sort_title,type,year
The Matrix,"Matrix, The",film,1999
Inception,Inception,film,2010
CSV);

        $this->artisan('library:import', ['file' => $csv, '--host-id' => $host->id])
            ->expectsOutputToContain('Imported 2 new items')
            ->assertSuccessful();

        $this->assertDatabaseCount('library_items', 2);
        $this->assertDatabaseHas('library_items', [
            'host_id' => $host->id,
            'title' => 'The Matrix',
            'sort_title' => 'Matrix, The',
            'year' => 1999,
        ]);
    }

    public function test_second_run_is_idempotent(): void
    {
        $host = Host::factory()->create();

        $csv = $this->writeCsv(<<<CSV
title,sort_title,type,year
The Matrix,"Matrix, The",film,1999
CSV);

        $this->artisan('library:import', ['file' => $csv, '--host-id' => $host->id])->assertSuccessful();
        $this->artisan('library:import', ['file' => $csv, '--host-id' => $host->id])
            ->expectsOutputToContain('Imported 0 new items')
            ->assertSuccessful();

        $this->assertDatabaseCount('library_items', 1);
    }

    public function test_updated_row_gets_new_sort_title(): void
    {
        $host = Host::factory()->create();

        $csv1 = $this->writeCsv(<<<CSV
title,sort_title,type,year
The Matrix,wrong sort,film,1999
CSV);
        $this->artisan('library:import', ['file' => $csv1, '--host-id' => $host->id])->assertSuccessful();
        $this->assertDatabaseHas('library_items', ['sort_title' => 'wrong sort']);

        $csv2 = $this->writeCsv(<<<CSV
title,sort_title,type,year
The Matrix,"Matrix, The",film,1999
CSV);
        $this->artisan('library:import', ['file' => $csv2, '--host-id' => $host->id])->assertSuccessful();
        $this->assertDatabaseHas('library_items', ['sort_title' => 'Matrix, The']);
    }

    public function test_derives_sort_title_when_column_missing(): void
    {
        $host = Host::factory()->create();

        $csv = $this->writeCsv(<<<CSV
title,type,year
The Matrix,film,1999
CSV);

        $this->artisan('library:import', ['file' => $csv, '--host-id' => $host->id])->assertSuccessful();

        $this->assertDatabaseHas('library_items', [
            'title' => 'The Matrix',
            'sort_title' => 'Matrix, The',
        ]);
    }

    public function test_missing_file_errors_cleanly(): void
    {
        $this->artisan('library:import', ['file' => '/nonexistent.csv'])
            ->expectsOutputToContain('not found')
            ->assertFailed();
    }

    public function test_missing_required_column_errors(): void
    {
        $host = Host::factory()->create();

        $csv = $this->writeCsv(<<<CSV
title,sort_title
The Matrix,"Matrix, The"
CSV);

        $this->artisan('library:import', ['file' => $csv, '--host-id' => $host->id])
            ->expectsOutputToContain('Missing required CSV column')
            ->assertFailed();
    }
}
