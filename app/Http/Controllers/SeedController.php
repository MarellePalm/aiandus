<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Seed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SeedController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $categories = Category::query()
            ->withCount([
                'seeds as count' => fn ($q) => $q->where('user_id', $user->id),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'image', 'is_favorite', 'created_at'])
            ->map(fn ($category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'image' => $category->image ?: $this->fallbackCategoryImageUrl($category->id),
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
        $category = Category::query()->where('slug', $slug)->firstOrFail();

        $seeds = Seed::query()
            ->where('user_id', $user->id)
            ->where('category_id', $category->id)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'year', 'expires_at', 'image_url', 'notes', 'is_favorite', 'created_at'])
            ->map(fn ($seed) => [
                'id' => $seed->id,
                'name' => $seed->name,
                'year' => $seed->year,
                'expires_at' => $seed->expires_at ? $seed->expires_at->toDateString() : null,
                'image_url' => $seed->image_url ?: $this->fallbackImageUrl($seed->id),
                'notes' => $seed->notes,
                'is_favorite' => (bool) $seed->is_favorite,
                'created_at' => $seed->created_at?->toIso8601String(),
            ]);

        $categories = Category::query()
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
        return Inertia::render('Seeds/AddSeed');
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:160'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'expires_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        $category = null;
        if (!empty($data['category_id'])) {
            $category = Category::query()->findOrFail($data['category_id']);
        } else {
            $category = Category::query()->firstOrCreate(
                ['slug' => 'seemned'],
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
                'year' => $seed->year,
                'expires_at' => $seed->expires_at?->toDateString(),
                'image_url' => $seed->image_url ?: $this->fallbackImageUrl($seed->id),
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

    private function fallbackImageUrl(int $seedId): string
    {
        return "https://picsum.photos/seed/seeme-{$seedId}/640/480";
    }

    private function fallbackCategoryImageUrl(int $categoryId): string
    {
        return "https://picsum.photos/seed/seemnekategooria-{$categoryId}/800/800";
    }
}