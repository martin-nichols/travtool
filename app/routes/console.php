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

Artisan::command('travian:prune-map-data {--days=14 : Keep successful map snapshots for this many days} {--staging-days=1 : Keep staging rows for completed import runs for this many days} {--history-days=30 : Keep compact player population history for this many days} {--world= : Limit pruning to one world key} {--force : Actually delete data; without this, only report what would be deleted}', function (): int {
    $retentionDays = max(1, (int) $this->option('days'));
    $stagingRetentionDays = max(0, (int) $this->option('staging-days'));
    $historyRetentionDays = max(3, (int) $this->option('history-days'));
    $worldKey = $this->option('world');
    $force = (bool) $this->option('force');
    $snapshotCutoff = CarbonImmutable::today('UTC')->subDays($retentionDays - 1)->toDateString();
    $stagingCutoff = CarbonImmutable::now('UTC')->subDays($stagingRetentionDays);
    $historyCutoff = CarbonImmutable::today('UTC')->subDays($historyRetentionDays - 1)->toDateString();

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
    $historyRows = DB::table('player_population_histories')
        ->whereIn('world_id', $worldIds)
        ->where('snapshot_date', '<', $historyCutoff)
        ->count();

    $this->line(sprintf(
        '%s %d map snapshot(s) before %s, %d staging row(s) before %s, and %d compact player history row(s) before %s.',
        $force ? 'Deleting' : 'Would delete',
        count($snapshotIds),
        $snapshotCutoff,
        $stagingRows,
        $stagingCutoff->toDateTimeString(),
        $historyRows,
        $historyCutoff,
    ));

    foreach ($snapshotRows as $table => $count) {
        $this->line(sprintf('%s: %d row(s)', $table, $count));
    }

    if (! $force) {
        $this->warn('Dry run only. Re-run with --force to delete.');

        return Command::SUCCESS;
    }

    DB::transaction(function () use ($historyCutoff, $snapshotIds, $stagingImportRunIds, $worldIds): void {
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

        DB::table('player_population_histories')
            ->whereIn('world_id', $worldIds)
            ->where('snapshot_date', '<', $historyCutoff)
            ->delete();
    });

    $this->info('Map data pruning completed.');

    return Command::SUCCESS;
})->purpose('Prune old Travian snapshot history and completed import staging rows');

