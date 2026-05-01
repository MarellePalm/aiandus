<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ilma ->after(): SQLite ja PostgreSQL ei toeta veeru järjekorra määramist nagu MySQL;
        // live’is võis migratsioon selle tõttu katkeda.
        Schema::table('beds', function (Blueprint $table) {
            if (! Schema::hasColumn('beds', 'garden_x')) {
                $table->unsignedSmallInteger('garden_x')->default(0);
            }

            if (! Schema::hasColumn('beds', 'garden_y')) {
                $table->unsignedSmallInteger('garden_y')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('beds', function (Blueprint $table) {
            $drops = [];

            if (Schema::hasColumn('beds', 'garden_x')) {
                $drops[] = 'garden_x';
            }

            if (Schema::hasColumn('beds', 'garden_y')) {
                $drops[] = 'garden_y';
            }

            if ($drops) {
                $table->dropColumn($drops);
            }
        });
    }
};
