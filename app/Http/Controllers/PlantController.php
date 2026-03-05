<?php

namespace App\Http\Controllers;

use App\Models\Plant;
use App\Models\Category;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Carbon\Carbon;
class PlantController extends Controller
{
    public function index()
    {
       $categories = Category::withCount([
    'plants as count' => function ($query) {
        $query->where('user_id', request()->user()->id);
    }
])->orderBy('name')->get();

    return Inertia::render('Plants/Index', [
        'categories' => $categories,
    ]);
    }

    public function category(string $slug)
{
    $category = Category::where('slug', $slug)->firstOrFail();

    $categories = Category::query()
        ->select('id', 'name', 'slug')
        ->orderBy('name')
        ->get();

    $plants = Plant::query()
        ->where('user_id', request()->user()->id)
        ->where('category_id', $category->id)
        ->orderByDesc('created_at')
        ->get()
        ->map(fn ($p) => [
            'id' => $p->id,
            'subtitle' => $p->subtitle,
            'name' => $p->name,
            'planted_at' => $p->planted_at ? date('d.m.Y', strtotime($p->planted_at)) : '',
            'status' => $p->status ?? 'ISTIK',
            'image_url' => $p->image_url,
        ]);

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
            'category_slug' => $plant->category->slug,
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
        'name' => $data['subtitle'],
        'category_id' => $category->id,
        'subtitle' => $data['subtitle'],
        'planted_at' => $data['planted_at'],
        'image_url' => $imagePath ? "/storage/{$imagePath}" : null,
        'user_id' => $request->user()->id,
    ]);

    return redirect()->route('plants.category', ['slug' => $category->slug])
        ->with('success', 'Taim lisatud!');
}

public function edit(Request $request, Plant $plant)
{
    abort_unless($plant->user_id === $request->user()->id, 403);

    return Inertia::render('Plants/Edit', [
        'plant' => [
            'id' => $plant->id,
            'name' => $plant->name,
            'subtitle' => $plant->subtitle,
            'notes' => $plant->notes,
            'tags' => $plant->tags,
            'image_url' => $plant->image_url,
            'watering_in_days' => $plant->watering_in_days,
            'fertilizing_frequency' => $plant->fertilizing_frequency,
            'bed_id' => $plant->bed_id,
            'position_in_bed' => $plant->position_in_bed,
        ],
    ]);
}

public function update(Request $request, Plant $plant)
{
    abort_unless($plant->user_id === $request->user()->id, 403);

    $data = $request->validate([
        // taime andmed
        'name' => ['nullable', 'string', 'max:255'],
        'subtitle' => ['nullable', 'string', 'max:160'],
        'notes' => ['nullable', 'string'],
        'watering_in_days' => ['nullable', 'integer', 'min:0'],
        'fertilizing_frequency' => ['nullable', 'string', 'max:255'],

        // peenra andmed (sul juba olid)
        'bed_id' => ['nullable', 'exists:beds,id'],
        'position_in_bed' => ['nullable', 'string', 'max:120'],

        // pilt (valikuline)
        'image' => ['nullable', 'image', 'max:4096'],
    ]);

    // bed kuuluvuse kontroll (sul juba oli)
    if (!empty($data['bed_id'])) {
        $bed = \App\Models\Bed::find($data['bed_id']);
        if ($bed && $bed->user_id !== $request->user()->id) {
            abort(403);
        }
    }

    // pildi upload (kui tuli)
    if ($request->hasFile('image')) {
        $imagePath = $request->file('image')->store('plant-images', 'public');
        $data['image_url'] = "/storage/{$imagePath}";
    }

    unset($data['image']); // Plant modelisse file objekti ei salvesta

    // Uuenda ainult need väljad, mis on saadetud (nullable ok)
    $plant->update([
        'subtitle' => $data['subtitle'] ?? $plant->subtitle,
        'notes' => $data['notes'] ?? $plant->notes,
        'watering_in_days' => $data['watering_in_days'] ?? $plant->watering_in_days,
        'fertilizing_frequency' => $data['fertilizing_frequency'] ?? $plant->fertilizing_frequency,
        'image_url' => $data['image_url'] ?? $plant->image_url,

        
    ]);

    return redirect()->route('plants.show', $plant->id)->with('success', 'Taim uuendatud!');
}

public function destroy(Request $request,Plant $plant)
{
    abort_unless($plant->user_id === $request->user()->id, 403);
    $plant->delete();
    return redirect('/dashboard');
}
}
