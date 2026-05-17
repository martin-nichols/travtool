<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class World extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function currentSnapshot(): BelongsTo
    {
        return $this->belongsTo(MapSnapshot::class, 'current_snapshot_id');
    }

    public function importRuns(): HasMany
    {
        return $this->hasMany(MapImportRun::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(MapSnapshot::class);
    }
}
