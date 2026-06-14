<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('user_played_accounts')) {
            return;
        }

        DB::statement("
            DELETE older
            FROM user_played_accounts older
            INNER JOIN user_played_accounts newer
                ON older.user_id = newer.user_id
                AND older.world_key = newer.world_key
                AND (
                    older.updated_at < newer.updated_at
                    OR (older.updated_at = newer.updated_at AND older.id < newer.id)
                )
        ");

        try {
            DB::statement('ALTER TABLE user_played_accounts DROP INDEX user_played_accounts_user_world_player_unique');
        } catch (Throwable) {
            // The corrected base migration may already have created the final index.
        }

        try {
            DB::statement('ALTER TABLE user_played_accounts ADD UNIQUE user_played_accounts_user_world_unique (user_id, world_key)');
        } catch (Throwable) {
            // Keep deploys idempotent when the index already exists.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('user_played_accounts')) {
            return;
        }

        try {
            DB::statement('ALTER TABLE user_played_accounts DROP INDEX user_played_accounts_user_world_unique');
        } catch (Throwable) {
            //
        }

        try {
            DB::statement('ALTER TABLE user_played_accounts ADD UNIQUE user_played_accounts_user_world_player_unique (user_id, world_key, player_name)');
        } catch (Throwable) {
            //
        }
    }
};
