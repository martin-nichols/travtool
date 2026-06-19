<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_maps', function (Blueprint $table): void {
            $table->foreignId('played_account_group_id')
                ->nullable()
                ->after('user_id')
                ->constrained('travtool_groups')
                ->nullOnDelete();

            $table->index(['played_account_group_id', 'updated_at'], 'user_maps_group_updated_idx');
            $table->index(['played_account_group_id', 'world_key'], 'user_maps_group_world_idx');
        });

        DB::statement(<<<'SQL'
            UPDATE user_maps um
            INNER JOIN user_played_accounts upa
                ON upa.user_id = um.user_id
                AND upa.world_key = um.world_key
            INNER JOIN travtool_groups tg
                ON tg.id = upa.played_account_group_id
                AND tg.type = 'played_account'
            SET um.played_account_group_id = upa.played_account_group_id
            WHERE um.played_account_group_id IS NULL
        SQL);
    }

    public function down(): void
    {
        Schema::table('user_maps', function (Blueprint $table): void {
            $table->dropIndex('user_maps_group_world_idx');
            $table->dropIndex('user_maps_group_updated_idx');
            $table->dropConstrainedForeignId('played_account_group_id');
        });
    }
};
