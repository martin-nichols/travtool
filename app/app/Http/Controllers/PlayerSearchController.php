<?php

namespace App\Http\Controllers;

use App\Models\Player;
use App\Models\World;
use App\Services\UserWorldPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerSearchController extends Controller
{
    public function __construct(
        private readonly UserWorldPreferenceService $worldPreferences,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'world_key' => ['required', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $worldKey = trim((string) $validated['world_key']);
        $query = trim((string) ($validated['q'] ?? ''));

        abort_unless($this->worldPreferences->isActiveWorldKey($worldKey), 422);

        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $world = World::query()->where('key', $worldKey)->first();

        if ($world === null) {
            return response()->json([]);
        }

        $players = Player::query()
            ->where('world_id', $world->id)
            ->where('is_present', true)
            ->where('name', 'like', '%' . $query . '%')
            ->orderByRaw('CASE WHEN LOWER(name) LIKE ? THEN 0 ELSE 1 END', [mb_strtolower($query) . '%'])
            ->orderByDesc('current_population_total')
            ->limit(12)
            ->get(['id', 'name', 'current_population_total', 'current_village_count'])
            ->map(static fn (Player $player): array => [
                'id' => $player->id,
                'name' => $player->name,
                'population' => $player->current_population_total,
                'villages' => $player->current_village_count,
            ])
            ->values();

        return response()->json($players);
    }
}
