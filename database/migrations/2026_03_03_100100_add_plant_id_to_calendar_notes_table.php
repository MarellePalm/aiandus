<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_notes', function (Blueprint $table) {
            $table
                ->foreignId('plant_id')
                ->nullable()
                ->after('bed_id')
                ->constrained('plants')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('calendar_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plant_id');
        });
    }
};

