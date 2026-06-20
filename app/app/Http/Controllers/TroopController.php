<?php

namespace App\Http\Controllers;

use App\Models\PlayedAccountTroop;
use App\Models\TravianTroop;
use App\Models\UserPlayedAccount;
use App\Services\UserWorldPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TroopController extends Controller
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

        $troops = $selectedAccount !== null
            ? PlayedAccountTroop::query()
                ->where('played_account_group_id', $selectedAccount['played_account_group_id'])
                ->orderBy('village_name')
                ->orderBy('sort_order')
                ->get()
            : collect();

        return Inertia::render('Troops', [
            'accounts' => $accounts->map(fn (array $account): array => [
                'world_key' => $account['world_key'],
                'world_name' => $account['world_name'],
                'player_name' => $account['player_name'],
                'is_owner' => $account['role'] === 'owner',
            ])->values()->all(),
            'selectedWorldKey' => $selectedWorldKey,
            'selectedAccount' => $selectedAccount !== null ? [
                'world_key' => $selectedAccount['world_key'],
                'world_name' => $selectedAccount['world_name'],
                'player_name' => $selectedAccount['player_name'],
                'is_owner' => $selectedAccount['role'] === 'owner',
            ] : null,
            'troopColumns' => $this->troopColumns($troops),
            'villages' => $this->villageRows($troops),
            'totals' => $this->totalRow($troops),
            'lastImportedAt' => $troops->max('imported_at')?->toIso8601String(),
        ]);
    }

    public function import(Request $request): RedirectResponse
    {
        $validated = Validator::make($request->all(), [
            'world_key' => ['required', 'string', 'max:100'],
            'troops_text' => ['required', 'string', 'max:200000'],
        ])->validate();

        $account = $request->user()
            ->playedAccounts()
            ->where('world_key', $validated['world_key'])
            ->whereNotNull('played_account_group_id')
            ->first(['id', 'world_key', 'played_account_group_id']);

        if ($account === null) {
            return back()->withErrors([
                'world_key' => 'Aucun compte joué lié à ce monde.',
            ]);
        }

        $parsed = $this->parseTroopsText($validated['troops_text']);

        if ($parsed['rows'] === []) {
            return back()->withErrors([
                'troops_text' => 'Aucune ligne de village n’a été détectée dans ce texte.',
            ]);
        }

        $now = now();
        $records = [];

        foreach ($parsed['rows'] as $row) {
            foreach ($parsed['columns'] as $index => $column) {
                $records[] = [
                    'played_account_group_id' => $account->played_account_group_id,
                    'world_key' => $account->world_key,
                    'village_name' => $row['village_name'],
                    'troop_key' => $column['key'],
                    'troop_name' => $column['name'],
                    'quantity' => $row['quantities'][$index] ?? 0,
                    'sort_order' => $index + 1,
                    'imported_by_user_id' => $request->user()->id,
                    'imported_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::transaction(function () use ($account, $records): void {
            PlayedAccountTroop::query()
                ->where('played_account_group_id', $account->played_account_group_id)
                ->delete();

            foreach (array_chunk($records, 500) as $chunk) {
                PlayedAccountTroop::query()->insert($chunk);
            }
        });

        return redirect()
            ->route('troops.index', ['world' => $account->world_key])
            ->with('status', 'Troupes chargées.');
    }

    /**
     * @return Collection<int, array{world_key:string,world_name:string,player_name:string,played_account_group_id:int,role:?string}>
     */
    private function playedAccounts(Request $request): Collection
    {
        $availableWorlds = $this->worldPreferences->availableWorldMap();

        $accounts = $request->user()
            ->playedAccounts()
            ->whereNotNull('played_account_group_id')
            ->latest('updated_at')
            ->get(['world_key', 'player_name', 'played_account_group_id']);

        $roles = DB::table('travtool_group_users')
            ->where('user_id', $request->user()->id)
            ->whereIn('travtool_group_id', $accounts->pluck('played_account_group_id')->all() ?: [0])
            ->pluck('role', 'travtool_group_id');

        return $accounts->map(static function (UserPlayedAccount $account) use ($availableWorlds, $roles): array {
            $world = $availableWorlds->get($account->world_key);

            return [
                'world_key' => $account->world_key,
                'world_name' => (string) ($world['name'] ?? $account->world_key),
                'player_name' => $account->player_name,
                'played_account_group_id' => (int) $account->played_account_group_id,
                'role' => $roles->get($account->played_account_group_id),
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

    /**
     * @param Collection<int, PlayedAccountTroop> $troops
     * @return array<int, array{key:string,name:string}>
     */
    private function troopColumns(Collection $troops): array
    {
        return $troops
            ->sortBy('sort_order')
            ->unique('troop_key')
            ->map(static fn (PlayedAccountTroop $troop): array => [
                'key' => $troop->troop_key,
                'name' => $troop->troop_name,
            ])
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, PlayedAccountTroop> $troops
     * @return array<int, array{village_name:string,total:int,troops:array<string,int>}>
     */
    private function villageRows(Collection $troops): array
    {
        return $troops
            ->groupBy('village_name')
            ->map(static fn (Collection $villageTroops, string $villageName): array => [
                'village_name' => $villageName,
                'total' => $villageTroops->sum('quantity'),
                'troops' => $villageTroops->mapWithKeys(static fn (PlayedAccountTroop $troop): array => [
                    $troop->troop_key => $troop->quantity,
                ])->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @param Collection<int, PlayedAccountTroop> $troops
     * @return array{total:int,troops:array<string,int>}
     */
    private function totalRow(Collection $troops): array
    {
        return [
            'total' => $troops->sum('quantity'),
            'troops' => $troops
                ->groupBy('troop_key')
                ->map(static fn (Collection $items): int => $items->sum('quantity'))
                ->all(),
        ];
    }

    /**
     * @return array{
     *     columns:array<int, array{key:string,name:string}>,
     *     rows:array<int, array{village_name:string,quantities:array<int,int>}>
     * }
     */
    private function parseTroopsText(string $text): array
    {
        $lines = collect(preg_split('/\R/u', $this->normalizeText($text)) ?: [])
            ->map(static fn (string $line): string => trim($line))
            ->filter(static fn (string $line): bool => $line !== '')
            ->values();

        $headerIndex = $lines->search(static fn (string $line): bool => str_starts_with($line, 'Village') && str_contains($line, "\t"));

        if ($headerIndex === false) {
            return ['columns' => [], 'rows' => []];
        }

        $headerCells = $this->splitCells($lines[$headerIndex]);

        if (count($headerCells) < 2 || $headerCells[0] !== 'Village') {
            return ['columns' => [], 'rows' => []];
        }

        $columns = collect(array_slice($headerCells, 1))
            ->map(fn (string $name): array => [
                'key' => $this->troopKey($name),
                'name' => $name,
            ])
            ->values()
            ->all();

        $rows = [];

        foreach ($lines->slice($headerIndex + 1) as $line) {
            $cells = $this->splitCells($line);

            if ($cells === [] || strcasecmp($cells[0], 'Somme') === 0) {
                break;
            }

            if (count($cells) < count($columns) + 1) {
                continue;
            }

            $quantities = [];

            foreach (array_slice($cells, 1, count($columns)) as $cell) {
                $quantities[] = $this->parseQuantity($cell);
            }

            $rows[] = [
                'village_name' => $cells[0],
                'quantities' => $quantities,
            ];
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
        ];
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace(["\u{00A0}", "\u{202F}"], ' ', $text);

        return preg_replace('/[\x{200E}\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', '', $text) ?? $text;
    }

    /**
     * @return array<int, string>
     */
    private function splitCells(string $line): array
    {
        $cells = str_contains($line, "\t")
            ? preg_split('/\t+/u', $line)
            : preg_split('/\s{2,}/u', $line);

        return collect($cells ?: [])
            ->map(static fn (string $cell): string => trim($cell))
            ->filter(static fn (string $cell): bool => $cell !== '')
            ->values()
            ->all();
    }

    private function parseQuantity(string $value): int
    {
        $normalized = preg_replace('/[^\d]/u', '', $value);

        return $normalized === null || $normalized === '' ? 0 : (int) $normalized;
    }

    private function troopKey(string $name): string
    {
        $normalized = $this->slug($name);
        $aliases = $this->troopAliases();

        return $aliases[$normalized] ?? $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function troopAliases(): array
    {
        $catalogAliases = TravianTroop::query()
            ->pluck('troop_key', 'name')
            ->mapWithKeys(fn (string $troopKey, string $name): array => [$this->slug($name) => $troopKey])
            ->all();

        return array_merge($catalogAliases, [
            'mercenaire' => 'mercenary',
            'archer' => 'bowman',
            'guetteur' => 'spotter_scout',
            'cavalier_des_steppes' => 'steppe',
            'archer_monte' => 'marksman',
            'maraudeur' => 'marauder',
            'belier' => 'ram',
            'catapulte' => 'catapult',
            'colon' => 'settler',
            'heros' => 'hero',
            'hero' => 'hero',
        ]);
    }

    private function slug(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->toString();
    }
}
