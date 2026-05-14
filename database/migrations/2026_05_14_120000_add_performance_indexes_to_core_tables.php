<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Originaalmigratsioon võib juba lisada (user_id, note_date); see nimi on ainult lisaindeksile. */
    private const CALENDAR_USER_DATE = 'calendar_notes_user_date_perf_idx';

    private const PLANTS_USER_BED = 'plants_user_id_bed_id_index';

    private const BEDS_USER_ID = 'beds_user_id_index';

    private const SEEDS_USER_ID = 'seeds_user_id_index';

    public function up(): void
    {
        if (! Schema::hasIndex('calendar_notes', ['user_id', 'note_date'])) {
            Schema::table('calendar_notes', function (Blueprint $table) {
                $table->index(['user_id', 'note_date'], self::CALENDAR_USER_DATE);
            });
        }

        if (! Schema::hasIndex('plants', ['user_id', 'bed_id'])) {
            Schema::table('plants', function (Blueprint $table) {
                $table->index(['user_id', 'bed_id'], self::PLANTS_USER_BED);
            });
        }

        if (! Schema::hasIndex('beds', ['user_id'])) {
            Schema::table('beds', function (Blueprint $table) {
                $table->index('user_id', self::BEDS_USER_ID);
            });
        }

        if (! Schema::hasIndex('seeds', ['user_id'])) {
            Schema::table('seeds', function (Blueprint $table) {
                $table->index('user_id', self::SEEDS_USER_ID);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('calendar_notes', self::CALENDAR_USER_DATE)) {
            Schema::table('calendar_notes', function (Blueprint $table) {
                $table->dropIndex(self::CALENDAR_USER_DATE);
            });
        }

        if (Schema::hasIndex('plants', self::PLANTS_USER_BED)) {
            Schema::table('plants', function (Blueprint $table) {
                $table->dropIndex(self::PLANTS_USER_BED);
            });
        }

        if (Schema::hasIndex('beds', self::BEDS_USER_ID)) {
            Schema::table('beds', function (Blueprint $table) {
                $table->dropIndex(self::BEDS_USER_ID);
            });
        }

        if (Schema::hasIndex('seeds', self::SEEDS_USER_ID)) {
            Schema::table('seeds', function (Blueprint $table) {
                $table->dropIndex(self::SEEDS_USER_ID);
            });
        }
    }
};
