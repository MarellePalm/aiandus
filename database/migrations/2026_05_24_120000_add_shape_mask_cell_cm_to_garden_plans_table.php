<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('garden_plans', function (Blueprint $table) {
            $table->unsignedSmallInteger('shape_mask_cell_cm')
                ->default(1000)
                ->after('shape_mask');
        });
    }

    public function down(): void
    {
        Schema::table('garden_plans', function (Blueprint $table) {
            $table->dropColumn('shape_mask_cell_cm');
        });
    }
};
