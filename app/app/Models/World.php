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
            'has_regions' => 'boolean',
            'registration_closed' => 'boolean',
            'mainpage_groups' => 'array',
            'languages' => 'array',
            'tribe_names' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'catalog_last_seen_at' => 'datetime',
            'catalog_synced_at' => 'datetime',
            'map_metadata_detected_at' => 'datetime',
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
