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

        $seeds = Seed::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'year', 'expires_at', 'image_url', 'is_favorite', 'created_at'])
            ->map(fn ($seed) => [
                'id' => $seed->id,
                'name' => $seed->name,
                'year' => $seed->year,
                'expires_at' => $seed->expires_at?->toDateString(),
                'image_url' => $seed->image_url,
                'is_favorite' => (bool) $seed->is_favorite,
                'created_at' => $seed->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Seeds/Index', [
            'seeds' => $seeds,
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
            'name' => ['required', 'string', 'max:160'],
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'expires_at' => ['nullable', 'date'],
            'image' => ['nullable', 'image', 'max:5120'],
        ]);

        // Leia või loo kasutajale vaikimisi kategooria "Seemned"
        // NB: kui sul on categories.slug globaal-unique, tee slug useri-põhiseks (nt 'seemned-'.$user->id)
        $defaultCategory = Category::query()->firstOrCreate(
            [
                'slug' => 'seemned-' . $user->id,
            ],
            [
                'user_id' => $user->id,
                'name' => 'Seemned',
            ]
        );

        $imageUrl = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('seeds', 'public');
            $imageUrl = Storage::url($path);
        }

        Seed::create([
            'user_id'     => $user->id,
            'category_id' => $defaultCategory->id,
            'name'        => $data['name'],
            'subtitle'    => null,
            'amount'      => 1,
            'year'        => $data['year'] ?? null,
            'expires_at'  => $data['expires_at'] ?? null,
            'image_url'   => $imageUrl,
            'is_favorite' => false,
            'notes'       => null,
        ]);

        return redirect()->route('seeds.index')
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
                'image_url' => $seed->image_url,
                'notes' => $seed->notes ?? null,
                'is_favorite' => (bool) $seed->is_favorite,
            ],
        ]);
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