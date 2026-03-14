<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('scope', 16)->default('plant')->after('slug')->index();
        });

        $seedOnlyIds = DB::table('categories as c')
            ->join('seeds as s', 's.category_id', '=', 'c.id')
            ->leftJoin('plants as p', 'p.category_id', '=', 'c.id')
            ->whereNull('p.id')
            ->distinct()
            ->pluck('c.id');

        if ($seedOnlyIds->isNotEmpty()) {
            DB::table('categories')
                ->whereIn('id', $seedOnlyIds->all())
                ->update(['scope' => 'seed']);
        }

        $mixedCategoryIds = DB::table('categories as c')
            ->join('seeds as s', 's.category_id', '=', 'c.id')
            ->join('plants as p', 'p.category_id', '=', 'c.id')
            ->distinct()
            ->pluck('c.id');

        foreach ($mixedCategoryIds as $categoryId) {
            $original = DB::table('categories')->where('id', $categoryId)->first();
            if (! $original) {
                continue;
            }

            $newSlug = "{$original->slug}-seed";
            $suffix = 2;
            while (DB::table('categories')->where('slug', $newSlug)->exists()) {
                $newSlug = "{$original->slug}-seed-{$suffix}";
                $suffix++;
            }

            $newCategoryId = DB::table('categories')->insertGetId([
                'name' => $original->name,
                'slug' => $newSlug,
                'scope' => 'seed',
                'image' => $original->image,
                'count' => $original->count,
                'is_favorite' => false,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            DB::table('seeds')
                ->where('category_id', $categoryId)
                ->update(['category_id' => $newCategoryId]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('scope');
        });
    }
};
