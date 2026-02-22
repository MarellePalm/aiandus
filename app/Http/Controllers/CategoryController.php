<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Inertia\Inertia;
use Illuminate\Http\Request;

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
}
