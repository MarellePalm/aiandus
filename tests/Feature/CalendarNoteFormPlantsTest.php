<?php

use App\Models\CalendarNote;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('calendar note form lists one picker row per plant variety', function () {
    $user = User::factory()->create();

    Plant::query()->create([
        'user_id' => $user->id,
        'name' => 'Roos',
        'subtitle' => 'Roos',
        'image_url' => 'https://example.test/old.jpg',
    ]);

    Plant::query()->create([
        'user_id' => $user->id,
        'name' => 'Roos',
        'subtitle' => 'Roos',
        'image_url' => 'https://example.test/new.jpg',
    ]);

    Plant::query()->create([
        'user_id' => $user->id,
        'name' => 'Roos2',
        'subtitle' => 'Roos2',
    ]);

    $this->actingAs($user)
        ->get(route('calendar.noteForm'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calendarNotes/NoteForm')
            ->has('plants', 2)
            ->where('plants.0.label', 'Roos')
            ->where('plants.0.image_url', 'https://example.test/new.jpg')
            ->where('plants.1.label', 'Roos2'));
});

test('calendar note edit maps linked plant to variety representative id', function () {
    $user = User::factory()->create();

    $older = Plant::query()->create([
        'user_id' => $user->id,
        'name' => 'Roos',
        'subtitle' => 'Roos',
    ]);

    $newer = Plant::query()->create([
        'user_id' => $user->id,
        'name' => 'Roos',
        'subtitle' => 'Roos',
    ]);

    $note = CalendarNote::query()->create([
        'user_id' => $user->id,
        'plant_id' => $older->id,
        'note_date' => now()->format('Y-m-d'),
        'title' => 'Test',
        'body' => '',
        'type' => 'note',
        'done' => null,
    ]);

    $this->actingAs($user)
        ->get(route('calendar.notes.edit', $note))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calendarNotes/NoteForm')
            ->has('plants', 1)
            ->where('note.plant_id', $newer->id));
});
