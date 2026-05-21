<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('garden_plans', function (Blueprint $table) {
            $table->json('boundary_polygon')->nullable()->after('center_lng');
        });
    }

    public function down(): void
    {
        Schema::table('garden_plans', function (Blueprint $table) {
            $table->dropColumn('boundary_polygon');
        });
    }
};
