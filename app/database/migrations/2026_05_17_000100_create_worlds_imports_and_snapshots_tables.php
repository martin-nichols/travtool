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
        Schema::create('worlds', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('name', 150);
            $table->string('base_url', 255);
            $table->string('map_sql_url', 255);
            $table->string('server_timezone', 64)->default('UTC');
            $table->unsignedSmallInteger('speed')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('map_import_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->string('status', 32)->default('pending');
            $table->string('source_url', 255);
            $table->string('raw_file_path', 255)->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedInteger('line_count')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['world_id', 'snapshot_date']);
            $table->index(['world_id', 'status']);
        });

        Schema::create('map_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('world_id')->constrained()->cascadeOnDelete();
            $table->foreignId('successful_import_run_id')->nullable()->constrained('map_import_runs')->nullOnDelete();
            $table->unsignedBigInteger('previous_snapshot_id')->nullable();
            $table->date('snapshot_date');
            $table->string('status', 32)->default('pending');
            $table->string('source_url', 255);
            $table->string('raw_file_path', 255)->nullable();
            $table->string('checksum', 64)->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->unsignedInteger('line_count')->nullable();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamp('staged_at')->nullable();
            $table->timestamp('normalized_at')->nullable();
            $table->timestamp('current_state_updated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['world_id', 'snapshot_date']);
            $table->index('previous_snapshot_id');
            $table->index(['world_id', 'status']);
        });

        Schema::table('worlds', function (Blueprint $table) {
            $table->foreignId('current_snapshot_id')->nullable()->after('is_active')->constrained('map_snapshots')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('worlds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_snapshot_id');
        });

        Schema::dropIfExists('map_snapshots');
        Schema::dropIfExists('map_import_runs');
        Schema::dropIfExists('worlds');
    }
};
