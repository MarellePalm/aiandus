<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:80'],
            'image' => ['nullable', 'image', 'max:4096'], // 4MB
        ]);

        // pildi salvestus (public disk)
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('category-images', 'public');
        }

        $slug = $this->uniqueSlug($data['name']);

        Category::create([
            'name' => $data['name'],
            'slug' => $slug,
            'image' => $imagePath ? "/storage/{$imagePath}" : null,
            'count' => 0,
            'is_favorite' => false,
        ]);

        return back()->with('success', 'Kategooria lisatud!');
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name'  => ['required', 'string', 'max:80'],
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

        return back()->with('success', 'Kategooria uuendatud!');
    }

    public function toggleFavorite(Request $request, Category $category)
    {
        $category->update([
            'is_favorite' => ! (bool) $category->is_favorite,
        ]);

        return redirect()->back();
    }

    public function destroy(Category $category)
    {
        if ($category->image && str_starts_with($category->image, '/storage/')) {
            $path = str_replace('/storage/', '', $category->image);
            Storage::disk('public')->delete($path);
        }

        $category->delete();

        return redirect()->back()->with('success', 'Kategooria kustutatud!');
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
