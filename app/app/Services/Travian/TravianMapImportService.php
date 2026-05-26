<?php

namespace App\Services\Travian;

use App\Models\MapImportRun;
use App\Models\MapSnapshot;
use App\Models\World;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SplFileObject;
use Throwable;

class TravianMapImportService
{
    public function __construct(
        private readonly MapSqlLineParser $parser,
        private readonly WorldMapMetadataDetector $worldMapMetadataDetector,
        private readonly HttpFactory $http,
    ) {
    }

    /**
     * @return list<string>
     */
    public function configuredWorldKeys(): array
    {
        $configKeys = array_keys(config('travtool.worlds', []));
        $storedKeys = World::query()
            ->where('key', '!=', '')
            ->pluck('key')
            ->all();

        return array_values(array_unique([...$configKeys, ...$storedKeys]));
    }

    /**
     * @return list<string>
     */
    public function activeConfiguredWorldKeys(): array
    {
        $configKeys = array_values(array_keys(array_filter(
            config('travtool.worlds', []),
            static fn (array $world): bool => (bool) ($world['is_active'] ?? true),
        )));

        $storedKeys = World::query()
            ->where('is_active', true)
            ->where('key', '!=', '')
            ->where('map_sql_url', '!=', '')
            ->pluck('key')
            ->all();

        return array_values(array_unique([...$configKeys, ...$storedKeys]));
    }

    /**
     * @return array<int, array{world_key:string,snapshot_date:string,local_time:string}>
     */
    public function dueWorldImports(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now('UTC');
        $dueImports = [];

        foreach ($this->activeConfiguredWorldKeys() as $worldKey) {
            $configuredWorld = $this->configuredWorld($worldKey);
            $localNow = $now->setTimezone($configuredWorld['server_timezone']);
            $snapshotDate = $localNow->toDateString();
            $scheduledAt = CarbonImmutable::parse(
                sprintf('%s %s', $snapshotDate, $configuredWorld['import_time']),
                $configuredWorld['server_timezone'],
            );

            if ($localNow->lt($scheduledAt)) {
                continue;
            }

            if ($this->hasSuccessfulSnapshotForDate($worldKey, $snapshotDate)) {
                continue;
            }

            $dueImports[] = [
                'world_key' => $worldKey,
                'snapshot_date' => $snapshotDate,
                'local_time' => $localNow->format('H:i'),
            ];
        }

        return $dueImports;
    }

