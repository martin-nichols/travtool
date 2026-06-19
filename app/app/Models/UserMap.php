<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserMap extends Model
{
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function playedAccountGroup(): BelongsTo
    {
        return $this->belongsTo(TravtoolGroup::class, 'played_account_group_id');
    }
}
