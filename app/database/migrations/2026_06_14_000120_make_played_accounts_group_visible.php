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

        DB::table('user_played_accounts')->update(['visibility' => 'group']);
    }

    public function down(): void
    {
        //
    }
};
