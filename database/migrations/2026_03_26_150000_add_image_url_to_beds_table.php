<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('beds', 'image_url')) {
            return;
        }

        Schema::table('beds', function (Blueprint $table) {
            $table->string('image_url')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('beds', 'image_url')) {
            return;
        }

        Schema::table('beds', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};

