<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('plants')->whereNull('quantity')->update(['quantity' => 1]);

        Schema::table('plants', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('plants', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->default(1)->nullable()->change();
        });
    }
};
