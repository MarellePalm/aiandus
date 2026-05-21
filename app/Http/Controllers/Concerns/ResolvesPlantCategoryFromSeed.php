<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Category;
use App\Models\Seed;

trait ResolvesPlantCategoryFromSeed
{
    private function plantCategoryFromSeed(Seed $seed, int $userId): ?Category
    {
        $seed->loadMissing('category');

        if (! $seed->category) {
            return null;
        }

        $existing = Category::query()
            ->where('user_id', $userId)
            ->where('scope', Category::SCOPE_PLANT)
            ->where('name', $seed->category->name)
            ->first();

        if ($existing) {
            return $existing;
        }

        $base = str($seed->category->name)->slug()->toString();
        $base = $base !== '' ? $base : 'kategooria';

        $slug = $base;
        $index = 2;
        while (
            Category::query()
                ->where('user_id', $userId)
                ->where('scope', Category::SCOPE_PLANT)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$base}-{$index}";
            $index++;
        }

        return Category::create([
            'user_id' => $userId,
            'name' => $seed->category->name,
            'slug' => $slug,
            'scope' => Category::SCOPE_PLANT,
            'image' => null,
            'count' => 0,
            'is_favorite' => false,
        ]);
    }
}
