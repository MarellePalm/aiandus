<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Inertia\Inertia;
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

        // slug nime põhjal
        $slug = str($data['name'])->slug()->toString();

        // loo kategooria (vajab Category model + fillable)
        Category::create([
            'name' => $data['name'],
            'slug' => $slug,
            'image' => $imagePath ? "/storage/{$imagePath}" : null,
            'count' => 0,
            'is_favorite' => false,
        ]);

        return back()->with('success', 'Kategooria lisatud!');
    }

   public function toggleFavorite(Request $request, Category $category)
    {
    // Kui hiljem teed user_id põhise, siia 403 kontroll.

    $category->update([
        'is_favorite' => ! (bool) $category->is_favorite,
    ]);

    return redirect()->back();
    }

    public function destroy(Category $category)
    {
    // Kui pilt on sinu storage'ist, kustuta ka fail
    if ($category->image && str_starts_with($category->image, '/storage/')) {
        $path = str_replace('/storage/', '', $category->image);
        Storage::disk('public')->delete($path);
    }

    $category->delete();

    return redirect()->back()->with('success', 'Kategooria kustutatud!');
    }
}
