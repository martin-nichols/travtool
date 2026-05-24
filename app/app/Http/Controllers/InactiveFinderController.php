<?php

namespace App\Http\Controllers;

use App\Models\World;
use App\Services\UserWorldPreferenceService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class InactiveFinderController extends Controller
{
    private const PER_PAGE = 25;

    public function __construct(
        private readonly UserWorldPreferenceService $worldPreferences,
    ) {
    }

    /**
     * @var array<int, array{value:int,label:string}>
     */
    private const TRIBES = [
        ['value' => 1, 'label' => 'Romans'],
        ['value' => 2, 'label' => 'Teutons'],
        ['value' => 3, 'label' => 'Gauls'],
        ['value' => 5, 'label' => 'Natars'],
        ['value' => 6, 'label' => 'Egyptians'],
        ['value' => 7, 'label' => 'Huns'],
        ['value' => 8, 'label' => 'Spartans'],
        ['value' => 9, 'label' => 'Vikings'],
    ];

    public function __invoke(Request $request): Response
    {
        $filters = $this->validatedFilters($request);
        $requestedWorldKey = $filters['world'];
        $filters['world'] = $this->worldPreferences->resolveSelectedWorldKey($request->user(), $filters['world']);
        $worldContext = $this->worldContext($filters['world']);

        if ($request->user() !== null && $requestedWorldKey !== '' && $worldContext['selected_world_key'] !== '') {
            $this->worldPreferences->rememberWorld($request->user(), $worldContext['selected_world_key']);
        }

        $searchContext = $this->searchResults($request, $worldContext, $filters);

        return Inertia::render('InactiveFinder', [
            'filters' => $filters,
            'worlds' => $worldContext['worlds'],
            'tribes' => self::TRIBES,
            'sorts' => [
                ['value' => 'score', 'label' => 'inactive_finder.sort.score'],
                ['value' => 'population_asc', 'label' => 'inactive_finder.sort.population_asc'],
                ['value' => 'population_desc', 'label' => 'inactive_finder.sort.population_desc'],
                ['value' => 'distance_asc', 'label' => 'inactive_finder.sort.distance_asc'],
            ],
            'summary' => [
                'activeWorldCount' => $worldContext['active_world_count'],
                'selectedWorldKey' => $worldContext['selected_world_key'],
                'selectedWorldName' => $worldContext['selected_world_name'],
                'selectedWorldBaseUrl' => $worldContext['selected_world_base_url'],
                'currentSnapshotDate' => $worldContext['current_snapshot_date'],
                'lastImportAt' => $worldContext['last_import_at'],
                'historyReady' => $worldContext['history_ready'],
                'resultsCount' => $searchContext['results_count'],
                'hasImportedSnapshot' => $worldContext['has_imported_snapshot'],
            ],
            'results' => $searchContext['results'],
        ]);
    }

    /**
     * @return array{
     *     world:string,
     *     q:?string,
     *     tribe_id:?int,
     *     min_score:?int,
     *     min_population:?int,
     *     max_population:?int,
     *     x:?int,
     *     y:?int,
     *     radius_min:?int,
     *     radius_max:?int,
     *     no_alliance:bool,
     *     one_village:bool,
     *     stable_only:bool,
     *     include_npcs:bool,
     *     sort:string
     * }
     */
    private function validatedFilters(Request $request): array
    {
        $validated = Validator::make($this->normalizedFilterInput($request), [
            'world' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:120'],
            'tribe_id' => ['nullable', 'integer', 'in:1,2,3,5,6,7,8,9'],
            'min_score' => ['nullable', 'integer', 'min:0', 'max:999'],
            'min_population' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'max_population' => ['nullable', 'integer', 'min:0', 'max:999999'],
            'x' => ['nullable', 'integer', 'min:-400', 'max:400'],
            'y' => ['nullable', 'integer', 'min:-400', 'max:400'],
            'radius_min' => ['nullable', 'integer', 'min:0', 'max:400'],
            'radius_max' => ['nullable', 'integer', 'min:0', 'max:400'],
            'no_alliance' => ['nullable', 'boolean'],
            'one_village' => ['nullable', 'boolean'],
            'stable_only' => ['nullable', 'boolean'],
            'include_npcs' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'in:score,population_asc,population_desc,distance_asc'],
        ])->after(function ($validator) use (&$request): void {
            $data = $validator->validated();

            if (
                isset($data['radius_min'], $data['radius_max'])
                && (int) $data['radius_min'] > (int) $data['radius_max']
            ) {
                $validator->errors()->add('radius_min', 'The minimum radius must be less than or equal to the maximum radius.');
            }
        })->validate();

        return [
            'world' => (string) ($validated['world'] ?? ''),
            'q' => $this->filledString($validated['q'] ?? null),
            'tribe_id' => isset($validated['tribe_id']) ? (int) $validated['tribe_id'] : null,
            'min_score' => isset($validated['min_score']) ? (int) $validated['min_score'] : 100,
            'min_population' => isset($validated['min_population']) ? (int) $validated['min_population'] : null,
            'max_population' => isset($validated['max_population']) ? (int) $validated['max_population'] : null,
            'x' => isset($validated['x']) ? (int) $validated['x'] : null,
            'y' => isset($validated['y']) ? (int) $validated['y'] : null,
            'radius_min' => isset($validated['radius_min']) ? (int) $validated['radius_min'] : null,
            'radius_max' => isset($validated['radius_max']) ? (int) $validated['radius_max'] : null,
            'no_alliance' => filter_var($validated['no_alliance'] ?? false, FILTER_VALIDATE_BOOL),
            'one_village' => filter_var($validated['one_village'] ?? true, FILTER_VALIDATE_BOOL),
            'stable_only' => filter_var($validated['stable_only'] ?? false, FILTER_VALIDATE_BOOL),
            'include_npcs' => filter_var($validated['include_npcs'] ?? false, FILTER_VALIDATE_BOOL),
            'sort' => (string) ($validated['sort'] ?? 'score'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizedFilterInput(Request $request): array
    {
        $input = $request->query();

        foreach (['world', 'q', 'tribe_id', 'min_score', 'min_population', 'max_population', 'x', 'y', 'radius', 'radius_min', 'radius_max', 'sort'] as $key) {
            if (array_key_exists($key, $input) && is_string($input[$key])) {
                $input[$key] = trim($input[$key]);
            }
        }

        foreach (['tribe_id', 'min_score', 'min_population', 'max_population', 'x', 'y', 'radius', 'radius_min', 'radius_max'] as $key) {
            if (($input[$key] ?? null) === '') {
                $input[$key] = null;
            }
        }

        foreach (['no_alliance', 'one_village', 'stable_only', 'include_npcs'] as $key) {
            if (array_key_exists($key, $input)) {
                $input[$key] = $this->normalizeBooleanInput($input[$key]);
            }
        }

        if (($input['y'] ?? null) === null && is_string($input['x'] ?? null)) {
            $coordinates = $this->parseCombinedCoordinates($input['x']);

            if ($coordinates !== null) {
                $input['x'] = $coordinates['x'];
                $input['y'] = $coordinates['y'];
            }
        }

        if (($input['radius_max'] ?? null) === null && ($input['radius'] ?? null) !== null) {
            $input['radius_max'] = $input['radius'];
        }

        if (($input['radius_max'] ?? null) === null && ($input['radius_min'] ?? null) !== null) {
            $input['radius_max'] = $input['radius_min'];
            $input['radius_min'] = null;
        }

        if (
            isset($input['radius_min'], $input['radius_max'])
            && is_numeric($input['radius_min'])
            && is_numeric($input['radius_max'])
            && (int) $input['radius_min'] > (int) $input['radius_max']
        ) {
            [$input['radius_min'], $input['radius_max']] = [$input['radius_max'], $input['radius_min']];
        }

        return $input;
    }

    private function normalizeBooleanInput(mixed $value): mixed
    {
        if ($value === null || is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (! is_string($value)) {
            return $value;
        }

        return match (strtolower(trim($value))) {
            '1', 'true', 'on', 'yes' => true,
            '0', 'false', 'off', 'no', '' => false,
            default => $value,
        };
    }

    /**
     * @return array{x:int,y:int}|null
     */
    private function parseCombinedCoordinates(string $value): ?array
    {
        if (! preg_match('/^\s*(-?\d+)\s*\|\s*(-?\d+)\s*$/', $value, $matches)) {
            return null;
        }

        return [
            'x' => (int) $matches[1],
            'y' => (int) $matches[2],
        ];
    }

    /**
     * @param string $selectedWorldKey
     * @return array{
     *     worlds: array<int, array{key:string,name:string,base_url:string,has_imported_snapshot:bool,current_snapshot_date:?string,history_ready:bool}>,
     *     active_world_count:int,
     *     selected_world_key:string,
     *     selected_world_name:string,
     *     selected_world_base_url:string,
     *     selected_world_topology:string,
     *     selected_world_radius:int,
     *     current_snapshot_id:?int,
     *     current_snapshot_date:?string,
     *     last_import_at:?string,
     *     history_ready:bool,
     *     has_imported_snapshot:bool,
     *     world_id:?int
     * }
     */
    private function worldContext(string $selectedWorldKey): array
    {
        $configuredWorlds = collect(config('travtool.worlds', []))
            ->filter(static fn (mixed $world): bool => is_array($world) && (bool) ($world['is_active'] ?? true));

        $worldModels = World::query()
            ->with('currentSnapshot:id,snapshot_date,completed_at,previous_snapshot_id')
            ->whereIn('key', $configuredWorlds->keys()->all())
            ->get()
            ->keyBy('key');

        $selectedKey = $configuredWorlds->has($selectedWorldKey) ? $selectedWorldKey : '';
        $selectedConfig = (array) ($configuredWorlds->get($selectedKey) ?? []);
        $selectedModel = $selectedKey !== '' ? $worldModels->get($selectedKey) : null;

        $worlds = $configuredWorlds
            ->map(function (array $configuredWorld, string $key) use ($worldModels): array {
                /** @var World|null $model */
                $model = $worldModels->get($key);
                $currentSnapshot = $model?->currentSnapshot;

                return [
                    'key' => $key,
                    'name' => (string) ($configuredWorld['name'] ?? $key),
                    'base_url' => (string) ($configuredWorld['base_url'] ?? ''),
                    'has_imported_snapshot' => $currentSnapshot !== null,
                    'current_snapshot_date' => $currentSnapshot?->snapshot_date?->toDateString(),
                    'history_ready' => $currentSnapshot?->previous_snapshot_id !== null,
                ];
            })
            ->values()
            ->all();

        return [
            'worlds' => $worlds,
            'active_world_count' => count($worlds),
            'selected_world_key' => $selectedKey,
            'selected_world_name' => (string) ($selectedConfig['name'] ?? $selectedKey),
            'selected_world_base_url' => (string) ($selectedConfig['base_url'] ?? ''),
            'selected_world_topology' => (string) ($selectedConfig['map_topology'] ?? 'torus'),
            'selected_world_radius' => (int) ($selectedConfig['map_radius'] ?? 400),
            'current_snapshot_id' => $selectedModel?->current_snapshot_id,
            'current_snapshot_date' => $selectedModel?->currentSnapshot?->snapshot_date?->toDateString(),
            'last_import_at' => $selectedModel?->currentSnapshot?->completed_at?->toIso8601String(),
            'history_ready' => $selectedModel?->currentSnapshot?->previous_snapshot_id !== null,
            'has_imported_snapshot' => $selectedModel?->currentSnapshot !== null,
            'world_id' => $selectedModel?->id,
        ];
    }

    /**
     * @param array{
     *     worlds: array<int, array{key:string,name:string,base_url:string,has_imported_snapshot:bool,current_snapshot_date:?string,history_ready:bool}>,
     *     active_world_count:int,
     *     selected_world_key:string,
     *     selected_world_name:string,
     *     selected_world_base_url:string,
     *     selected_world_topology:string,
     *     selected_world_radius:int,
     *     current_snapshot_id:?int,
     *     current_snapshot_date:?string,
     *     last_import_at:?string,
     *     history_ready:bool,
     *     has_imported_snapshot:bool,
     *     world_id:?int
     * } $worldContext
     * @param array{
     *     world:string,
     *     q:?string,
     *     tribe_id:?int,
     *     min_score:?int,
     *     min_population:?int,
     *     max_population:?int,
     *     x:?int,
     *     y:?int,
     *     radius_min:?int,
     *     radius_max:?int,
     *     no_alliance:bool,
     *     one_village:bool,
     *     stable_only:bool,
     *     include_npcs:bool,
     *     sort:string
     * } $filters
     * @return array{results:LengthAwarePaginator,results_count:int}
     */
    private function searchResults(Request $request, array $worldContext, array $filters): array
    {
        if ($worldContext['world_id'] === null || $worldContext['current_snapshot_id'] === null) {
            return [
                'results' => $this->emptyPaginator($request),
                'results_count' => 0,
            ];
        }

        $hasDistanceSearch = $filters['x'] !== null && $filters['y'] !== null;
        $query = DB::table('villages as v')
            ->join('players as p', 'p.id', '=', 'v.player_id')
            ->leftJoin('alliances as a', 'a.id', '=', 'v.alliance_id')
            ->leftJoin('player_snapshots as ps', function ($join) use ($worldContext): void {
                $join->on('ps.player_id', '=', 'p.id')
                    ->where('ps.snapshot_id', '=', $worldContext['current_snapshot_id']);
            })
            ->where('v.world_id', $worldContext['world_id'])
            ->where('v.is_present', true)
            ->where('p.is_present', true)
            ->select([
                'v.id',
                'v.name as village_name',
                'v.x',
                'v.y',
                'v.tribe_id',
                'v.population',
                'v.region_name',
                'p.name as player_name',
                'p.current_village_count',
                'p.current_population_total',
                'ps.population_delta_1d',
                'ps.village_count_delta_1d',
                'a.tag as alliance_tag',
            ])
            ->selectRaw($this->scoreExpression().' as score');

        if ($hasDistanceSearch) {
            [$distanceExpression, $distanceBindings] = $this->distanceSql(
                $filters['x'],
                $filters['y'],
                $worldContext['selected_world_topology'],
                $worldContext['selected_world_radius'],
            );

            $query->selectRaw(
                'ROUND('.$distanceExpression.', 2) as distance',
                $distanceBindings,
            );
        } else {
            $query->selectRaw('NULL as distance');
        }

        $this->applyFilters(
            $query,
            $filters,
            $worldContext['history_ready'],
            $worldContext['selected_world_topology'],
            $worldContext['selected_world_radius'],
        );
        $this->applySorting($query, $filters['sort'], $hasDistanceSearch);

        $results = $query
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(function (object $row): array {
                return [
                    'village_name' => (string) $row->village_name,
                    'player_name' => (string) $row->player_name,
                    'alliance_tag' => $row->alliance_tag !== null && $row->alliance_tag !== '' ? (string) $row->alliance_tag : null,
                    'coords' => [
                        'x' => (int) $row->x,
                        'y' => (int) $row->y,
                    ],
                    'tribe_id' => (int) $row->tribe_id,
                    'population' => (int) $row->population,
                    'region_name' => $row->region_name !== null && $row->region_name !== '' ? (string) $row->region_name : null,
                    'player_village_count' => (int) $row->current_village_count,
                    'player_population_total' => (int) $row->current_population_total,
                    'population_delta_1d' => $row->population_delta_1d !== null ? (int) $row->population_delta_1d : null,
                    'village_count_delta_1d' => $row->village_count_delta_1d !== null ? (int) $row->village_count_delta_1d : null,
                    'score' => (int) $row->score,
                    'distance' => $row->distance !== null ? (float) $row->distance : null,
                ];
            });

        return [
            'results' => $results,
            'results_count' => $results->total(),
        ];
    }

    /**
     * @param array{
     *     world:string,
     *     q:?string,
     *     tribe_id:?int,
     *     min_score:?int,
     *     min_population:?int,
     *     max_population:?int,
     *     x:?int,
     *     y:?int,
     *     radius_min:?int,
     *     radius_max:?int,
     *     no_alliance:bool,
     *     one_village:bool,
     *     stable_only:bool,
     *     include_npcs:bool,
     *     sort:string
     * } $filters
     */
    private function applyFilters(
        Builder $query,
        array $filters,
        bool $historyReady,
        string $worldTopology,
        int $worldRadius,
    ): void
    {
        if (! $filters['include_npcs']) {
            $query->where('v.tribe_id', '!=', 5);
        }

        if ($filters['q'] !== null) {
            $needle = '%'.$filters['q'].'%';

            $query->where(function (Builder $subQuery) use ($needle): void {
                $subQuery
                    ->where('v.name', 'like', $needle)
                    ->orWhere('p.name', 'like', $needle)
                    ->orWhere('a.tag', 'like', $needle);
            });
        }

        if ($filters['tribe_id'] !== null) {
            $query->where('v.tribe_id', $filters['tribe_id']);
        }

        if ($filters['min_score'] !== null) {
            $query->whereRaw($this->scoreExpression().' >= ?', [$filters['min_score']]);
        }

        if ($filters['min_population'] !== null) {
            $query->where('v.population', '>=', $filters['min_population']);
        }

        if ($filters['max_population'] !== null) {
            $query->where('v.population', '<=', $filters['max_population']);
        }

        if ($filters['no_alliance']) {
            $query->whereNull('v.alliance_id');
        }

        if ($filters['one_village']) {
            $query->where('p.current_village_count', 1);
        }

        if ($filters['stable_only'] && $historyReady) {
            $query->where('ps.population_delta_1d', 0)
                ->where('ps.village_count_delta_1d', 0);
        }

        if ($filters['x'] !== null && $filters['y'] !== null && $filters['radius_max'] !== null) {
            [$distanceExpression, $distanceBindings] = $this->distanceSql(
                $filters['x'],
                $filters['y'],
                $worldTopology,
                $worldRadius,
            );

            $query->whereRaw(
                $distanceExpression.' <= ?',
                [...$distanceBindings, $filters['radius_max']],
            );
        }

        if ($filters['x'] !== null && $filters['y'] !== null && $filters['radius_min'] !== null) {
            [$distanceExpression, $distanceBindings] = $this->distanceSql(
                $filters['x'],
                $filters['y'],
                $worldTopology,
                $worldRadius,
            );

            $query->whereRaw(
                $distanceExpression.' >= ?',
                [...$distanceBindings, $filters['radius_min']],
            );
        }
    }

    /**
     * @return array{0:string,1:list<int|string>}
     */
    private function distanceSql(int $centerX, int $centerY, string $topology, int $worldRadius): array
    {
        if ($topology === 'plane') {
            return [
                'SQRT(POW(v.x - ?, 2) + POW(v.y - ?, 2))',
                [$centerX, $centerY],
            ];
        }

        $wrapSize = ($worldRadius * 2) + 1;

        return [
            'SQRT(POW(LEAST(ABS(v.x - ?), ? - ABS(v.x - ?)), 2) + POW(LEAST(ABS(v.y - ?), ? - ABS(v.y - ?)), 2))',
            [
                $centerX,
                $wrapSize,
                $centerX,
                $centerY,
                $wrapSize,
                $centerY,
            ],
        ];
    }

    private function applySorting(Builder $query, string $sort, bool $hasDistanceSearch): void
    {
        match ($sort) {
            'population_asc' => $query->orderBy('v.population')->orderByDesc('score')->orderBy('v.id'),
            'population_desc' => $query->orderByDesc('v.population')->orderByDesc('score')->orderBy('v.id'),
            'distance_asc' => $hasDistanceSearch
                ? $query->orderBy('distance')->orderByDesc('score')->orderBy('v.population')->orderBy('v.id')
                : $query->orderByDesc('score')->orderBy('v.population')->orderBy('v.id'),
            default => $hasDistanceSearch
                ? $query->orderByDesc('score')->orderBy('distance')->orderBy('v.population')->orderBy('v.id')
                : $query->orderByDesc('score')->orderBy('v.population')->orderBy('v.id'),
        };
    }

    private function scoreExpression(): string
    {
        return <<<'SQL'
(
    CASE WHEN p.current_village_count = 1 THEN 35 ELSE 0 END
    + CASE WHEN v.alliance_id IS NULL THEN 20 ELSE 0 END
    + CASE WHEN v.population <= 120 THEN 18 WHEN v.population <= 180 THEN 10 WHEN v.population <= 250 THEN 4 ELSE 0 END
    + CASE WHEN ps.population_delta_1d = 0 THEN 12 ELSE 0 END
    + CASE WHEN ps.village_count_delta_1d = 0 THEN 15 ELSE 0 END
)
SQL;
    }

    private function emptyPaginator(Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            [],
            0,
            self::PER_PAGE,
            1,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ],
        );
    }

    private function filledString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
