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
        Schema::create('player_population_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_id')->constrained()->cascadeOnDelete();
            $table->foreignId('snapshot_id')->nullable()->constrained('map_snapshots')->nullOnDelete();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('external_player_id');
            $table->unsignedInteger('village_count')->default(0);
            $table->unsignedBigInteger('population_total')->default(0);
            $table->integer('population_delta_1d')->nullable();
            $table->integer('population_delta_2d')->nullable();
            $table->integer('population_delta_3d')->nullable();
            $table->integer('village_count_delta_1d')->nullable();
            $table->integer('village_count_delta_2d')->nullable();
            $table->integer('village_count_delta_3d')->nullable();
            $table->timestamps();

            $table->unique(['world_id', 'player_id', 'snapshot_date'], 'player_pop_hist_world_player_date_unique');
            $table->index(['world_id', 'snapshot_date'], 'player_pop_hist_world_date_idx');
            $table->index(['world_id', 'external_player_id', 'snapshot_date'], 'player_pop_hist_world_ext_player_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_population_histories');
    }
};
