<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'last_world_key'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function worldPreferences(): HasMany
    {
        return $this->hasMany(UserWorld::class);
    }

    public function maps(): HasMany
    {
        return $this->hasMany(UserMap::class);
    }

    public function playedAccounts(): HasMany
    {
        return $this->hasMany(UserPlayedAccount::class);
    }

    public function groupMemberships(): HasMany
    {
        return $this->hasMany(TravtoolGroupUser::class);
    }
}
