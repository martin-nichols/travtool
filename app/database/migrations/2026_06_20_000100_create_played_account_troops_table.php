<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('played_account_troops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('played_account_group_id')->constrained('travtool_groups')->cascadeOnDelete();
            $table->string('world_key', 100);
            $table->string('village_name', 80);
            $table->string('troop_key', 80);
            $table->string('troop_name', 120);
            $table->unsignedInteger('quantity')->default(0);
            $table->unsignedSmallInteger('sort_order');
            $table->foreignId('imported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['played_account_group_id', 'village_name', 'troop_key'], 'played_troops_group_village_troop_unique');
            $table->index(['played_account_group_id', 'sort_order']);
            $table->index(['world_key', 'played_account_group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('played_account_troops');
    }
};
