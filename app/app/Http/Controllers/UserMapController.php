<?php

namespace App\Http\Controllers;

use App\Models\UserMap;
use App\Services\UserWorldPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserMapController extends Controller
{
    private const MAX_MAPS_PER_USER = 10;

    public function __construct(
        private readonly UserWorldPreferenceService $worldPreferences,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'world_key' => ['required', 'string', 'max:100'],
            'alliance_tags' => ['nullable', 'string', 'max:4000'],
            'player_names' => ['nullable', 'string', 'max:4000'],
            'region_names' => ['nullable', 'string', 'max:4000'],
        ]);

        abort_unless($this->worldPreferences->isActiveWorldKey((string) $validated['world_key']), 422);

        $user = $request->user();
        $worldKey = trim((string) $validated['world_key']);
        $playedAccountGroupId = $this->playedAccountGroupId($request, $worldKey);
        $savedMapCount = $playedAccountGroupId !== null
            ? UserMap::query()->where('played_account_group_id', $playedAccountGroupId)->count()
            : $user->maps()->whereNull('played_account_group_id')->count();

        if ($savedMapCount >= self::MAX_MAPS_PER_USER) {
            return back()->withErrors([
                'saved_map' => sprintf('Maximum %d cartes sauvegardées par compte.', self::MAX_MAPS_PER_USER),
            ]);
        }

        $name = trim((string) ($validated['name'] ?? ''));

        $user->maps()->create([
            'name' => $name !== '' ? $name : 'Carte '.($savedMapCount + 1),
            'played_account_group_id' => $playedAccountGroupId,
            'world_key' => $worldKey,
            'alliance_tags' => $this->filledText($validated['alliance_tags'] ?? null),
            'player_names' => $this->filledText($validated['player_names'] ?? null),
            'region_names' => $this->filledText($validated['region_names'] ?? null),
        ]);

        return back();
    }

    public function destroy(Request $request, int $userMap): RedirectResponse
    {
        $map = UserMap::query()
            ->whereKey($userMap)
            ->first(['id', 'user_id', 'played_account_group_id']);

        if ($map === null) {
            return back();
        }

        $canDelete = $map->user_id === $request->user()->id
            || (
                $map->played_account_group_id !== null
                && $this->playedAccountGroupIds($request)->contains($map->played_account_group_id)
            );

        abort_unless($canDelete, 403);

        $map->delete();

        return back();
    }

    private function playedAccountGroupId(Request $request, string $worldKey): ?int
    {
        $groupId = $request->user()
            ->playedAccounts()
            ->where('world_key', $worldKey)
            ->value('played_account_group_id');

        return $groupId !== null ? (int) $groupId : null;
    }

    private function playedAccountGroupIds(Request $request)
    {
        return DB::table('travtool_group_users')
            ->join('travtool_groups', 'travtool_groups.id', '=', 'travtool_group_users.travtool_group_id')
            ->where('user_id', $request->user()->id)
            ->where('travtool_groups.type', 'played_account')
            ->pluck('travtool_group_users.travtool_group_id');
    }

    private function filledText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
