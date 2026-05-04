<?php

// FILE: routes/web.php

use App\Http\Controllers\BedController;
use App\Http\Controllers\CalendarNoteController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GardenMapController;
use App\Http\Controllers\GardenObjectController;
use App\Http\Controllers\GardenPlanController;
use App\Http\Controllers\PlantController;
use App\Http\Controllers\SeedCategoryController;
use App\Http\Controllers\SeedController;
use App\Http\Controllers\WeatherController;
use App\Models\Bed;
use App\Models\CalendarNote;
use App\Models\Plant;
use App\Models\Seed;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Socialite\Facades\Socialite;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('api/weather', WeatherController::class)->name('api.weather');

    Route::get('dashboard', function (Request $request) {
        $user = $request->user();
        $today = now()->startOfDay();

        $recentNotes = CalendarNote::query()
            ->where('user_id', $user->id)
            ->orderBy('note_date', 'desc')
            ->orderBy('id', 'desc')
            ->limit(5)
            ->get(['id', 'note_date', 'title', 'type', 'done', 'media']);

        $recentPlants = Plant::query()
            ->where('user_id', $user->id)
            ->with('category:id,name,slug')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'image_url' => $p->image_url,
                'created_at' => $p->created_at?->toIso8601String(),
                'category' => $p->category
                    ? ['name' => $p->category->name, 'slug' => $p->category->slug]
                    : null,
            ]);

        $recentSeeds = Seed::query()
            ->where('user_id', $user->id)
            ->with('category:id,name,slug')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
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
            'recentSeeds' => $recentSeeds,
            'todayTasks' => $todayTasks,
            'dashboardSummary' => [
                'bedsCount' => $bedsCount,
                'plantsCount' => $plantsCount,
                'seedsCount' => $seedsCount,
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
    })->name('dashboard');

    Route::get('map', [GardenMapController::class, 'redirect'])->name('map');
    Route::get('map/{gardenPlan}/beds/new', [GardenMapController::class, 'createBed'])
        ->name('map.beds.create');
    Route::get('map/{gardenPlan}', [GardenMapController::class, 'show'])->name('map.show');

    Route::get('beds/{bed}', function (Request $request, Bed $bed) {
        abort_unless($bed->user_id === $request->user()->id, 403);

        $plantsWithoutBed = Plant::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('bed_id')
            ->orderBy('name')
            ->with('category:id,name,slug')
            ->get(['id', 'name', 'image_url', 'category_id', 'quantity'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'image_url' => $p->image_url,
                'quantity' => (int) ($p->quantity ?? 1),
                'category' => $p->category ? ['name' => $p->category->name, 'slug' => $p->category->slug] : null,
            ]);

        $bed->load(['plants' => fn ($q) => $q->select('id', 'name', 'image_url', 'bed_id', 'position_in_bed', 'quantity')]);

        $bedNotes = CalendarNote::query()
            ->where('user_id', $request->user()->id)
            ->where('bed_id', $bed->id)
            ->orderByDesc('note_date')
            ->orderByDesc('id')
            ->limit(40)
            ->get(['id', 'note_date', 'title', 'body', 'type', 'done'])
            ->map(fn ($n) => [
                'id' => $n->id,
                'note_date' => $n->note_date?->format('Y-m-d'),
                'title' => $n->title,
                'body' => $n->body,
                'type' => $n->type,
                'done' => $n->done,
            ]);

        return Inertia::render('map/BedView', [
            'bed' => [
                'id' => $bed->id,
                'garden_plan_id' => $bed->garden_plan_id,
                'name' => $bed->name,
                'location' => $bed->location,
                'image_url' => $bed->image_url,
                'rows' => (int) ($bed->rows ?? 3),
                'columns' => (int) ($bed->columns ?? 3),
                'cell_size_cm' => (int) ($bed->cell_size_cm ?? 30),
                'layout' => $bed->layout,
                'plants' => $bed->plants->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'image_url' => $p->image_url,
                    'position_in_bed' => $p->position_in_bed,
                    'quantity' => (int) ($p->quantity ?? 1),
                ]),
            ],
            'plantsWithoutBed' => $plantsWithoutBed,
            'bedNotes' => $bedNotes,
        ]);
    })->name('beds.show');

    Route::get('beds/{bed}/edit', function (Request $request, Bed $bed) {
        abort_unless($bed->user_id === $request->user()->id, 403);
        $bed->load(['plants' => fn ($q) => $q->select('id', 'name', 'image_url', 'bed_id', 'position_in_bed')]);

        return Inertia::render('map/EditBedPage', [
            'bed' => [
                'id' => $bed->id,
                'garden_plan_id' => $bed->garden_plan_id,
                'name' => $bed->name,
                'location' => $bed->location,
                'image_url' => $bed->image_url,
                'rows' => (int) ($bed->rows ?? 3),
                'columns' => (int) ($bed->columns ?? 3),
                'cell_size_cm' => (int) ($bed->cell_size_cm ?? 30),
                'layout' => $bed->layout,
                'plants' => $bed->plants->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'image_url' => $p->image_url,
                    'position_in_bed' => $p->position_in_bed,
                ]),
            ],
        ]);
    })->name('beds.edit');

    Route::post('beds', [BedController::class, 'store'])->name('beds.store');
    Route::put('beds/{bed}', [BedController::class, 'update'])->name('beds.update');
    Route::delete('beds/{bed}', [BedController::class, 'destroy'])->name('beds.destroy');
    Route::post('garden-objects', [GardenObjectController::class, 'store'])->name('garden-objects.store');
    Route::put('garden-objects/{gardenObject}', [GardenObjectController::class, 'update'])->name('garden-objects.update');
    Route::post('garden-objects/{gardenObject}/duplicate', [GardenObjectController::class, 'duplicate'])->name('garden-objects.duplicate');
    Route::post('garden-objects/{gardenObject}/rotate', [GardenObjectController::class, 'rotate'])->name('garden-objects.rotate');
    Route::delete('garden-objects/{gardenObject}', [GardenObjectController::class, 'destroy'])->name('garden-objects.destroy');
    Route::post('garden-plans', [GardenPlanController::class, 'store'])->name('garden-plans.store');
    Route::put('garden-plans/{gardenPlan}', [GardenPlanController::class, 'update'])->name('garden-plans.update');
    Route::delete('garden-plans/{gardenPlan}', [GardenPlanController::class, 'destroy'])->name('garden-plans.destroy');

    // ✅ SEEDS — ainult resource
    Route::resource('seeds', SeedController::class);
    Route::get('/seeds/category/{slug}', [SeedController::class, 'category'])
        ->name('seeds.category');
    Route::patch('seeds/{seed}/favorite', [SeedController::class, 'toggleFavorite'])
        ->name('seeds.favorite');
    Route::post('/seeds/categories', [SeedCategoryController::class, 'store'])
        ->name('seeds.categories.store');
    Route::patch('/seeds/categories/{category}', [SeedCategoryController::class, 'update'])
        ->name('seeds.categories.update');
    Route::patch('/seeds/categories/{category}/favorite', [SeedCategoryController::class, 'toggleFavorite'])
        ->name('seeds.categories.favorite');
    Route::delete('/seeds/categories/{category}', [SeedCategoryController::class, 'destroy'])
        ->name('seeds.categories.destroy');

    // Calendar
    Route::get('calendar', [CalendarNoteController::class, 'index'])->name('calendar');

    Route::post('/plants/categories', [CategoryController::class, 'store'])
        ->name('categories.store');
    Route::patch('/plants/categories/{category}', [CategoryController::class, 'update'])
        ->name('categories.update');

    Route::patch('/plants/categories/{category}/favorite', [CategoryController::class, 'toggleFavorite'])
        ->name('plants.categories.favorite');

    Route::delete('/plants/categories/{category}', [CategoryController::class, 'destroy'])
        ->name('plants.categories.destroy');

    Route::get('/plants/category/{slug}', [PlantController::class, 'category'])
        ->name('plants.category');

    Route::get('calendar/note-form', [CalendarNoteController::class, 'create'])
        ->name('calendar.noteForm');

    Route::post('calendar/notes', [CalendarNoteController::class, 'store'])
        ->name('calendar.notes.store');

    Route::get('calendar/notes/{note}/edit', [CalendarNoteController::class, 'edit'])
        ->name('calendar.notes.edit');

    Route::put('calendar/notes/{note}', [CalendarNoteController::class, 'update'])
        ->name('calendar.notes.update');

    Route::delete('calendar/notes/{note}', [CalendarNoteController::class, 'destroy'])
        ->name('calendar.notes.destroy');

    Route::post('calendar/notes/{note}/toggle-done', [CalendarNoteController::class, 'toggleDone'])
        ->name('calendar.notes.toggleDone');

    Route::get('calendar/overview', [CalendarNoteController::class, 'overview'])
        ->name('calendar.overview');

    Route::get('calendar/moon', fn () => Inertia::render('calendarNotes/MoonCalendar'))
        ->name('calendar.moon');

    Route::get('overview', fn () => redirect()->route('calendar.overview'))
        ->name('overview');

    // Plants
    Route::get('plants/create', fn () => Inertia::render('AddPlant'))->name('plants.create');
    Route::resource('plants', PlantController::class)->except(['create']);
    Route::patch('/plants/{plant}/favorite', [PlantController::class, 'toggleFavorite'])
        ->name('plants.favorite');
    Route::post('/plants/{plant}/waterings', [PlantController::class, 'water'])
        ->name('plants.water');

});

Route::get('/auth/redirect', function () {
    return Socialite::driver('google')->redirect();
});

Route::get('/auth/callback', function () {
    $googleUser = Socialite::driver('google')->user();

    $user = User::updateOrCreate(
        ['email' => $googleUser->email],
        [
            'google_id' => $googleUser->id,
            'name' => $googleUser->name,
        ]
    );

    Auth::login($user);

    return redirect('/dashboard');
});

require __DIR__.'/settings.php';
