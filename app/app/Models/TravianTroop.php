<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TravianTroop extends Model
{
    protected $guarded = [];

    protected $casts = [
        'attack_power' => 'integer',
        'infantry_defense' => 'integer',
        'cavalry_defense' => 'integer',
        'crop_consumption' => 'integer',
        'speed_fields_per_hour' => 'integer',
        'carry_capacity' => 'integer',
        'total_resource_cost' => 'integer',
        'training_time_level_1_seconds' => 'integer',
        'wood_cost' => 'integer',
        'clay_cost' => 'integer',
        'iron_cost' => 'integer',
        'crop_cost' => 'integer',
        'sort_order' => 'integer',
    ];
}
