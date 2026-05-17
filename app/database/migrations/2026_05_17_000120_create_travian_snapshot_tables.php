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
        Schema::create('alliance_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_id')->constrained()->cascadeOnDelete();
            $table->foreignId('snapshot_id')->constrained('map_snapshots')->cascadeOnDelete();
            $table->foreignId('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('external_alliance_id');
            $table->string('tag', 64);
            $table->unsignedInteger('member_count')->default(0);
            $table->unsignedInteger('village_count')->default(0);
            $table->unsignedBigInteger('population_total')->default(0);
            $table->timestamps();

            $table->unique(['snapshot_id', 'alliance_id']);
            $table->index(['world_id', 'snapshot_date', 'alliance_id']);
            $table->index(['world_id', 'snapshot_date', 'external_alliance_id']);
        });

        Schema::create('player_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_id')->constrained()->cascadeOnDelete();
            $table->foreignId('snapshot_id')->constrained('map_snapshots')->cascadeOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('alliance_id')->nullable()->constrained('alliances')->nullOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('external_player_id');
            $table->unsignedInteger('external_alliance_id')->nullable();
            $table->string('name', 255);
            $table->unsignedInteger('village_count')->default(0);
            $table->unsignedBigInteger('population_total')->default(0);
            $table->integer('population_delta_1d')->nullable();
            $table->integer('village_count_delta_1d')->nullable();
            $table->timestamps();

            $table->unique(['snapshot_id', 'player_id']);
            $table->index(['world_id', 'snapshot_date', 'player_id']);
            $table->index(['world_id', 'snapshot_date', 'alliance_id']);
            $table->index(['world_id', 'snapshot_date', 'external_alliance_id']);
        });

        Schema::create('village_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_id')->constrained()->cascadeOnDelete();
            $table->foreignId('snapshot_id')->constrained('map_snapshots')->cascadeOnDelete();
            $table->foreignId('village_id')->constrained('villages')->cascadeOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignId('alliance_id')->nullable()->constrained('alliances')->nullOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('map_tile_id');
            $table->unsignedInteger('external_village_id');
            $table->unsignedInteger('external_player_id');
            $table->unsignedInteger('external_alliance_id')->default(0);
            $table->smallInteger('x');
            $table->smallInteger('y');
            $table->unsignedTinyInteger('tribe_id');
            $table->string('name', 255);
            $table->unsignedInteger('population');
            $table->unsignedInteger('region_id')->nullable();
            $table->boolean('is_capital')->nullable();
            $table->boolean('is_city')->nullable();
            $table->boolean('has_harbor')->nullable();
            $table->unsignedInteger('victory_points')->nullable();
            $table->timestamps();

            $table->unique(['snapshot_id', 'village_id']);
            $table->unique(['snapshot_id', 'map_tile_id']);
            $table->index(['world_id', 'snapshot_date', 'player_id']);
            $table->index(['world_id', 'snapshot_date', 'alliance_id']);
            $table->index(['world_id', 'x', 'y', 'snapshot_date']);
            $table->index(['world_id', 'snapshot_date', 'external_player_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('village_snapshots');
        Schema::dropIfExists('player_snapshots');
        Schema::dropIfExists('alliance_snapshots');
    }
};
