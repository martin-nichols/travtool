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
            $table->string('import_time', 5)->default('00:10')->after('server_timezone');
            $table->string('external_uuid', 64)->nullable()->after('key');
            $table->string('catalog_slug', 120)->nullable()->after('external_uuid');
            $table->string('catalog_domain', 64)->nullable()->after('catalog_slug');
            $table->string('game_type', 32)->nullable()->after('speed');
            $table->timestamp('starts_at')->nullable()->after('game_type');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            $table->boolean('registration_closed')->nullable()->after('ends_at');
            $table->string('mainpage_background', 191)->nullable()->after('registration_closed');
            $table->json('mainpage_groups')->nullable()->after('mainpage_background');
            $table->json('languages')->nullable()->after('mainpage_groups');
            $table->json('tribe_names')->nullable()->after('languages');
            $table->timestamp('catalog_last_seen_at')->nullable()->after('tribe_names');
            $table->timestamp('catalog_synced_at')->nullable()->after('catalog_last_seen_at');

            $table->unique('external_uuid', 'worlds_external_uuid_unique');
            $table->index(['catalog_domain', 'catalog_slug'], 'worlds_catalog_domain_slug_idx');
            $table->index('game_type', 'worlds_game_type_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('worlds', function (Blueprint $table) {
            $table->dropUnique('worlds_external_uuid_unique');
            $table->dropIndex('worlds_catalog_domain_slug_idx');
            $table->dropIndex('worlds_game_type_idx');

            $table->dropColumn([
                'import_time',
                'external_uuid',
                'catalog_slug',
                'catalog_domain',
                'game_type',
                'starts_at',
                'ends_at',
                'registration_closed',
                'mainpage_background',
                'mainpage_groups',
                'languages',
                'tribe_names',
                'catalog_last_seen_at',
                'catalog_synced_at',
            ]);
        });
    }
};