    /**
     * @return array{
     *     world_key:string,
     *     snapshot_date:string,
     *     import_run_id:int,
     *     snapshot_id:int,
     *     line_count:int,
     *     stored_path:string,
     *     used_source:string,
     *     map_width:?int,
     *     map_height:?int,
     *     map_radius:?int,
     *     map_topology:string,
     *     has_regions:bool
     * }
     */
    public function importWorld(
        string $worldKey,
        ?string $sourcePath = null,
        ?string $snapshotDate = null,
        bool $force = false,
    ): array {
        $configuredWorld = $this->configuredWorld($worldKey);
        $world = $this->syncWorld($worldKey, $configuredWorld);
        $resolvedSnapshotDate = $this->resolveSnapshotDate($configuredWorld['server_timezone'], $snapshotDate);
        $latestSuccessfulSnapshot = $this->latestSuccessfulSnapshot($world->id);

        if (
            $latestSuccessfulSnapshot !== null
            && $resolvedSnapshotDate < $latestSuccessfulSnapshot->snapshot_date->toDateString()
        ) {
            throw new RuntimeException('Backfill imports are not supported yet. Import the latest snapshot first.');
        }

        $snapshot = $this->prepareSnapshot($world, $configuredWorld, $resolvedSnapshotDate, $force);
        $importRun = MapImportRun::query()->create([
            'world_id' => $world->id,
            'snapshot_date' => $resolvedSnapshotDate,
            'status' => MapImportRun::STATUS_PENDING,
            'source_url' => $sourcePath !== null ? $sourcePath : $configuredWorld['map_sql_url'],
            'started_at' => now(),
        ]);

        $sourceFile = null;

        try {
            $sourceFile = $this->prepareSourceFile($worldKey, $configuredWorld['map_sql_url'], $resolvedSnapshotDate, $importRun->id, $sourcePath);
            $fileMetadata = $this->sourceMetadata($sourceFile['absolute_path']);

            $importRun->forceFill([
                'status' => MapImportRun::STATUS_DOWNLOADED,
                'raw_file_path' => $sourceFile['stored_path'],
                'checksum' => $fileMetadata['checksum'],
                'file_size_bytes' => $fileMetadata['file_size_bytes'],
            ])->save();

            $snapshot->forceFill([
                'status' => MapSnapshot::STATUS_DOWNLOADED,
                'raw_file_path' => $sourceFile['stored_path'],
                'checksum' => $fileMetadata['checksum'],
                'file_size_bytes' => $fileMetadata['file_size_bytes'],
                'downloaded_at' => now(),
                'error_message' => null,
            ])->save();

            $lineCount = $this->loadStagingRows($world, $importRun, $resolvedSnapshotDate, $sourceFile['absolute_path']);
            $worldMapMetadata = $this->syncDetectedWorldMapMetadata($world, $importRun->id);

            $importRun->forceFill([
                'status' => MapImportRun::STATUS_STAGED,
                'line_count' => $lineCount,
            ])->save();

            $snapshot->forceFill([
                'status' => MapSnapshot::STATUS_STAGED,
                'line_count' => $lineCount,
                'staged_at' => now(),
            ])->save();

            $this->normalizeSnapshot($world, $snapshot, $importRun, $resolvedSnapshotDate);

            $importRun->forceFill([
                'status' => MapImportRun::STATUS_SUCCESS,
                'finished_at' => now(),
            ])->save();

            return [
                'world_key' => $worldKey,
                'snapshot_date' => $resolvedSnapshotDate,
                'import_run_id' => $importRun->id,
                'snapshot_id' => $snapshot->id,
                'line_count' => $lineCount,
                'stored_path' => $sourceFile['stored_path'] ?? $sourceFile['absolute_path'],
                'used_source' => $sourcePath !== null ? 'local-file' : 'remote-download',
                'map_width' => $worldMapMetadata['map_width'],
                'map_height' => $worldMapMetadata['map_height'],
                'map_radius' => $worldMapMetadata['map_radius'],
                'map_topology' => $worldMapMetadata['map_topology'],
                'has_regions' => $worldMapMetadata['has_regions'],
            ];
        } catch (Throwable $exception) {
            $importRun->forceFill([
                'status' => MapImportRun::STATUS_FAILED,
                'finished_at' => now(),
                'error_message' => $exception->getMessage(),
            ])->save();

            $snapshot->forceFill([
                'status' => MapSnapshot::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        } finally {
            if ($sourceFile !== null) {
                $this->cleanupSourceFile($sourceFile);
            }

            $this->cleanupStagingRows($importRun->id);
        }
    }

    /**
     * @param array{name:string,base_url:string,map_sql_url:string,server_timezone:string,import_time:string,speed:int|null,is_active:bool,map_topology:?string,map_radius:?int} $configuredWorld
     */
    private function syncWorld(string $worldKey, array $configuredWorld): World
    {
        return World::query()->updateOrCreate(
            ['key' => $worldKey],
            [
                'name' => $configuredWorld['name'],
                'base_url' => $configuredWorld['base_url'],
                'map_sql_url' => $configuredWorld['map_sql_url'],
                'server_timezone' => $configuredWorld['server_timezone'],
                'import_time' => $configuredWorld['import_time'],
                'speed' => $configuredWorld['speed'],
                'map_topology' => $configuredWorld['map_topology'],
                'map_radius' => $configuredWorld['map_radius'],
                'is_active' => $configuredWorld['is_active'],
            ],
        );
    }

    private function latestSuccessfulSnapshot(int $worldId): ?MapSnapshot
    {
        return MapSnapshot::query()
            ->where('world_id', $worldId)
            ->where('status', MapSnapshot::STATUS_SUCCESS)
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id')
            ->first();
    }

    public function hasSuccessfulSnapshotForDate(string $worldKey, string $snapshotDate): bool
    {
        $world = World::query()->where('key', $worldKey)->first();

        if ($world === null) {
            return false;
        }

        return MapSnapshot::query()
            ->where('world_id', $world->id)
            ->whereDate('snapshot_date', $snapshotDate)
            ->where('status', MapSnapshot::STATUS_SUCCESS)
            ->exists();
    }

    /**
     * @param array{name:string,base_url:string,map_sql_url:string,server_timezone:string,import_time:string,speed:int|null,is_active:bool,map_topology:?string,map_radius:?int} $configuredWorld
     */
    private function prepareSnapshot(
        World $world,
        array $configuredWorld,
        string $snapshotDate,
        bool $force,
    ): MapSnapshot {
        $existingSnapshot = MapSnapshot::query()
            ->where('world_id', $world->id)
            ->whereDate('snapshot_date', $snapshotDate)
            ->first();

        if ($existingSnapshot !== null && $existingSnapshot->status === MapSnapshot::STATUS_SUCCESS && ! $force) {
            throw new RuntimeException(sprintf(
                'Snapshot %s for world [%s] was already imported successfully. Use --force to re-import it.',
                $snapshotDate,
                $world->key,
            ));
        }

        $previousSnapshotId = MapSnapshot::query()
            ->where('world_id', $world->id)
            ->where('status', MapSnapshot::STATUS_SUCCESS)
            ->whereDate('snapshot_date', '<', $snapshotDate)
            ->orderByDesc('snapshot_date')
            ->orderByDesc('id')
            ->value('id');

        if ($existingSnapshot !== null) {
            $existingSnapshot->forceFill([
                'successful_import_run_id' => null,
                'previous_snapshot_id' => $previousSnapshotId,
                'status' => MapSnapshot::STATUS_PENDING,
                'source_url' => $configuredWorld['map_sql_url'],
                'raw_file_path' => null,
                'checksum' => null,
                'file_size_bytes' => null,
                'line_count' => null,
                'downloaded_at' => null,
                'staged_at' => null,
                'normalized_at' => null,
                'current_state_updated_at' => null,
                'completed_at' => null,
                'error_message' => null,
            ])->save();

            return $existingSnapshot;
        }

        return MapSnapshot::query()->create([
            'world_id' => $world->id,
            'successful_import_run_id' => null,
            'previous_snapshot_id' => $previousSnapshotId,
            'snapshot_date' => $snapshotDate,
            'status' => MapSnapshot::STATUS_PENDING,
            'source_url' => $configuredWorld['map_sql_url'],
        ]);
    }

    /**
     * @return array{name:string,base_url:string,map_sql_url:string,server_timezone:string,import_time:string,speed:int|null,is_active:bool,map_topology:?string,map_radius:?int}
     */
    private function configuredWorld(string $worldKey): array
    {
        $worlds = config('travtool.worlds', []);

        if (isset($worlds[$worldKey])) {
            $world = $worlds[$worldKey];

            return [
                'name' => (string) $world['name'],
                'base_url' => rtrim((string) $world['base_url'], '/').'/',
                'map_sql_url' => (string) ($world['map_sql_url'] ?? rtrim((string) $world['base_url'], '/').'/map.sql'),
                'server_timezone' => (string) ($world['server_timezone'] ?? 'UTC'),
                'import_time' => (string) ($world['import_time'] ?? '00:10'),
                'speed' => isset($world['speed']) ? (int) $world['speed'] : null,
                'map_topology' => isset($world['map_topology']) ? (string) $world['map_topology'] : null,
                'map_radius' => isset($world['map_radius']) ? (int) $world['map_radius'] : null,
                'is_active' => (bool) ($world['is_active'] ?? true),
            ];
        }

        $storedWorld = World::query()
            ->where('key', $worldKey)
            ->first();

        if ($storedWorld === null || $storedWorld->base_url === '' || $storedWorld->map_sql_url === '') {
            throw new RuntimeException(sprintf(
                'Unknown world key [%s]. Configured worlds: %s',
                $worldKey,
                implode(', ', array_keys($worlds)),
            ));
        }

        return [
            'name' => (string) $storedWorld->name,
            'base_url' => rtrim((string) $storedWorld->base_url, '/').'/',
            'map_sql_url' => (string) $storedWorld->map_sql_url,
            'server_timezone' => (string) ($storedWorld->server_timezone ?: config('travtool.catalog.default_server_timezone', 'UTC')),
            'import_time' => (string) ($storedWorld->import_time ?: config('travtool.catalog.default_import_time', '00:10')),
            'speed' => $storedWorld->speed !== null ? (int) $storedWorld->speed : null,
            'map_topology' => $storedWorld->map_topology !== null ? (string) $storedWorld->map_topology : null,
            'map_radius' => $storedWorld->map_radius !== null ? (int) $storedWorld->map_radius : null,
            'is_active' => (bool) $storedWorld->is_active,
        ];
    }

    private function resolveSnapshotDate(string $timezone, ?string $snapshotDate): string
    {
        return $snapshotDate !== null
            ? CarbonImmutable::parse($snapshotDate, $timezone)->toDateString()
            : CarbonImmutable::now($timezone)->toDateString();
    }

    private function prepareSourceFile(
        string $worldKey,
        string $mapSqlUrl,
        string $snapshotDate,
        int $importRunId,
        ?string $sourcePath,
    ): array {
        $disk = config('travtool.imports.disk', 'local');
        $retainRawFiles = (bool) config('travtool.imports.retain_raw_files', false);
        $directory = trim((string) config(
            $retainRawFiles ? 'travtool.imports.directory' : 'travtool.imports.temporary_directory',
            $retainRawFiles ? 'map-imports' : 'map-imports-temp',
        ), '/');
        $storedPath = sprintf('%s/%s/%s/run-%d-map.sql', $directory, $worldKey, $snapshotDate, $importRunId);

        Storage::disk($disk)->makeDirectory(dirname($storedPath));

        if ($sourcePath !== null) {
            if (! is_file($sourcePath)) {
                throw new RuntimeException(sprintf('Source file not found: %s', $sourcePath));
            }

            $absolutePath = realpath($sourcePath);

            if ($absolutePath === false) {
                throw new RuntimeException(sprintf('Unable to resolve source file path: %s', $sourcePath));
            }

            if (! $retainRawFiles) {
                return [
                    'absolute_path' => $absolutePath,
                    'stored_path' => null,
                    'cleanup' => false,
                    'disk' => $disk,
                ];
            }

            $stream = fopen($absolutePath, 'rb');

            if ($stream === false) {
                throw new RuntimeException(sprintf('Unable to open source file: %s', $sourcePath));
            }

            Storage::disk($disk)->put($storedPath, $stream);

            if (is_resource($stream)) {
                fclose($stream);
            }

            return [
                'absolute_path' => Storage::disk($disk)->path($storedPath),
                'stored_path' => $storedPath,
                'cleanup' => false,
                'disk' => $disk,
            ];
        }

        $response = $this->http
            ->timeout(120)
            ->connectTimeout(20)
            ->retry(3, 2000)
            ->withUserAgent('Travtool/0.1')
            ->get($mapSqlUrl);

        if (! $response->successful()) {
            throw new RuntimeException(sprintf('Failed to download map.sql from %s (HTTP %s).', $mapSqlUrl, $response->status()));
        }

        Storage::disk($disk)->put($storedPath, $response->body());

        return [
            'absolute_path' => Storage::disk($disk)->path($storedPath),
            'stored_path' => $retainRawFiles ? $storedPath : null,
            'cleanup' => ! $retainRawFiles,
            'cleanup_path' => $storedPath,
            'disk' => $disk,
        ];
    }

    /**
     * @return array{checksum:string,file_size_bytes:int,absolute_path:string}
     */
    private function sourceMetadata(string $absolutePath): array
    {
        return [
            'checksum' => hash_file('sha256', $absolutePath),
            'file_size_bytes' => filesize($absolutePath) ?: 0,
            'absolute_path' => $absolutePath,
        ];
    }

    private function loadStagingRows(
        World $world,
        MapImportRun $importRun,
        string $snapshotDate,
        string $absolutePath,
    ): int {
        $chunkSize = max(1, (int) config('travtool.imports.staging_chunk_size', 500));

        DB::table('staging_map_rows')
            ->where('import_run_id', $importRun->id)
            ->delete();

        $file = new SplFileObject($absolutePath, 'rb');
        $rows = [];
        $lineNumber = 0;

        while (! $file->eof()) {
            $rawLine = $file->fgets();

            if ($rawLine === false) {
                break;
            }

            $trimmed = trim($rawLine);

            if ($trimmed === '') {
                continue;
            }

            $lineNumber++;
            $parsed = $this->parser->parse($trimmed);

            if ($parsed === null) {
                continue;
            }

            $rows[] = array_merge($parsed, [
                'import_run_id' => $importRun->id,
                'world_id' => $world->id,
                'snapshot_date' => $snapshotDate,
                'line_number' => $lineNumber,
            ]);

            if (count($rows) >= $chunkSize) {
                DB::table('staging_map_rows')->insert($rows);
                $rows = [];
            }
        }

        if ($rows !== []) {
            DB::table('staging_map_rows')->insert($rows);
        }

        return $lineNumber;
    }

    /**
     * @param array{absolute_path:string,stored_path:?string,cleanup:bool,disk:string,cleanup_path?:string} $sourceFile
     */
    private function cleanupSourceFile(array $sourceFile): void
    {
        if (! ($sourceFile['cleanup'] ?? false)) {
            return;
        }

        $cleanupPath = $sourceFile['cleanup_path'] ?? null;

        if ($cleanupPath === null) {
            return;
        }

        Storage::disk($sourceFile['disk'])->delete($cleanupPath);
    }

    private function cleanupStagingRows(int $importRunId): void
    {
        DB::table('staging_map_rows')
            ->where('import_run_id', $importRunId)
            ->delete();
    }

    /**
     * @return array{
     *     has_regions: bool,
     *     map_topology: string,
     *     map_width: ?int,
     *     map_height: ?int,
     *     map_tile_count: ?int,
     *     map_radius: ?int
     * }
     */
    private function syncDetectedWorldMapMetadata(World $world, int $importRunId): array
    {
        $summary = DB::table('staging_map_rows')
            ->where('import_run_id', $importRunId)
            ->selectRaw('MAX(map_tile_id) as max_map_tile_id')
            ->selectRaw("MAX(CASE WHEN region_name_raw IS NOT NULL AND region_name_raw <> '' THEN 1 ELSE 0 END) as has_regions")
            ->first();

        $detected = $this->worldMapMetadataDetector->detect(
            (int) ($summary->max_map_tile_id ?? 0),
            ((int) ($summary->has_regions ?? 0)) === 1,
        );

        $world->forceFill([
            'has_regions' => $detected['has_regions'],
            'map_topology' => $detected['map_topology'],
            'map_width' => $detected['map_width'],
            'map_height' => $detected['map_height'],
            'map_tile_count' => $detected['map_tile_count'],
            'map_radius' => $detected['map_radius'],
            'map_metadata_detected_at' => now(),
        ])->save();

        return $detected;
    }

    private function normalizeSnapshot(
        World $world,
        MapSnapshot $snapshot,
        MapImportRun $importRun,
        string $snapshotDate,
    ): void {
        DB::transaction(function () use ($world, $snapshot, $importRun, $snapshotDate): void {
            $allianceStats = $this->allianceStats($importRun->id);
            $playerStats = $this->playerStats($importRun->id);

            $allianceIds = $this->syncAlliances($world, $snapshot, $allianceStats);
            $playerIds = $this->syncPlayers($world, $snapshot, $playerStats, $allianceIds);
            $villageIds = $this->syncVillages($world, $snapshot, $importRun, $playerIds, $allianceIds);

            $this->markMissingAsNotPresent('alliances', $world->id, 'external_alliance_id', array_keys($allianceIds));
            $this->markMissingAsNotPresent('players', $world->id, 'external_player_id', array_keys($playerIds));
            $this->markMissingAsNotPresent('villages', $world->id, 'external_village_id', array_keys($villageIds));

            $this->replaceAllianceSnapshots($world, $snapshot, $snapshotDate, $allianceStats, $allianceIds);
            $this->replacePlayerSnapshots($world, $snapshot, $snapshotDate, $playerStats, $playerIds, $allianceIds);
            $this->replaceVillageSnapshots($world, $snapshot, $snapshotDate, $importRun, $villageIds, $playerIds, $allianceIds);

            $snapshot->forceFill([
                'status' => MapSnapshot::STATUS_SUCCESS,
                'successful_import_run_id' => $importRun->id,
                'normalized_at' => now(),
                'current_state_updated_at' => now(),
                'completed_at' => now(),
            ])->save();

            $world->forceFill([
                'current_snapshot_id' => $snapshot->id,
            ])->save();
        });
    }

    /**
     * @return Collection<int, object>
     */
    private function allianceStats(int $importRunId): Collection
    {
        return DB::table('staging_map_rows')
            ->selectRaw('external_alliance_id, alliance_tag_raw, COUNT(*) as village_count, SUM(population) as population_total, COUNT(DISTINCT external_player_id) as member_count')
            ->where('import_run_id', $importRunId)
            ->where('external_alliance_id', '>', 0)
            ->groupBy('external_alliance_id', 'alliance_tag_raw')
            ->orderBy('external_alliance_id')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    private function playerStats(int $importRunId): Collection
    {
        return DB::table('staging_map_rows')
            ->selectRaw('external_player_id, player_name_raw, MAX(external_alliance_id) as external_alliance_id, COUNT(*) as village_count, SUM(population) as population_total')
            ->where('import_run_id', $importRunId)
            ->groupBy('external_player_id', 'player_name_raw')
            ->orderBy('external_player_id')
            ->get();
    }

    /**
     * @param Collection<int, object> $allianceStats
     * @return array<int,int>
     */
    private function syncAlliances(World $world, MapSnapshot $snapshot, Collection $allianceStats): array
    {
        $externalIds = $allianceStats
            ->pluck('external_alliance_id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();

        if ($externalIds === []) {
            return [];
        }

        $existing = DB::table('alliances')
            ->where('world_id', $world->id)
            ->whereIn('external_alliance_id', $externalIds)
            ->get()
            ->keyBy('external_alliance_id');

        $inserts = [];
        $now = now();

        foreach ($allianceStats as $stat) {
            $externalId = (int) $stat->external_alliance_id;
            $payload = [
                'tag' => $this->normalizeTag($stat->alliance_tag_raw),
                'last_seen_snapshot_id' => $snapshot->id,
                'is_present' => true,
                'current_member_count' => (int) $stat->member_count,
                'current_village_count' => (int) $stat->village_count,
                'current_population_total' => (int) $stat->population_total,
                'updated_at' => $now,
            ];

            if ($existing->has($externalId)) {
                DB::table('alliances')
                    ->where('id', $existing[$externalId]->id)
                    ->update($payload);

                continue;
            }

            $inserts[] = array_merge($payload, [
                'world_id' => $world->id,
                'external_alliance_id' => $externalId,
                'first_seen_snapshot_id' => $snapshot->id,
                'created_at' => $now,
            ]);
        }

        foreach (array_chunk($inserts, 500) as $chunk) {
            DB::table('alliances')->insert($chunk);
        }

        return DB::table('alliances')
            ->where('world_id', $world->id)
            ->whereIn('external_alliance_id', $externalIds)
            ->pluck('id', 'external_alliance_id')
            ->mapWithKeys(static fn (mixed $id, mixed $externalId): array => [(int) $externalId => (int) $id])
            ->all();
    }

    /**
     * @param Collection<int, object> $playerStats
     * @param array<int,int> $allianceIds
     * @return array<int,int>
     */
    private function syncPlayers(
        World $world,
        MapSnapshot $snapshot,
        Collection $playerStats,
        array $allianceIds,
    ): array {
        $externalIds = $playerStats
            ->pluck('external_player_id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();

        $existing = DB::table('players')
            ->where('world_id', $world->id)
            ->whereIn('external_player_id', $externalIds)
            ->get()
            ->keyBy('external_player_id');

        $inserts = [];
        $now = now();

        foreach ($playerStats as $stat) {
            $externalPlayerId = (int) $stat->external_player_id;
            $externalAllianceId = (int) $stat->external_alliance_id;
            $payload = [
                'alliance_id' => $externalAllianceId > 0 ? ($allianceIds[$externalAllianceId] ?? null) : null,
                'name' => $this->normalizeRequiredText($stat->player_name_raw),
                'last_seen_snapshot_id' => $snapshot->id,
                'is_present' => true,
                'current_village_count' => (int) $stat->village_count,
                'current_population_total' => (int) $stat->population_total,
                'updated_at' => $now,
            ];

            if ($existing->has($externalPlayerId)) {
                DB::table('players')
                    ->where('id', $existing[$externalPlayerId]->id)
                    ->update($payload);

                continue;
            }

            $inserts[] = array_merge($payload, [
                'world_id' => $world->id,
                'external_player_id' => $externalPlayerId,
                'first_seen_snapshot_id' => $snapshot->id,
                'created_at' => $now,
            ]);
        }

        foreach (array_chunk($inserts, 500) as $chunk) {
            DB::table('players')->insert($chunk);
        }

        return DB::table('players')
            ->where('world_id', $world->id)
            ->whereIn('external_player_id', $externalIds)
            ->pluck('id', 'external_player_id')
            ->mapWithKeys(static fn (mixed $id, mixed $externalId): array => [(int) $externalId => (int) $id])
            ->all();
    }

    /**
     * @param array<int,int> $playerIds
     * @param array<int,int> $allianceIds
     * @return array<int,int>
     */
    private function syncVillages(
        World $world,
        MapSnapshot $snapshot,
        MapImportRun $importRun,
        array $playerIds,
        array $allianceIds,
    ): array {
        $externalVillageIds = DB::table('staging_map_rows')
            ->where('import_run_id', $importRun->id)
            ->distinct()
            ->orderBy('external_village_id')
            ->pluck('external_village_id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();

        $existing = DB::table('villages')
            ->where('world_id', $world->id)
            ->whereIn('external_village_id', $externalVillageIds)
            ->get()
            ->keyBy('external_village_id');

        $chunkSize = max(1, (int) config('travtool.imports.snapshot_chunk_size', 500));
        $inserts = [];
        $now = now();

        DB::table('staging_map_rows')
            ->where('import_run_id', $importRun->id)
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $rows) use (&$inserts, $allianceIds, $existing, $now, $playerIds, $snapshot, $world): void {
                foreach ($rows as $row) {
                    $externalVillageId = (int) $row->external_village_id;
                    $externalAllianceId = (int) $row->external_alliance_id;
                    $payload = [
                        'map_tile_id' => (int) $row->map_tile_id,
                        'player_id' => $playerIds[(int) $row->external_player_id] ?? null,
                        'alliance_id' => $externalAllianceId > 0 ? ($allianceIds[$externalAllianceId] ?? null) : null,
                        'name' => $this->normalizeRequiredText($row->village_name_raw),
                        'x' => (int) $row->x,
                        'y' => (int) $row->y,
                        'tribe_id' => (int) $row->tribe_id,
                        'population' => (int) $row->population,
                        'region_name' => $this->normalizeText($row->region_name_raw),
                        'is_capital' => $row->is_capital,
                        'is_city' => $row->is_city,
                        'has_harbor' => $row->has_harbor,
                        'victory_points' => $row->victory_points !== null ? (int) $row->victory_points : null,
                        'last_seen_snapshot_id' => $snapshot->id,
                        'is_present' => true,
                        'updated_at' => $now,
                    ];

                    if ($existing->has($externalVillageId)) {
                        DB::table('villages')
                            ->where('id', $existing[$externalVillageId]->id)
                            ->update($payload);

                        continue;
                    }

                    $inserts[] = array_merge($payload, [
                        'world_id' => $world->id,
                        'external_village_id' => $externalVillageId,
                        'first_seen_snapshot_id' => $snapshot->id,
                        'created_at' => $now,
                    ]);
                }

                if ($inserts === []) {
                    return;
                }

                foreach (array_chunk($inserts, 500) as $chunk) {
                    DB::table('villages')->insert($chunk);
                }

                $inserts = [];
            });

        if ($inserts !== []) {
            foreach (array_chunk($inserts, 500) as $chunk) {
                DB::table('villages')->insert($chunk);
            }
        }

        return DB::table('villages')
            ->where('world_id', $world->id)
            ->whereIn('external_village_id', $externalVillageIds)
            ->pluck('id', 'external_village_id')
            ->mapWithKeys(static fn (mixed $id, mixed $externalId): array => [(int) $externalId => (int) $id])
            ->all();
    }

    /**
     * @param array<int,int> $presentExternalIds
     */
    private function markMissingAsNotPresent(
        string $table,
        int $worldId,
        string $externalIdColumn,
        array $presentExternalIds,
    ): void {
        $query = DB::table($table)
            ->where('world_id', $worldId)
            ->where('is_present', true);

        if ($presentExternalIds !== []) {
            $query->whereNotIn($externalIdColumn, $presentExternalIds);
        }

        $query->update([
            'is_present' => false,
            'updated_at' => now(),
        ]);
    }

    /**
     * @param Collection<int, object> $allianceStats
     * @param array<int,int> $allianceIds
     */
    private function replaceAllianceSnapshots(
        World $world,
        MapSnapshot $snapshot,
        string $snapshotDate,
        Collection $allianceStats,
        array $allianceIds,
    ): void {
        DB::table('alliance_snapshots')
            ->where('snapshot_id', $snapshot->id)
            ->delete();

        if ($allianceStats->isEmpty()) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($allianceStats as $stat) {
            $externalAllianceId = (int) $stat->external_alliance_id;
            $rows[] = [
                'world_id' => $world->id,
                'snapshot_id' => $snapshot->id,
                'alliance_id' => $allianceIds[$externalAllianceId],
                'snapshot_date' => $snapshotDate,
                'external_alliance_id' => $externalAllianceId,
                'tag' => $this->normalizeTag($stat->alliance_tag_raw),
                'member_count' => (int) $stat->member_count,
                'village_count' => (int) $stat->village_count,
                'population_total' => (int) $stat->population_total,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('alliance_snapshots')->insert($chunk);
        }
    }

    /**
     * @param Collection<int, object> $playerStats
     * @param array<int,int> $playerIds
     * @param array<int,int> $allianceIds
     */
    private function replacePlayerSnapshots(
        World $world,
        MapSnapshot $snapshot,
        string $snapshotDate,
        Collection $playerStats,
        array $playerIds,
        array $allianceIds,
    ): void {
        DB::table('player_snapshots')
            ->where('snapshot_id', $snapshot->id)
            ->delete();

        if ($playerStats->isEmpty()) {
            return;
        }

        $previousSnapshots = $snapshot->previous_snapshot_id === null
            ? collect()
            : DB::table('player_snapshots')
                ->where('snapshot_id', $snapshot->previous_snapshot_id)
                ->get()
                ->keyBy('player_id');

        $now = now();
        $rows = [];

        foreach ($playerStats as $stat) {
            $externalPlayerId = (int) $stat->external_player_id;
            $externalAllianceId = (int) $stat->external_alliance_id;
            $playerId = $playerIds[$externalPlayerId];
            $previous = $previousSnapshots->get($playerId);
            $populationTotal = (int) $stat->population_total;
            $villageCount = (int) $stat->village_count;

            $rows[] = [
                'world_id' => $world->id,
                'snapshot_id' => $snapshot->id,
                'player_id' => $playerId,
                'alliance_id' => $externalAllianceId > 0 ? ($allianceIds[$externalAllianceId] ?? null) : null,
                'snapshot_date' => $snapshotDate,
                'external_player_id' => $externalPlayerId,
                'external_alliance_id' => $externalAllianceId > 0 ? $externalAllianceId : null,
                'name' => $this->normalizeRequiredText($stat->player_name_raw),
                'village_count' => $villageCount,
                'population_total' => $populationTotal,
                'population_delta_1d' => $previous !== null ? $populationTotal - (int) $previous->population_total : null,
                'village_count_delta_1d' => $previous !== null ? $villageCount - (int) $previous->village_count : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('player_snapshots')->insert($chunk);
        }
    }

    /**
     * @param array<int,int> $villageIds
     * @param array<int,int> $playerIds
     * @param array<int,int> $allianceIds
     */
    private function replaceVillageSnapshots(
        World $world,
        MapSnapshot $snapshot,
        string $snapshotDate,
        MapImportRun $importRun,
        array $villageIds,
        array $playerIds,
        array $allianceIds,
    ): void {
        DB::table('village_snapshots')
            ->where('snapshot_id', $snapshot->id)
            ->delete();

        $chunkSize = max(1, (int) config('travtool.imports.snapshot_chunk_size', 500));
        $now = now();

        DB::table('staging_map_rows')
            ->where('import_run_id', $importRun->id)
            ->orderBy('id')
            ->chunkById($chunkSize, function (Collection $stagingRows) use ($allianceIds, $now, $playerIds, $snapshot, $snapshotDate, $villageIds, $world): void {
                $rows = [];

                foreach ($stagingRows as $row) {
                    $externalAllianceId = (int) $row->external_alliance_id;

                    $rows[] = [
                        'world_id' => $world->id,
                        'snapshot_id' => $snapshot->id,
                        'village_id' => $villageIds[(int) $row->external_village_id],
                        'player_id' => $playerIds[(int) $row->external_player_id] ?? null,
                        'alliance_id' => $externalAllianceId > 0 ? ($allianceIds[$externalAllianceId] ?? null) : null,
                        'snapshot_date' => $snapshotDate,
                        'map_tile_id' => (int) $row->map_tile_id,
                        'external_village_id' => (int) $row->external_village_id,
                        'external_player_id' => (int) $row->external_player_id,
                        'external_alliance_id' => $externalAllianceId,
                        'x' => (int) $row->x,
                        'y' => (int) $row->y,
                        'tribe_id' => (int) $row->tribe_id,
                        'name' => $this->normalizeRequiredText($row->village_name_raw),
                        'population' => (int) $row->population,
                        'region_name' => $this->normalizeText($row->region_name_raw),
                        'is_capital' => $row->is_capital,
                        'is_city' => $row->is_city,
                        'has_harbor' => $row->has_harbor,
                        'victory_points' => $row->victory_points !== null ? (int) $row->victory_points : null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('village_snapshots')->insert($rows);
                }
            });
    }

    private function normalizeText(?string $value, int $limit = 255): ?string
    {
        if ($value === null) {
            return null;
        }

        $decoded = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = trim($decoded);

        if ($decoded === '') {
            return null;
        }

        return mb_substr($decoded, 0, $limit);
    }

    private function normalizeRequiredText(?string $value): string
    {
        return $this->normalizeText($value) ?? '';
    }

    private function normalizeTag(?string $value): string
    {
        return $this->normalizeText($value, 64) ?? '';
    }
}
