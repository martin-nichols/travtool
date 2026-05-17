<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapImportRun extends Model
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
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function world(): BelongsTo
    {
        return $this->belongsTo(World::class);
    }
}
