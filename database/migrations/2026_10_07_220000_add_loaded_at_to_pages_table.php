<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dateTime('loaded_at', 6)->nullable();
            $table->index(['scenario_id', 'loaded_at', 'id'], 'pages_fifo_index');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex('pages_fifo_index');
            $table->dropColumn('loaded_at');
        });
    }
};
