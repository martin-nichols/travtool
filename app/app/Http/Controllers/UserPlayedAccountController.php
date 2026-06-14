<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\World;
use App\Services\UserWorldPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
            'visibility' => ['nullable', 'string', 'in:private,group'],
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

        $request->user()->playedAccounts()->updateOrCreate(
            [
                'world_key' => $worldKey,
                'player_name' => $playerName,
            ],
            [
                'world_id' => $world?->id,
                'player_id' => $player?->id,
                'visibility' => (string) ($validated['visibility'] ?? 'private'),
            ],
        );

        return back();
    }

    public function destroy(Request $request, int $playedAccount): RedirectResponse
    {
        $request->user()
            ->playedAccounts()
            ->whereKey($playedAccount)
            ->delete();

        return back();
    }
}
