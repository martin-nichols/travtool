<?php

namespace App\Http\Middleware;

use App\Services\UserWorldPreferenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $locales = collect(config('travtool.locales', []))
            ->map(fn (string $label, string $code) => [
                'code' => $code,
                'label' => $label,
            ])
            ->values()
            ->all();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                    'last_world_key' => $user->last_world_key,
                    'is_admin' => $user->hasAdminAccess(),
                    'last_login_at' => $user->last_login_at?->toIso8601String(),
                    'played_world_keys' => app(UserWorldPreferenceService::class)->playedWorldKeys($user),
                    'created_at' => $user->created_at?->toIso8601String(),
                    'updated_at' => $user->updated_at?->toIso8601String(),
                ] : null,
            ],
            'locale' => [
                'current' => app()->getLocale(),
                'available' => $locales,
            ],
            'translations' => Lang::get('ui'),
        ];
    }
}
