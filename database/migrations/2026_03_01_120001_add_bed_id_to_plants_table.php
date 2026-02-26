<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->foreignId('bed_id')
                ->nullable()
                ->after('category_id')
                ->constrained('beds')
                ->nullOnDelete();
            $table->string('position_in_bed')->nullable()->after('bed_id')
                ->comment('Asukoht peenral, nt "Vasakul ääres", "Keskel", "1. rida"');
        });
    }

    public function down(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bed_id');
            $table->dropColumn('position_in_bed');
        });
    }
};
