<?php

use App\Services\Travian\TravianMapImportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Command\Command;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('travian:import-map {worldKey? : Configured world key, for example rof} {--source= : Local path to a map.sql file for testing} {--snapshot-date= : Override the snapshot date (YYYY-MM-DD)} {--force : Re-import an already successful snapshot}', function (TravianMapImportService $service): int {
    $worldKey = $this->argument('worldKey');
    $sourcePath = $this->option('source');
    $snapshotDate = $this->option('snapshot-date');
    $force = (bool) $this->option('force');

    $worldKeys = $worldKey !== null
        ? [$worldKey]
        : $service->activeConfiguredWorldKeys();

    if ($worldKeys === []) {
        $this->error('No active Travian worlds are configured.');

        return Command::FAILURE;
    }

    if ($sourcePath !== null && count($worldKeys) !== 1) {
        $this->error('When using --source, specify exactly one world key.');

        return Command::FAILURE;
    }

    foreach ($worldKeys as $key) {
        $this->line(sprintf('Importing map.sql for [%s]...', $key));

        try {
            $result = $service->importWorld($key, $sourcePath, $snapshotDate, $force);
        } catch (\Throwable $exception) {
            $this->error(sprintf('Import failed for [%s]: %s', $key, $exception->getMessage()));

            return Command::FAILURE;
        }

        $this->info(sprintf(
            'Imported [%s] snapshot %s (%d lines, run #%d, snapshot #%d).',
            $result['world_key'],
            $result['snapshot_date'],
            $result['line_count'],
            $result['import_run_id'],
            $result['snapshot_id'],
        ));
    }

    return Command::SUCCESS;
})->purpose('Download and import a Travian map.sql snapshot into Travtool');
