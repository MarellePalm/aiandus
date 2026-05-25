<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beds', function (Blueprint $table) {
            $table->json('cell_bricks')
                ->nullable()
                ->after('layout')
                ->comment('Peenra plokid: x, y, w, h, kind (plantable|walkway|empty)');
        });
    }

    public function down(): void
    {
        Schema::table('beds', function (Blueprint $table) {
            $table->dropColumn('cell_bricks');
        });
    }
};
