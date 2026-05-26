<?php

use App\Services\Travian\TravianMapImportService;
use App\Services\Travian\TravianWorldCatalogService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
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
            'Imported [%s] snapshot %s (%d lines, run #%d, snapshot #%d, %s, %s).',
            $result['world_key'],
            $result['snapshot_date'],
            $result['line_count'],
            $result['import_run_id'],
            $result['snapshot_id'],
            $result['map_width'] !== null && $result['map_height'] !== null
                ? sprintf('%dx%d', $result['map_width'], $result['map_height'])
                : 'unknown map size',
            $result['map_topology'],
        ));
    }

    return Command::SUCCESS;
})->purpose('Download and import a Travian map.sql snapshot into Travtool');

Artisan::command('travian:import-due-maps', function (TravianMapImportService $service): int {
    $dueImports = $service->dueWorldImports();

    if ($dueImports === []) {
        $this->line('No world import is due right now.');

        return Command::SUCCESS;
    }

    foreach ($dueImports as $dueImport) {
        $this->line(sprintf(
            'Scheduled import for [%s] at %s (%s).',
            $dueImport['world_key'],
            $dueImport['local_time'],
            $dueImport['snapshot_date'],
        ));

        try {
            $result = $service->importWorld(
                $dueImport['world_key'],
                snapshotDate: $dueImport['snapshot_date'],
            );
        } catch (\Throwable $exception) {
            $this->error(sprintf('Scheduled import failed for [%s]: %s', $dueImport['world_key'], $exception->getMessage()));

            return Command::FAILURE;
        }

        $this->info(sprintf(
            'Scheduled import completed for [%s] (%d lines, run #%d, snapshot #%d).',
            $result['world_key'],
            $result['line_count'],
            $result['import_run_id'],
            $result['snapshot_id'],
        ));
    }

    return Command::SUCCESS;
})->purpose('Import map.sql for all active worlds that are due now');

Artisan::command('travian:sync-worlds {--calendar-source= : Local path to a saved calendar JSON payload} {--metadata-source= : Local path to a saved metadata JSON payload} {--activate-new : Mark newly discovered worlds as active}', function (TravianWorldCatalogService $service): int {
    $this->line('Syncing Travian world catalog...');

    try {
        $result = $service->sync(
            $this->option('calendar-source'),
            $this->option('metadata-source'),
            (bool) $this->option('activate-new'),
        );
    } catch (\Throwable $exception) {
        $this->error(sprintf('World catalog sync failed: %s', $exception->getMessage()));

        return Command::FAILURE;
    }

    $this->info(sprintf(
        'World catalog synced (%d processed, %d created, %d updated, %d skipped).',
        $result['processed'],
        $result['created'],
        $result['updated'],
        $result['skipped'],
    ));

    return Command::SUCCESS;
})->purpose('Sync Travian worlds from the public calendar and metadata catalog');

Artisan::command('travian:prune-map-data {--days=14 : Keep successful map snapshots for this many days} {--staging-days=1 : Keep staging rows for completed import runs for this many days} {--world= : Limit pruning to one world key} {--force : Actually delete data; without this, only report what would be deleted}', function (): int {
    $retentionDays = max(1, (int) $this->option('days'));
    $stagingRetentionDays = max(0, (int) $this->option('staging-days'));
    $worldKey = $this->option('world');
    $force = (bool) $this->option('force');
    $snapshotCutoff = CarbonImmutable::today('UTC')->subDays($retentionDays - 1)->toDateString();
    $stagingCutoff = CarbonImmutable::now('UTC')->subDays($stagingRetentionDays);

    $worldIds = DB::table('worlds')
        ->when($worldKey !== null, fn ($query) => $query->where('key', $worldKey))
        ->pluck('id')
        ->map(static fn (mixed $id): int => (int) $id)
        ->all();

    if ($worldIds === []) {
        $this->error($worldKey !== null ? sprintf('Unknown world key [%s].', $worldKey) : 'No worlds found.');

        return Command::FAILURE;
    }

    $currentSnapshotIds = DB::table('worlds')
        ->whereIn('id', $worldIds)
        ->whereNotNull('current_snapshot_id')
        ->pluck('current_snapshot_id')
        ->map(static fn (mixed $id): int => (int) $id)
        ->all();

    $snapshotIds = DB::table('map_snapshots')
        ->whereIn('world_id', $worldIds)
        ->where('snapshot_date', '<', $snapshotCutoff)
        ->whereNotIn('id', $currentSnapshotIds ?: [0])
        ->pluck('id')
        ->map(static fn (mixed $id): int => (int) $id)
        ->all();

    $stagingImportRunIds = DB::table('map_import_runs')
        ->whereIn('world_id', $worldIds)
        ->whereIn('status', ['success', 'failed'])
        ->where('created_at', '<', $stagingCutoff)
        ->pluck('id')
        ->map(static fn (mixed $id): int => (int) $id)
        ->all();

    $snapshotRows = [
        'alliance_snapshots' => DB::table('alliance_snapshots')->whereIn('snapshot_id', $snapshotIds ?: [0])->count(),
        'player_snapshots' => DB::table('player_snapshots')->whereIn('snapshot_id', $snapshotIds ?: [0])->count(),
        'village_snapshots' => DB::table('village_snapshots')->whereIn('snapshot_id', $snapshotIds ?: [0])->count(),
    ];
    $stagingRows = DB::table('staging_map_rows')->whereIn('import_run_id', $stagingImportRunIds ?: [0])->count();

    $this->line(sprintf(
        '%s %d map snapshot(s) before %s and %d staging row(s) before %s.',
        $force ? 'Deleting' : 'Would delete',
        count($snapshotIds),
        $snapshotCutoff,
        $stagingRows,
        $stagingCutoff->toDateTimeString(),
    ));

    foreach ($snapshotRows as $table => $count) {
        $this->line(sprintf('%s: %d row(s)', $table, $count));
    }

    if (! $force) {
        $this->warn('Dry run only. Re-run with --force to delete.');

        return Command::SUCCESS;
    }

    DB::transaction(function () use ($snapshotIds, $stagingImportRunIds): void {
        if ($snapshotIds !== []) {
            DB::table('map_snapshots')
                ->whereIn('previous_snapshot_id', $snapshotIds)
                ->update(['previous_snapshot_id' => null]);

            DB::table('map_snapshots')
                ->whereIn('id', $snapshotIds)
                ->delete();
        }

        if ($stagingImportRunIds !== []) {
            DB::table('staging_map_rows')
                ->whereIn('import_run_id', $stagingImportRunIds)
                ->delete();
        }
    });

    $this->info('Map data pruning completed.');

    return Command::SUCCESS;
})->purpose('Prune old Travian snapshot history and completed import staging rows');

Schedule::command('travian:import-due-maps')
    ->everyMinute()
    ->withoutOverlapping(30)
    ->name('travian-import-due-maps');

Schedule::command('travian:sync-worlds')
    ->everySixHours()
    ->withoutOverlapping(30)
    ->name('travian-sync-worlds');
