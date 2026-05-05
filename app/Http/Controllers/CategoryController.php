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
            'name' => ['required', 'string', 'max:80'],
            'image' => ['nullable', 'image', 'max:5120', 'dimensions:min_width=300,min_height=300,max_width=6000,max_height=6000'],
        ]);

        // pildi salvestus (public disk)
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('category-images', 'public');
        }

        $slug = $this->uniqueSlug($data['name'], $request->user()->id);

        Category::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'slug' => $slug,
            'scope' => Category::SCOPE_PLANT,
            'image' => $imagePath ? "/storage/{$imagePath}" : null,
            'count' => 0,
            'is_favorite' => false,
        ]);

        return back()->with('success', 'Kategooria lisatud!');
    }

    public function update(Request $request, Category $category)
    {
        abort_unless($category->scope === Category::SCOPE_PLANT, 404);
        abort_unless($category->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'image' => ['nullable', 'image', 'max:5120', 'dimensions:min_width=300,min_height=300,max_width=6000,max_height=6000'],
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
            'slug' => $this->uniqueSlug($data['name'], $request->user()->id, $category->id),
            'image' => $imageUrl,
        ]);

        return back()->with('success', 'Kategooria uuendatud!');
    }

    public function toggleFavorite(Request $request, Category $category)
    {
        abort_unless($category->scope === Category::SCOPE_PLANT, 404);
        abort_unless($category->user_id === $request->user()->id, 403);

        $category->update([
            'is_favorite' => ! (bool) $category->is_favorite,
        ]);

        return redirect()->back();
    }

    public function destroy(Request $request, Category $category)
    {
        abort_unless($category->scope === Category::SCOPE_PLANT, 404);
        abort_unless($category->user_id === $request->user()->id, 403);

        if ($category->image && str_starts_with($category->image, '/storage/')) {
            $path = str_replace('/storage/', '', $category->image);
            Storage::disk('public')->delete($path);
        }

        $category->delete();

        return redirect()->back()->with('success', 'Kategooria kustutatud!');
    }

    private function uniqueSlug(string $name, int $userId, ?int $ignoreId = null): string
    {
        $base = str($name)->slug()->toString();
        $base = $base !== '' ? $base : 'kategooria';

        $slug = $base;
        $index = 2;
        while (
            Category::query()
                ->where('user_id', $userId)
                ->where('scope', Category::SCOPE_PLANT)
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
