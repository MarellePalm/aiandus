<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beds', function (Blueprint $table) {
            $table->json('layout')->nullable()->after('columns')
                ->comment('2D: 1=peenra ruut, 0=vahekäik/tühi. Kui null, kasuta rows*columns täisruudustikuna.');
        });
    }

    public function down(): void
    {
        Schema::table('beds', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }
};
