<?php

use App\Models\CalendarNote;
use App\Models\Category;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('plant show includes calendar notes linked to same variety', function () {
    $user = User::factory()->create();

    $category = Category::query()->create([
        'user_id' => $user->id,
        'scope' => Category::SCOPE_PLANT,
        'name' => 'Lilled',
        'slug' => 'lilled',
        'count' => 0,
        'is_favorite' => false,
    ]);

    $p1 = Plant::query()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'name' => 'Roos',
        'subtitle' => 'Roos',
    ]);

    $p2 = Plant::query()->create([
        'user_id' => $user->id,
        'category_id' => $category->id,
        'name' => 'Roos',
        'subtitle' => 'Roos',
    ]);

    $note = CalendarNote::query()->create([
        'user_id' => $user->id,
        'plant_id' => $p2->id,
        'note_date' => now()->format('Y-m-d'),
        'title' => 'Kasta',
        'body' => 'Vajab vett',
        'type' => 'note',
        'done' => null,
    ]);

    $this->actingAs($user)
        ->get(route('plants.show', $p1))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Plants/Show')
            ->has('plant.calendar_notes', 1)
            ->where('plant.calendar_notes.0.id', $note->id)
            ->where('plant.calendar_notes.0.title', 'Kasta'));
});
