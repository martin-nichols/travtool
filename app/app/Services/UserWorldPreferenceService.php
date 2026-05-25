<?php

namespace App\Services;

use App\Models\World;
use App\Models\User;
use Illuminate\Support\Collection;

class UserWorldPreferenceService
{
    /**
     * @return array<int, string>
     */
    public function activeWorldKeys(): array
    {
        return $this->availableWorldMap()->keys()->all();
    }

    public function isActiveWorldKey(?string $worldKey): bool
    {
        $worldKey = trim((string) $worldKey);

        return $worldKey !== '' && $this->availableWorldMap()->has($worldKey);
    }

    public function resolveSelectedWorldKey(?User $user, ?string $requestedWorldKey): string
    {
        $requestedWorldKey = trim((string) $requestedWorldKey);

        if ($this->isActiveWorldKey($requestedWorldKey)) {
            return $requestedWorldKey;
        }

        $lastWorldKey = trim((string) $user?->last_world_key);

        return $this->isActiveWorldKey($lastWorldKey) ? $lastWorldKey : '';
    }

    public function rememberWorld(User $user, ?string $worldKey): void
    {
        $worldKey = trim((string) $worldKey);

        if (! $this->isActiveWorldKey($worldKey)) {
            return;
        }

        if ($user->last_world_key !== $worldKey) {
            $user->forceFill(['last_world_key' => $worldKey])->save();
        }

        $user->worldPreferences()->updateOrCreate(
            ['world_key' => $worldKey],
            ['last_used_at' => now()],
        );
    }

    public function redirectPath(?User $user): string
    {
        $worldKey = trim((string) $user?->last_world_key);

        if ($this->isActiveWorldKey($worldKey)) {
            return route('inactive-finder', ['world' => $worldKey], false);
        }

        return route('inactive-finder', [], false);
    }

    /**
     * @return array<int, string>
     */
    public function playedWorldKeys(User $user): array
    {
        return $user->worldPreferences()
            ->orderByDesc('last_used_at')
            ->orderByDesc('updated_at')
            ->pluck('world_key')
            ->all();
    }

    /**
     * @return Collection<string, array<string, mixed>>
     */
    public function availableWorldMap(): Collection
    {
        $storedWorlds = World::query()
            ->where('key', '!=', '')
            ->where('base_url', '!=', '')
            ->where('map_sql_url', '!=', '')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['key', 'name', 'base_url', 'is_active']);

        if ($storedWorlds->isNotEmpty()) {
            return $storedWorlds->mapWithKeys(static fn (World $world): array => [
                $world->key => [
                    'name' => $world->name,
                    'base_url' => $world->base_url,
                    'is_active' => (bool) $world->is_active,
                    'category_key' => self::worldCategoryKey(
                        $world->game_type,
                        $world->catalog_domain,
                        $world->name,
                        $world->base_url,
                    ),
                ],
            ]);
        }

        return collect(config('travtool.worlds', []))
            ->filter(static fn (mixed $world): bool => is_array($world) && (bool) ($world['is_active'] ?? true))
            ->map(static fn (array $world, string $key): array => [
                ...$world,
                'category_key' => self::worldCategoryKey(
                    $world['game_type'] ?? ($key === 'rof' ? 'RoF' : null),
                    $world['catalog_domain'] ?? null,
                    $world['name'] ?? $key,
                    $world['base_url'] ?? null,
                ),
            ]);
    }

    private static function worldCategoryKey(
        mixed $gameType,
        mixed $catalogDomain,
        mixed $name,
        mixed $baseUrl,
    ): string {
        $normalizedType = strtolower(trim((string) $gameType));
        $normalizedDomain = strtolower(trim((string) $catalogDomain));
        $normalizedName = strtolower(trim((string) $name));
        $normalizedUrl = strtolower(trim((string) $baseUrl));

        if ($normalizedType === 'rof') {
            return 'rof';
        }

        if ($normalizedDomain === 'nordics' || str_contains($normalizedName, 'nordics') || str_contains($normalizedUrl, 'nordics.')) {
            return 'nordics';
        }

        if (in_array($normalizedType, ['ttq', 'tournament'], true)) {
            return 'tournament';
        }

        return 'other';
    }
}
