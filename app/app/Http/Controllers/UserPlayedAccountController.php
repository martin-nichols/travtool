<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\TravtoolGroup;
use App\Models\TravtoolGroupUser;
use App\Models\UserPlayedAccount;
use App\Models\World;
use App\Services\UserWorldPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserPlayedAccountController extends Controller
{
    public function __construct(
        private readonly UserWorldPreferenceService $worldPreferences,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'world_key' => ['required', 'string', 'max:100'],
            'player_name' => ['required', 'string', 'max:255'],
        ]);

        $worldKey = trim((string) $validated['world_key']);
        $playerName = trim((string) $validated['player_name']);

        abort_unless($this->worldPreferences->isActiveWorldKey($worldKey), 422);

        $world = World::query()->where('key', $worldKey)->first();
        $player = $world !== null
            ? Player::query()
                ->where('world_id', $world->id)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($playerName)])
                ->first()
            : null;

        $user = $request->user();
        $existingPlayedAccount = $user->playedAccounts()
            ->where('world_key', $worldKey)
            ->first(['id', 'played_account_group_id']);
        $oldGroupId = $existingPlayedAccount?->played_account_group_id;
        $group = $this->playedAccountGroup($world, $worldKey, $player, $playerName, $user->id);

        $playedAccount = $user->playedAccounts()->updateOrCreate(
            [
                'world_key' => $worldKey,
            ],
            [
                'world_id' => $world?->id,
                'player_id' => $player?->id,
                'played_account_group_id' => $group->id,
                'player_name' => $playerName,
                'visibility' => 'group',
            ],
        );

        $this->syncGroupMembership($group, $user->id, 'owner');

        if ($oldGroupId !== null && $oldGroupId !== $group->id) {
            $this->removeGroupMembershipIfUnused($oldGroupId, $user->id, $playedAccount->id);
        }

        $this->worldPreferences->rememberWorld($user, $worldKey);

        return back();
    }

    public function destroy(Request $request, int $playedAccount): RedirectResponse
    {
        $account = $request->user()
            ->playedAccounts()
            ->whereKey($playedAccount)
            ->first(['id', 'played_account_group_id']);

        if ($account !== null) {
            $groupId = $account->played_account_group_id;
            $account->delete();

            if ($groupId !== null) {
                $this->removeGroupMembershipIfUnused($groupId, $request->user()->id);
            }
        }

        return back();
    }

    public function join(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'invite_code' => ['required', 'string', 'max:64'],
        ]);

        $inviteCode = trim((string) $validated['invite_code']);
        $group = TravtoolGroup::query()
            ->where('type', 'played_account')
            ->where('invite_code', $inviteCode)
            ->whereNull('invite_revoked_at')
            ->first();

        if ($group === null || ! $this->worldPreferences->isActiveWorldKey($group->world_key)) {
            return back()->withErrors([
                'invite_code' => 'Code dual invalide.',
            ]);
        }

        $player = $group->player_id !== null
            ? Player::query()->whereKey($group->player_id)->first()
            : null;
        $playerName = $player?->name ?? $group->player_name ?? $group->name;
        $user = $request->user();
        $existingPlayedAccount = $user->playedAccounts()
            ->where('world_key', $group->world_key)
            ->first(['id', 'played_account_group_id']);
        $oldGroupId = $existingPlayedAccount?->played_account_group_id;

        $playedAccount = $user->playedAccounts()->updateOrCreate(
            [
                'world_key' => $group->world_key,
            ],
            [
                'world_id' => $group->world_id,
                'player_id' => $player?->id ?? $group->player_id,
                'played_account_group_id' => $group->id,
                'player_name' => $playerName,
                'visibility' => 'group',
            ],
        );

        $this->syncGroupMembership($group, $user->id, 'member');

        if ($oldGroupId !== null && $oldGroupId !== $group->id) {
            $this->removeGroupMembershipIfUnused($oldGroupId, $user->id, $playedAccount->id);
        }

        $this->worldPreferences->rememberWorld($user, $group->world_key);

        return back();
    }

    private function playedAccountGroup(?World $world, string $worldKey, ?Player $player, string $playerName, int $userId): TravtoolGroup
    {
        $normalizedPlayerName = $this->normalizedPlayerName($player?->name ?? $playerName);
        $query = TravtoolGroup::query()
            ->where('world_key', $worldKey)
            ->where('type', 'played_account');

        if ($player !== null) {
            $query->where('player_id', $player->id);
        } else {
            $query->whereNull('player_id')
                ->where('player_name_normalized', $normalizedPlayerName);
        }

        $group = $query->first();

        if ($group !== null) {
            if ($group->invite_code === null) {
                $group->forceFill(['invite_code' => $this->newInviteCode(), 'invite_created_at' => now()])->save();
            }

            return $group;
        }

        return TravtoolGroup::query()->create([
            'world_id' => $world?->id,
            'player_id' => $player?->id,
            'world_key' => $worldKey,
            'name' => $player?->name ?? $playerName,
            'player_name' => $player?->name ?? $playerName,
            'player_name_normalized' => $normalizedPlayerName,
            'type' => 'played_account',
            'created_by_user_id' => $userId,
            'invite_code' => $this->newInviteCode(),
            'invite_created_at' => now(),
        ]);
    }

    private function syncGroupMembership(TravtoolGroup $group, int $userId, string $role): void
    {
        TravtoolGroupUser::query()->updateOrCreate(
            [
                'travtool_group_id' => $group->id,
                'user_id' => $userId,
            ],
            [
                'role' => $role,
                'joined_at' => now(),
            ],
        );
    }

    private function removeGroupMembershipIfUnused(int $groupId, int $userId, ?int $exceptPlayedAccountId = null): void
    {
        $query = UserPlayedAccount::query()
            ->where('user_id', $userId)
            ->where('played_account_group_id', $groupId);

        if ($exceptPlayedAccountId !== null) {
            $query->where('id', '!=', $exceptPlayedAccountId);
        }

        if ($query->exists()) {
            return;
        }

        TravtoolGroupUser::query()
            ->where('travtool_group_id', $groupId)
            ->where('user_id', $userId)
            ->delete();
    }

    private function normalizedPlayerName(string $playerName): string
    {
        return mb_strtolower(trim($playerName));
    }

    private function newInviteCode(): string
    {
        do {
            $code = Str::random(32);
        } while (TravtoolGroup::query()->where('invite_code', $code)->exists());

        return $code;
    }
}
