<?php

namespace App\Http\Controllers;

use App\Services\UserWorldPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

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
        $savedMapCount = $user->maps()->count();

        if ($savedMapCount >= self::MAX_MAPS_PER_USER) {
            return back()->withErrors([
                'saved_map' => sprintf('Maximum %d cartes sauvegardees par compte.', self::MAX_MAPS_PER_USER),
            ]);
        }

        $name = trim((string) ($validated['name'] ?? ''));

        $user->maps()->create([
            'name' => $name !== '' ? $name : 'Carte '.($savedMapCount + 1),
            'world_key' => trim((string) $validated['world_key']),
            'alliance_tags' => $this->filledText($validated['alliance_tags'] ?? null),
            'player_names' => $this->filledText($validated['player_names'] ?? null),
            'region_names' => $this->filledText($validated['region_names'] ?? null),
        ]);

        return back();
    }

    public function destroy(Request $request, int $userMap): RedirectResponse
    {
        $request->user()
            ->maps()
            ->whereKey($userMap)
            ->delete();

        return back();
    }

    private function filledText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
