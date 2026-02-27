<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Seed;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SeedController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Kategooriad samast tabelist mis Plants
        $categories = Category::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name', 'slug'])
            ->map(function ($c) use ($user) {
                $count = Seed::query()
                    ->where('user_id', $user->id)
                    ->where('category_id', $c->id)
                    ->count();

                return [
                    'id' => $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'count' => $count,
                    'image' => '/images/seeds/default.jpg', // pane endale sobiv default
                    'is_favorite' => (bool) ($c->is_favorite ?? false),
                    'created_at' => $c->created_at?->toIso8601String(),
                ];
            });

        return Inertia::render('Seeds/Index', [
            'categories' => $categories,
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        $categories = Category::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Seeds/Create', [
            'categories' => $categories,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'name'        => ['required', 'string', 'max:160'],
            'subtitle'    => ['nullable', 'string', 'max:160'], // sort / märkus
            'amount'      => ['required', 'integer', 'min:1'],
            'year'        => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'expires_at'  => ['nullable', 'date'],
            'image_url'   => ['nullable', 'url'],
        ]);

        // kontrolli, et valitud category kuulub userile
        $category = Category::query()
            ->where('id', $data['category_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        Seed::create([
            'user_id'     => $user->id,
            'category_id' => $category->id,
            'name'        => $data['name'],
            'subtitle'    => $data['subtitle'] ?? null,
            'amount'      => $data['amount'],
            'year'        => $data['year'] ?? null,
            'expires_at'  => $data['expires_at'] ?? null,
            'image_url'   => $data['image_url'] ?? null,
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
                'is_favorite' => (bool) ($seed->is_favorite ?? false),
            ],
        ]);
    }

    public function destroy(Request $request, Seed $seed)
    {
        abort_unless($seed->user_id === $request->user()->id, 403);

        $seed->delete();

        return redirect()->route('seeds.index');
    }
}
