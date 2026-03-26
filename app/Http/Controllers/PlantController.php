<?php

namespace App\Http\Controllers;

use App\Models\Plant;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Carbon\Carbon;
class PlantController extends Controller
{
    public function index()
    {
       $user = request()->user();

       $categories = Category::query()
    ->where('user_id', $user->id)
    ->where('scope', Category::SCOPE_PLANT)
    ->withCount([
        'plants as count' => function ($query) {
            $query->where('user_id', request()->user()->id);
        }
    ])
    ->orderBy('name')
    ->get();

    return Inertia::render('Plants/Index', [
        'categories' => $categories,
    ]);
    }

    public function category(string $slug)
{
    $user = request()->user();

    $category = Category::query()
        ->where('user_id', $user->id)
        ->where('scope', Category::SCOPE_PLANT)
        ->where('slug', $slug)
        ->firstOrFail();

    $categories = Category::query()
        ->where('user_id', $user->id)
        ->where('scope', Category::SCOPE_PLANT)
        ->select('id', 'name', 'slug')
        ->orderBy('name')
        ->get();

    $plants = Plant::query()
        ->where('user_id', $user->id)
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
            'planted_at' => $plant->planted_at,
            'watering_in_days' => $plant->watering_in_days,
            'fertilizing_frequency' => $plant->fertilizing_frequency,
            'next_fertilizing_label' => $plant->next_fertilizing_label,
            'category_slug' => $plant->category?->slug,
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
    $user = $request->user();

    $data = $request->validate([
        'category_id' => [
            'required',
            Rule::exists('categories', 'id')
                ->where('scope', Category::SCOPE_PLANT)
                ->where('user_id', $user->id),
        ],
        'subtitle' => ['required', 'string', 'max:160'],
        'planted_at' => ['required', 'date'],
        'watering_in_days' => ['nullable', 'string', 'max:255'],
        'fertilizing_frequency' => ['nullable', 'string', 'max:255'],
        'notes' => ['nullable', 'string'],
        'image' => ['nullable', 'image', 'max:4096'],
    ]);

    $imagePath = null;
    if ($request->hasFile('image')) {
        $imagePath = $request->file('image')->store('plant-images', 'public');
    }

    $category = Category::query()
        ->where('id', $data['category_id'])
        ->where('user_id', $user->id)
        ->where('scope', Category::SCOPE_PLANT)
        ->select('id', 'name', 'slug')
        ->firstOrFail();

    Plant::create([
        'name' => $data['subtitle'],
        'category_id' => $category->id,
        'subtitle' => $data['subtitle'],
        'planted_at' => $data['planted_at'],
        'watering_in_days' => $data['watering_in_days'] ?? null,
        'fertilizing_frequency' => $data['fertilizing_frequency'] ?? null,
        'notes' => $data['notes'] ?? null,
        'image_url' => $imagePath ? "/storage/{$imagePath}" : null,
        'user_id' => $user->id,
    ]);

    return redirect()
        ->route('plants.category', ['slug' => $category->slug])
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
            'planted_at' => $plant->planted_at,
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
        'watering_in_days' => ['nullable', 'string', 'max:255'],
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

    $payload = [
        'subtitle' => $data['subtitle'] ?? $plant->subtitle,
        'notes' => $data['notes'] ?? $plant->notes,
        'watering_in_days' => $data['watering_in_days'] ?? $plant->watering_in_days,
        'fertilizing_frequency' => $data['fertilizing_frequency'] ?? $plant->fertilizing_frequency,
        'image_url' => $data['image_url'] ?? $plant->image_url,
    ];

    if (array_key_exists('notes', $data)) {
        $payload['notes'] = $data['notes'] === '' ? null : $data['notes'];
    }

    if (array_key_exists('watering_in_days', $data)) {
        $payload['watering_in_days'] = $data['watering_in_days'] === '' ? null : $data['watering_in_days'];
    }

    if (array_key_exists('fertilizing_frequency', $data)) {
        $payload['fertilizing_frequency'] = $data['fertilizing_frequency'] === '' ? null : $data['fertilizing_frequency'];
    }
    if (array_key_exists('bed_id', $data)) {
        $payload['bed_id'] = $data['bed_id'];
    }
    if (array_key_exists('position_in_bed', $data)) {
        $payload['position_in_bed'] = $data['position_in_bed'];
    }

    $plant->update($payload);

    // Peenrale määramisel (kaardilt) jääme kaardile; muul juhul taime vaatesse
    if ($request->has('bed_id') || $request->has('position_in_bed')) {
        return back()->with('success', 'Taim peenrale määratud.');
    }

    return redirect()->route('plants.show', $plant->id)->with('success', 'Taim uuendatud!');
}

public function destroy(Request $request,Plant $plant)
{
    abort_unless($plant->user_id === $request->user()->id, 403);
    $plant->delete();
    return redirect('/dashboard');
}
}
