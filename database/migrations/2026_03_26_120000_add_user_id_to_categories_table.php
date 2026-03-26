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
            $table->foreignId('user_id')->nullable()->after('id')->index();
        });

        $categories = DB::table('categories')->get([
            'id', 'name', 'slug', 'scope', 'image', 'count', 'is_favorite',
        ]);

        $map = [];

        foreach ($categories as $category) {
            $plantUsers = DB::table('plants')
                ->where('category_id', $category->id)
                ->pluck('user_id');

            $seedUsers = DB::table('seeds')
                ->where('category_id', $category->id)
                ->pluck('user_id');

            $userIds = $plantUsers
                ->merge($seedUsers)
                ->filter()
                ->unique()
                ->values();

            foreach ($userIds as $userId) {
                $base = $category->slug ?: 'kategooria';
                $slug = $base;
                $i = 2;

                while (
                    DB::table('categories')
                        ->where('user_id', $userId)
                        ->where('scope', $category->scope)
                        ->where('slug', $slug)
                        ->exists()
                ) {
                    $slug = "{$base}-{$i}";
                    $i++;
                }

                $newId = DB::table('categories')->insertGetId([
                    'user_id' => $userId,
                    'name' => $category->name,
                    'slug' => $slug,
                    'scope' => $category->scope,
                    'image' => $category->image,
                    'count' => 0,
                    'is_favorite' => false,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);

                $map[$category->id.'_'.$userId] = $newId;
            }
        }

        foreach ($map as $key => $newCategoryId) {
            [$oldCategoryId, $userId] = explode('_', $key);

            DB::table('plants')
                ->where('category_id', (int) $oldCategoryId)
                ->where('user_id', (int) $userId)
                ->update(['category_id' => $newCategoryId]);

            DB::table('seeds')
                ->where('category_id', (int) $oldCategoryId)
                ->where('user_id', (int) $userId)
                ->update(['category_id' => $newCategoryId]);
        }

        DB::table('categories')->whereNull('user_id')->delete();

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique('categories_slug_unique');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->unique(['user_id', 'scope', 'slug'], 'categories_user_scope_slug_unique');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique('categories_user_scope_slug_unique');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('slug')->unique()->change();
            $table->dropColumn('user_id');
        });
    }
};
