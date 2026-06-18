<?php

namespace App\Http\Controllers;

use App\Models\TravtoolGroup;
use App\Models\TravtoolGroupUser;
use App\Models\UserPlayedAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function show(Request $request): Response
    {
        $ownedPlayedAccounts = TravtoolGroup::query()
            ->where('type', 'played_account')
            ->whereHas('members', function ($query) use ($request): void {
                $query->where('user_id', $request->user()->id)
                    ->where('role', 'owner');
            })
            ->with([
                'members' => function ($query): void {
                    $query->where('role', '!=', 'owner')
                        ->with('user:id,name,email')
                        ->orderBy('joined_at')
                        ->orderBy('id');
                },
            ])
            ->orderBy('world_key')
            ->orderBy('name')
            ->get(['id', 'world_key', 'name', 'player_name', 'invite_code'])
            ->map(static fn (TravtoolGroup $group): array => [
                'id' => $group->id,
                'world_key' => $group->world_key,
                'name' => $group->player_name ?? $group->name,
                'invite_code' => $group->invite_code,
                'duals' => $group->members->map(static fn (TravtoolGroupUser $member): array => [
                    'membership_id' => $member->id,
                    'name' => $member->user?->name ?? 'Compte supprimé',
                    'email' => $member->user?->email,
                    'joined_at' => $member->joined_at?->toIso8601String(),
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return Inertia::render('Account', [
            'ownedPlayedAccounts' => $ownedPlayedAccounts,
        ]);
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check((string) $validated['current_password'], (string) $request->user()->password)) {
            throw ValidationException::withMessages([
                'current_password' => 'Le mot de passe actuel est incorrect.',
            ]);
        }

        $request->user()->forceFill([
            'password' => $validated['password'],
        ])->save();

        return back()->with('status', 'password-updated');
    }

    public function revokeDual(Request $request, TravtoolGroupUser $membership): RedirectResponse
    {
        abort_if($membership->role === 'owner', 403);

        $group = TravtoolGroup::query()
            ->whereKey($membership->travtool_group_id)
            ->where('type', 'played_account')
            ->first();

        abort_if($group === null, 404);

        $isOwner = TravtoolGroupUser::query()
            ->where('travtool_group_id', $group->id)
            ->where('user_id', $request->user()->id)
            ->where('role', 'owner')
            ->exists();

        abort_unless($isOwner, 403);

        UserPlayedAccount::query()
            ->where('user_id', $membership->user_id)
            ->where('played_account_group_id', $group->id)
            ->delete();

        $membership->delete();

        return back()->with('status', 'dual-revoked');
    }
}
