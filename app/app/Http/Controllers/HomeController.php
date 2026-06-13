<?php

namespace App\Http\Controllers;

use App\Services\UserWorldPreferenceService;
use Illuminate\Http\Request;
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
            ],
        ]);
    }
}
