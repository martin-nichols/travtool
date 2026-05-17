<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('staging_map_rows', 'region_id')) {
            DB::statement('ALTER TABLE staging_map_rows CHANGE region_id region_name_raw VARCHAR(255) NULL');
        }

        if (Schema::hasColumn('villages', 'region_id')) {
            DB::statement('ALTER TABLE villages CHANGE region_id region_name VARCHAR(255) NULL');
        }

        if (Schema::hasColumn('village_snapshots', 'region_id')) {
            DB::statement('ALTER TABLE village_snapshots CHANGE region_id region_name VARCHAR(255) NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('staging_map_rows', 'region_name_raw')) {
            DB::statement('ALTER TABLE staging_map_rows CHANGE region_name_raw region_id INT UNSIGNED NULL');
        }

        if (Schema::hasColumn('villages', 'region_name')) {
            DB::statement('ALTER TABLE villages CHANGE region_name region_id INT UNSIGNED NULL');
        }

        if (Schema::hasColumn('village_snapshots', 'region_name')) {
            DB::statement('ALTER TABLE village_snapshots CHANGE region_name region_id INT UNSIGNED NULL');
        }
    }
};
