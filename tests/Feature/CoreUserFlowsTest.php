<?php

use App\Models\Bed;
use App\Models\CalendarNote;
use App\Models\Category;
use App\Models\GardenObject;
use App\Models\GardenPlan;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeGardenPlan(User $user, array $overrides = []): GardenPlan
{
    return GardenPlan::query()->create(array_merge([
        'user_id' => $user->id,
        'name' => 'Minu aed',
        'width' => 1200,
        'height' => 800,
        'unit' => 'cm',
    ], $overrides));
}

test('guest is redirected from plants create page', function () {
    $this->get(route('plants.create'))
        ->assertRedirect(route('login'));
});

test('dashboard returns action summary for authenticated user', function () {
    $user = User::factory()->create();
    $plan = makeGardenPlan($user);

    $filledBed = Bed::query()->create([
        'user_id' => $user->id,
        'garden_plan_id' => $plan->id,
        'name' => 'Tomatipeenar',
        'location' => null,
        'sort_order' => 1,
    ]);

    Bed::query()->create([
        'user_id' => $user->id,
        'garden_plan_id' => $plan->id,
        'name' => 'Tühi peenar',
        'location' => null,
        'sort_order' => 2,
    ]);

    Plant::query()->create([
        'user_id' => $user->id,
        'bed_id' => $filledBed->id,
        'position_in_bed' => '0,0',
        'name' => 'Tomat',
        'subtitle' => 'Tomat',
    ]);

    Plant::query()->create([
        'user_id' => $user->id,
        'name' => 'Salat',
        'subtitle' => 'Salat',
    ]);

    CalendarNote::query()->create([
        'user_id' => $user->id,
        'note_date' => now()->format('Y-m-d'),
        'title' => 'Kasta tomatid',
        'body' => 'Kontrolli kasvuhoone peenart ja kasta tomatid.',
        'type' => 'task',
        'done' => false,
    ]);

    CalendarNote::query()->create([
        'user_id' => $user->id,
        'note_date' => now()->subDay()->format('Y-m-d'),
        'title' => 'Rohi peenar läbi',
        'body' => 'Eemalda umbrohi tühjast peenrast.',
        'type' => 'task',
        'done' => false,
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('dashboardSummary.bedsCount', 2)
            ->where('dashboardSummary.plantsCount', 2)
            ->where('dashboardSummary.emptyBedsCount', 1)
            ->where('dashboardSummary.plantsWithoutBedCount', 1)
            ->where('dashboardSummary.todayTasksCount', 1)
            ->where('dashboardSummary.overdueTasksCount', 1)
            ->where('todayTasks.0.title', 'Kasta tomatid'));
});

test('authenticated user can create a bed', function () {
    $user = User::factory()->create();
    $plan = makeGardenPlan($user);

    $response = $this->actingAs($user)->post(route('beds.store'), [
        'garden_plan_id' => $plan->id,
        'name' => 'Tagaaia peenar',
        'location' => 'Kasvuhoone taga',
        'cell_size_cm' => 50,
        'layout' => [
            [1, 1, 0],
            [1, -1, 1],
        ],
    ]);

    $bed = Bed::query()->where('user_id', $user->id)->first();

    expect($bed)->not()->toBeNull();
    expect($bed->garden_plan_id)->toBe($plan->id);
    expect($bed->name)->toBe('Tagaaia peenar');
    expect($bed->rows)->toBe(2);
    expect($bed->columns)->toBe(3);
    expect($bed->garden_x)->toBeGreaterThanOrEqual(0);
    expect($bed->garden_y)->toBeGreaterThanOrEqual(0);
    expect($bed->cell_size_cm)->toBe(50);
    expect($bed->layout)->toBe([
        [1, 1, 0],
        [1, -1, 1],
    ]);

    $response
        ->assertRedirect(
            route('map.show', [
                'gardenPlan' => $plan->id,
                'bed' => $bed->id,
            ]),
        )
        ->assertSessionHas(
            'success',
            'Peenar lisatud. Lohista see aiaplaanil õigesse kohta.',
        );
});

test('user can update bed garden position', function () {
    $user = User::factory()->create();
    $plan = makeGardenPlan($user);

    $bed = Bed::query()->create([
        'user_id' => $user->id,
        'garden_plan_id' => $plan->id,
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

test('user can update bed cell size', function () {
    $user = User::factory()->create();
    $plan = makeGardenPlan($user);

    $bed = Bed::query()->create([
        'user_id' => $user->id,
        'garden_plan_id' => $plan->id,
        'name' => 'Mõõtkavaga peenar',
        'location' => 'Aianurk',
        'sort_order' => 1,
        'cell_size_cm' => 30,
    ]);

    $this->actingAs($user)
        ->put(route('beds.update', $bed), [
            'cell_size_cm' => 50,
        ])
        ->assertRedirect();

    expect($bed->fresh()->cell_size_cm)->toBe(50);
});

test('bed creation requires at least one active cell', function () {
    $user = User::factory()->create();
    $plan = makeGardenPlan($user);

    $this->actingAs($user)->post(route('beds.store'), [
        'garden_plan_id' => $plan->id,
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
    $plan = makeGardenPlan($user);

    $response = $this->actingAs($user)->post(route('beds.store'), [
        'garden_plan_id' => $plan->id,
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

    $response->assertRedirect(
        route('map.show', [
            'gardenPlan' => $plan->id,
            'bed' => $bed->id,
        ]),
    );
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
    $userPlan = makeGardenPlan($user);
    $otherPlan = makeGardenPlan($otherUser);

    $usersBed = Bed::query()->create([
        'user_id' => $user->id,
        'garden_plan_id' => $userPlan->id,
        'name' => 'Minu peenar',
        'location' => null,
        'sort_order' => 1,
    ]);

    $otherUsersBed = Bed::query()->create([
        'user_id' => $otherUser->id,
        'garden_plan_id' => $otherPlan->id,
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
    $otherPlan = makeGardenPlan($otherUser);

    $otherUsersBed = Bed::query()->create([
        'user_id' => $otherUser->id,
        'garden_plan_id' => $otherPlan->id,
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
    $plan = makeGardenPlan($user);

    $bed = Bed::query()->create([
        'user_id' => $user->id,
        'garden_plan_id' => $plan->id,
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
    $otherPlan = makeGardenPlan($otherUser);

    $usersPlant = Plant::query()->create([
        'user_id' => $user->id,
        'name' => 'Basiilik',
        'subtitle' => 'Basiilik',
    ]);

    $otherUsersBed = Bed::query()->create([
        'user_id' => $otherUser->id,
        'garden_plan_id' => $otherPlan->id,
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

test('user can assign plant to bed with quantity', function () {
    $user = User::factory()->create();
    $plan = makeGardenPlan($user);

    $bed = Bed::query()->create([
        'user_id' => $user->id,
        'garden_plan_id' => $plan->id,
        'name' => 'Kogusega peenar',
        'location' => null,
        'sort_order' => 1,
    ]);

    $plant = Plant::query()->create([
        'user_id' => $user->id,
        'name' => 'Redis',
        'subtitle' => 'Redis',
        'quantity' => 1,
    ]);

    $this->actingAs($user)
        ->put(route('plants.update', $plant), [
            'bed_id' => $bed->id,
            'position_in_bed' => '0,0',
            'quantity' => 8,
        ])
        ->assertSessionHas('success', 'Taim peenrale määratud.');

    expect($plant->fresh()->bed_id)->toBe($bed->id);
    expect($plant->fresh()->position_in_bed)->toBe('0,0');
    expect($plant->fresh()->quantity)->toBe(8);
});

test('user cannot update bed layout to remove planted cell', function () {
    $user = User::factory()->create();
    $plan = makeGardenPlan($user);

    $bed = Bed::query()->create([
        'user_id' => $user->id,
        'garden_plan_id' => $plan->id,
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
    $plan = makeGardenPlan($user);

    $bed = Bed::query()->create([
        'user_id' => $user->id,
        'garden_plan_id' => $plan->id,
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

test('user can view own calendar note detail', function () {
    $user = User::factory()->create();

    $note = CalendarNote::query()->create([
        'user_id' => $user->id,
        'note_date' => '2026-05-06',
        'title' => 'Testmärge',
        'body' => 'Sisu ridadel.',
        'type' => 'note',
        'done' => null,
    ]);

    $this->actingAs($user)
        ->get(route('calendar.notes.show', $note))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('calendarNotes/NoteShow')
            ->where('note.id', $note->id)
            ->where('note.title', 'Testmärge')
            ->where('note.body', 'Sisu ridadel.'));
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
    $plan = GardenPlan::query()->create([
        'user_id' => $user->id,
        'name' => 'Minu aed',
        'width' => 1200,
        'height' => 800,
        'unit' => 'cm',
    ]);
    $otherPlan = GardenPlan::query()->create([
        'user_id' => $otherUser->id,
        'name' => 'Võõras aed',
        'width' => 1400,
        'height' => 900,
        'unit' => 'cm',
    ]);

    $usersBed = Bed::query()->create([
        'user_id' => $user->id,
        'garden_plan_id' => $plan->id,
        'name' => 'Minu map peenar',
        'location' => 'Tagaaed',
        'sort_order' => 1,
        'rows' => 3,
        'columns' => 3,
    ]);

    $otherUsersBed = Bed::query()->create([
        'user_id' => $otherUser->id,
        'garden_plan_id' => $otherPlan->id,
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

    $usersObject = GardenObject::query()->create([
        'garden_plan_id' => $plan->id,
        'type' => 'greenhouse',
        'name' => 'Minu kasvuhoone',
        'x' => 120,
        'y' => 80,
        'width' => 300,
        'height' => 200,
    ]);

    GardenObject::query()->create([
        'garden_plan_id' => $otherPlan->id,
        'type' => 'pond',
        'name' => 'Võõras tiik',
        'x' => 90,
        'y' => 90,
        'width' => 250,
        'height' => 180,
    ]);

    $this->actingAs($user)
        ->get(route('map'))
        ->assertRedirect(route('map.show', $plan));

    $response = $this->actingAs($user)->get(route('map.show', $plan));
    $response->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('map/MapView')
        ->has('gardenPlans')
        ->has('gardenPlan')
        ->where('beds.0.id', $usersBed->id)
        ->where('beds.0.cell_size_cm', 30)
        ->missing('beds.1')
        ->where('gardenObjects.0.id', $usersObject->id)
        ->where('gardenObjects.0.type', 'greenhouse')
        ->missing('gardenObjects.1')
        ->where('plantsWithoutBed.0.id', $usersUnassignedPlant->id)
        ->missing('plantsWithoutBed.1')
    );

    expect($otherUsersBed->user_id)->toBe($otherUser->id);
    expect(GardenPlan::query()->where('user_id', $user->id)->exists())->toBeTrue();
});

test('user can update garden plan dimensions', function () {
    $user = User::factory()->create();
    $plan = makeGardenPlan($user);

    $this->actingAs($user)
        ->put(route('garden-plans.update', $plan), [
            'name' => 'Tagaaed',
            'width' => 100000,
            'height' => 50000,
        ])
        ->assertSessionHas('success', 'Aia mõõdud uuendatud.');

    $plan->refresh();
    expect($plan->name)->toBe('Tagaaed');
    expect($plan->width)->toBe(100000);
    expect($plan->height)->toBe(50000);
});

test('user can save small balcony-sized garden plan dimensions', function () {
    $user = User::factory()->create();
    $plan = makeGardenPlan($user);

    $this->actingAs($user)
        ->put(route('garden-plans.update', $plan), [
            'name' => 'Laagri rõdu',
            'width' => 285,
            'height' => 150,
        ])
        ->assertSessionHas('success', 'Aia mõõdud uuendatud.');

    $plan->refresh();
    expect($plan->name)->toBe('Laagri rõdu');
    expect($plan->width)->toBe(285);
    expect($plan->height)->toBe(150);
});

test('garden plan dimensions reject zero width', function () {
    $user = User::factory()->create();
    $plan = makeGardenPlan($user);

    $this->actingAs($user)
        ->put(route('garden-plans.update', $plan), [
            'name' => 'Test',
            'width' => 0,
            'height' => 100,
        ])
        ->assertSessionHasErrors('width');
});

test('user cannot delete their only garden plan', function () {
    $user = User::factory()->create();
    $plan = makeGardenPlan($user);

    $this->actingAs($user)
        ->from(route('map.show', $plan))
        ->delete(route('garden-plans.destroy', $plan))
        ->assertRedirect(route('map.show', $plan))
        ->assertSessionHasErrors('garden_plan');

    expect(GardenPlan::query()->whereKey($plan->id)->exists())->toBeTrue();
});

test('user can delete a secondary garden plan and objects are removed', function () {
    $user = User::factory()->create();
    $planA = makeGardenPlan($user, ['name' => 'A']);
    $planB = makeGardenPlan($user, ['name' => 'B']);

    $object = GardenObject::query()->create([
        'garden_plan_id' => $planB->id,
        'type' => 'shed',
        'name' => 'Kuur',
        'x' => 100,
        'y' => 100,
        'width' => 200,
        'height' => 150,
    ]);

    $this->actingAs($user)
        ->delete(route('garden-plans.destroy', $planB))
        ->assertRedirect(route('map.show', $planA))
        ->assertSessionHas('success', 'Aiaplaan ja selle peenrad kustutatud.');

    expect(GardenPlan::query()->whereKey($planB->id)->exists())->toBeFalse();
    expect(GardenPlan::query()->whereKey($planA->id)->exists())->toBeTrue();
    expect(GardenObject::query()->whereKey($object->id)->exists())->toBeFalse();

    $this->actingAs($user)->get(route('map'))->assertRedirect(route('map.show', $planA));
});

test('garden object type other requires name on create', function () {
    $user = User::factory()->create();
    $plan = GardenPlan::query()->create([
        'user_id' => $user->id,
        'name' => 'Minu aed',
        'width' => 1200,
        'height' => 800,
        'unit' => 'cm',
    ]);

    $this->actingAs($user)
        ->post(route('garden-objects.store'), [
            'garden_plan_id' => $plan->id,
            'type' => 'other',
            'name' => '',
            'x' => 100,
            'y' => 100,
            'width' => 200,
            'height' => 150,
        ])
        ->assertSessionHasErrors('name');

    $this->actingAs($user)
        ->post(route('garden-objects.store'), [
            'garden_plan_id' => $plan->id,
            'type' => 'other',
            'name' => 'Puuriit',
            'x' => 100,
            'y' => 100,
            'width' => 200,
            'height' => 150,
        ])
        ->assertSessionHas('success', 'Aiaelement lisatud.');

    $object = GardenObject::query()->where('garden_plan_id', $plan->id)->first();
    expect($object)->not()->toBeNull();
    expect($object->type)->toBe('other');
    expect($object->name)->toBe('Puuriit');
});

test('user can create and move a garden object', function () {
    $user = User::factory()->create();
    $plan = GardenPlan::query()->create([
        'user_id' => $user->id,
        'name' => 'Minu aed',
        'width' => 1200,
        'height' => 800,
        'unit' => 'cm',
    ]);

    $this->actingAs($user)
        ->post(route('garden-objects.store'), [
            'garden_plan_id' => $plan->id,
            'type' => 'greenhouse',
            'name' => 'Uus kasvuhoone',
            'x' => 150,
            'y' => 120,
            'width' => 300,
            'height' => 200,
        ])
        ->assertSessionHas('success', 'Aiaelement lisatud.');

    $object = GardenObject::query()->where('garden_plan_id', $plan->id)->first();
    expect($object)->not()->toBeNull();
    expect($object->type)->toBe('greenhouse');
    expect($object->name)->toBe('Uus kasvuhoone');

    $this->actingAs($user)
        ->put(route('garden-objects.update', $object), [
            'x' => 240,
            'y' => 180,
        ])
        ->assertSessionHas('success', 'Aiaelement uuendatud.');

    expect($object->fresh()->x)->toBe(240);
    expect($object->fresh()->y)->toBe(180);
});

test('user can create shed and compost garden objects', function () {
    $user = User::factory()->create();
    $plan = GardenPlan::query()->create([
        'user_id' => $user->id,
        'name' => 'Minu aed',
        'width' => 1200,
        'height' => 800,
        'unit' => 'cm',
    ]);

    $this->actingAs($user)
        ->post(route('garden-objects.store'), [
            'garden_plan_id' => $plan->id,
            'type' => 'shed',
            'name' => 'Aiakuur',
            'x' => 200,
            'y' => 120,
            'width' => 220,
            'height' => 180,
        ])
        ->assertSessionHas('success', 'Aiaelement lisatud.');

    $this->actingAs($user)
        ->post(route('garden-objects.store'), [
            'garden_plan_id' => $plan->id,
            'type' => 'compost',
            'name' => 'Kompostikast',
            'x' => 320,
            'y' => 200,
            'width' => 140,
            'height' => 140,
        ])
        ->assertSessionHas('success', 'Aiaelement lisatud.');

    expect(GardenObject::query()->where('garden_plan_id', $plan->id)->where('type', 'shed')->exists())->toBeTrue();
    expect(GardenObject::query()->where('garden_plan_id', $plan->id)->where('type', 'compost')->exists())->toBeTrue();
});

test('user can duplicate a garden object', function () {
    $user = User::factory()->create();
    $plan = GardenPlan::query()->create([
        'user_id' => $user->id,
        'name' => 'Minu aed',
        'width' => 1200,
        'height' => 800,
        'unit' => 'cm',
    ]);

    $object = GardenObject::query()->create([
        'garden_plan_id' => $plan->id,
        'type' => 'greenhouse',
        'name' => 'Põhikasvuhoone',
        'x' => 160,
        'y' => 120,
        'width' => 300,
        'height' => 200,
    ]);

    $this->actingAs($user)
        ->post(route('garden-objects.duplicate', $object))
        ->assertSessionHas('success', 'Aiaelement dubleeritud.');

    $copies = GardenObject::query()
        ->where('garden_plan_id', $plan->id)
        ->orderBy('id')
        ->get();

    expect($copies)->toHaveCount(2);
    expect($copies[1]->name)->toBe('Põhikasvuhoone koopia');
    expect($copies[1]->x)->toBe(205);
    expect($copies[1]->y)->toBe(165);
    expect($copies[1]->width)->toBe(300);
    expect($copies[1]->height)->toBe(200);
});

test('user can rotate a garden object', function () {
    $user = User::factory()->create();
    $plan = GardenPlan::query()->create([
        'user_id' => $user->id,
        'name' => 'Minu aed',
        'width' => 1200,
        'height' => 800,
        'unit' => 'cm',
    ]);

    $object = GardenObject::query()->create([
        'garden_plan_id' => $plan->id,
        'type' => 'shed',
        'name' => 'Aiakuur',
        'x' => 220,
        'y' => 140,
        'width' => 220,
        'height' => 180,
        'meta' => ['rotation' => 0],
    ]);

    $this->actingAs($user)
        ->post(route('garden-objects.rotate', $object))
        ->assertSessionHas('success', 'Aiaelement pööratud.');

    $object->refresh();

    expect($object->width)->toBe(180);
    expect($object->height)->toBe(220);
    expect($object->meta['rotation'])->toBe(90);
});
