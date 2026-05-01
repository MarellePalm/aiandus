<?php

use App\Models\Bed;
use App\Models\CalendarNote;
use App\Models\Category;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest is redirected from plants create page', function () {
    $this->get(route('plants.create'))
        ->assertRedirect(route('login'));
});

test('authenticated user can create a bed', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('beds.store'), [
        'name' => 'Tagaaia peenar',
        'location' => 'Kasvuhoone taga',
        'layout' => [
            [1, 1, 0],
            [1, -1, 1],
        ],
    ]);

    $bed = Bed::query()->where('user_id', $user->id)->first();

    expect($bed)->not()->toBeNull();
    expect($bed->name)->toBe('Tagaaia peenar');
    expect($bed->rows)->toBe(2);
    expect($bed->columns)->toBe(3);
    expect($bed->garden_x)->toBeGreaterThanOrEqual(0);
    expect($bed->garden_y)->toBeGreaterThanOrEqual(0);
    expect($bed->layout)->toBe([
        [1, 1, 0],
        [1, -1, 1],
    ]);

    $response->assertRedirect(route('beds.show', $bed));
});

test('user can update bed garden position', function () {
    $user = User::factory()->create();

    $bed = Bed::query()->create([
        'user_id' => $user->id,
        'name' => 'Plaanitav peenar',
        'location' => 'Aia keskel',
        'sort_order' => 1,
        'garden_x' => 48,
        'garden_y' => 48,
    ]);

    $this->actingAs($user)
        ->put(route('beds.update', $bed), [
            'garden_x' => 220,
            'garden_y' => 140,
        ])
        ->assertRedirect();

    expect($bed->fresh()->garden_x)->toBe(220);
    expect($bed->fresh()->garden_y)->toBe(140);
});

test('bed creation requires at least one active cell', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('beds.store'), [
        'name' => 'Tühi peenar',
        'layout' => [
            [-1, -1],
            [-1, -1],
        ],
    ])->assertSessionHasErrors('layout');

    expect(Bed::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

test('authenticated user can create a bed from coordinate cells payload', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('beds.store'), [
        'name' => 'L-kujuline peenar',
        'cells' => [
            ['x' => 0, 'y' => 0, 'plants' => []],
            ['x' => 1, 'y' => 0, 'plants' => []],
            ['x' => 0, 'y' => 1, 'plants' => []],
        ],
    ]);

    $bed = Bed::query()->where('user_id', $user->id)->latest('id')->first();

    expect($bed)->not()->toBeNull();
    expect($bed->rows)->toBe(2);
    expect($bed->columns)->toBe(2);
    expect($bed->layout)->toBe([
        [1, 1],
        [1, -1],
    ]);

    $response->assertRedirect(route('beds.show', $bed));
});

test('authenticated user can create a plant in own category', function () {
    $user = User::factory()->create();
    $category = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Tomatid',
        'slug' => 'tomatid',
        'scope' => Category::SCOPE_PLANT,
        'count' => 0,
        'is_favorite' => false,
    ]);

    $response = $this->actingAs($user)->post(route('plants.store'), [
        'category_id' => $category->id,
        'subtitle' => 'Marmande',
        'planted_at' => '2026-04-10',
        'watering_frequency' => '2x nädalas',
        'fertilizing_frequency' => '1x kuus',
        'notes' => 'Kasvab hästi.',
        'quantity' => 3,
    ]);

    $plant = Plant::query()
        ->where('user_id', $user->id)
        ->where('category_id', $category->id)
        ->first();

    expect($plant)->not()->toBeNull();
    expect($plant->name)->toBe('Marmande');
    expect($plant->subtitle)->toBe('Marmande');
    expect($plant->quantity)->toBe(3);

    $response->assertRedirect(route('plants.category', ['slug' => $category->slug]));
});

test('user cannot create plant in another users category', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $otherUsersCategory = Category::query()->create([
        'user_id' => $otherUser->id,
        'name' => 'Paprikad',
        'slug' => 'paprikad',
        'scope' => Category::SCOPE_PLANT,
        'count' => 0,
        'is_favorite' => false,
    ]);

    $this->actingAs($user)
        ->post(route('plants.store'), [
            'category_id' => $otherUsersCategory->id,
            'subtitle' => 'Unauthorized taim',
            'planted_at' => '2026-04-10',
        ])
        ->assertSessionHasErrors('category_id');
});

