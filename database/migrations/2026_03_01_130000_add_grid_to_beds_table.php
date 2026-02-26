<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beds', function (Blueprint $table) {
            $table->unsignedTinyInteger('rows')->default(3)->after('location');
            $table->unsignedTinyInteger('columns')->default(3)->after('rows');
        });
    }

    public function down(): void
    {
        Schema::table('beds', function (Blueprint $table) {
            $table->dropColumn(['rows', 'columns']);
        });
    }
};
