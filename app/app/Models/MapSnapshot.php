<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapSnapshot extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DOWNLOADED = 'downloaded';
    public const STATUS_STAGED = 'staged';
    public const STATUS_NORMALIZED = 'normalized';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'downloaded_at' => 'datetime',
            'staged_at' => 'datetime',
            'normalized_at' => 'datetime',
            'current_state_updated_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function world(): BelongsTo
    {
        return $this->belongsTo(World::class);
    }

    public function previousSnapshot(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_snapshot_id');
    }

    public function successfulImportRun(): BelongsTo
    {
        return $this->belongsTo(MapImportRun::class, 'successful_import_run_id');
    }
}