test('calendar note only links to users own bed and plant', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $usersBed = Bed::query()->create([
        'user_id' => $user->id,
        'name' => 'Minu peenar',
        'location' => null,
        'sort_order' => 1,
    ]);

    $otherUsersBed = Bed::query()->create([
        'user_id' => $otherUser->id,
        'name' => 'Võõras peenar',
        'location' => null,
        'sort_order' => 1,
    ]);

    $usersPlant = Plant::query()->create([
        'user_id' => $user->id,
        'name' => 'Minu taim',
        'subtitle' => 'Minu taim',
    ]);

    $otherUsersPlant = Plant::query()->create([
        'user_id' => $otherUser->id,
        'name' => 'Võõras taim',
        'subtitle' => 'Võõras taim',
    ]);

    $this->actingAs($user)->post(route('calendar.notes.store'), [
        'note_date' => '2026-04-20',
        'title' => 'Kastmine',
        'body' => 'Kastsin tomateid.',
        'bed_id' => $otherUsersBed->id,
        'plant_id' => $otherUsersPlant->id,
    ])->assertRedirect(route('calendar', [
        'month' => 4,
        'year' => 2026,
    ]));

    $note = CalendarNote::query()->latest('id')->first();
    expect($note)->not()->toBeNull();
    expect($note->user_id)->toBe($user->id);
    expect($note->bed_id)->toBeNull();
    expect($note->plant_id)->toBeNull();

    $this->actingAs($user)->post(route('calendar.notes.store'), [
        'note_date' => '2026-04-21',
        'title' => 'Rohimine',
        'body' => 'Rohisin peenart.',
        'bed_id' => $usersBed->id,
        'plant_id' => $usersPlant->id,
    ]);

    $secondNote = CalendarNote::query()->latest('id')->first();
    expect($secondNote->bed_id)->toBe($usersBed->id);
    expect($secondNote->plant_id)->toBe($usersPlant->id);
});

test('user cannot view another users bed page', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $otherUsersBed = Bed::query()->create([
        'user_id' => $otherUser->id,
        'name' => 'Privaatne peenar',
        'location' => null,
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->get(route('beds.show', $otherUsersBed))
        ->assertForbidden();
});

test('user can delete own bed and plants are unlinked', function () {
    $user = User::factory()->create();

    $bed = Bed::query()->create([
        'user_id' => $user->id,
        'name' => 'Kustutatav peenar',
        'location' => null,
        'sort_order' => 1,
    ]);

    $plant = Plant::query()->create([
        'user_id' => $user->id,
        'bed_id' => $bed->id,
        'position_in_bed' => '0,0',
        'name' => 'Petersell',
        'subtitle' => 'Petersell',
    ]);

    $this->actingAs($user)
        ->delete(route('beds.destroy', $bed))
        ->assertSessionHas('success', 'Peenar eemaldatud.');

    expect(Bed::query()->whereKey($bed->id)->exists())->toBeFalse();
    expect($plant->fresh()->bed_id)->toBeNull();
    expect($plant->fresh()->position_in_bed)->toBeNull();
});

test('user cannot assign plant to another users bed', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $usersPlant = Plant::query()->create([
        'user_id' => $user->id,
        'name' => 'Basiilik',
        'subtitle' => 'Basiilik',
    ]);

    $otherUsersBed = Bed::query()->create([
        'user_id' => $otherUser->id,
        'name' => 'Võõras peenar',
        'location' => null,
        'sort_order' => 1,
    ]);

    $this->actingAs($user)
        ->put(route('plants.update', $usersPlant), [
            'bed_id' => $otherUsersBed->id,
        ])
        ->assertForbidden();
});

test('user cannot update bed layout to remove planted cell', function () {
    $user = User::factory()->create();

    $bed = Bed::query()->create([
        'user_id' => $user->id,
        'name' => 'Peenar taimega',
        'location' => null,
        'sort_order' => 1,
        'rows' => 2,
        'columns' => 2,
        'layout' => [
            [1, 1],
            [1, 1],
        ],
    ]);

    Plant::query()->create([
        'user_id' => $user->id,
        'bed_id' => $bed->id,
        'position_in_bed' => '0,0',
        'name' => 'Tomat',
        'subtitle' => 'Tomat',
    ]);

    $this->actingAs($user)
        ->put(route('beds.update', $bed), [
            'layout' => [
                [-1, 1],
                [1, 1],
            ],
        ])
        ->assertSessionHasErrors('layout');

    expect($bed->fresh()->layout)->toBe([
        [1, 1],
        [1, 1],
    ]);
});