Artisan::command('travian:reset-world-data {--keep=* : World key to keep active; repeat for multiple worlds} {--preserve-kept-data : Only delete data for worlds outside the keep list} {--force : Actually update and delete data; without this, only report what would change}', function (): int {
    $keepKeys = array_values(array_unique(array_filter(
        array_map(static fn (mixed $key): string => trim((string) $key), (array) $this->option('keep')),
        static fn (string $key): bool => $key !== '',
    )));
    $force = (bool) $this->option('force');
    $preserveKeptData = (bool) $this->option('preserve-kept-data');

    if ($keepKeys === []) {
        $this->error('Provide at least one --keep world key.');

        return Command::FAILURE;
    }

    $existingKeepKeys = DB::table('worlds')
        ->whereIn('key', $keepKeys)
        ->pluck('key')
        ->all();
    $missingKeepKeys = array_values(array_diff($keepKeys, $existingKeepKeys));

    if ($missingKeepKeys !== []) {
        $this->error(sprintf('Unknown kept world key(s): %s', implode(', ', $missingKeepKeys)));

        return Command::FAILURE;
    }

    $worldsToWipe = DB::table('worlds')
        ->when($preserveKeptData, fn ($query) => $query->whereNotIn('key', $keepKeys))
        ->select('id', 'key', 'name')
        ->orderBy('key')
        ->get();
    $worldIdsToWipe = $worldsToWipe
        ->pluck('id')
        ->map(static fn (mixed $id): int => (int) $id)
        ->all();

    $rowCounts = $worldIdsToWipe === []
        ? []
        : [
            'staging_map_rows' => DB::table('staging_map_rows')->whereIn('world_id', $worldIdsToWipe)->count(),
            'village_snapshots' => DB::table('village_snapshots')->whereIn('world_id', $worldIdsToWipe)->count(),
            'player_snapshots' => DB::table('player_snapshots')->whereIn('world_id', $worldIdsToWipe)->count(),
            'player_population_histories' => DB::table('player_population_histories')->whereIn('world_id', $worldIdsToWipe)->count(),
            'alliance_snapshots' => DB::table('alliance_snapshots')->whereIn('world_id', $worldIdsToWipe)->count(),
            'villages' => DB::table('villages')->whereIn('world_id', $worldIdsToWipe)->count(),
            'players' => DB::table('players')->whereIn('world_id', $worldIdsToWipe)->count(),
            'alliances' => DB::table('alliances')->whereIn('world_id', $worldIdsToWipe)->count(),
            'map_snapshots' => DB::table('map_snapshots')->whereIn('world_id', $worldIdsToWipe)->count(),
            'map_import_runs' => DB::table('map_import_runs')->whereIn('world_id', $worldIdsToWipe)->count(),
        ];

    $this->line(sprintf('%s keep active world(s): %s', $force ? 'Will' : 'Would', implode(', ', $keepKeys)));
    $this->line(sprintf('%s deactivate %d world(s).', $force ? 'Will' : 'Would', DB::table('worlds')->whereNotIn('key', $keepKeys)->count()));
    $this->line(sprintf(
        '%s wipe data for %d world(s)%s.',
        $force ? 'Will' : 'Would',
        count($worldIdsToWipe),
        $preserveKeptData ? ' outside the keep list' : ', including kept worlds',
    ));

    foreach ($rowCounts as $table => $count) {
        $this->line(sprintf('%s: %d row(s)', $table, $count));
    }

    if (! $force) {
        $this->warn('Dry run only. Re-run with --force to apply.');

        return Command::SUCCESS;
    }

    DB::transaction(function () use ($keepKeys, $worldIdsToWipe): void {
        DB::table('worlds')->whereIn('key', $keepKeys)->update(['is_active' => true, 'updated_at' => now()]);
        DB::table('worlds')->whereNotIn('key', $keepKeys)->update(['is_active' => false, 'updated_at' => now()]);

        if ($worldIdsToWipe === []) {
            return;
        }

        DB::table('worlds')
            ->whereIn('id', $worldIdsToWipe)
            ->update(['current_snapshot_id' => null, 'updated_at' => now()]);

        DB::table('staging_map_rows')->whereIn('world_id', $worldIdsToWipe)->delete();
        DB::table('village_snapshots')->whereIn('world_id', $worldIdsToWipe)->delete();
        DB::table('player_snapshots')->whereIn('world_id', $worldIdsToWipe)->delete();
        DB::table('player_population_histories')->whereIn('world_id', $worldIdsToWipe)->delete();
        DB::table('alliance_snapshots')->whereIn('world_id', $worldIdsToWipe)->delete();
        DB::table('villages')->whereIn('world_id', $worldIdsToWipe)->delete();
        DB::table('players')->whereIn('world_id', $worldIdsToWipe)->delete();
        DB::table('alliances')->whereIn('world_id', $worldIdsToWipe)->delete();
        DB::table('map_snapshots')->whereIn('world_id', $worldIdsToWipe)->delete();
        DB::table('map_import_runs')->whereIn('world_id', $worldIdsToWipe)->delete();
    });

    $this->info('World data reset completed.');

    return Command::SUCCESS;
})->purpose('Deactivate non-kept Travian worlds and wipe map data');

