<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beds', function (Blueprint $table) {
            $table->unsignedSmallInteger('cell_size_cm')
                ->default(30)
                ->after('garden_y');
        });
    }

    public function down(): void
    {
        Schema::table('beds', function (Blueprint $table) {
            $table->dropColumn('cell_size_cm');
        });
    }
};
