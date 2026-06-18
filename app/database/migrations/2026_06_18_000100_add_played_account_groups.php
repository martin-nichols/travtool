<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('travtool_groups', function (Blueprint $table): void {
            $table->foreignId('player_id')->nullable()->after('world_id')->constrained('players')->nullOnDelete();
            $table->string('player_name', 255)->nullable()->after('name');
            $table->string('player_name_normalized', 255)->nullable()->after('player_name');

            $table->index(['world_key', 'type', 'player_name_normalized'], 'travtool_groups_world_type_player_name_idx');
            $table->index(['world_id', 'type', 'player_id'], 'travtool_groups_world_type_player_idx');
        });

        Schema::table('user_played_accounts', function (Blueprint $table): void {
            $table->foreignId('played_account_group_id')
                ->nullable()
                ->after('player_id')
                ->constrained('travtool_groups')
                ->nullOnDelete();

            $table->index('played_account_group_id', 'user_played_accounts_group_idx');
        });

        $this->backfillPlayedAccountGroups();
    }

    public function down(): void
    {
        Schema::table('user_played_accounts', function (Blueprint $table): void {
            $table->dropIndex('user_played_accounts_group_idx');
            $table->dropConstrainedForeignId('played_account_group_id');
        });

        Schema::table('travtool_groups', function (Blueprint $table): void {
            $table->dropIndex('travtool_groups_world_type_player_name_idx');
            $table->dropIndex('travtool_groups_world_type_player_idx');
            $table->dropConstrainedForeignId('player_id');
            $table->dropColumn(['player_name', 'player_name_normalized']);
        });
    }

    private function backfillPlayedAccountGroups(): void
    {
        $now = now();
        $groupIdsByKey = [];

        DB::table('user_played_accounts')
            ->orderBy('id')
            ->get(['id', 'user_id', 'world_id', 'player_id', 'world_key', 'player_name'])
            ->each(function (object $account) use (&$groupIdsByKey, $now): void {
                $normalizedPlayerName = mb_strtolower(trim((string) $account->player_name));
                $groupKey = implode('|', [
                    (string) $account->world_key,
                    $account->player_id !== null ? 'player:'.$account->player_id : 'name:'.$normalizedPlayerName,
                ]);

                if (! isset($groupIdsByKey[$groupKey])) {
                    $groupIdsByKey[$groupKey] = DB::table('travtool_groups')->insertGetId([
                        'world_id' => $account->world_id,
                        'player_id' => $account->player_id,
                        'world_key' => $account->world_key,
                        'name' => $account->player_name,
                        'player_name' => $account->player_name,
                        'player_name_normalized' => $normalizedPlayerName,
                        'type' => 'played_account',
                        'created_by_user_id' => $account->user_id,
                        'invite_code' => $this->newInviteCode(),
                        'invite_created_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                DB::table('user_played_accounts')
                    ->where('id', $account->id)
                    ->update([
                        'played_account_group_id' => $groupIdsByKey[$groupKey],
                        'updated_at' => $now,
                    ]);

                DB::table('travtool_group_users')->updateOrInsert(
                    [
                        'travtool_group_id' => $groupIdsByKey[$groupKey],
                        'user_id' => $account->user_id,
                    ],
                    [
                        'role' => 'owner',
                        'joined_at' => $now,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            });
    }

    private function newInviteCode(): string
    {
        do {
            $code = Str::random(32);
        } while (DB::table('travtool_groups')->where('invite_code', $code)->exists());

        return $code;
    }
};
