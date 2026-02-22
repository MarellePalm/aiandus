<?php

namespace App\Http\Controllers;

use App\Models\Plant;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlantController extends Controller
{
    public function index()
    {
       $categories = Category::orderBy('name')->get();

    return Inertia::render('Plants/Index', [
        'categories' => $categories,
    ]);
    }

    public function category(string $slug)
{
    $category = Category::where('slug', $slug)->firstOrFail();

    // ajutiselt tühi list kuni taimed DB-st tulevad
    $plants = [];

    // ⬇️ lisa kõik kategooriad modali selecti jaoks
    $categories = Category::query()
        ->select('id', 'name', 'slug')
        ->orderBy('name')
        ->get();

    return Inertia::render('Plants/SortView', [
        'category' => [
            'name' => $category->name,
            'slug' => $category->slug,
        ],
        'plants' => $plants,
        'categories' => $categories,
    ]);
}

  

    
    public function show(Request $request, Plant $plant)
{
    abort_unless($plant->user_id === $request->user()->id, 403);

    return Inertia::render('Plants/Show', [
        'plant' => [
            'id' => $plant->id,
            'name' => $plant->name,
            'subtitle' => $plant->subtitle,
            'image_url' => $plant->image_url,
            'notes' => $plant->notes,
            'tags' => $plant->tags,
            'watering_in_days' => $plant->watering_in_days,
            'fertilizing_frequency' => $plant->fertilizing_frequency,
            'next_fertilizing_label' => $plant->next_fertilizing_label,
        ],
    ]);
}

public function water(Request $request, Plant $plant)
{
    abort_unless($plant->user_id === $request->user()->id, 403);

    $plant->update([
        'last_watered_at' => now(),
    ]);

    // jää detailvaatele
    return redirect()->route('plants.show', $plant->id);
}

public function create()
{
    return Inertia::render('Plants/Create');
}

public function store(Request $request)
{
     $data = $request->validate([
        'category_id' => ['required', 'exists:categories,id'],
        'subtitle' => ['required', 'string', 'max:160'],
        'planted_at' => ['required', 'date'],
        'image' => ['nullable', 'image', 'max:4096'],
    ]);

    $imagePath = null;
    if ($request->hasFile('image')) {
        $imagePath = $request->file('image')->store('plant-images', 'public');
    }

    $category = Category::select('id', 'name', 'slug')->findOrFail($data['category_id']);

    $plant = Plant::create([
        'name' => $category->name,
        'category_id' => $category->id,
        'subtitle' => $data['subtitle'],
        'planted_at' => $data['planted_at'],
        'image_url' => $imagePath ? "/storage/{$imagePath}" : null,
        'user_id' => $request->user()->id,
    ]);

    return redirect()->route('plants.category', ['slug' => $category->slug])
        ->with('success', 'Taim lisatud!');
}

public function destroy(Plant $plant)
{
    $plant->delete();
    return redirect('/dashboard'); // või back()
}


}