test('user can update bed with cells payload and plant position is preserved', function () {
    $user = User::factory()->create();

    $bed = Bed::query()->create([
        'user_id' => $user->id,
        'name' => 'Muudetav peenar',
        'location' => null,
        'sort_order' => 1,
        'rows' => 1,
        'columns' => 2,
        'layout' => [
            [1, 1],
        ],
    ]);

    $plant = Plant::query()->create([
        'user_id' => $user->id,
        'bed_id' => $bed->id,
        'position_in_bed' => '0,1',
        'name' => 'Basiilik',
        'subtitle' => 'Basiilik',
    ]);

    $this->actingAs($user)
        ->put(route('beds.update', $bed), [
            'cells' => [
                ['x' => 0, 'y' => 0, 'plants' => []],
                ['x' => 1, 'y' => 0, 'plants' => [['plant_id' => $plant->id, 'quantity' => 1, 'size' => null, 'note' => null]]],
                ['x' => 2, 'y' => 0, 'plants' => []],
            ],
        ])
        ->assertSessionHasNoErrors();

    $bed->refresh();
    $plant->refresh();

    expect($bed->layout)->toBe([
        [1, 1, 1],
    ]);
    expect($plant->position_in_bed)->toBe('0,1');
});

test('user can update own calendar note', function () {
    $user = User::factory()->create();

    $note = CalendarNote::query()->create([
        'user_id' => $user->id,
        'note_date' => '2026-04-10',
        'title' => 'Algne pealkiri',
        'body' => 'Algne sisu',
        'type' => 'note',
        'done' => null,
    ]);

    $this->actingAs($user)->put(route('calendar.notes.update', $note), [
        'note_date' => '2026-04-11',
        'title' => 'Uus pealkiri',
        'body' => 'Uus sisu',
    ])->assertRedirect(route('calendar', [
        'month' => 4,
        'year' => 2026,
    ]));

    $note->refresh();
    expect($note->note_date->format('Y-m-d'))->toBe('2026-04-11');
    expect($note->title)->toBe('Uus pealkiri');
    expect($note->body)->toBe('Uus sisu');
});

test('user can toggle calendar note done status', function () {
    $user = User::factory()->create();

    $note = CalendarNote::query()->create([
        'user_id' => $user->id,
        'note_date' => '2026-04-12',
        'title' => 'Kastmine',
        'body' => 'Kasta peenar',
        'type' => 'note',
        'done' => null,
    ]);

    $this->actingAs($user)
        ->post(route('calendar.notes.toggleDone', $note))
        ->assertRedirect();

    expect($note->fresh()->done)->toBeTrue();
});

test('map view returns only users beds and unassigned plants', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $usersBed = Bed::query()->create([
        'user_id' => $user->id,
        'name' => 'Minu map peenar',
        'location' => 'Tagaaed',
        'sort_order' => 1,
        'rows' => 3,
        'columns' => 3,
    ]);

    $otherUsersBed = Bed::query()->create([
        'user_id' => $otherUser->id,
        'name' => 'Võõras map peenar',
        'location' => 'Eeshoov',
        'sort_order' => 1,
        'rows' => 3,
        'columns' => 3,
    ]);

    $usersUnassignedPlant = Plant::query()->create([
        'user_id' => $user->id,
        'name' => 'Minu sidumata taim',
        'subtitle' => 'Minu sidumata taim',
        'bed_id' => null,
    ]);

    Plant::query()->create([
        'user_id' => $otherUser->id,
        'name' => 'Võõras sidumata taim',
        'subtitle' => 'Võõras sidumata taim',
        'bed_id' => null,
    ]);

    $response = $this->actingAs($user)->get(route('map'));
    $response->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('map/MapView')
        ->where('beds.0.id', $usersBed->id)
        ->missing('beds.1')
        ->where('plantsWithoutBed.0.id', $usersUnassignedPlant->id)
        ->missing('plantsWithoutBed.1')
    );

    expect($otherUsersBed->user_id)->toBe($otherUser->id);
});
