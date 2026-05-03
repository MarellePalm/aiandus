<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beds', function (Blueprint $table) {
            $table->foreignId('garden_plan_id')
                ->nullable()
                ->after('user_id')
                ->constrained('garden_plans')
                ->cascadeOnDelete();
        });

        $beds = DB::table('beds')->select('id', 'user_id')->get();
        foreach ($beds as $bed) {
            $planId = DB::table('garden_plans')
                ->where('user_id', $bed->user_id)
                ->orderBy('id')
                ->value('id');

            if (! $planId) {
                $planId = DB::table('garden_plans')->insertGetId([
                    'user_id' => $bed->user_id,
                    'name' => 'Minu aed',
                    'width' => 1200,
                    'height' => 800,
                    'unit' => 'cm',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('beds')->where('id', $bed->id)->update(['garden_plan_id' => $planId]);
        }

        Schema::table('garden_plans', function (Blueprint $table) {
            $table->dropUnique(['user_id']);
        });

        Schema::table('beds', function (Blueprint $table) {
            $table->unsignedBigInteger('garden_plan_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('garden_plans', function (Blueprint $table) {
            $table->unique('user_id');
        });

        Schema::table('beds', function (Blueprint $table) {
            $table->dropConstrainedForeignId('garden_plan_id');
        });
    }
};
