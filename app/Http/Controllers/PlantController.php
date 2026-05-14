<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\CalendarNote;
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
                $totalQty = $group->sum(fn ($p) => (int) $p->quantity);
                $inBed = $group->filter(fn ($p) => $p->bed_id !== null)->sum(fn ($p) => (int) $p->quantity);
                $inStock = $group->filter(fn ($p) => $p->bed_id === null)->sum(fn ($p) => (int) $p->quantity);

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

        $plant->loadMissing(['category:id,slug', 'bed:id,name,garden_plan_id,image_url']);

        $bedLocations = collect();

        if ($plant->bed_id && $plant->bed) {
            $bedLocations->push([
                'bed_id' => (int) $plant->bed->id,
                'bed_name' => $plant->bed->name,
                'garden_plan_id' => (int) $plant->bed->garden_plan_id,
                'image_url' => $plant->bed->image_url,
                'quantity' => (int) $plant->quantity,
            ]);
        }

        if ($plant->category_id) {
            $displayKey = self::plantVarietyKey($plant);

            $otherBeds = Plant::query()
                ->where('user_id', $plant->user_id)
                ->where('category_id', $plant->category_id)
                ->whereNotNull('bed_id')
                ->where('id', '!=', $plant->id)
                ->when(
                    $plant->bed_id,
                    fn ($q) => $q->where('bed_id', '!=', $plant->bed_id),
                )
                ->with(['bed:id,name,garden_plan_id,image_url'])
                ->get()
                ->filter(function (Plant $p) use ($displayKey) {
                    return self::plantVarietyKey($p) === $displayKey;
                })
                ->groupBy('bed_id')
                ->map(function ($group) {
                    $first = $group->first();
                    if (! $first?->bed) {
                        return null;
                    }

                    return [
                        'bed_id' => (int) $first->bed->id,
                        'bed_name' => $first->bed->name,
                        'garden_plan_id' => (int) $first->bed->garden_plan_id,
                        'image_url' => $first->bed->image_url,
                        'quantity' => $group->sum(fn (Plant $p) => (int) $p->quantity),
                    ];
                })
                ->filter()
                ->values();

            $bedLocations = $bedLocations->concat($otherBeds);
        }

        $bedLocations = $bedLocations->sortBy('bed_name')->values()->all();

        $qtySplit = self::sumVarietyStockAndBeds($plant);

        $varietyKey = self::plantVarietyKey($plant);
        $varietyPlantIds = Plant::query()
            ->where('user_id', $plant->user_id)
            ->get(['id', 'name', 'subtitle'])
            ->filter(fn (Plant $p) => self::plantVarietyKey($p) === $varietyKey)
            ->pluck('id')
            ->values()
            ->all();

        $calendarNotes = CalendarNote::query()
            ->where('user_id', $plant->user_id)
            ->whereIn('plant_id', $varietyPlantIds)
            ->orderByDesc('note_date')
            ->orderByDesc('id')
            ->limit(40)
            ->get(['id', 'note_date', 'title', 'body'])
            ->map(fn (CalendarNote $n) => [
                'id' => $n->id,
                'note_date' => $n->note_date->format('Y-m-d'),
                'title' => $n->title,
                'body' => Str::limit((string) ($n->body ?? ''), 200),
            ])
            ->values()
            ->all();

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
                'quantity' => (int) $plant->quantity,
                'quantity_in_stock' => $qtySplit['in_stock'],
                'quantity_on_beds' => $qtySplit['on_beds'],
                'bed_locations' => $bedLocations,
                'calendar_notes' => $calendarNotes,
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

        $data = array_merge(['quantity' => 1], $request->validate([
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
            'quantity' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]));

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
            'quantity' => (int) $data['quantity'],
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
                'quantity' => (int) $plant->quantity,
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

        DB::transaction(function () use ($request, $plant, $data): void {
            $locked = Plant::query()->whereKey($plant->id)->lockForUpdate()->firstOrFail();

            abort_unless($locked->user_id === $request->user()->id, 403);

            $assigningToBedFromStock = $locked->bed_id === null
                && array_key_exists('bed_id', $data)
                && ! empty($data['bed_id'])
                && array_key_exists('position_in_bed', $data)
                && ($data['position_in_bed'] ?? '') !== '';

            $prevQty = max(1, (int) $locked->quantity);

            $assignQty = $prevQty;

            if ($assigningToBedFromStock) {
                $requested = array_key_exists('quantity', $data) ? (int) $data['quantity'] : $prevQty;
                $assignQty = max(1, min($requested, $prevQty));
                $data['quantity'] = $assignQty;
            }

            $payload = [
                'subtitle' => $data['subtitle'] ?? $locked->subtitle,
                'notes' => $data['notes'] ?? $locked->notes,
                'watering_frequency' => $data['watering_frequency'] ?? $locked->watering_frequency,
                'fertilizing_frequency' => $data['fertilizing_frequency'] ?? $locked->fertilizing_frequency,
                'image_url' => $data['image_url'] ?? $locked->image_url,
                'quantity' => array_key_exists('quantity', $data) ? $data['quantity'] : $locked->quantity,
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
                $remainder = $prevQty - $assignQty;
                $stockPlant = $locked->replicate(['bed_id', 'position_in_bed', 'quantity']);
                $stockPlant->bed_id = null;
                $stockPlant->position_in_bed = null;
                $stockPlant->quantity = $remainder;
                $stockPlant->save();

                $locked->update($payload);
            } else {
                $locked->update($payload);
            }
        });

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

    private static function plantVarietyKey(Plant $plant): string
    {
        $label = filled($plant->subtitle) ? (string) $plant->subtitle : (string) $plant->name;

        return Str::lower(trim($label));
    }

    /**
     * @return array{in_stock: int, on_beds: int}
     */
    private static function sumVarietyStockAndBeds(Plant $plant): array
    {
        if (! $plant->category_id) {
            $q = (int) $plant->quantity;
            if ($plant->bed_id === null) {
                return ['in_stock' => $q, 'on_beds' => 0];
            }

            return ['in_stock' => 0, 'on_beds' => $q];
        }

        $key = self::plantVarietyKey($plant);
        $inStock = 0;
        $onBeds = 0;

        $rows = Plant::query()
            ->where('user_id', $plant->user_id)
            ->where('category_id', $plant->category_id)
            ->get(['subtitle', 'name', 'bed_id', 'quantity']);

        foreach ($rows as $row) {
            if (self::plantVarietyKey($row) !== $key) {
                continue;
            }
            $q = (int) $row->quantity;
            if ($row->bed_id === null) {
                $inStock += $q;
            } else {
                $onBeds += $q;
            }
        }

        return ['in_stock' => $inStock, 'on_beds' => $onBeds];
    }
}
