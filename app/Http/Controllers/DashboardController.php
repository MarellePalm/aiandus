<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\CalendarNote;
use App\Models\GardenPlan;
use App\Models\Plant;
use App\Models\Seed;
use Illuminate\Http\Request;
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
            ->withCount('plants')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['id', 'name', 'image_url', 'location', 'updated_at'])
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'image_url' => $b->image_url,
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
