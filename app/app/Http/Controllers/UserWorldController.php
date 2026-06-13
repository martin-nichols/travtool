<?php

namespace App\Http\Controllers;

use App\Services\UserWorldPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserWorldController extends Controller
{
    public function __construct(
        private readonly UserWorldPreferenceService $worldPreferences,
    ) {
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'world_key' => ['required', 'string', 'max:100'],
        ]);

        $worldKey = (string) $validated['world_key'];

        abort_unless($this->worldPreferences->isActiveWorldKey($worldKey), 422);

        $this->worldPreferences->rememberWorld($request->user(), $worldKey);

        return back();
    }

    public function select(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'world_key' => ['required', 'string', 'max:100'],
        ]);

        $worldKey = (string) $validated['world_key'];

        abort_unless($this->worldPreferences->isActiveWorldKey($worldKey), 422);

        $request->user()->forceFill(['last_world_key' => $worldKey])->save();

        $request->user()->worldPreferences()->updateOrCreate(
            ['world_key' => $worldKey],
            ['last_used_at' => now()],
        );

        return back();
    }

    public function destroy(Request $request, string $worldKey): RedirectResponse
    {
        $user = $request->user();

        $user->worldPreferences()
            ->where('world_key', $worldKey)
            ->delete();

        if ($user->last_world_key === $worldKey) {
            $nextWorldKey = $this->worldPreferences->playedWorldKeys($user)[0] ?? null;
            $user->forceFill(['last_world_key' => $nextWorldKey])->save();
        }

        return back();
    }
}
