<?php
// FILE: routes/web.php

use App\Http\Controllers\CalendarNoteController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PlantController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');

    Route::get('map', fn () => Inertia::render('MapView'))->name('map');
    Route::get('seeds', fn () => Inertia::render('Seeds'))->name('seeds');

    // Calendar notes
    Route::get('calendar', [CalendarNoteController::class, 'index'])->name('calendar');

    Route::post('/plants/categories', [CategoryController::class, 'store'])
        ->name('categories.store');

    Route::patch('/plants/categories/{category}/favorite', [CategoryController::class, 'toggleFavorite'])
        ->name('plants.categories.favorite');

    Route::delete('/plants/categories/{category}', [CategoryController::class, 'destroy'])
        ->name('plants.categories.destroy');

    Route::get('/plants/category/{slug}', [PlantController::class, 'category'])
        ->name('plants.category');

    Route::get('calendar/note-form', fn () => Inertia::render('calendarNotes/NoteForm'))
        ->name('calendar.noteForm');

    Route::post('calendar/notes', [CalendarNoteController::class, 'store'])->name('calendar.notes.store');
    Route::get('calendar/notes/{note}/edit', [CalendarNoteController::class, 'edit'])->name('calendar.notes.edit');
    Route::put('calendar/notes/{note}', [CalendarNoteController::class, 'update'])->name('calendar.notes.update');
    Route::delete('calendar/notes/{note}', [CalendarNoteController::class, 'destroy'])->name('calendar.notes.destroy');
    Route::post('calendar/notes/{note}/toggle-done', [CalendarNoteController::class, 'toggleDone'])
        ->name('calendar.notes.toggleDone');

    Route::get('calendar/overview', [CalendarNoteController::class, 'overview'])
        ->name('calendar.overview');

    Route::get('overview', fn () => redirect()->route('calendar.overview'))
        ->name('overview');

    // Plants (resource + custom actions)
    Route::resource('plants', PlantController::class);

    Route::post('/plants/{plant}/waterings', [PlantController::class, 'water'])->name('plants.water');
});

require __DIR__ . '/settings.php';
