<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\World;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()?->hasAdminAccess(), 403);

        $users = User::query()
            ->with([
                'playedAccounts' => fn ($query) => $query
                    ->latest('updated_at')
                    ->select(['id', 'user_id', 'world_key', 'player_name', 'player_id', 'updated_at']),
                'worldPreferences' => fn ($query) => $query
                    ->latest('last_used_at')
                    ->select(['id', 'user_id', 'world_key', 'last_used_at']),
            ])
            ->withCount(['playedAccounts', 'maps'])
            ->orderByDesc('last_login_at')
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'email', 'is_admin', 'last_login_at', 'last_world_key', 'created_at'])
            ->map(static fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->hasAdminAccess(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'last_world_key' => $user->last_world_key,
                'created_at' => $user->created_at?->toIso8601String(),
                'played_accounts_count' => $user->played_accounts_count,
                'maps_count' => $user->maps_count,
                'played_accounts' => $user->playedAccounts->map(static fn ($account): array => [
                    'id' => $account->id,
                    'world_key' => $account->world_key,
                    'player_name' => $account->player_name,
                    'matched_player' => $account->player_id !== null,
                    'updated_at' => $account->updated_at?->toIso8601String(),
                ])->values()->all(),
                'worlds' => $user->worldPreferences->map(static fn ($world): array => [
                    'world_key' => $world->world_key,
                    'last_used_at' => $world->last_used_at?->toIso8601String(),
                ])->values()->all(),
            ])
            ->all();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'played_accounts' => DB::table('user_played_accounts')->count(),
                'saved_maps' => DB::table('user_maps')->count(),
                'active_worlds' => World::query()->where('is_active', true)->count(),
                'imported_worlds' => World::query()->whereNotNull('current_snapshot_id')->count(),
            ],
            'users' => $users,
        ]);
    }
}
