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
        Schema::table('calendar_notes', function (Blueprint $table) {
            $table->string('type', 20)->default('note')->after('body');
            $table->boolean('done')->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calendar_notes', function (Blueprint $table) {
            $table->dropColumn(['type', 'done']);
        });
    }
};
