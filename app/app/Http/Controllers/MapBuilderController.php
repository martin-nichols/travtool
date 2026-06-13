<?php

namespace App\Http\Controllers;

use App\Models\World;
use App\Services\UserWorldPreferenceService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class MapBuilderController extends Controller
{
    /**
     * @var list<string>
     */
    private const ALLIANCE_PALETTE = [
        '#ff7a1a',
        '#4c7cf0',
        '#19a974',
        '#c64d79',
        '#7a5cff',
        '#e0a100',
        '#2aa6a4',
        '#d45b34',
        '#4b8b3b',
        '#8b5cf6',
        '#0f9dce',
        '#cb4f3f',
    ];

    /**
     * @var list<string>
     */
    private const REGION_PALETTE = [
        '#2e86ab',
        '#7d5ba6',
        '#3d9970',
        '#b56576',
        '#0081a7',
        '#6c757d',
        '#9c6644',
        '#588157',
    ];

    /**
     * @var list<string>
     */
    private const STANDALONE_PLAYER_PALETTE = [
        '#4c7cf0',
        '#ff7a1a',
        '#19a974',
        '#d45b34',
        '#8b5cf6',
        '#2aa6a4',
        '#c64d79',
        '#e0a100',
    ];

    /**
     * @var list<int>
     */
    private const PLAYER_VARIANTS = [18, -14, 32, -28, 8, -8, 42, -38];

    /**
     * @var list<int>
     */
    private const PALETTE_CYCLE_VARIANTS = [22, -18, 34, -30, 12, -42];

    private const WORLD_MIN = -400;
    private const WORLD_MAX = 400;
    private const WORLD_SIZE = 800;
    private const VIEWBOX_PADDING = 22;
    private const MIN_VIEWBOX_SIZE = 120;

    public function __construct(
        private readonly UserWorldPreferenceService $worldPreferences,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $filters = $this->validatedFilters($request);
        $requestedWorldKey = $filters['world'];
        $filters['world'] = $this->worldPreferences->resolveSelectedWorldKey($request->user(), $filters['world']);
        $worldContext = $this->worldContext($filters['world']);

        if ($request->user() !== null && $requestedWorldKey !== '' && $worldContext['selected_world_key'] !== '') {
            $this->worldPreferences->rememberWorld($request->user(), $worldContext['selected_world_key']);
        }

        $mapData = $this->buildMapData($worldContext, $filters);

        return Inertia::render('MapBuilder', [
            'filters' => $filters,
            'worlds' => $worldContext['worlds'],
            'savedMaps' => $this->savedMaps($request),
            'summary' => [
                'selectedWorldKey' => $worldContext['selected_world_key'],
                'selectedWorldName' => $worldContext['selected_world_name'],
                'selectedWorldBaseUrl' => $worldContext['selected_world_base_url'],
                'selectedWorldHasRegions' => $worldContext['selected_world_has_regions'],
                'currentSnapshotDate' => $worldContext['current_snapshot_date'],
                'hasImportedSnapshot' => $worldContext['has_imported_snapshot'],
                'matchedVillageCount' => $mapData['matched_village_count'],
                'legendCount' => count($mapData['legend']),
                'hasCriteria' => $mapData['has_criteria'],
                'shareUrl' => $this->shareUrl($filters, $worldContext['selected_world_key'], $mapData['has_criteria']),
            ],
            'map' => $mapData,
        ]);
    }

    /**
     * @return array{
     *     world:string,
     *     alliance_tags:string,
     *     player_names:string,
     *     region_names:string
     * }
     */
    private function validatedFilters(Request $request): array
    {
        $validated = Validator::make($request->query(), [
            'world' => ['nullable', 'string', 'max:100'],
            'alliance_tags' => ['nullable', 'string', 'max:4000'],
            'player_names' => ['nullable', 'string', 'max:4000'],
            'region_names' => ['nullable', 'string', 'max:4000'],
        ])->validate();

        return [
            'world' => trim((string) ($validated['world'] ?? '')),
            'alliance_tags' => trim((string) ($validated['alliance_tags'] ?? '')),
            'player_names' => trim((string) ($validated['player_names'] ?? '')),
            'region_names' => trim((string) ($validated['region_names'] ?? '')),
        ];
    }

    /**
     * @return array{
     *     worlds: array<int, array{key:string,name:string,base_url:string,category_key:string,has_imported_snapshot:bool,current_snapshot_date:?string}>,
     *     selected_world_key:string,
     *     selected_world_name:string,
     *     selected_world_base_url:string,
     *     selected_world_has_regions:bool,
     *     current_snapshot_id:?int,
     *     current_snapshot_date:?string,
     *     has_imported_snapshot:bool,
     *     world_id:?int
     * }
     */
    private function worldContext(string $selectedWorldKey): array
    {
        $availableWorlds = $this->worldPreferences->availableWorldMap();

        $worldModels = World::query()
            ->with('currentSnapshot:id,snapshot_date')
            ->whereIn('key', $availableWorlds->keys()->all())
            ->get()
            ->keyBy('key');

        $selectedKey = $availableWorlds->has($selectedWorldKey) ? $selectedWorldKey : '';
        $selectedWorld = (array) ($availableWorlds->get($selectedKey) ?? []);
        $selectedModel = $selectedKey !== '' ? $worldModels->get($selectedKey) : null;

        $worlds = $availableWorlds
            ->map(function (array $availableWorld, string $key) use ($worldModels): array {
                /** @var World|null $model */
                $model = $worldModels->get($key);
                $currentSnapshot = $model?->currentSnapshot;

                return [
                    'key' => $key,
                    'name' => (string) ($availableWorld['name'] ?? $key),
                    'base_url' => (string) ($availableWorld['base_url'] ?? ''),
                    'category_key' => (string) ($availableWorld['category_key'] ?? 'other'),
                    'has_imported_snapshot' => $currentSnapshot !== null,
                    'current_snapshot_date' => $currentSnapshot?->snapshot_date?->toDateString(),
                ];
            })
            ->values()
            ->all();

        return [
            'worlds' => $worlds,
            'selected_world_key' => $selectedKey,
            'selected_world_name' => (string) ($selectedWorld['name'] ?? ''),
            'selected_world_base_url' => (string) ($selectedWorld['base_url'] ?? ''),
            'selected_world_has_regions' => (bool) ($selectedModel?->has_regions ?? false),
            'current_snapshot_id' => $selectedModel?->current_snapshot_id,
            'current_snapshot_date' => $selectedModel?->currentSnapshot?->snapshot_date?->toDateString(),
            'has_imported_snapshot' => $selectedModel?->currentSnapshot !== null,
            'world_id' => $selectedModel?->id,
        ];
    }

    /**
     * @param array{
     *     selected_world_key:string,
     *     selected_world_name:string,
     *     selected_world_base_url:string,
     *     selected_world_has_regions:bool,
     *     current_snapshot_id:?int,
     *     current_snapshot_date:?string,
     *     has_imported_snapshot:bool,
     *     world_id:?int,
     *     worlds: array<int, array{key:string,name:string,base_url:string,has_imported_snapshot:bool,current_snapshot_date:?string}>
     * } $worldContext
     * @param array{
     *     world:string,
     *     alliance_tags:string,
     *     player_names:string,
     *     region_names:string
     * } $filters
     * @return array{
     *     status:string,
     *     has_criteria:bool,
     *     matched_village_count:int,
     *     criteria: array{alliances:list<string>,players:list<string>,regions:list<string>},
     *     villages: array<int, array<string, mixed>>,
     *     legend: array<int, array<string, mixed>>,
     *     bounds: array{min_x:int,max_x:int,min_y:int,max_y:int}|null,
     *     view_box: array{x:float,y:float,width:float,height:float},
     *     world_size:int
     * }
     */
    private function buildMapData(array $worldContext, array $filters): array
    {
        $criteria = [
            'alliances' => $this->parseInputList($filters['alliance_tags']),
            'players' => $this->parseInputList($filters['player_names']),
            'regions' => $worldContext['selected_world_has_regions'] ? $this->parseInputList($filters['region_names']) : [],
        ];

        $hasCriteria = $criteria['alliances'] !== [] || $criteria['players'] !== [] || $criteria['regions'] !== [];

        if ($worldContext['selected_world_key'] === '') {
            return $this->emptyMapPayload('choose_world', $criteria, $hasCriteria);
        }

        if (! $worldContext['has_imported_snapshot'] || $worldContext['world_id'] === null) {
            return $this->emptyMapPayload('waiting_snapshot', $criteria, $hasCriteria);
        }

        if (! $hasCriteria) {
            $criteria = $this->automaticCriteria((int) $worldContext['world_id']);
            $hasCriteria = $criteria['alliances'] !== [] || $criteria['players'] !== [];

            if (! $hasCriteria) {
                return $this->emptyMapPayload('choose_criteria', $criteria, false);
            }
        }

        $rows = $this->matchingVillagesQuery((int) $worldContext['world_id'], $criteria)->get();

        if ($rows->isEmpty()) {
            return $this->emptyMapPayload('empty', $criteria, true);
        }

        [$villages, $legend, $bounds, $viewBox] = $this->buildVisualPayload($rows, $criteria);

        return [
            'status' => 'ready',
            'has_criteria' => true,
            'matched_village_count' => count($villages),
            'criteria' => $criteria,
            'villages' => $villages,
            'legend' => $legend,
            'bounds' => $bounds,
            'view_box' => $viewBox,
            'world_size' => self::WORLD_SIZE,
        ];
    }

    /**
     * @param array{alliances:list<string>,players:list<string>,regions:list<string>} $criteria
     */
    private function matchingVillagesQuery(int $worldId, array $criteria): Builder
    {
        $allianceLookup = $this->normalizedLookup($criteria['alliances']);
        $playerLookup = $this->normalizedLookup($criteria['players']);
        $regionLookup = $this->normalizedLookup($criteria['regions']);

        $query = DB::table('villages as v')
            ->join('players as p', 'p.id', '=', 'v.player_id')
            ->leftJoin('alliances as a', 'a.id', '=', 'v.alliance_id')
            ->where('v.world_id', $worldId)
            ->where('v.is_present', true)
            ->where('p.is_present', true)
            ->where(function (Builder $subQuery) use ($allianceLookup, $playerLookup, $regionLookup): void {
                if ($allianceLookup !== []) {
                    $subQuery->orWhereIn(DB::raw('LOWER(a.tag)'), array_keys($allianceLookup));
                }

                if ($playerLookup !== []) {
                    $subQuery->orWhereIn(DB::raw('LOWER(p.name)'), array_keys($playerLookup));
                }

                if ($regionLookup !== []) {
                    $subQuery->orWhereIn(DB::raw('LOWER(v.region_name)'), array_keys($regionLookup));
                }
            })
            ->select([
                'v.id',
                'v.name as village_name',
                'v.x',
                'v.y',
                'v.tribe_id',
                'v.population',
                'v.region_name',
                'p.name as player_name',
                'a.tag as alliance_tag',
            ])
            ->orderBy('v.x')
            ->orderBy('v.y')
            ->orderBy('v.id');

        return $query;
    }

    /**
     * @return array{alliances:list<string>,players:list<string>,regions:list<string>}
     */
    private function automaticCriteria(int $worldId): array
    {
        $alliances = DB::table('alliances')
            ->where('world_id', $worldId)
            ->where('is_present', true)
            ->where('tag', '!=', '')
            ->orderByDesc('current_population_total')
            ->orderBy('tag')
            ->limit(5)
            ->pluck('tag')
            ->map(static fn (mixed $tag): string => (string) $tag)
            ->all();

        $players = DB::table('players')
            ->where('world_id', $worldId)
            ->where('is_present', true)
            ->where('name', '!=', '')
            ->orderByDesc('current_population_total')
            ->orderBy('name')
            ->limit(5)
            ->pluck('name')
            ->map(static fn (mixed $name): string => (string) $name)
            ->all();

        return [
            'alliances' => $alliances,
            'players' => $players,
            'regions' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function savedMaps(Request $request): array
    {
        $user = $request->user();

        if ($user === null) {
            return [];
        }

        return $user->maps()
            ->latest('updated_at')
            ->limit(10)
            ->get(['id', 'name', 'world_key', 'alliance_tags', 'player_names', 'region_names', 'updated_at'])
            ->map(static fn ($map): array => [
                'id' => $map->id,
                'name' => $map->name,
                'world_key' => $map->world_key,
                'alliance_tags' => $map->alliance_tags ?? '',
                'player_names' => $map->player_names ?? '',
                'region_names' => $map->region_names ?? '',
                'updated_at' => $map->updated_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @param Collection<int, object> $rows
     * @param array{alliances:list<string>,players:list<string>,regions:list<string>} $criteria
     * @return array{
     *     0: array<int, array<string, mixed>>,
     *     1: array<int, array<string, mixed>>,
     *     2: array{min_x:int,max_x:int,min_y:int,max_y:int},
     *     3: array{x:float,y:float,width:float,height:float}
     * }
     */
    private function buildVisualPayload(Collection $rows, array $criteria): array
    {
        $allianceSelection = $this->normalizedLookup($criteria['alliances']);
        $playerSelection = $this->normalizedLookup($criteria['players']);
        $regionSelection = $this->normalizedLookup($criteria['regions']);

        $allianceLegend = [];
        $playerLegend = [];
        $regionLegend = [];
        $villages = [];
        $mapXValues = [];
        $mapYValues = [];

        foreach ($rows as $row) {
            $playerName = (string) $row->player_name;
            $allianceTag = $row->alliance_tag !== null && $row->alliance_tag !== '' ? (string) $row->alliance_tag : null;
            $regionName = $row->region_name !== null && $row->region_name !== '' ? (string) $row->region_name : null;

            $matchedAlliance = $allianceTag !== null ? ($allianceSelection[mb_strtolower($allianceTag)] ?? null) : null;
            $matchedPlayer = $playerSelection[mb_strtolower($playerName)] ?? null;
            $matchedRegion = $regionName !== null ? ($regionSelection[mb_strtolower($regionName)] ?? null) : null;

            $legendKey = '';
            $legendType = '';
            $legendLabel = '';
            $parentLabel = null;
            $note = null;
            $color = '#7f8c8d';

            if ($matchedPlayer !== null) {
                if ($allianceTag !== null) {
                    $allianceItem = &$this->ensureAllianceLegendItem($allianceLegend, $allianceTag);
                    $playerItem = &$this->ensurePlayerLegendItem($playerLegend, $playerName, $allianceTag, $allianceItem['color']);

                    $legendKey = $playerItem['key'];
                    $legendType = 'player';
                    $legendLabel = $playerItem['label'];
                    $parentLabel = $playerItem['parent_label'];
                    $note = $playerItem['note'];
                    $color = $playerItem['color'];
                } else {
                    $playerItem = &$this->ensureStandalonePlayerLegendItem($playerLegend, $playerName);

                    $legendKey = $playerItem['key'];
                    $legendType = 'player';
                    $legendLabel = $playerItem['label'];
                    $color = $playerItem['color'];
                }
            } elseif ($matchedAlliance !== null && $allianceTag !== null) {
                $allianceItem = &$this->ensureAllianceLegendItem($allianceLegend, $allianceTag);

                $legendKey = $allianceItem['key'];
                $legendType = 'alliance';
                $legendLabel = $allianceItem['label'];
                $color = $allianceItem['color'];
            } elseif ($matchedRegion !== null && $regionName !== null) {
                $regionItem = &$this->ensureRegionLegendItem($regionLegend, $regionName);

                $legendKey = $regionItem['key'];
                $legendType = 'region';
                $legendLabel = $regionItem['label'];
                $color = $regionItem['color'];
            }

            if ($legendKey === '') {
                continue;
            }

            $this->incrementLegendUsage($allianceLegend, $playerLegend, $regionLegend, $legendKey);

            $mapX = $this->mapX((int) $row->x);
            $mapY = $this->mapY((int) $row->y);
            $mapXValues[] = $mapX;
            $mapYValues[] = $mapY;

            $villages[] = [
                'id' => (int) $row->id,
                'village_name' => (string) $row->village_name,
                'player_name' => $playerName,
                'alliance_tag' => $allianceTag,
                'region_name' => $regionName,
                'tribe_id' => $row->tribe_id !== null ? (int) $row->tribe_id : null,
                'population' => (int) $row->population,
                'coords' => [
                    'x' => (int) $row->x,
                    'y' => (int) $row->y,
                ],
                'map' => [
                    'x' => $mapX,
                    'y' => $mapY,
                ],
                'legend_key' => $legendKey,
                'legend_type' => $legendType,
                'legend_label' => $legendLabel,
                'legend_parent_label' => $parentLabel,
                'legend_note' => $note,
                'color' => $color,
                'stroke_color' => $this->adjustHexColor($color, -34),
            ];
        }

        $bounds = $this->buildBounds($villages);
        $viewBox = $this->buildViewBox($bounds);

        return [
            $villages,
            $this->buildLegendPayload($allianceLegend, $playerLegend, $regionLegend),
            $bounds,
            $viewBox,
        ];
    }

    /**
     * @param list<string> $values
     * @return array<string, string>
     */
    private function normalizedLookup(array $values): array
    {
        $lookup = [];

        foreach ($values as $value) {
            $lookup[mb_strtolower($value)] = $value;
        }

        return $lookup;
    }

    /**
     * @return list<string>
     */
    private function parseInputList(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $items = preg_split('/[\r\n,;]+/', $value) ?: [];
        $cleaned = [];

        foreach ($items as $item) {
            $item = trim($item);

            if ($item === '') {
                continue;
            }

            $cleaned[mb_strtolower($item)] = $item;
        }

        return array_values($cleaned);
    }

    /**
     * @param array<string, array<string, mixed>> $allianceLegend
     * @return array<string, mixed>
     */
    private function &ensureAllianceLegendItem(array &$allianceLegend, string $allianceTag): array
    {
        $key = 'alliance:'.$allianceTag;

        if (! isset($allianceLegend[$key])) {
            $allianceLegend[$key] = [
                'key' => $key,
                'type' => 'alliance',
                'label' => $allianceTag,
                'color' => $this->pickSequentialPaletteColor(count($allianceLegend), self::ALLIANCE_PALETTE),
                'count' => 0,
                'parent_key' => null,
                'parent_label' => null,
                'note' => null,
            ];
        }

        return $allianceLegend[$key];
    }

    /**
     * @param array<string, array<string, mixed>> $playerLegend
     * @return array<string, mixed>
     */
    private function &ensurePlayerLegendItem(array &$playerLegend, string $playerName, string $allianceTag, string $baseAllianceColor): array
    {
        $key = 'player:'.$playerName;

        if (! isset($playerLegend[$key])) {
            $variantIndex = count(array_filter(
                $playerLegend,
                static fn (array $item): bool => ($item['parent_key'] ?? null) === 'alliance:'.$allianceTag,
            ));

            $playerLegend[$key] = [
                'key' => $key,
                'type' => 'player',
                'label' => $playerName,
                'color' => $this->buildPlayerVariantColor($baseAllianceColor, $variantIndex),
                'count' => 0,
                'parent_key' => 'alliance:'.$allianceTag,
                'parent_label' => $allianceTag,
                'note' => $allianceTag,
            ];
        }

        return $playerLegend[$key];
    }

    /**
     * @param array<string, array<string, mixed>> $playerLegend
     * @return array<string, mixed>
     */
    private function &ensureStandalonePlayerLegendItem(array &$playerLegend, string $playerName): array
    {
        $key = 'player:'.$playerName;

        if (! isset($playerLegend[$key])) {
            $standaloneCount = count(array_filter(
                $playerLegend,
                static fn (array $item): bool => ($item['parent_key'] ?? null) === null,
            ));

            $playerLegend[$key] = [
                'key' => $key,
                'type' => 'player',
                'label' => $playerName,
                'color' => $this->pickSequentialPaletteColor($standaloneCount, self::STANDALONE_PLAYER_PALETTE),
                'count' => 0,
                'parent_key' => null,
                'parent_label' => null,
                'note' => null,
            ];
        }

        return $playerLegend[$key];
    }

    /**
     * @param array<string, array<string, mixed>> $regionLegend
     * @return array<string, mixed>
     */
    private function &ensureRegionLegendItem(array &$regionLegend, string $regionName): array
    {
        $key = 'region:'.$regionName;

        if (! isset($regionLegend[$key])) {
            $regionLegend[$key] = [
                'key' => $key,
                'type' => 'region',
                'label' => $regionName,
                'color' => $this->pickSequentialPaletteColor(count($regionLegend), self::REGION_PALETTE),
                'count' => 0,
                'parent_key' => null,
                'parent_label' => null,
                'note' => null,
            ];
        }

        return $regionLegend[$key];
    }

    /**
     * @param array<string, array<string, mixed>> $allianceLegend
     * @param array<string, array<string, mixed>> $playerLegend
     * @param array<string, array<string, mixed>> $regionLegend
     */
    private function incrementLegendUsage(array &$allianceLegend, array &$playerLegend, array &$regionLegend, string $legendKey): void
    {
        if (isset($allianceLegend[$legendKey])) {
            $allianceLegend[$legendKey]['count']++;

            return;
        }

        if (isset($playerLegend[$legendKey])) {
            $playerLegend[$legendKey]['count']++;

            return;
        }

        if (isset($regionLegend[$legendKey])) {
            $regionLegend[$legendKey]['count']++;
        }
    }

    /**
     * @param array<string, array<string, mixed>> $allianceLegend
     * @param array<string, array<string, mixed>> $playerLegend
     * @param array<string, array<string, mixed>> $regionLegend
     * @return array<int, array<string, mixed>>
     */
    private function buildLegendPayload(array $allianceLegend, array $playerLegend, array $regionLegend): array
    {
        $legend = [];

        foreach ($allianceLegend as $allianceItem) {
            $legend[] = $allianceItem;

            foreach ($playerLegend as $playerItem) {
                if ($playerItem['parent_key'] === $allianceItem['key']) {
                    $legend[] = $playerItem;
                }
            }
        }

        foreach ($regionLegend as $regionItem) {
            $legend[] = $regionItem;
        }

        foreach ($playerLegend as $playerItem) {
            if ($playerItem['parent_key'] === null) {
                $legend[] = $playerItem;
            }
        }

        return array_values($legend);
    }

    private function pickSequentialPaletteColor(int $index, array $palette): string
    {
        if ($palette === []) {
            return '#7f8c8d';
        }

        $paletteSize = count($palette);
        $baseColor = $palette[$index % $paletteSize];
        $cycle = intdiv($index, $paletteSize);

        if ($cycle === 0) {
            return $baseColor;
        }

        $delta = self::PALETTE_CYCLE_VARIANTS[($cycle - 1) % count(self::PALETTE_CYCLE_VARIANTS)];

        return $this->adjustHexColor($baseColor, $delta);
    }

    private function buildPlayerVariantColor(string $baseHex, int $variantIndex): string
    {
        $delta = self::PLAYER_VARIANTS[$variantIndex % count(self::PLAYER_VARIANTS)];

        if ($variantIndex >= count(self::PLAYER_VARIANTS)) {
            $cycleIndex = (intdiv($variantIndex, count(self::PLAYER_VARIANTS)) - 1) % count(self::PALETTE_CYCLE_VARIANTS);
            $delta += self::PALETTE_CYCLE_VARIANTS[$cycleIndex];
        }

        return $this->adjustHexColor($baseHex, $delta);
    }

    private function adjustHexColor(string $hex, int $delta): string
    {
        $hex = ltrim($hex, '#');

        if (strlen($hex) !== 6) {
            return '#7f8c8d';
        }

        $rgb = [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];

        foreach ($rgb as $index => $channel) {
            if ($delta >= 0) {
                $rgb[$index] = (int) round($channel + ((255 - $channel) * ($delta / 100)));
            } else {
                $rgb[$index] = (int) round($channel * (1 + ($delta / 100)));
            }

            $rgb[$index] = max(0, min(255, $rgb[$index]));
        }

        return sprintf('#%02x%02x%02x', $rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * @param array<int, array<string, mixed>> $villages
     * @return array{min_x:int,max_x:int,min_y:int,max_y:int}
     */
    private function buildBounds(array $villages): array
    {
        $coordsX = array_map(static fn (array $village): int => (int) $village['coords']['x'], $villages);
        $coordsY = array_map(static fn (array $village): int => (int) $village['coords']['y'], $villages);

        return [
            'min_x' => min($coordsX),
            'max_x' => max($coordsX),
            'min_y' => min($coordsY),
            'max_y' => max($coordsY),
        ];
    }

    /**
     * @param array{min_x:int,max_x:int,min_y:int,max_y:int} $bounds
     * @return array{x:float,y:float,width:float,height:float}
     */
    private function buildViewBox(array $bounds): array
    {
        $minMapX = $this->mapX($bounds['min_x']);
        $maxMapX = $this->mapX($bounds['max_x']);
        $minMapY = $this->mapY($bounds['max_y']);
        $maxMapY = $this->mapY($bounds['min_y']);

        $width = max(self::MIN_VIEWBOX_SIZE, ($maxMapX - $minMapX) + (self::VIEWBOX_PADDING * 2));
        $height = max(self::MIN_VIEWBOX_SIZE, ($maxMapY - $minMapY) + (self::VIEWBOX_PADDING * 2));

        $x = max(0, ($minMapX - self::VIEWBOX_PADDING) - max(0, (self::MIN_VIEWBOX_SIZE - ($maxMapX - $minMapX)) / 2));
        $y = max(0, ($minMapY - self::VIEWBOX_PADDING) - max(0, (self::MIN_VIEWBOX_SIZE - ($maxMapY - $minMapY)) / 2));

        if ($x + $width > self::WORLD_SIZE) {
            $x = max(0, self::WORLD_SIZE - $width);
        }

        if ($y + $height > self::WORLD_SIZE) {
            $y = max(0, self::WORLD_SIZE - $height);
        }

        return [
            'x' => $x,
            'y' => $y,
            'width' => min(self::WORLD_SIZE, $width),
            'height' => min(self::WORLD_SIZE, $height),
        ];
    }

    private function mapX(int $x): int
    {
        return $x - self::WORLD_MIN;
    }

    private function mapY(int $y): int
    {
        return self::WORLD_MAX - $y;
    }

    /**
     * @param array{alliances:list<string>,players:list<string>,regions:list<string>} $criteria
     * @return array{
     *     status:string,
     *     has_criteria:bool,
     *     matched_village_count:int,
     *     criteria: array{alliances:list<string>,players:list<string>,regions:list<string>},
     *     villages: array<int, array<string, mixed>>,
     *     legend: array<int, array<string, mixed>>,
     *     bounds: null,
     *     view_box: array{x:float,y:float,width:float,height:float},
     *     world_size:int
     * }
     */
    private function emptyMapPayload(string $status, array $criteria, bool $hasCriteria): array
    {
        return [
            'status' => $status,
            'has_criteria' => $hasCriteria,
            'matched_village_count' => 0,
            'criteria' => $criteria,
            'villages' => [],
            'legend' => [],
            'bounds' => null,
            'view_box' => [
                'x' => 0,
                'y' => 0,
                'width' => self::WORLD_SIZE,
                'height' => self::WORLD_SIZE,
            ],
            'world_size' => self::WORLD_SIZE,
        ];
    }

    /**
     * @param array{
     *     world:string,
     *     alliance_tags:string,
     *     player_names:string,
     *     region_names:string
     * } $filters
     */
    private function shareUrl(array $filters, string $selectedWorldKey, bool $hasCriteria): ?string
    {
        if ($selectedWorldKey === '' || ! $hasCriteria) {
            return null;
        }

        $query = array_filter([
            'world' => $selectedWorldKey,
            'alliance_tags' => implode(', ', $this->parseInputList($filters['alliance_tags'])),
            'player_names' => implode(', ', $this->parseInputList($filters['player_names'])),
            'region_names' => implode(', ', $this->parseInputList($filters['region_names'])),
        ], static fn (mixed $value): bool => is_string($value) ? $value !== '' : $value !== null);

        return route('map-builder', $query, true);
    }
}
