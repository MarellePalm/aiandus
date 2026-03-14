<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SeedCategoryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('category-images', 'public');
        }

        Category::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'scope' => Category::SCOPE_SEED,
            'image' => $imagePath ? "/storage/{$imagePath}" : null,
            'count' => 0,
            'is_favorite' => false,
        ]);

        return back()->with('success', 'Seemnekategooria lisatud!');
    }

    public function update(Request $request, Category $category)
    {
        abort_unless($category->scope === Category::SCOPE_SEED, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'image' => ['nullable', 'image', 'max:4096'],
        ]);

        $imageUrl = $category->image;
        if ($request->hasFile('image')) {
            if ($category->image && str_starts_with($category->image, '/storage/')) {
                $oldPath = str_replace('/storage/', '', $category->image);
                Storage::disk('public')->delete($oldPath);
            }
            $newPath = $request->file('image')->store('category-images', 'public');
            $imageUrl = "/storage/{$newPath}";
        }

        $category->update([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name'], $category->id),
            'image' => $imageUrl,
        ]);

        return back()->with('success', 'Seemnekategooria uuendatud!');
    }

    public function toggleFavorite(Category $category)
    {
        abort_unless($category->scope === Category::SCOPE_SEED, 404);

        $category->update([
            'is_favorite' => ! (bool) $category->is_favorite,
        ]);

        return back();
    }

    public function destroy(Category $category)
    {
        abort_unless($category->scope === Category::SCOPE_SEED, 404);

        if ($category->image && str_starts_with($category->image, '/storage/')) {
            $path = str_replace('/storage/', '', $category->image);
            Storage::disk('public')->delete($path);
        }

        $category->delete();

        return back()->with('success', 'Seemnekategooria kustutatud!');
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = str($name)->slug()->toString();
        $base = $base !== '' ? $base : 'kategooria';

        $slug = $base;
        $index = 2;
        while (
            Category::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$index}";
            $index++;
        }

        return $slug;
    }
}
