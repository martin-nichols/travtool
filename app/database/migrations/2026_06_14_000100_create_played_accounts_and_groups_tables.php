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
        Schema::create('user_played_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('world_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('world_key', 100);
            $table->string('player_name', 255);
            $table->string('visibility', 32)->default('private');
            $table->timestamps();

            $table->unique(['user_id', 'world_key'], 'user_played_accounts_user_world_unique');
            $table->index(['world_id', 'player_id'], 'user_played_accounts_world_player_idx');
        });

        Schema::create('travtool_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_id')->nullable()->constrained()->nullOnDelete();
            $table->string('world_key', 100);
            $table->string('name', 150);
            $table->string('type', 32)->default('alliance');
            $table->foreignId('created_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('invite_code', 64)->nullable()->unique();
            $table->timestamp('invite_created_at')->nullable();
            $table->timestamp('invite_revoked_at')->nullable();
            $table->timestamps();

            $table->index(['world_key', 'type']);
        });

        Schema::create('travtool_group_users', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travtool_group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 32)->default('member');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['travtool_group_id', 'user_id'], 'travtool_group_users_group_user_unique');
            $table->index(['user_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('travtool_group_users');
        Schema::dropIfExists('travtool_groups');
        Schema::dropIfExists('user_played_accounts');
    }
};
