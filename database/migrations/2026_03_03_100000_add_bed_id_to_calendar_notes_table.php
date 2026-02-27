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
                ->foreignId('bed_id')
                ->nullable()
                ->after('user_id')
                ->constrained('beds')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('calendar_notes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bed_id');
        });
    }
};

