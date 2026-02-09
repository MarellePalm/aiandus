<?php

namespace App\Http\Controllers;

use App\Models\Plant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlantController extends Controller
{
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
        'name' => ['required', 'string', 'max:120'],
        'subtitle' => ['nullable', 'string', 'max:160'],
        'planted_at' => ['nullable', 'date'],
    ]);

    $plant = Plant::create([
        ...$data,
        'user_id' => $request->user()->id,
    ]);

    return redirect()->route('plants.show', $plant->id);
}


}
