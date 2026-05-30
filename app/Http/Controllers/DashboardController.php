<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\CalendarNote;
use App\Models\GardenPlan;
use App\Models\Plant;
use App\Models\Seed;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $today = now()->startOfDay();

        $recentNotes = CalendarNote::query()
            ->where('user_id', $user->id)
            ->with(['bed:id,name', 'plant:id,name'])
            ->orderBy('note_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get(['id', 'note_date', 'title', 'type', 'done', 'media', 'due_at', 'bed_id', 'plant_id']);

        $recentPlants = Plant::query()
            ->where('user_id', $user->id)
            ->with('category:id,name,slug')
            ->orderByDesc('created_at')
            ->get()
            ->unique('name')
            ->take(5)
            ->values()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'image_url' => $p->image_url,
                'created_at' => $p->created_at?->toIso8601String(),
                'category' => $p->category
                    ? ['name' => $p->category->name, 'slug' => $p->category->slug]
                    : null,
            ]);

        $recentBeds = Bed::query()
            ->where('user_id', $user->id)
            ->with(['plants:id,bed_id,image_url'])
            ->withCount('plants')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'name', 'image_url', 'location', 'updated_at'])
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'image_url' => $b->image_url
                    ?: $b->plants->firstWhere('image_url')?->image_url,
                'location' => $b->location,
                'plants_count' => $b->plants_count,
            ]);

        $recentSeeds = Seed::query()
            ->where('user_id', $user->id)
            ->with('category:id,name,slug')
            ->orderByDesc('created_at')
            ->get()
            ->unique('name')
            ->take(5)
            ->values()
            ->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'image_url' => $s->image_url,
                'created_at' => $s->created_at?->toIso8601String(),
                'category' => $s->category
                    ? ['name' => $s->category->name, 'slug' => $s->category->slug]
                    : null,
            ]);

        $galleryItems = collect();

        Bed::query()
            ->where('user_id', $user->id)
            ->with(['plants:id,bed_id,image_url'])
            ->orderByDesc('updated_at')
            ->limit(12)
            ->get(['id', 'name', 'image_url', 'updated_at'])
            ->each(function (Bed $bed) use ($galleryItems) {
                $imageUrl = $bed->image_url
                    ?: $bed->plants->firstWhere('image_url')?->image_url;
                if (! $imageUrl) {
                    return;
                }

                $galleryItems->push([
                    'key' => "bed-{$bed->id}",
                    'kind' => 'bed',
                    'kind_label' => 'Peenar',
                    'title' => $bed->name,
                    'subtitle' => $bed->updated_at?->format('Y-m-d'),
                    'image_url' => $imageUrl,
                    'href' => "/beds/{$bed->id}",
                    'sort_date' => $bed->updated_at?->toIso8601String(),
                ]);
            });

        Plant::query()
            ->where('user_id', $user->id)
            ->whereNotNull('image_url')
            ->orderByDesc('created_at')
            ->limit(60)
            ->get(['id', 'name', 'subtitle', 'image_url', 'created_at'])
            ->groupBy(function (Plant $plant): string {
                $label = filled($plant->subtitle) ? $plant->subtitle : $plant->name;

                return Str::lower(trim((string) $label)).'|'.$plant->image_url;
            })
            ->map(fn ($group) => $group->sortByDesc(
                fn (Plant $plant) => $plant->created_at?->getTimestamp() ?? 0,
            )->first())
            ->take(12)
            ->each(fn (Plant $plant) => $galleryItems->push([
                'key' => "plant-{$plant->id}",
                'kind' => 'plant',
                'kind_label' => 'Taim',
                'title' => filled($plant->subtitle) ? $plant->subtitle : $plant->name,
                'subtitle' => $plant->created_at?->format('Y-m-d'),
                'image_url' => $plant->image_url,
                'href' => "/plants/{$plant->id}",
                'sort_date' => $plant->created_at?->toIso8601String(),
            ]));

        Seed::query()
            ->where('user_id', $user->id)
            ->whereNotNull('image_url')
            ->orderByDesc('created_at')
            ->limit(16)
            ->get(['id', 'name', 'image_url', 'created_at'])
            ->each(fn (Seed $seed) => $galleryItems->push([
                'key' => "seed-{$seed->id}",
                'kind' => 'seed',
                'kind_label' => 'Seeme',
                'title' => $seed->name,
                'subtitle' => $seed->created_at?->format('Y-m-d'),
                'image_url' => $seed->image_url,
                'href' => "/seeds/{$seed->id}",
                'sort_date' => $seed->created_at?->toIso8601String(),
            ]));

        CalendarNote::query()
            ->where('user_id', $user->id)
            ->whereNotNull('media')
            ->orderByDesc('note_date')
            ->orderByDesc('id')
            ->limit(16)
            ->get(['id', 'note_date', 'title', 'media'])
            ->each(function (CalendarNote $note) use ($galleryItems) {
                foreach ($note->media_urls as $index => $imageUrl) {
                    if (! $imageUrl) {
                        continue;
                    }

                    $galleryItems->push([
                        'key' => "note-{$note->id}-{$index}",
                        'kind' => 'note',
                        'kind_label' => 'Märge',
                        'title' => $note->title ?: 'Aiamärge',
                        'subtitle' => $note->note_date?->format('Y-m-d'),
                        'image_url' => $imageUrl,
                        'href' => "/calendar/notes/{$note->id}",
                        'sort_date' => $note->note_date?->format('Y-m-d'),
                    ]);
                }
            });

        $gardenGallery = $galleryItems
            ->filter(fn (array $item) => filled($item['image_url'] ?? null))
            ->unique(fn (array $item) => "{$item['kind']}|{$item['title']}|{$item['image_url']}")
            ->sortByDesc(fn (array $item) => $item['sort_date'] ?? '')
            ->unique('image_url')
            ->take(18)
            ->map(function (array $item) {
                unset($item['sort_date']);

                return $item;
            })
            ->values();

        $todayTasks = CalendarNote::query()
            ->where('user_id', $user->id)
            ->where('done', false)
            ->whereDate('note_date', $today)
            ->orderBy('note_date')
            ->orderBy('id')
            ->limit(3)
            ->get(['id', 'note_date', 'title', 'type'])
            ->map(fn ($note) => [
                'id' => $note->id,
                'note_date' => $note->note_date?->format('Y-m-d'),
                'title' => $note->title,
                'type' => $note->type,
            ]);

        $bedsCount = Bed::query()
            ->where('user_id', $user->id)
            ->count();

        $plantsCount = Plant::query()
            ->where('user_id', $user->id)
            ->count();

        $seedsCount = Seed::query()
            ->where('user_id', $user->id)
            ->count();

        $gardensCount = GardenPlan::query()
            ->where('user_id', $user->id)
            ->count();

        $emptyBedsCount = Bed::query()
            ->where('user_id', $user->id)
            ->doesntHave('plants')
            ->count();

        $plantsWithoutBedCount = Plant::query()
            ->where('user_id', $user->id)
            ->whereNull('bed_id')
            ->count();

        $openTasksCount = CalendarNote::query()
            ->where('user_id', $user->id)
            ->where('done', false)
            ->count();

        $todayTasksCount = CalendarNote::query()
            ->where('user_id', $user->id)
            ->where('done', false)
            ->whereDate('note_date', $today)
            ->count();

        $overdueTasksCount = CalendarNote::query()
            ->where('user_id', $user->id)
            ->where('done', false)
            ->whereDate('note_date', '<', $today)
            ->count();

        return Inertia::render('Dashboard', [
            'recentNotes' => $recentNotes,
            'recentPlants' => $recentPlants,
            'recentBeds' => $recentBeds,
            'recentSeeds' => $recentSeeds,
            'gardenGallery' => $gardenGallery,
            'todayTasks' => $todayTasks,
            'dashboardSummary' => [
                'bedsCount' => $bedsCount,
                'plantsCount' => $plantsCount,
                'seedsCount' => $seedsCount,
                'gardensCount' => $gardensCount,
                'emptyBedsCount' => $emptyBedsCount,
                'plantsWithoutBedCount' => $plantsWithoutBedCount,
                'openTasksCount' => $openTasksCount,
                'todayTasksCount' => $todayTasksCount,
                'overdueTasksCount' => $overdueTasksCount,
                'notesCount' => CalendarNote::query()
                    ->where('user_id', $user->id)
                    ->count(),
            ],
        ]);
    }
}
