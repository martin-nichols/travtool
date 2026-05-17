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
        Schema::create('staging_map_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_run_id')->constrained('map_import_runs')->cascadeOnDelete();
            $table->foreignId('world_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('line_number');
            $table->unsignedInteger('map_tile_id');
            $table->smallInteger('x');
            $table->smallInteger('y');
            $table->unsignedTinyInteger('tribe_id');
            $table->unsignedInteger('external_village_id');
            $table->string('village_name_raw', 255);
            $table->unsignedInteger('external_player_id');
            $table->string('player_name_raw', 255);
            $table->unsignedInteger('external_alliance_id')->default(0);
            $table->string('alliance_tag_raw', 64)->nullable();
            $table->unsignedInteger('population');
            $table->string('region_name_raw', 255)->nullable();
            $table->boolean('is_capital')->nullable();
            $table->boolean('is_city')->nullable();
            $table->boolean('has_harbor')->nullable();
            $table->unsignedInteger('victory_points')->nullable();
            $table->text('raw_sql_line')->nullable();

            $table->unique(['import_run_id', 'line_number']);
            $table->index(['world_id', 'snapshot_date']);
            $table->index(['import_run_id', 'map_tile_id']);
            $table->index(['import_run_id', 'external_village_id']);
        });

        Schema::create('alliances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('external_alliance_id');
            $table->string('tag', 64);
            $table->foreignId('first_seen_snapshot_id')->nullable()->constrained('map_snapshots')->nullOnDelete();
            $table->foreignId('last_seen_snapshot_id')->nullable()->constrained('map_snapshots')->nullOnDelete();
            $table->boolean('is_present')->default(true);
            $table->unsignedInteger('current_member_count')->default(0);
            $table->unsignedInteger('current_village_count')->default(0);
            $table->unsignedBigInteger('current_population_total')->default(0);
            $table->timestamps();

            $table->unique(['world_id', 'external_alliance_id']);
            $table->index(['world_id', 'tag']);
            $table->index(['world_id', 'is_present']);
        });

        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('external_player_id');
            $table->foreignId('alliance_id')->nullable()->constrained('alliances')->nullOnDelete();
            $table->string('name', 255);
            $table->foreignId('first_seen_snapshot_id')->nullable()->constrained('map_snapshots')->nullOnDelete();
            $table->foreignId('last_seen_snapshot_id')->nullable()->constrained('map_snapshots')->nullOnDelete();
            $table->boolean('is_present')->default(true);
            $table->unsignedInteger('current_village_count')->default(0);
            $table->unsignedBigInteger('current_population_total')->default(0);
            $table->timestamps();

            $table->unique(['world_id', 'external_player_id']);
            $table->index(['world_id', 'alliance_id']);
            $table->index(['world_id', 'name']);
            $table->index(['world_id', 'is_present']);
        });

        Schema::create('villages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('external_village_id');
            $table->unsignedInteger('map_tile_id');
            $table->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignId('alliance_id')->nullable()->constrained('alliances')->nullOnDelete();
            $table->string('name', 255);
            $table->smallInteger('x');
            $table->smallInteger('y');
            $table->unsignedTinyInteger('tribe_id');
            $table->unsignedInteger('population');
            $table->string('region_name', 255)->nullable();
            $table->boolean('is_capital')->nullable();
            $table->boolean('is_city')->nullable();
            $table->boolean('has_harbor')->nullable();
            $table->unsignedInteger('victory_points')->nullable();
            $table->foreignId('first_seen_snapshot_id')->nullable()->constrained('map_snapshots')->nullOnDelete();
            $table->foreignId('last_seen_snapshot_id')->nullable()->constrained('map_snapshots')->nullOnDelete();
            $table->boolean('is_present')->default(true);
            $table->timestamps();

            $table->unique(['world_id', 'external_village_id']);
            $table->index(['world_id', 'x', 'y']);
            $table->index(['world_id', 'map_tile_id']);
            $table->index(['world_id', 'player_id']);
            $table->index(['world_id', 'alliance_id']);
            $table->index(['world_id', 'is_present']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('villages');
        Schema::dropIfExists('players');
        Schema::dropIfExists('alliances');
        Schema::dropIfExists('staging_map_rows');
    }
};
