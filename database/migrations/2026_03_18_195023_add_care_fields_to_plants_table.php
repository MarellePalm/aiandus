<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            if (!Schema::hasColumn('plants', 'planted_at')) {
                $table->date('planted_at')->nullable();
            }

            if (!Schema::hasColumn('plants', 'notes')) {
                $table->text('notes')->nullable();
            }

            if (!Schema::hasColumn('plants', 'watering_in_days')) {
                $table->integer('watering_in_days')->nullable();
            }

            if (!Schema::hasColumn('plants', 'fertilizing_frequency')) {
                $table->string('fertilizing_frequency')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $columnsToDrop = [];

            if (Schema::hasColumn('plants', 'fertilizing_frequency')) {
                $columnsToDrop[] = 'fertilizing_frequency';
            }

            if (Schema::hasColumn('plants', 'watering_in_days')) {
                $columnsToDrop[] = 'watering_in_days';
            }

            if (Schema::hasColumn('plants', 'notes')) {
                $columnsToDrop[] = 'notes';
            }

            if (Schema::hasColumn('plants', 'planted_at')) {
                $columnsToDrop[] = 'planted_at';
            }

            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
