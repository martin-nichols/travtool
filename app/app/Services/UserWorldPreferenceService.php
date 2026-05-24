<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;

class UserWorldPreferenceService
{
    /**
     * @return array<int, string>
     */
    public function activeWorldKeys(): array
    {
        return $this->activeWorldMap()->keys()->all();
    }

    public function isActiveWorldKey(?string $worldKey): bool
    {
        $worldKey = trim((string) $worldKey);

        return $worldKey !== '' && $this->activeWorldMap()->has($worldKey);
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
    private function activeWorldMap(): Collection
    {
        return collect(config('travtool.worlds', []))
            ->filter(static fn (mixed $world): bool => is_array($world) && (bool) ($world['is_active'] ?? true));
    }
}
