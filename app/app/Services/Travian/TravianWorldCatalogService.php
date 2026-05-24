<?php

namespace App\Services\Travian;

use App\Models\World;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class TravianWorldCatalogService
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    /**
     * @return array{processed:int,created:int,updated:int,skipped:int}
     */
    public function sync(
        ?string $calendarSource = null,
        ?string $metadataSource = null,
        bool $activateNewWorlds = false,
    ): array {
        $calendarPayload = $this->loadJsonPayload(
            $calendarSource,
            (string) config('travtool.catalog.calendar_url'),
            'calendar',
        );

        $metadataPayload = $this->loadJsonPayload(
            $metadataSource,
            (string) config('travtool.catalog.metadata_url'),
            'metadata',
        );

        $calendarWorlds = $this->normalizeCalendarPayload($calendarPayload);
        $metadataWorlds = $this->normalizeMetadataPayload($metadataPayload);
        $catalogWorlds = $this->mergeCatalogWorlds($calendarWorlds, $metadataWorlds);

        $now = now();
        $processed = 0;
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $activateNewWorlds,
            $catalogWorlds,
            $now,
            &$processed,
            &$created,
            &$updated,
            &$skipped,
        ): void {
            foreach ($catalogWorlds as $worldData) {
                if (
                    $worldData['external_uuid'] === null
                    || $worldData['catalog_slug'] === null
                    || $worldData['base_url'] === null
                ) {
                    $skipped++;

                    continue;
                }

                $processed++;
                $existingWorld = $this->findExistingWorld($worldData);
                $isNewWorld = $existingWorld === null;

                if ($existingWorld === null) {
                    $existingWorld = new World([
                        'key' => $this->uniqueWorldKey(
                            $worldData['catalog_slug'],
                            $worldData['external_uuid'],
                        ),
                        'is_active' => $activateNewWorlds || (bool) config('travtool.catalog.activate_new_worlds', false),
                    ]);
                }

                $existingWorld->forceFill($this->worldAttributes($existingWorld, $worldData, $now));
                $existingWorld->save();

                if ($isNewWorld) {
                    $created++;
                } else {
                    $updated++;
                }
            }
        });

        return [
            'processed' => $processed,
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array<int, array{
     *     external_uuid:?string,
     *     catalog_slug:?string,
     *     catalog_domain:?string,
     *     name:?string,
     *     base_url:?string,
     *     map_sql_url:?string,
     *     game_type:?string,
     *     speed:?int,
     *     starts_at:?Carbon,
     *     ends_at:?Carbon,
     *     registration_closed:?bool,
     *     mainpage_background:?string,
     *     mainpage_groups:array<int,string>,
     *     languages:array<int,string>,
     *     tribe_names:array<int,string>,
     *     map_width:?int,
     *     map_height:?int
     * }>
     */
    private function normalizeCalendarPayload(mixed $payload): array
    {
        $items = $this->payloadItems($payload);
        $worlds = [];

        foreach ($items as $item) {
            $normalized = $this->normalizeCatalogItem($item);

            if ($normalized['external_uuid'] === null) {
                continue;
            }

            $worlds[$normalized['external_uuid']] = $normalized;
        }

        return array_values($worlds);
    }

    /**
     * @return array<int, array{
     *     external_uuid:?string,
     *     catalog_slug:?string,
     *     catalog_domain:?string,
     *     name:?string,
     *     base_url:?string,
     *     map_sql_url:?string,
     *     game_type:?string,
     *     speed:?int,
     *     starts_at:?Carbon,
     *     ends_at:?Carbon,
     *     registration_closed:?bool,
     *     mainpage_background:?string,
     *     mainpage_groups:array<int,string>,
     *     languages:array<int,string>,
     *     tribe_names:array<int,string>,
     *     map_width:?int,
     *     map_height:?int
     * }>
     */
    private function normalizeMetadataPayload(mixed $payload): array
    {
        $items = $this->payloadItems($payload);
        $worlds = [];

        foreach ($items as $item) {
            $normalized = $this->normalizeCatalogItem($item);

            if ($normalized['external_uuid'] === null) {
                continue;
            }

            $worlds[$normalized['external_uuid']] = $normalized;
        }

        return array_values($worlds);
    }

    /**
     * @param array<int, array<string, mixed>> $calendarWorlds
     * @param array<int, array<string, mixed>> $metadataWorlds
     * @return array<int, array<string, mixed>>
     */
    private function mergeCatalogWorlds(array $calendarWorlds, array $metadataWorlds): array
    {
        $merged = [];

        foreach ($calendarWorlds as $world) {
            $uuid = (string) $world['external_uuid'];
            $merged[$uuid] = $world;
        }

        foreach ($metadataWorlds as $world) {
            $uuid = (string) $world['external_uuid'];
            $merged[$uuid] = isset($merged[$uuid])
                ? $this->mergeCatalogItem($merged[$uuid], $world)
                : $world;
        }

        return array_values($merged);
    }

    /**
     * @param array<string, mixed> $primary
     * @param array<string, mixed> $overlay
     * @return array<string, mixed>
     */
    private function mergeCatalogItem(array $primary, array $overlay): array
    {
        foreach ($overlay as $key => $value) {
            if ($value !== null) {
                $primary[$key] = $value;
            }
        }

        return $primary;
    }

    /**
     * @param array<string, mixed> $worldData
     */
    private function findExistingWorld(array $worldData): ?World
    {
        $externalUuid = $worldData['external_uuid'];
        $baseUrl = $worldData['base_url'];
        $mapSqlUrl = $worldData['map_sql_url'];
        $catalogSlug = $worldData['catalog_slug'];

        if ($externalUuid !== null) {
            $byUuid = World::query()->where('external_uuid', $externalUuid)->first();

            if ($byUuid !== null) {
                return $byUuid;
            }
        }

        if ($baseUrl !== null) {
            $byBaseUrl = World::query()->where('base_url', $baseUrl)->first();

            if ($byBaseUrl !== null) {
                return $byBaseUrl;
            }
        }

        if ($mapSqlUrl !== null) {
            $byMapSqlUrl = World::query()->where('map_sql_url', $mapSqlUrl)->first();

            if ($byMapSqlUrl !== null) {
                return $byMapSqlUrl;
            }
        }

        if ($catalogSlug !== null) {
            return World::query()->where('key', $catalogSlug)->first();
        }

        return null;
    }

    /**
     * @param array<string, mixed> $worldData
     * @return array<string, mixed>
     */
    private function worldAttributes(World $world, array $worldData, Carbon $now): array
    {
        $derivedGeometry = $this->deriveGeometryFromCatalog(
            $worldData['game_type'],
            $worldData['map_width'],
            $worldData['map_height'],
        );

        $prefillGeometry = $world->map_metadata_detected_at === null;

        return [
            'external_uuid' => $worldData['external_uuid'],
            'catalog_slug' => $worldData['catalog_slug'],
            'catalog_domain' => $worldData['catalog_domain'],
            'name' => $worldData['name'] ?? $world->name ?? $worldData['catalog_slug'],
            'base_url' => $worldData['base_url'],
            'map_sql_url' => $worldData['map_sql_url'],
            'server_timezone' => $world->server_timezone ?: (string) config('travtool.catalog.default_server_timezone', 'UTC'),
            'import_time' => $world->import_time ?: (string) config('travtool.catalog.default_import_time', '00:10'),
            'speed' => $worldData['speed'] ?? $world->speed,
            'game_type' => $worldData['game_type'],
            'starts_at' => $worldData['starts_at'],
            'ends_at' => $worldData['ends_at'],
            'registration_closed' => $worldData['registration_closed'],
            'mainpage_background' => $worldData['mainpage_background'],
            'mainpage_groups' => $worldData['mainpage_groups'],
            'languages' => $worldData['languages'],
            'tribe_names' => $worldData['tribe_names'],
            'catalog_last_seen_at' => $now,
            'catalog_synced_at' => $now,
            'has_regions' => $prefillGeometry ? $derivedGeometry['has_regions'] : $world->has_regions,
            'map_topology' => $prefillGeometry ? $derivedGeometry['map_topology'] : $world->map_topology,
            'map_width' => $prefillGeometry ? $derivedGeometry['map_width'] : $world->map_width,
            'map_height' => $prefillGeometry ? $derivedGeometry['map_height'] : $world->map_height,
            'map_tile_count' => $prefillGeometry ? $derivedGeometry['map_tile_count'] : $world->map_tile_count,
            'map_radius' => $prefillGeometry ? $derivedGeometry['map_radius'] : $world->map_radius,
        ];
    }

    /**
     * @return array{
     *     has_regions:bool,
     *     map_topology:string,
     *     map_width:?int,
     *     map_height:?int,
     *     map_tile_count:?int,
     *     map_radius:?int
     * }
     */
    private function deriveGeometryFromCatalog(?string $gameType, ?int $mapWidth, ?int $mapHeight): array
    {
        $normalizedType = strtolower(trim((string) $gameType));
        $hasRegions = $normalizedType === 'rof';
        $mapTopology = $hasRegions ? 'plane' : 'torus';

        if ($mapWidth === null || $mapHeight === null || $mapWidth <= 0 || $mapHeight <= 0) {
            return [
                'has_regions' => $hasRegions,
                'map_topology' => $mapTopology,
                'map_width' => null,
                'map_height' => null,
                'map_tile_count' => null,
                'map_radius' => null,
            ];
        }

        return [
            'has_regions' => $hasRegions,
            'map_topology' => $mapTopology,
            'map_width' => $mapWidth,
            'map_height' => $mapHeight,
            'map_tile_count' => $mapWidth * $mapHeight,
            'map_radius' => $mapWidth === $mapHeight ? intdiv($mapWidth - 1, 2) : null,
        ];
    }

    /**
     * @param array<string, mixed> $item
     * @return array{
     *     external_uuid:?string,
     *     catalog_slug:?string,
     *     catalog_domain:?string,
     *     name:?string,
     *     base_url:?string,
     *     map_sql_url:?string,
     *     game_type:?string,
     *     speed:?int,
     *     starts_at:?Carbon,
     *     ends_at:?Carbon,
     *     registration_closed:?bool,
     *     mainpage_background:?string,
     *     mainpage_groups:array<int,string>,
     *     languages:array<int,string>,
     *     tribe_names:array<int,string>,
     *     map_width:?int,
     *     map_height:?int
     * }
     */
    private function normalizeCatalogItem(array $item): array
    {
        $baseUrl = $this->normalizeBaseUrl($this->stringOrNull($this->value($item, ['metadata', 'url'])));
        $mapWidth = $this->positiveInt($this->value($item, ['info', 'mapConfiguration', 'width']))
            ?? $this->positiveInt($this->value($item, ['info', 'serverConfiguration', 'map', 'width']));
        $mapHeight = $this->positiveInt($this->value($item, ['info', 'mapConfiguration', 'height']))
            ?? $this->positiveInt($this->value($item, ['info', 'serverConfiguration', 'map', 'height']));

        return [
            'external_uuid' => $this->stringOrNull($item['uuid'] ?? null),
            'catalog_slug' => $this->stringOrNull($item['name'] ?? null),
            'catalog_domain' => $this->stringOrNull($item['domain'] ?? null),
            'name' => $this->stringOrNull($this->value($item, ['metadata', 'name'])),
            'base_url' => $baseUrl,
            'map_sql_url' => $baseUrl !== null ? $baseUrl.'map.sql' : null,
            'game_type' => $this->stringOrNull($this->value($item, ['metadata', 'type'])),
            'speed' => $this->positiveInt($this->value($item, ['metadata', 'speed']))
                ?? $this->positiveInt($this->value($item, ['info', 'serverConfiguration', 'speed'])),
            'starts_at' => $this->timestampToCarbon($item['start'] ?? null),
            'ends_at' => $this->timestampToCarbon($item['end'] ?? null),
            'registration_closed' => $this->booleanOrNull($this->value($item, ['flags', 'registrationClosed'])),
            'mainpage_background' => $this->stringOrNull($this->value($item, ['metadata', 'mainpageBackground'])),
            'mainpage_groups' => $this->stringList($this->value($item, ['metadata', 'mainpageGroups'])),
            'languages' => $this->stringList($this->value($item, ['info', 'serverConfiguration', 'languages'])),
            'tribe_names' => $this->stringList($this->value($item, ['info', 'serverConfiguration', 'tribeNames'])),
            'map_width' => $mapWidth,
            'map_height' => $mapHeight,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function payloadItems(mixed $payload): array
    {
        if (is_array($payload)) {
            if ($this->isList($payload)) {
                return $payload;
            }

            $gameworlds = $this->value($payload, ['data', 'gameworlds']);

            if (is_array($gameworlds) && $this->isList($gameworlds)) {
                return $gameworlds;
            }

            $gameworlds = $payload['gameworlds'] ?? null;

            if (is_array($gameworlds) && $this->isList($gameworlds)) {
                return $gameworlds;
            }
        }

        throw new RuntimeException('Unsupported world catalog payload structure.');
    }

    private function loadJsonPayload(?string $sourcePath, string $url, string $label): mixed
    {
        if ($sourcePath !== null) {
            if (! is_file($sourcePath)) {
                throw new RuntimeException(sprintf('Catalog source file not found: %s', $sourcePath));
            }

            $raw = file_get_contents($sourcePath);

            if ($raw === false) {
                throw new RuntimeException(sprintf('Unable to read catalog source file: %s', $sourcePath));
            }
        } else {
            $normalizedUrl = trim($url);

            if ($normalizedUrl === '') {
                throw new RuntimeException(sprintf('Missing %s catalog URL.', $label));
            }

            try {
                $response = $this->http
                    ->timeout(60)
                    ->connectTimeout(20)
                    ->retry(3, 2000)
                    ->withUserAgent('Travtool/0.1')
                    ->get($normalizedUrl);

                if (! $response->successful()) {
                    throw new RuntimeException(sprintf(
                        'Failed to load %s catalog from %s (HTTP %s).',
                        $label,
                        $normalizedUrl,
                        $response->status(),
                    ));
                }

                $raw = $response->body();
            } catch (Throwable $exception) {
                $raw = $this->fallbackRemoteRead($normalizedUrl);

                if ($raw === null) {
                    throw new RuntimeException(sprintf(
                        'Failed to load %s catalog from %s: %s',
                        $label,
                        $normalizedUrl,
                        $exception->getMessage(),
                    ), previous: $exception);
                }
            }
        }

        /** @var mixed $decoded */
        $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function fallbackRemoteRead(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 60,
                'header' => "User-Agent: Travtool/0.1\r\n",
            ],
        ]);

        $content = @file_get_contents($url, false, $context);

        return $content === false ? null : $content;
    }

    private function uniqueWorldKey(string $catalogSlug, string $externalUuid): string
    {
        $baseKey = strtolower(trim($catalogSlug));
        $baseKey = preg_replace('/[^a-z0-9_-]+/', '-', $baseKey) ?? '';
        $baseKey = trim($baseKey, '-');
        $baseKey = $baseKey !== '' ? $baseKey : 'world-'.substr(strtolower($externalUuid), 0, 8);
        $baseKey = substr($baseKey, 0, 100);
        $candidate = $baseKey;
        $suffix = 2;

        while (World::query()->where('key', $candidate)->exists()) {
            $suffixToken = '-'.$suffix;
            $candidate = substr($baseKey, 0, 100 - strlen($suffixToken)).$suffixToken;
            $suffix++;
        }

        return $candidate;
    }

    private function normalizeBaseUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }

        return rtrim($url, '/').'/';
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function positiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }

    private function booleanOrNull(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        return (bool) $value;
    }

    /**
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $items = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }

            $item = trim($item);

            if ($item === '') {
                continue;
            }

            $items[] = $item;
        }

        return array_values(array_unique($items));
    }

    private function timestampToCarbon(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return Carbon::createFromTimestampUTC((int) $value);
    }

    /**
     * @param array<string, mixed> $item
     * @param list<string> $path
     */
    private function value(array $item, array $path): mixed
    {
        $current = $item;

        foreach ($path as $segment) {
            if (! is_array($current) || ! array_key_exists($segment, $current)) {
                return null;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    /**
     * @param array<mixed> $value
     */
    private function isList(array $value): bool
    {
        if (function_exists('array_is_list')) {
            return array_is_list($value);
        }

        return array_keys($value) === range(0, count($value) - 1);
    }
}
