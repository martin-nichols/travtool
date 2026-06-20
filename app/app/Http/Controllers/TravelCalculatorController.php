<?php

namespace App\Http\Controllers;

use App\Models\PlayedAccountTroop;
use App\Models\TravianTroop;
use App\Models\UserPlayedAccount;
use App\Models\World;
use App\Services\UserWorldPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class TravelCalculatorController extends Controller
{
    public function __construct(
        private readonly UserWorldPreferenceService $worldPreferences,
    ) {
    }

    public function index(Request $request): Response
    {
        $accounts = $this->playedAccounts($request);
        $requestedWorldKey = is_string($request->query('world')) ? trim($request->query('world')) : '';
        $selectedWorldKey = $this->selectedWorldKey($request, $accounts, $requestedWorldKey);
        $selectedAccount = $accounts->firstWhere('world_key', $selectedWorldKey);

        if ($selectedAccount !== null && $requestedWorldKey !== '') {
            $this->worldPreferences->rememberWorld($request->user(), $selectedWorldKey);
        }

        $world = $selectedWorldKey !== '' ? World::query()->where('key', $selectedWorldKey)->first() : null;
        $worldRadius = (int) ($world?->map_radius ?? config("travtool.worlds.$selectedWorldKey.map_radius", 400));
        $serverSpeed = $world?->speed !== null ? (int) $world->speed : (int) (config("travtool.worlds.$selectedWorldKey.speed") ?? 1);
        $movementSpeedFactor = $this->movementSpeedFactor($serverSpeed);
        $ownedVillages = $this->ownedVillages($selectedAccount, $world);

        return Inertia::render('TravelCalculator', [
            'selectedWorldKey' => $selectedWorldKey,
            'selectedAccount' => $selectedAccount !== null ? [
                'world_key' => $selectedAccount['world_key'],
                'world_name' => $selectedAccount['world_name'],
                'player_name' => $selectedAccount['player_name'],
            ] : null,
            'world' => [
                'speed' => $serverSpeed,
                'movement_speed_factor' => $movementSpeedFactor,
                'radius' => $worldRadius,
                'size' => ($worldRadius * 2) + 1,
            ],
            'ownedVillages' => $ownedVillages,
            'availableTroopsByVillage' => $this->availableTroopsByVillage($selectedAccount),
            'catalogTroops' => $this->catalogTroops(),
        ]);
    }

    public function searchVillages(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'world_key' => ['required', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $worldKey = trim((string) $validated['world_key']);
        $query = trim((string) ($validated['q'] ?? ''));

        abort_unless($this->worldPreferences->isActiveWorldKey($worldKey), 422);

        if (mb_strlen($query) < 2 || ! Schema::hasTable('villages')) {
            return response()->json([]);
        }

        $world = World::query()->where('key', $worldKey)->first();

        if ($world === null) {
            return response()->json([]);
        }

        $villages = DB::table('villages as v')
            ->leftJoin('players as p', 'p.id', '=', 'v.player_id')
            ->where('v.world_id', $world->id)
            ->where('v.is_present', true)
            ->where('v.name', 'like', '%'.$query.'%')
            ->orderByRaw('CASE WHEN LOWER(v.name) LIKE ? THEN 0 ELSE 1 END', [mb_strtolower($query).'%'])
            ->orderByDesc('v.population')
            ->orderBy('v.name')
            ->limit(12)
            ->get(['v.id', 'v.name', 'v.x', 'v.y', 'v.population', 'p.name as player_name'])
            ->map(static fn (object $village): array => [
                'id' => (int) $village->id,
                'name' => (string) $village->name,
                'x' => (int) $village->x,
                'y' => (int) $village->y,
                'population' => (int) $village->population,
                'player_name' => $village->player_name !== null ? (string) $village->player_name : null,
            ])
            ->values();

        return response()->json($villages);
    }

    /**
     * @return Collection<int, array{world_key:string,world_name:string,player_name:string,player_id:?int,played_account_group_id:int}>
     */
    private function playedAccounts(Request $request): Collection
    {
        $availableWorlds = $this->worldPreferences->availableWorldMap();

        return $request->user()
            ->playedAccounts()
            ->whereNotNull('played_account_group_id')
            ->latest('updated_at')
            ->get(['world_key', 'player_name', 'player_id', 'played_account_group_id'])
            ->map(static function (UserPlayedAccount $account) use ($availableWorlds): array {
                $world = $availableWorlds->get($account->world_key);

                return [
                    'world_key' => $account->world_key,
                    'world_name' => (string) ($world['name'] ?? $account->world_key),
                    'player_name' => $account->player_name,
                    'player_id' => $account->player_id,
                    'played_account_group_id' => (int) $account->played_account_group_id,
                ];
            });
    }

    /**
     * @param Collection<int, array{world_key:string}> $accounts
     */
    private function selectedWorldKey(Request $request, Collection $accounts, string $requestedWorldKey): string
    {
        if ($requestedWorldKey !== '' && $accounts->contains('world_key', $requestedWorldKey)) {
            return $requestedWorldKey;
        }

        $lastWorldKey = $request->user()->last_world_key;

        if ($lastWorldKey !== null && $accounts->contains('world_key', $lastWorldKey)) {
            return $lastWorldKey;
        }

        return (string) ($accounts->first()['world_key'] ?? '');
    }

    private function movementSpeedFactor(?int $serverSpeed): int
    {
        if ($serverSpeed === 10) {
            return 4;
        }

        if ($serverSpeed !== null && $serverSpeed >= 2) {
            return 2;
        }

        return 1;
    }

    /**
     * @param array{world_key:string,player_name:string,player_id:?int}|null $selectedAccount
     * @return array<int, array{id:int,name:string,x:int,y:int,population:int}>
     */
    private function ownedVillages(?array $selectedAccount, ?World $world): array
    {
        if ($selectedAccount === null || $world === null || ! Schema::hasTable('villages')) {
            return [];
        }

        $query = DB::table('villages as v')
            ->where('v.world_id', $world->id)
            ->where('v.is_present', true)
            ->orderByDesc('v.population')
            ->orderBy('v.name')
            ->select(['v.id', 'v.name', 'v.x', 'v.y', 'v.population']);

        if ($selectedAccount['player_id'] !== null) {
            $query->where('v.player_id', $selectedAccount['player_id']);
        } else {
            $query
                ->join('players as p', 'p.id', '=', 'v.player_id')
                ->whereRaw('LOWER(p.name) = ?', [mb_strtolower($selectedAccount['player_name'])]);
        }

        return $query
            ->get()
            ->map(static fn (object $village): array => [
                'id' => (int) $village->id,
                'name' => (string) $village->name,
                'x' => (int) $village->x,
                'y' => (int) $village->y,
                'population' => (int) $village->population,
            ])
            ->all();
    }

    /**
     * @param array{played_account_group_id:int}|null $selectedAccount
     * @return array<string, array<int, array{key:string,name:string,speed:int,quantity:int}>>
     */
    private function availableTroopsByVillage(?array $selectedAccount): array
    {
        if ($selectedAccount === null || ! Schema::hasTable('played_account_troops')) {
            return [];
        }

        $speedByTroop = $this->speedByTroopKey();

        return PlayedAccountTroop::query()
            ->where('played_account_group_id', $selectedAccount['played_account_group_id'])
            ->where('quantity', '>', 0)
            ->orderBy('village_name')
            ->orderBy('sort_order')
            ->get(['village_name', 'troop_key', 'troop_name', 'quantity'])
            ->groupBy('village_name')
            ->map(static fn (Collection $troops): array => $troops
                ->map(static fn (PlayedAccountTroop $troop): array => [
                    'key' => $troop->troop_key,
                    'name' => $troop->troop_name,
                    'speed' => (int) ($speedByTroop[$troop->troop_key] ?? 0),
                    'quantity' => (int) $troop->quantity,
                ])
                ->values()
                ->all())
            ->all();
    }

    /**
     * @return array<int, array{key:string,tribe_key:string,name:string,speed:int}>
     */
    private function catalogTroops(): array
    {
        if (! Schema::hasTable('travian_troops')) {
            return [];
        }

        return TravianTroop::query()
            ->orderBy('sort_order')
            ->get(['tribe_key', 'troop_key', 'name', 'speed_fields_per_hour'])
            ->map(static fn (TravianTroop $troop): array => [
                'key' => $troop->tribe_key.':'.$troop->troop_key,
                'tribe_key' => $troop->tribe_key,
                'name' => $troop->name,
                'speed' => (int) $troop->speed_fields_per_hour,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function speedByTroopKey(): array
    {
        if (! Schema::hasTable('travian_troops')) {
            return ['hero' => 7];
        }

        $speeds = TravianTroop::query()
            ->select('troop_key', DB::raw('MAX(speed_fields_per_hour) as speed_fields_per_hour'))
            ->groupBy('troop_key')
            ->pluck('speed_fields_per_hour', 'troop_key')
            ->map(static fn (mixed $speed): int => (int) $speed)
            ->all();

        $speeds['hero'] = 7;

        return $speeds;
    }
}
