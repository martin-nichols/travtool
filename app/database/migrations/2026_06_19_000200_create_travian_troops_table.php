<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travian_troops', function (Blueprint $table) {
            $table->id();
            $table->string('tribe_key', 32);
            $table->string('troop_key', 80);
            $table->string('name', 120);
            $table->unsignedSmallInteger('attack_power');
            $table->unsignedSmallInteger('infantry_defense');
            $table->unsignedSmallInteger('cavalry_defense');
            $table->unsignedTinyInteger('crop_consumption');
            $table->unsignedTinyInteger('speed_fields_per_hour');
            $table->unsignedSmallInteger('carry_capacity');
            $table->unsignedInteger('total_resource_cost');
            $table->unsignedInteger('training_time_level_1_seconds');
            $table->unsignedInteger('wood_cost')->nullable();
            $table->unsignedInteger('clay_cost')->nullable();
            $table->unsignedInteger('iron_cost')->nullable();
            $table->unsignedInteger('crop_cost')->nullable();
            $table->unsignedSmallInteger('sort_order');
            $table->timestamps();

            $table->unique(['tribe_key', 'troop_key']);
            $table->index(['tribe_key', 'sort_order']);
        });

        $now = now();

        DB::table('travian_troops')->insert(array_map(
            fn (array $troop): array => array_merge($troop, [
                'created_at' => $now,
                'updated_at' => $now,
            ]),
            $this->troops(),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('travian_troops');
    }

    /**
     * Base values extracted from .github/travian_troupes_extraites.xlsx, sheet Base_toutes_troupes.
     */
    private function troops(): array
    {
        return [
            ['tribe_key' => 'gauls', 'troop_key' => 'phalanx', 'name' => 'Phalanx', 'attack_power' => 15, 'infantry_defense' => 40, 'cavalry_defense' => 50, 'crop_consumption' => 1, 'speed_fields_per_hour' => 7, 'carry_capacity' => 35, 'total_resource_cost' => 315, 'training_time_level_1_seconds' => 1300, 'sort_order' => 1],
            ['tribe_key' => 'gauls', 'troop_key' => 'swordsman', 'name' => 'Swordsman', 'attack_power' => 65, 'infantry_defense' => 35, 'cavalry_defense' => 20, 'crop_consumption' => 1, 'speed_fields_per_hour' => 6, 'carry_capacity' => 45, 'total_resource_cost' => 535, 'training_time_level_1_seconds' => 1800, 'sort_order' => 2],
            ['tribe_key' => 'gauls', 'troop_key' => 'pathfinder_scout', 'name' => 'Pathfinder (Scout)', 'attack_power' => 0, 'infantry_defense' => 20, 'cavalry_defense' => 10, 'crop_consumption' => 2, 'speed_fields_per_hour' => 17, 'carry_capacity' => 0, 'total_resource_cost' => 380, 'training_time_level_1_seconds' => 1700, 'sort_order' => 3],
            ['tribe_key' => 'gauls', 'troop_key' => 'theutates_thunder', 'name' => 'Theutates Thunder', 'attack_power' => 100, 'infantry_defense' => 25, 'cavalry_defense' => 40, 'crop_consumption' => 2, 'speed_fields_per_hour' => 19, 'carry_capacity' => 75, 'total_resource_cost' => 1090, 'training_time_level_1_seconds' => 3100, 'sort_order' => 4],
            ['tribe_key' => 'gauls', 'troop_key' => 'druidrider', 'name' => 'Druidrider', 'attack_power' => 45, 'infantry_defense' => 115, 'cavalry_defense' => 55, 'crop_consumption' => 2, 'speed_fields_per_hour' => 16, 'carry_capacity' => 35, 'total_resource_cost' => 1090, 'training_time_level_1_seconds' => 3200, 'sort_order' => 5],
            ['tribe_key' => 'gauls', 'troop_key' => 'haeduan', 'name' => 'Haeduan', 'attack_power' => 140, 'infantry_defense' => 50, 'cavalry_defense' => 165, 'crop_consumption' => 3, 'speed_fields_per_hour' => 13, 'carry_capacity' => 65, 'total_resource_cost' => 1965, 'training_time_level_1_seconds' => 3900, 'sort_order' => 6],
            ['tribe_key' => 'gauls', 'troop_key' => 'ram', 'name' => 'Ram', 'attack_power' => 50, 'infantry_defense' => 30, 'cavalry_defense' => 105, 'crop_consumption' => 3, 'speed_fields_per_hour' => 4, 'carry_capacity' => 0, 'total_resource_cost' => 1910, 'training_time_level_1_seconds' => 5000, 'sort_order' => 7],
            ['tribe_key' => 'gauls', 'troop_key' => 'trebuchet', 'name' => 'Trebuchet', 'attack_power' => 70, 'infantry_defense' => 45, 'cavalry_defense' => 10, 'crop_consumption' => 6, 'speed_fields_per_hour' => 3, 'carry_capacity' => 0, 'total_resource_cost' => 3130, 'training_time_level_1_seconds' => 9000, 'sort_order' => 8],
            ['tribe_key' => 'gauls', 'troop_key' => 'chieftain', 'name' => 'Chieftain', 'attack_power' => 40, 'infantry_defense' => 50, 'cavalry_defense' => 50, 'crop_consumption' => 4, 'speed_fields_per_hour' => 5, 'carry_capacity' => 0, 'total_resource_cost' => 144650, 'training_time_level_1_seconds' => 90700, 'sort_order' => 9],
            ['tribe_key' => 'gauls', 'troop_key' => 'settler', 'name' => 'Settler', 'attack_power' => 0, 'infantry_defense' => 80, 'cavalry_defense' => 80, 'crop_consumption' => 1, 'speed_fields_per_hour' => 5, 'carry_capacity' => 3000, 'total_resource_cost' => 22700, 'training_time_level_1_seconds' => 22700, 'sort_order' => 10],
            ['tribe_key' => 'romans', 'troop_key' => 'legionnaire', 'name' => 'Legionnaire', 'attack_power' => 40, 'infantry_defense' => 35, 'cavalry_defense' => 50, 'crop_consumption' => 1, 'speed_fields_per_hour' => 6, 'carry_capacity' => 50, 'total_resource_cost' => 400, 'training_time_level_1_seconds' => 2000, 'sort_order' => 11],
            ['tribe_key' => 'romans', 'troop_key' => 'praetorian', 'name' => 'Praetorian', 'attack_power' => 30, 'infantry_defense' => 65, 'cavalry_defense' => 35, 'crop_consumption' => 1, 'speed_fields_per_hour' => 5, 'carry_capacity' => 20, 'total_resource_cost' => 460, 'training_time_level_1_seconds' => 2200, 'sort_order' => 12],
            ['tribe_key' => 'romans', 'troop_key' => 'imperian', 'name' => 'Imperian', 'attack_power' => 70, 'infantry_defense' => 40, 'cavalry_defense' => 25, 'crop_consumption' => 1, 'speed_fields_per_hour' => 7, 'carry_capacity' => 50, 'total_resource_cost' => 600, 'training_time_level_1_seconds' => 2400, 'sort_order' => 13],
            ['tribe_key' => 'romans', 'troop_key' => 'equites_legati_scout', 'name' => 'Equites Legati (Scout)', 'attack_power' => 0, 'infantry_defense' => 20, 'cavalry_defense' => 10, 'crop_consumption' => 2, 'speed_fields_per_hour' => 16, 'carry_capacity' => 0, 'total_resource_cost' => 360, 'training_time_level_1_seconds' => 1700, 'sort_order' => 14],
            ['tribe_key' => 'romans', 'troop_key' => 'equites_imperatoris', 'name' => 'Equites Imperatoris', 'attack_power' => 120, 'infantry_defense' => 65, 'cavalry_defense' => 50, 'crop_consumption' => 3, 'speed_fields_per_hour' => 14, 'carry_capacity' => 100, 'total_resource_cost' => 1410, 'training_time_level_1_seconds' => 3300, 'sort_order' => 15],
            ['tribe_key' => 'romans', 'troop_key' => 'equites_caesaris', 'name' => 'Equites Caesaris', 'attack_power' => 180, 'infantry_defense' => 80, 'cavalry_defense' => 105, 'crop_consumption' => 4, 'speed_fields_per_hour' => 10, 'carry_capacity' => 70, 'total_resource_cost' => 2170, 'training_time_level_1_seconds' => 4400, 'sort_order' => 16],
            ['tribe_key' => 'romans', 'troop_key' => 'battering_ram', 'name' => 'Battering Ram', 'attack_power' => 60, 'infantry_defense' => 30, 'cavalry_defense' => 75, 'crop_consumption' => 3, 'speed_fields_per_hour' => 4, 'carry_capacity' => 0, 'total_resource_cost' => 1830, 'training_time_level_1_seconds' => 4600, 'sort_order' => 17],
            ['tribe_key' => 'romans', 'troop_key' => 'fire_catapult', 'name' => 'Fire Catapult', 'attack_power' => 75, 'infantry_defense' => 60, 'cavalry_defense' => 10, 'crop_consumption' => 6, 'speed_fields_per_hour' => 3, 'carry_capacity' => 0, 'total_resource_cost' => 2990, 'training_time_level_1_seconds' => 9000, 'sort_order' => 18],
            ['tribe_key' => 'romans', 'troop_key' => 'senator', 'name' => 'Senator', 'attack_power' => 50, 'infantry_defense' => 40, 'cavalry_defense' => 30, 'crop_consumption' => 5, 'speed_fields_per_hour' => 4, 'carry_capacity' => 0, 'total_resource_cost' => 140450, 'training_time_level_1_seconds' => 90700, 'sort_order' => 19],
            ['tribe_key' => 'romans', 'troop_key' => 'settler', 'name' => 'Settler', 'attack_power' => 0, 'infantry_defense' => 80, 'cavalry_defense' => 80, 'crop_consumption' => 1, 'speed_fields_per_hour' => 5, 'carry_capacity' => 3000, 'total_resource_cost' => 23800, 'training_time_level_1_seconds' => 26900, 'sort_order' => 20],
            ['tribe_key' => 'teutons', 'troop_key' => 'macemen', 'name' => 'Macemen', 'attack_power' => 40, 'infantry_defense' => 20, 'cavalry_defense' => 5, 'crop_consumption' => 1, 'speed_fields_per_hour' => 7, 'carry_capacity' => 60, 'total_resource_cost' => 250, 'training_time_level_1_seconds' => 900, 'sort_order' => 21],
            ['tribe_key' => 'teutons', 'troop_key' => 'spearman', 'name' => 'Spearman', 'attack_power' => 10, 'infantry_defense' => 35, 'cavalry_defense' => 60, 'crop_consumption' => 1, 'speed_fields_per_hour' => 7, 'carry_capacity' => 40, 'total_resource_cost' => 340, 'training_time_level_1_seconds' => 1400, 'sort_order' => 22],
            ['tribe_key' => 'teutons', 'troop_key' => 'axeman', 'name' => 'Axeman', 'attack_power' => 60, 'infantry_defense' => 30, 'cavalry_defense' => 30, 'crop_consumption' => 1, 'speed_fields_per_hour' => 6, 'carry_capacity' => 50, 'total_resource_cost' => 490, 'training_time_level_1_seconds' => 1500, 'sort_order' => 23],
            ['tribe_key' => 'teutons', 'troop_key' => 'scout', 'name' => 'Scout', 'attack_power' => 0, 'infantry_defense' => 10, 'cavalry_defense' => 5, 'crop_consumption' => 1, 'speed_fields_per_hour' => 9, 'carry_capacity' => 0, 'total_resource_cost' => 360, 'training_time_level_1_seconds' => 1400, 'sort_order' => 24],
            ['tribe_key' => 'teutons', 'troop_key' => 'paladin', 'name' => 'Paladin', 'attack_power' => 55, 'infantry_defense' => 100, 'cavalry_defense' => 40, 'crop_consumption' => 2, 'speed_fields_per_hour' => 10, 'carry_capacity' => 110, 'total_resource_cost' => 1005, 'training_time_level_1_seconds' => 3000, 'sort_order' => 25],
            ['tribe_key' => 'teutons', 'troop_key' => 'teutonic_knight', 'name' => 'Teutonic Knight', 'attack_power' => 150, 'infantry_defense' => 50, 'cavalry_defense' => 75, 'crop_consumption' => 3, 'speed_fields_per_hour' => 9, 'carry_capacity' => 80, 'total_resource_cost' => 1525, 'training_time_level_1_seconds' => 3700, 'sort_order' => 26],
            ['tribe_key' => 'teutons', 'troop_key' => 'ram', 'name' => 'Ram', 'attack_power' => 65, 'infantry_defense' => 30, 'cavalry_defense' => 80, 'crop_consumption' => 3, 'speed_fields_per_hour' => 4, 'carry_capacity' => 0, 'total_resource_cost' => 1720, 'training_time_level_1_seconds' => 4200, 'sort_order' => 27],
            ['tribe_key' => 'teutons', 'troop_key' => 'catapult', 'name' => 'Catapult', 'attack_power' => 50, 'infantry_defense' => 60, 'cavalry_defense' => 10, 'crop_consumption' => 6, 'speed_fields_per_hour' => 3, 'carry_capacity' => 0, 'total_resource_cost' => 2760, 'training_time_level_1_seconds' => 9000, 'sort_order' => 28],
            ['tribe_key' => 'teutons', 'troop_key' => 'chief', 'name' => 'Chief', 'attack_power' => 40, 'infantry_defense' => 60, 'cavalry_defense' => 40, 'crop_consumption' => 4, 'speed_fields_per_hour' => 4, 'carry_capacity' => 0, 'total_resource_cost' => 114300, 'training_time_level_1_seconds' => 70500, 'sort_order' => 29],
            ['tribe_key' => 'teutons', 'troop_key' => 'settler', 'name' => 'Settler', 'attack_power' => 10, 'infantry_defense' => 80, 'cavalry_defense' => 80, 'crop_consumption' => 1, 'speed_fields_per_hour' => 5, 'carry_capacity' => 3000, 'total_resource_cost' => 25000, 'training_time_level_1_seconds' => 31000, 'sort_order' => 30],
            ['tribe_key' => 'huns', 'troop_key' => 'mercenary', 'name' => 'Mercenary', 'attack_power' => 35, 'infantry_defense' => 40, 'cavalry_defense' => 30, 'crop_consumption' => 1, 'speed_fields_per_hour' => 6, 'carry_capacity' => 50, 'total_resource_cost' => 290, 'training_time_level_1_seconds' => 810, 'sort_order' => 31],
            ['tribe_key' => 'huns', 'troop_key' => 'bowman', 'name' => 'Bowman', 'attack_power' => 50, 'infantry_defense' => 30, 'cavalry_defense' => 10, 'crop_consumption' => 1, 'speed_fields_per_hour' => 6, 'carry_capacity' => 30, 'total_resource_cost' => 370, 'training_time_level_1_seconds' => 1120, 'sort_order' => 32],
            ['tribe_key' => 'huns', 'troop_key' => 'spotter_scout', 'name' => 'Spotter (Scout)', 'attack_power' => 0, 'infantry_defense' => 20, 'cavalry_defense' => 10, 'crop_consumption' => 2, 'speed_fields_per_hour' => 19, 'carry_capacity' => 0, 'total_resource_cost' => 380, 'training_time_level_1_seconds' => 1360, 'sort_order' => 33],
            ['tribe_key' => 'huns', 'troop_key' => 'steppe', 'name' => 'Steppe', 'attack_power' => 120, 'infantry_defense' => 30, 'cavalry_defense' => 15, 'crop_consumption' => 2, 'speed_fields_per_hour' => 16, 'carry_capacity' => 75, 'total_resource_cost' => 895, 'training_time_level_1_seconds' => 2400, 'sort_order' => 34],
            ['tribe_key' => 'huns', 'troop_key' => 'marksman', 'name' => 'Marksman', 'attack_power' => 110, 'infantry_defense' => 80, 'cavalry_defense' => 70, 'crop_consumption' => 2, 'speed_fields_per_hour' => 16, 'carry_capacity' => 105, 'total_resource_cost' => 1050, 'training_time_level_1_seconds' => 2480, 'sort_order' => 35],
            ['tribe_key' => 'huns', 'troop_key' => 'marauder', 'name' => 'Marauder', 'attack_power' => 180, 'infantry_defense' => 60, 'cavalry_defense' => 40, 'crop_consumption' => 3, 'speed_fields_per_hour' => 14, 'carry_capacity' => 80, 'total_resource_cost' => 1760, 'training_time_level_1_seconds' => 2990, 'sort_order' => 36],
            ['tribe_key' => 'huns', 'troop_key' => 'ram', 'name' => 'Ram', 'attack_power' => 45, 'infantry_defense' => 30, 'cavalry_defense' => 90, 'crop_consumption' => 3, 'speed_fields_per_hour' => 4, 'carry_capacity' => 0, 'total_resource_cost' => 1820, 'training_time_level_1_seconds' => 4400, 'sort_order' => 37],
            ['tribe_key' => 'huns', 'troop_key' => 'catapult', 'name' => 'Catapult', 'attack_power' => 45, 'infantry_defense' => 55, 'cavalry_defense' => 10, 'crop_consumption' => 6, 'speed_fields_per_hour' => 3, 'carry_capacity' => 0, 'total_resource_cost' => 2910, 'training_time_level_1_seconds' => 9000, 'sort_order' => 38],
            ['tribe_key' => 'huns', 'troop_key' => 'logades', 'name' => 'Logades', 'attack_power' => 50, 'infantry_defense' => 40, 'cavalry_defense' => 30, 'crop_consumption' => 4, 'speed_fields_per_hour' => 5, 'carry_capacity' => 0, 'total_resource_cost' => 117600, 'training_time_level_1_seconds' => 90700, 'sort_order' => 39],
            ['tribe_key' => 'huns', 'troop_key' => 'settler', 'name' => 'Settler', 'attack_power' => 10, 'infantry_defense' => 80, 'cavalry_defense' => 80, 'crop_consumption' => 1, 'speed_fields_per_hour' => 5, 'carry_capacity' => 3000, 'total_resource_cost' => 20900, 'training_time_level_1_seconds' => 28950, 'sort_order' => 40],
            ['tribe_key' => 'egyptians', 'troop_key' => 'slave_militia', 'name' => 'Slave Militia', 'attack_power' => 10, 'infantry_defense' => 30, 'cavalry_defense' => 20, 'crop_consumption' => 1, 'speed_fields_per_hour' => 7, 'carry_capacity' => 15, 'total_resource_cost' => 150, 'training_time_level_1_seconds' => 530, 'sort_order' => 41],
            ['tribe_key' => 'egyptians', 'troop_key' => 'ash_warden', 'name' => 'Ash Warden', 'attack_power' => 30, 'infantry_defense' => 55, 'cavalry_defense' => 40, 'crop_consumption' => 1, 'speed_fields_per_hour' => 6, 'carry_capacity' => 50, 'total_resource_cost' => 420, 'training_time_level_1_seconds' => 1320, 'sort_order' => 42],
            ['tribe_key' => 'egyptians', 'troop_key' => 'khopesh_warrior', 'name' => 'Khopesh Warrior', 'attack_power' => 65, 'infantry_defense' => 50, 'cavalry_defense' => 20, 'crop_consumption' => 1, 'speed_fields_per_hour' => 7, 'carry_capacity' => 45, 'total_resource_cost' => 650, 'training_time_level_1_seconds' => 1440, 'sort_order' => 43],
            ['tribe_key' => 'egyptians', 'troop_key' => 'sophu_explorer_scout', 'name' => 'Sophu Explorer (Scout)', 'attack_power' => 0, 'infantry_defense' => 20, 'cavalry_defense' => 10, 'crop_consumption' => 2, 'speed_fields_per_hour' => 16, 'carry_capacity' => 0, 'total_resource_cost' => 380, 'training_time_level_1_seconds' => 1360, 'sort_order' => 44],
            ['tribe_key' => 'egyptians', 'troop_key' => 'anhur_guard', 'name' => 'Anhur Guard', 'attack_power' => 50, 'infantry_defense' => 110, 'cavalry_defense' => 50, 'crop_consumption' => 2, 'speed_fields_per_hour' => 15, 'carry_capacity' => 50, 'total_resource_cost' => 1090, 'training_time_level_1_seconds' => 2560, 'sort_order' => 45],
            ['tribe_key' => 'egyptians', 'troop_key' => 'resheph_chariot', 'name' => 'Resheph Chariot', 'attack_power' => 110, 'infantry_defense' => 120, 'cavalry_defense' => 150, 'crop_consumption' => 3, 'speed_fields_per_hour' => 10, 'carry_capacity' => 70, 'total_resource_cost' => 1800, 'training_time_level_1_seconds' => 3240, 'sort_order' => 46],
            ['tribe_key' => 'egyptians', 'troop_key' => 'ram', 'name' => 'Ram', 'attack_power' => 55, 'infantry_defense' => 30, 'cavalry_defense' => 95, 'crop_consumption' => 3, 'speed_fields_per_hour' => 4, 'carry_capacity' => 0, 'total_resource_cost' => 1990, 'training_time_level_1_seconds' => 4800, 'sort_order' => 47],
            ['tribe_key' => 'egyptians', 'troop_key' => 'stone_catapult', 'name' => 'Stone Catapult', 'attack_power' => 65, 'infantry_defense' => 55, 'cavalry_defense' => 10, 'crop_consumption' => 6, 'speed_fields_per_hour' => 3, 'carry_capacity' => 0, 'total_resource_cost' => 3250, 'training_time_level_1_seconds' => 9000, 'sort_order' => 48],
            ['tribe_key' => 'egyptians', 'troop_key' => 'nomarch', 'name' => 'Nomarch', 'attack_power' => 40, 'infantry_defense' => 50, 'cavalry_defense' => 50, 'crop_consumption' => 4, 'speed_fields_per_hour' => 4, 'carry_capacity' => 0, 'total_resource_cost' => 160000, 'training_time_level_1_seconds' => 90700, 'sort_order' => 49],
            ['tribe_key' => 'egyptians', 'troop_key' => 'settler', 'name' => 'Settler', 'attack_power' => 0, 'infantry_defense' => 80, 'cavalry_defense' => 80, 'crop_consumption' => 1, 'speed_fields_per_hour' => 5, 'carry_capacity' => 3000, 'total_resource_cost' => 19000, 'training_time_level_1_seconds' => 24800, 'sort_order' => 50],
            ['tribe_key' => 'spartans', 'troop_key' => 'hoplite', 'name' => 'Hoplite', 'attack_power' => 50, 'infantry_defense' => 35, 'cavalry_defense' => 30, 'crop_consumption' => 1, 'speed_fields_per_hour' => 6, 'carry_capacity' => 60, 'total_resource_cost' => 440, 'training_time_level_1_seconds' => 0, 'sort_order' => 51],
            ['tribe_key' => 'spartans', 'troop_key' => 'sentinel_scout', 'name' => 'Sentinel (Scout)', 'attack_power' => 0, 'infantry_defense' => 40, 'cavalry_defense' => 22, 'crop_consumption' => 1, 'speed_fields_per_hour' => 9, 'carry_capacity' => 0, 'total_resource_cost' => 445, 'training_time_level_1_seconds' => 0, 'sort_order' => 52],
            ['tribe_key' => 'spartans', 'troop_key' => 'shieldsman', 'name' => 'Shieldsman', 'attack_power' => 40, 'infantry_defense' => 85, 'cavalry_defense' => 45, 'crop_consumption' => 1, 'speed_fields_per_hour' => 8, 'carry_capacity' => 40, 'total_resource_cost' => 530, 'training_time_level_1_seconds' => 0, 'sort_order' => 53],
            ['tribe_key' => 'spartans', 'troop_key' => 'twinsteel_therion', 'name' => 'Twinsteel Therion', 'attack_power' => 90, 'infantry_defense' => 55, 'cavalry_defense' => 40, 'crop_consumption' => 1, 'speed_fields_per_hour' => 6, 'carry_capacity' => 50, 'total_resource_cost' => 795, 'training_time_level_1_seconds' => 0, 'sort_order' => 54],
            ['tribe_key' => 'spartans', 'troop_key' => 'elpida_rider', 'name' => 'Elpida Rider', 'attack_power' => 55, 'infantry_defense' => 120, 'cavalry_defense' => 90, 'crop_consumption' => 2, 'speed_fields_per_hour' => 16, 'carry_capacity' => 110, 'total_resource_cost' => 1440, 'training_time_level_1_seconds' => 0, 'sort_order' => 55],
            ['tribe_key' => 'spartans', 'troop_key' => 'corinthian_crusher', 'name' => 'Corinthian Crusher', 'attack_power' => 195, 'infantry_defense' => 80, 'cavalry_defense' => 75, 'crop_consumption' => 3, 'speed_fields_per_hour' => 9, 'carry_capacity' => 80, 'total_resource_cost' => 2315, 'training_time_level_1_seconds' => 0, 'sort_order' => 56],
            ['tribe_key' => 'spartans', 'troop_key' => 'ram', 'name' => 'Ram', 'attack_power' => 65, 'infantry_defense' => 30, 'cavalry_defense' => 80, 'crop_consumption' => 3, 'speed_fields_per_hour' => 4, 'carry_capacity' => 0, 'total_resource_cost' => 1705, 'training_time_level_1_seconds' => 0, 'sort_order' => 57],
            ['tribe_key' => 'spartans', 'troop_key' => 'ballista', 'name' => 'Ballista', 'attack_power' => 50, 'infantry_defense' => 60, 'cavalry_defense' => 10, 'crop_consumption' => 6, 'speed_fields_per_hour' => 3, 'carry_capacity' => 0, 'total_resource_cost' => 2750, 'training_time_level_1_seconds' => 0, 'sort_order' => 58],
            ['tribe_key' => 'spartans', 'troop_key' => 'ephor', 'name' => 'Ephor', 'attack_power' => 40, 'infantry_defense' => 60, 'cavalry_defense' => 40, 'crop_consumption' => 4, 'speed_fields_per_hour' => 4, 'carry_capacity' => 0, 'total_resource_cost' => 114290, 'training_time_level_1_seconds' => 0, 'sort_order' => 59],
            ['tribe_key' => 'spartans', 'troop_key' => 'settler', 'name' => 'Settler', 'attack_power' => 10, 'infantry_defense' => 80, 'cavalry_defense' => 80, 'crop_consumption' => 1, 'speed_fields_per_hour' => 5, 'carry_capacity' => 3000, 'total_resource_cost' => 19995, 'training_time_level_1_seconds' => 0, 'sort_order' => 60],
        ];
    }
};
