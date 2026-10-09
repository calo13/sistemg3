<?php

use App\Support\DatabaseCompatibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('MemoryLab requiere MySQL 8.0.16 o superior, o MariaDB 10.4.3 o superior.');
        }

        $version = DB::selectOne('SELECT VERSION() AS version')->version;
        DatabaseCompatibility::assertSupported($driver, $version);

        Schema::create('scenarios', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->enum('mode', ['PAGING', 'SEGMENTATION', 'CONTIGUOUS'])->default('PAGING');
            $table->enum('status', ['DRAFT', 'READY', 'RUNNING', 'COMPLETED'])->default('DRAFT');
            $table->boolean('is_demo')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'updated_at']);
        });
        DB::statement('ALTER TABLE scenarios ADD CONSTRAINT scenarios_name_not_empty CHECK (CHAR_LENGTH(TRIM(name)) > 0)');

        Schema::create('memory_configurations', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('scenario_id')->unique()->constrained()->restrictOnDelete();
            $table->unsignedInteger('ram_size_bytes');
            $table->unsignedInteger('page_size_bytes');
            $table->unsignedInteger('secondary_storage_bytes');
            $table->timestamps();
        });
        DB::statement('ALTER TABLE memory_configurations ADD CONSTRAINT memory_configurations_geometry_valid CHECK (ram_size_bytes > 0 AND page_size_bytes > 0 AND page_size_bytes <= ram_size_bytes AND MOD(ram_size_bytes, page_size_bytes) = 0)');

        Schema::create('processes', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('scenario_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->unsignedInteger('size_bytes');
            $table->enum('status', ['READY', 'RUNNING', 'WAITING', 'TERMINATED'])->default('READY');
            $table->timestamps();
            $table->unique(['scenario_id', 'id']);
            $table->index(['scenario_id', 'status']);
        });
        DB::statement('ALTER TABLE processes ADD CONSTRAINT processes_size_positive CHECK (size_bytes > 0)');
        DB::statement('ALTER TABLE processes ADD CONSTRAINT processes_name_not_empty CHECK (CHAR_LENGTH(TRIM(name)) > 0)');

        Schema::create('memory_frames', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('scenario_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('frame_number');
            $table->timestamps();
            $table->unique(['scenario_id', 'frame_number']);
            $table->unique(['scenario_id', 'id']);
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('scenario_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('process_id');
            $table->unsignedBigInteger('frame_id')->nullable()->unique();
            $table->unsignedInteger('page_number');
            $table->timestamps();
            $table->unique(['process_id', 'page_number']);
            $table->foreign(['scenario_id', 'process_id'], 'pages_scenario_process_fk')
                ->references(['scenario_id', 'id'])->on('processes')->restrictOnDelete();
            $table->foreign(['scenario_id', 'frame_id'], 'pages_scenario_frame_fk')
                ->references(['scenario_id', 'id'])->on('memory_frames')->restrictOnDelete();
        });

        Schema::create('segments', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('scenario_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('process_id');
            $table->unsignedInteger('segment_number');
            $table->string('name', 100);
            $table->unsignedInteger('base');
            $table->unsignedInteger('size_bytes');
            $table->enum('status', ['ACTIVE', 'RELEASED'])->default('ACTIVE');
            $table->timestamps();
            $table->unique(['process_id', 'segment_number']);
            $table->foreign(['scenario_id', 'process_id'], 'segments_scenario_process_fk')
                ->references(['scenario_id', 'id'])->on('processes')->restrictOnDelete();
        });
        DB::statement('ALTER TABLE segments ADD CONSTRAINT segments_size_positive CHECK (size_bytes > 0)');
        DB::statement('ALTER TABLE segments ADD CONSTRAINT segments_name_not_empty CHECK (CHAR_LENGTH(TRIM(name)) > 0)');

        Schema::create('simulation_events', function (Blueprint $table) {
            $table->engine('InnoDB');
            $table->id();
            $table->foreignId('scenario_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('process_id')->nullable();
            $table->enum('type', [
                'PROCESS_CREATED', 'PAGE_REQUEST', 'PAGE_HIT', 'PAGE_FAULT', 'PAGE_LOADED',
                'SEGMENT_ACCESS', 'SEGMENTATION_FAULT', 'MEMORY_RESET', 'SCENARIO_CREATED',
                'MEMORY_CONFIGURED', 'PROCESS_TERMINATED',
            ]);
            $table->text('description');
            $table->json('metadata')->nullable();
            $table->dateTime('occurred_at', 6);
            $table->timestamps(6);
            $table->index(['scenario_id', 'occurred_at', 'id'], 'simulation_events_timeline_index');
            $table->index(['scenario_id', 'type', 'occurred_at'], 'simulation_events_type_time_index');
            $table->foreign(['scenario_id', 'process_id'], 'simulation_events_scenario_process_fk')
                ->references(['scenario_id', 'id'])->on('processes')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('simulation_events');
        Schema::dropIfExists('segments');
        Schema::dropIfExists('pages');
        Schema::dropIfExists('memory_frames');
        Schema::dropIfExists('processes');
        Schema::dropIfExists('memory_configurations');
        Schema::dropIfExists('scenarios');
    }
};
