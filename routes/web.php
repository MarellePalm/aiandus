<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use App\Http\Controllers\PlantController;
use App\Http\Controllers\CalendarNoteController;


Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');




Route::middleware(['auth'])->group(function () {

    Route::get('/plants/create', [PlantController::class, 'create'])->name('plants.create');
    Route::post('/plants', [PlantController::class, 'store'])->name('plants.store');

    Route::get('/plants/{plant}', [PlantController::class, 'show'])->name('plants.show');
    
    Route::post('/plants/{plant}/waterings', [PlantController::class, 'water'])->name('plants.water');

    Route::get('/plants/category/{slug}', [PlantController::class, 'category'])
    ->name('plants.category');


});

Route::get('map', function () {
    return Inertia::render('MapView');
})->middleware(['auth', 'verified'])->name('map');

// Calendar notes
Route::get('calendar', [CalendarNoteController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('calendar');

Route::get('calendar/note-form', function () {
    return Inertia::render('NoteForm');
})->middleware(['auth', 'verified'])->name('calendar.noteForm');

Route::post('calendar/notes', [CalendarNoteController::class, 'store'])
    ->middleware(['auth', 'verified'])
    ->name('calendar.notes.store');

Route::get('calendar/notes/{note}/edit', [CalendarNoteController::class, 'edit'])
    ->middleware(['auth', 'verified'])
    ->name('calendar.notes.edit');

Route::put('calendar/notes/{note}', [CalendarNoteController::class, 'update'])
    ->middleware(['auth', 'verified'])
    ->name('calendar.notes.update');

Route::delete('calendar/notes/{note}', [CalendarNoteController::class, 'destroy'])
    ->middleware(['auth', 'verified'])
    ->name('calendar.notes.destroy');

Route::post('calendar/notes/{note}/toggle-done', [CalendarNoteController::class, 'toggleDone'])
    ->middleware(['auth', 'verified'])
    ->name('calendar.notes.toggleDone');

Route::resource('plants', PlantController::class);


require __DIR__.'/settings.php';