Artisan::command('travian:backfill-player-history {--world= : Limit backfill to one world key}', function (): int {
    $worldKey = $this->option('world');
    $worlds = DB::table('worlds')
        ->when($worldKey !== null, fn ($query) => $query->where('key', $worldKey))
        ->select('id', 'key')
        ->orderBy('key')
        ->get();

    if ($worlds->isEmpty()) {
        $this->error($worldKey !== null ? sprintf('Unknown world key [%s].', $worldKey) : 'No worlds found.');

        return Command::FAILURE;
    }

    foreach ($worlds as $world) {
        $this->line(sprintf('Backfilling compact player history for [%s]...', $world->key));

        DB::table('player_population_histories')
            ->where('world_id', $world->id)
            ->delete();

        $snapshotDates = DB::table('player_snapshots')
            ->where('world_id', $world->id)
            ->distinct()
            ->orderBy('snapshot_date')
            ->pluck('snapshot_date')
            ->map(static fn (mixed $date): string => (string) $date)
            ->all();
        $historyByDate = [];
        $inserted = 0;

        foreach ($snapshotDates as $snapshotDate) {
            $comparisonDates = collect([1, 2, 3])
                ->mapWithKeys(static fn (int $days): array => [
                    $days => CarbonImmutable::parse($snapshotDate, 'UTC')->subDays($days)->toDateString(),
                ]);
            $rows = [];
            $currentHistory = [];
            $now = now();

            DB::table('player_snapshots')
                ->where('world_id', $world->id)
                ->whereDate('snapshot_date', $snapshotDate)
                ->orderBy('id')
                ->chunkById(1000, function ($playerSnapshots) use (&$currentHistory, &$inserted, &$rows, $comparisonDates, $historyByDate, $now): void {
                    foreach ($playerSnapshots as $playerSnapshot) {
                        $populationTotal = (int) $playerSnapshot->population_total;
                        $villageCount = (int) $playerSnapshot->village_count;
                        $history1d = $historyByDate[$comparisonDates[1]][(int) $playerSnapshot->player_id] ?? null;
                        $history2d = $historyByDate[$comparisonDates[2]][(int) $playerSnapshot->player_id] ?? null;
                        $history3d = $historyByDate[$comparisonDates[3]][(int) $playerSnapshot->player_id] ?? null;

                        $rows[] = [
                            'world_id' => (int) $playerSnapshot->world_id,
                            'snapshot_id' => (int) $playerSnapshot->snapshot_id,
                            'player_id' => (int) $playerSnapshot->player_id,
                            'snapshot_date' => (string) $playerSnapshot->snapshot_date,
                            'external_player_id' => (int) $playerSnapshot->external_player_id,
                            'village_count' => $villageCount,
                            'population_total' => $populationTotal,
                            'population_delta_1d' => $history1d !== null ? $populationTotal - $history1d['population_total'] : null,
                            'population_delta_2d' => $history2d !== null ? $populationTotal - $history2d['population_total'] : null,
                            'population_delta_3d' => $history3d !== null ? $populationTotal - $history3d['population_total'] : null,
                            'village_count_delta_1d' => $history1d !== null ? $villageCount - $history1d['village_count'] : null,
                            'village_count_delta_2d' => $history2d !== null ? $villageCount - $history2d['village_count'] : null,
                            'village_count_delta_3d' => $history3d !== null ? $villageCount - $history3d['village_count'] : null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                        $currentHistory[(int) $playerSnapshot->player_id] = [
                            'population_total' => $populationTotal,
                            'village_count' => $villageCount,
                        ];

                        if (count($rows) >= 1000) {
                            DB::table('player_population_histories')->insert($rows);
                            $inserted += count($rows);
                            $rows = [];
                        }
                    }
                });

            if ($rows !== []) {
                DB::table('player_population_histories')->insert($rows);
                $inserted += count($rows);
            }

            $historyByDate[$snapshotDate] = $currentHistory;
        }

        $this->info(sprintf('Backfilled [%s] with %d row(s).', $world->key, $inserted));
    }

    return Command::SUCCESS;
})->purpose('Backfill compact player population history from existing player snapshots');

Schedule::command('travian:import-due-maps')
    ->everyMinute()
    ->withoutOverlapping(30)
    ->name('travian-import-due-maps');

Schedule::command('travian:sync-worlds')
    ->everySixHours()
    ->withoutOverlapping(30)
    ->name('travian-sync-worlds');

Schedule::command('travian:prune-map-data --days=2 --staging-days=0 --history-days=30 --force')
    ->dailyAt('03:30')
    ->withoutOverlapping(30)
    ->name('travian-prune-map-data');
