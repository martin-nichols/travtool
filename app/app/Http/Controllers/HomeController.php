<?php

namespace App\Http\Controllers;

use App\Services\UserWorldPreferenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __construct(
        private readonly UserWorldPreferenceService $worldPreferences,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        $availableWorlds = $this->worldPreferences->availableWorldMap();
        $playedWorldKeys = $user !== null ? $this->worldPreferences->playedWorldKeys($user) : [];
        $playedAccounts = $user?->playedAccounts()
            ->with(['playedAccountGroup:id,invite_code'])
            ->latest('updated_at')
            ->get(['id', 'world_key', 'player_name', 'visibility', 'player_id', 'played_account_group_id']);
        $membershipRoles = $user !== null && $playedAccounts !== null
            ? DB::table('travtool_group_users')
                ->where('user_id', $user->id)
                ->whereIn('travtool_group_id', $playedAccounts->pluck('played_account_group_id')->filter()->all() ?: [0])
                ->pluck('role', 'travtool_group_id')
            : collect();

        return Inertia::render('Home', [
            'worldDashboard' => [
                'availableWorlds' => $availableWorlds
                    ->map(fn (array $world, string $key): array => [
                        'key' => $key,
                        'name' => (string) ($world['name'] ?? $key),
                        'base_url' => (string) ($world['base_url'] ?? ''),
                        'is_active' => (bool) ($world['is_active'] ?? false),
                        'category_key' => (string) ($world['category_key'] ?? 'other'),
                    ])
                    ->values()
                    ->all(),
                'myWorldKeys' => $playedWorldKeys,
                'selectedWorldKey' => $user?->last_world_key,
                'playedAccounts' => $playedAccounts
                    ?->map(static function ($account) use ($membershipRoles): array {
                        $role = $membershipRoles->get($account->played_account_group_id);

                        return [
                            'id' => $account->id,
                            'world_key' => $account->world_key,
                            'player_name' => $account->player_name,
                            'visibility' => $account->visibility,
                            'matched_player' => $account->player_id !== null,
                            'invite_code' => $role === 'owner' ? $account->playedAccountGroup?->invite_code : null,
                        ];
                    })
                    ->all() ?? [],
            ],
        ]);
    }
}
