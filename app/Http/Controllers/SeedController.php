<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesPlantCategoryFromSeed;
use App\Models\Bed;
use App\Models\Category;
use App\Models\Plant;
use App\Models\Seed;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SeedController extends Controller
{
    use ResolvesPlantCategoryFromSeed;

    public function index(Request $request)
    {
        $user = $request->user();

        $categories = Category::query()
            ->where('user_id', $user->id)
            ->where('scope', Category::SCOPE_SEED)
            ->withCount([
                'seeds as count' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'image', 'is_favorite', 'created_at'])
            ->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'image' => $category->image,
                'count' => (int) ($category->count ?? 0),
                'is_favorite' => (bool) $category->is_favorite,
                'created_at' => $category->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Seeds/Index', [
            'categories' => $categories,
        ]);
    }

    public function category(Request $request, string $slug)
    {
        $user = $request->user();
        $category = Category::query()
            ->where('user_id', $user->id)
            ->where('scope', Category::SCOPE_SEED)
            ->where('slug', $slug)
            ->firstOrFail();

        $seeds = Seed::query()
            ->where('user_id', $user->id)
            ->where('category_id', $category->id)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'amount_text', 'year', 'expires_at', 'image_url', 'notes', 'is_favorite', 'created_at'])
            ->map(fn ($seed) => [
                'id' => $seed->id,
                'name' => $seed->name,
                'amount_text' => $seed->amount_text,
                'year' => $seed->year,
                'expires_at' => $seed->expires_at ? $seed->expires_at->format('Y-m-d') : null,
                'image_url' => $seed->image_url,
                'notes' => $seed->notes,
                'is_favorite' => (bool) $seed->is_favorite,
                'created_at' => $seed->created_at?->toIso8601String(),
            ]);

        $categories = Category::query()
            ->where('user_id', $user->id)
            ->where('scope', Category::SCOPE_SEED)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('Seeds/SortView', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ],
            'seeds' => $seeds,
            'categories' => $categories,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        $categories = Category::query()
            ->where('user_id', $user->id)
            ->where('scope', Category::SCOPE_SEED)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('Seeds/AddSeed', [
            'categories' => $categories,
            'standalone' => true,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate(
            [
                'category_id' => [
                    'nullable',
                    'integer',
                    Rule::exists('categories', 'id')
                        ->where('scope', Category::SCOPE_SEED)
                        ->where('user_id', $user->id),
                ],
                'name' => ['required', 'string', 'max:160'],
                'amount_text' => ['nullable', 'string', 'max:120'],
                'year' => ['nullable', 'integer', 'between:2000,2100'],
                'expires_at' => ['required', 'date'],
                'notes' => ['nullable', 'string', 'max:5000'],
                'image' => ['nullable', 'image', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            ],
            [
                'expires_at.required' => 'Kohustuslik väli.',
                'expires_at.date' => 'Kohustuslik väli.',
            ],
            [
                'amount_text' => 'kogus',
            ]
        );

        $category = null;
        if (! empty($data['category_id'])) {
            $category = Category::query()
                ->where('id', $data['category_id'])
                ->where('user_id', $user->id)
                ->where('scope', Category::SCOPE_SEED)
                ->firstOrFail();
        } else {
            $category = Category::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'scope' => Category::SCOPE_SEED,
                    'slug' => 'seemned-seed-default',
                ],
                ['name' => 'Varud']
            );
        }

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('seeds', 'public');
            $imageUrl = Storage::url($path);
        }

        Seed::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'name' => $data['name'],
            'subtitle' => null,
            'amount' => 1,
            'amount_text' => $data['amount_text'] ?? null,
            'year' => $data['year'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'image_url' => $imageUrl,
            'is_favorite' => false,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('seeds.category', ['slug' => $category->slug])
            ->with('success', 'Seeme lisatud!');
    }

    public function show(Request $request, Seed $seed)
    {
        abort_unless($seed->user_id === $request->user()->id, 403);

        $user = $request->user();

        $beds = Bed::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'garden_plan_id']);

        $bedsUsed = Plant::query()
            ->where('seed_id', $seed->id)
            ->where('user_id', $user->id)
            ->whereNotNull('bed_id')
            ->with('bed:id,name,garden_plan_id')
            ->get()
            ->filter(fn (Plant $plant) => $plant->bed !== null)
            ->unique(fn (Plant $plant) => $plant->bed_id)
            ->map(fn (Plant $plant) => [
                'bed_id' => $plant->bed_id,
                'bed_name' => $plant->bed->name,
                'garden_plan_id' => $plant->bed->garden_plan_id,
            ])
            ->values()
            ->all();

        $hasPendingGermination = Plant::query()
            ->where('seed_id', $seed->id)
            ->where('user_id', $user->id)
            ->where('quantity', 0)
            ->exists();

        return Inertia::render('Seeds/Show', [
            'beds' => $beds,
            'bedsUsed' => $bedsUsed,
            'hasPendingGermination' => $hasPendingGermination,
            'seed' => [
                'id' => $seed->id,
                'name' => $seed->name,
                'subtitle' => $seed->subtitle,
                'amount' => $seed->amount,
                'amount_text' => $seed->amount_text,
                'year' => $seed->year,
                'expires_at' => $seed->expires_at?->format('Y-m-d'),
                'image_url' => $seed->image_url,
                'notes' => $seed->notes ?? null,
                'is_favorite' => (bool) $seed->is_favorite,
            ],
        ]);
    }

    public function plantFromSeed(Request $request, Seed $seed): RedirectResponse
    {
        abort_unless($seed->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'bed_id' => [
                'required',
                'integer',
                Rule::exists('beds', 'id')->where('user_id', $request->user()->id),
            ],
        ]);

        $category = $this->plantCategoryFromSeed($seed, $request->user()->id);

        $plant = Plant::create([
            'user_id' => $request->user()->id,
            'category_id' => $category?->id,
            'seed_id' => $seed->id,
            'name' => $seed->name,
            'subtitle' => $seed->name,
            'planted_at' => now()->toDateString(),
            'image_url' => $seed->image_url,
            'quantity' => 0,
        ]);

        return redirect()
            ->route('beds.show', ['bed' => $data['bed_id']])
            ->with('success', 'Taim seemnepaketist lisatud. Vali peenral ruut.');
    }

    public function markGerminated(Request $request, Seed $seed): RedirectResponse
    {
        abort_unless($seed->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'germinated_count' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);

        $plants = Plant::query()
            ->where('seed_id', $seed->id)
            ->where('user_id', $request->user()->id)
            ->get();

        foreach ($plants as $plant) {
            $plant->update(['quantity' => $data['germinated_count']]);
        }

        $dateLabel = now()->format('d.m.Y');
        $usageLine = "Kasutatud {$dateLabel}, {$data['germinated_count']} tõusis";
        $existingNotes = trim((string) ($seed->notes ?? ''));
        $seed->update([
            'notes' => $existingNotes !== ''
                ? "{$existingNotes}\n{$usageLine}"
                : $usageLine,
        ]);

        return back()->with('success', 'Idanemine salvestatud.');
    }

    public function update(Request $request, Seed $seed)
    {
        abort_unless($seed->user_id === $request->user()->id, 403);

        $data = $request->validate(
            [
                'name' => ['required', 'string', 'max:160'],
                'amount_text' => ['nullable', 'string', 'max:120'],
                'year' => ['nullable', 'integer', 'between:2000,2100'],
                'expires_at' => ['required', 'date'],
                'notes' => ['nullable', 'string', 'max:5000'],
                'image' => ['nullable', 'image', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            ],
            [
                'expires_at.required' => 'Kohustuslik väli.',
                'expires_at.date' => 'Kohustuslik väli.',
            ],
            [
                'amount_text' => 'kogus',
            ]
        );

        $imageUrl = $seed->image_url;
        if ($request->hasFile('image')) {
            if ($seed->image_url && str_starts_with($seed->image_url, '/storage/')) {
                $oldPath = str_replace('/storage/', '', $seed->image_url);
                Storage::disk('public')->delete($oldPath);
            }
            $path = $request->file('image')->store('seeds', 'public');
            $imageUrl = Storage::url($path);
        }

        $seed->update([
            'name' => $data['name'],
            'amount_text' => $data['amount_text'] ?? null,
            'year' => $data['year'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'image_url' => $imageUrl,
        ]);

        return back()->with('success', 'Seeme uuendatud!');
    }

    public function toggleFavorite(Request $request, Seed $seed)
    {
        abort_unless($seed->user_id === $request->user()->id, 403);

        $seed->is_favorite = ! $seed->is_favorite;
        $seed->save();

        return back();
    }

    public function destroy(Request $request, Seed $seed)
    {
        abort_unless($seed->user_id === $request->user()->id, 403);

        $seed->delete();

        return back()->with('success', 'Varu kustutatud!');
    }
}
