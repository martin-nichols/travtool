<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayedAccountTroop extends Model
{
    protected $guarded = [];

    protected $casts = [
        'quantity' => 'integer',
        'sort_order' => 'integer',
        'imported_at' => 'datetime',
    ];

    public function playedAccountGroup(): BelongsTo
    {
        return $this->belongsTo(TravtoolGroup::class, 'played_account_group_id');
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_user_id');
    }
}
