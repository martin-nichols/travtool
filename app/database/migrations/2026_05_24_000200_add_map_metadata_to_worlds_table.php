<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('worlds', function (Blueprint $table) {
            $table->boolean('has_regions')->nullable()->after('speed');
            $table->string('map_topology', 16)->nullable()->after('has_regions');
            $table->unsignedSmallInteger('map_width')->nullable()->after('map_topology');
            $table->unsignedSmallInteger('map_height')->nullable()->after('map_width');
            $table->unsignedInteger('map_tile_count')->nullable()->after('map_height');
            $table->unsignedSmallInteger('map_radius')->nullable()->after('map_tile_count');
            $table->timestamp('map_metadata_detected_at')->nullable()->after('map_radius');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('worlds', function (Blueprint $table) {
            $table->dropColumn([
                'has_regions',
                'map_topology',
                'map_width',
                'map_height',
                'map_tile_count',
                'map_radius',
                'map_metadata_detected_at',
            ]);
        });
    }
};
