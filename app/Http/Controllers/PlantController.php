<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\Category;
use App\Models\Plant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PlantController extends Controller
{
    public function create()
    {
        $user = request()->user();

        $categories = Category::query()
            ->where('user_id', $user->id)
            ->where('scope', Category::SCOPE_PLANT)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('AddPlant', [
            'categories' => $categories,
        ]);
    }

    public function index()
    {
        $user = request()->user();

        $categories = Category::query()
            ->where('user_id', $user->id)
            ->where('scope', Category::SCOPE_PLANT)
            ->withCount([
                'plants as count' => function ($query) {
                    $query->where('user_id', request()->user()->id);
                },
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
            ->groupBy(function (Plant $p): string {
                $label = filled($p->subtitle) ? (string) $p->subtitle : (string) $p->name;

                return Str::lower(trim($label));
            })
            ->map(function ($group) {
                $first = $group->sortByDesc(
                    fn (Plant $p) => $p->created_at?->getTimestamp() ?? 0,
                )->first();
                $totalQty = $group->sum(fn ($p) => $p->quantity ?? 1);
                $inBed = $group->filter(fn ($p) => $p->bed_id !== null)->sum(fn ($p) => $p->quantity ?? 1);
                $inStock = $group->filter(fn ($p) => $p->bed_id === null)->sum(fn ($p) => $p->quantity ?? 1);

                return [
                    'id' => $first->id,
                    'subtitle' => $first->subtitle,
                    'name' => $first->name,
                    'planted_at' => $first->planted_at ? date('d.m.Y', strtotime($first->planted_at)) : '',
                    'status' => $first->status ?? 'ISTIK',
                    'image_url' => $first->image_url,
                    'is_favorite' => (bool) $first->is_favorite,
                    'quantity' => $totalQty,
                    'in_bed' => $inBed,
                    'in_stock' => $inStock,
                ];
            })
            ->values();

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
                'watering_frequency' => $plant->watering_frequency,
                'fertilizing_frequency' => $plant->fertilizing_frequency,
                'next_fertilizing_label' => $plant->next_fertilizing_label,
                'category_slug' => $plant->category?->slug,
                'quantity' => $plant->quantity ?? 1,
            ],
        ]);
    }

    public function water(Request $request, Plant $plant)
    {
        abort_unless($plant->user_id === $request->user()->id, 403);

        $plant->update([
            'last_watered_at' => now(),
        ]);

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
            'watering_frequency' => ['nullable', 'string', 'max:255'],
            'fertilizing_frequency' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:5120', 'dimensions:min_width=300,min_height=300,max_width=6000,max_height=6000'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
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
            'watering_frequency' => $data['watering_frequency'] ?? null,
            'fertilizing_frequency' => $data['fertilizing_frequency'] ?? null,
            'notes' => $data['notes'] ?? null,
            'image_url' => $imagePath ? "/storage/{$imagePath}" : null,
            'user_id' => $user->id,
            'quantity' => $data['quantity'] ?? 1,
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
                'watering_frequency' => $plant->watering_frequency,
                'fertilizing_frequency' => $plant->fertilizing_frequency,
                'bed_id' => $plant->bed_id,
                'position_in_bed' => $plant->position_in_bed,
                'quantity' => $plant->quantity ?? 1,
            ],
        ]);
    }

    public function update(Request $request, Plant $plant)
    {
        abort_unless($plant->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'subtitle' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string'],
            'watering_frequency' => ['nullable', 'string', 'max:255'],
            'fertilizing_frequency' => ['nullable', 'string', 'max:255'],
            'bed_id' => ['nullable', 'exists:beds,id'],
            'position_in_bed' => ['nullable', 'string', 'max:120'],
            'image' => ['nullable', 'image', 'max:5120', 'dimensions:min_width=300,min_height=300,max_width=6000,max_height=6000'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if (! empty($data['bed_id'])) {
            $bed = Bed::find($data['bed_id']);
            if ($bed && $bed->user_id !== $request->user()->id) {
                abort(403);
            }
        }

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('plant-images', 'public');
            $data['image_url'] = "/storage/{$imagePath}";
        }

        unset($data['image']);

        $assigningToBedFromStock = $plant->bed_id === null
            && array_key_exists('bed_id', $data)
            && ! empty($data['bed_id'])
            && array_key_exists('position_in_bed', $data)
            && ($data['position_in_bed'] ?? '') !== '';

        $prevQty = max(1, (int) ($plant->quantity ?? 1));
        $assignQty = $prevQty;

        if ($assigningToBedFromStock) {
            $requested = array_key_exists('quantity', $data) ? (int) $data['quantity'] : $prevQty;
            $assignQty = max(1, min($requested, $prevQty));
            $data['quantity'] = $assignQty;
        }

        $payload = [
            'subtitle' => $data['subtitle'] ?? $plant->subtitle,
            'notes' => $data['notes'] ?? $plant->notes,
            'watering_frequency' => $data['watering_frequency'] ?? $plant->watering_frequency,
            'fertilizing_frequency' => $data['fertilizing_frequency'] ?? $plant->fertilizing_frequency,
            'image_url' => $data['image_url'] ?? $plant->image_url,
            'quantity' => $data['quantity'] ?? $plant->quantity,
        ];

        if (array_key_exists('notes', $data)) {
            $payload['notes'] = $data['notes'] === '' ? null : $data['notes'];
        }

        if (array_key_exists('watering_frequency', $data)) {
            $payload['watering_frequency'] = $data['watering_frequency'] === '' ? null : $data['watering_frequency'];
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

        if ($assigningToBedFromStock && $assignQty < $prevQty) {
            DB::transaction(function () use ($plant, $payload, $assignQty, $prevQty): void {
                $remainder = $prevQty - $assignQty;
                $stockPlant = $plant->replicate(['bed_id', 'position_in_bed', 'quantity']);
                $stockPlant->bed_id = null;
                $stockPlant->position_in_bed = null;
                $stockPlant->quantity = $remainder;
                $stockPlant->save();

                $plant->update($payload);
            });
        } else {
            $plant->update($payload);
        }

        if ($request->has('bed_id') || $request->has('position_in_bed')) {
            return back()->with('success', 'Taim peenrale määratud.');
        }

        return redirect()->route('plants.show', $plant->id)->with('success', 'Taim uuendatud!');
    }

    public function toggleFavorite(Plant $plant)
    {
        $plant->update([
            'is_favorite' => ! $plant->is_favorite,
        ]);

        return back();
    }

    public function destroy(Request $request, Plant $plant)
    {
        abort_unless($plant->user_id === $request->user()->id, 403);
        $plant->delete();

        return redirect('/dashboard');
    }
}
