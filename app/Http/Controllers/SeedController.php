<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Seed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SeedController extends Controller
{
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
                'expires_at' => $seed->expires_at ? $seed->expires_at->format('Y') : null,
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

        $data = $request->validate([
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
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $category = null;
        if (!empty($data['category_id'])) {
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
                ['name' => 'Seemned']
            );
        }

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('seeds', 'public');
            $imageUrl = Storage::url($path);
        }

        Seed::create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'name'        => $data['name'],
            'subtitle'    => null,
            'amount'      => 1,
            'amount_text' => $data['amount_text'] ?? null,
            'year'        => $data['year'] ?? null,
            'expires_at'  => $data['expires_at'] ?? null,
            'image_url'   => $imageUrl,
            'is_favorite' => false,
            'notes'       => $data['notes'] ?? null,
        ]);

        return redirect()->route('seeds.category', ['slug' => $category->slug])
            ->with('success', 'Seeme lisatud!');
    }

    public function show(Request $request, Seed $seed)
    {
        abort_unless($seed->user_id === $request->user()->id, 403);

        return Inertia::render('Seeds/Show', [
            'seed' => [
                'id' => $seed->id,
                'name' => $seed->name,
                'subtitle' => $seed->subtitle,
                'amount' => $seed->amount,
                'amount_text' => $seed->amount_text,
                'year' => $seed->year,
                'expires_at' => $seed->expires_at?->format('Y'),
                'image_url' => $seed->image_url,
                'notes' => $seed->notes ?? null,
                'is_favorite' => (bool) $seed->is_favorite,
            ],
        ]);
    }

    public function update(Request $request, Seed $seed)
    {
        abort_unless($seed->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'amount_text' => ['nullable', 'string', 'max:120'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

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

        $seed->is_favorite = !$seed->is_favorite;
        $seed->save();

        return back();
    }

    public function destroy(Request $request, Seed $seed)
    {
        abort_unless($seed->user_id === $request->user()->id, 403);

        $seed->delete();

        return redirect()->route('seeds.index');
    }

}