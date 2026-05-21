<?php

use App\Models\Bed;
use App\Models\Category;
use App\Models\GardenPlan;
use App\Models\Plant;
use App\Models\Seed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can create plant from seed and open bed view', function () {
    $user = User::factory()->create();
    $plan = GardenPlan::query()->create([
        'user_id' => $user->id,
        'name' => 'Minu aed',
        'width' => 1200,
        'height' => 800,
        'unit' => 'cm',
    ]);
    $bed = Bed::query()->create([
        'user_id' => $user->id,
        'garden_plan_id' => $plan->id,
        'name' => 'Peenar 1',
        'sort_order' => 1,
    ]);
    $seedCategory = Category::query()->create([
        'user_id' => $user->id,
        'name' => 'Köögiviljad',
        'slug' => 'kogiviljad',
        'scope' => Category::SCOPE_SEED,
    ]);
    $seed = Seed::query()->create([
        'user_id' => $user->id,
        'category_id' => $seedCategory->id,
        'name' => 'Tomat F1',
        'amount' => 1,
        'expires_at' => now()->addYear(),
    ]);

    $this->actingAs($user)
        ->post(route('seeds.plant-from-seed', $seed), ['bed_id' => $bed->id])
        ->assertRedirect(route('beds.show', $bed));

    $plant = Plant::query()->where('seed_id', $seed->id)->first();

    expect($plant)->not->toBeNull()
        ->and($plant->user_id)->toBe($user->id)
        ->and($plant->quantity)->toBe(0)
        ->and($plant->bed_id)->toBeNull()
        ->and($plant->planted_at?->format('Y-m-d'))->toBe(now()->format('Y-m-d'));

    $plantCategory = Category::query()->find($plant->category_id);
    expect($plantCategory)->not->toBeNull()
        ->and($plantCategory->scope)->toBe(Category::SCOPE_PLANT)
        ->and($plantCategory->name)->toBe('Köögiviljad');
});

test('user cannot plant from another users seed', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $plan = GardenPlan::query()->create([
        'user_id' => $owner->id,
        'name' => 'Minu aed',
        'width' => 1200,
        'height' => 800,
        'unit' => 'cm',
    ]);
    $bed = Bed::query()->create([
        'user_id' => $owner->id,
        'garden_plan_id' => $plan->id,
        'name' => 'Peenar',
        'sort_order' => 1,
    ]);
    $seed = Seed::query()->create([
        'user_id' => $owner->id,
        'category_id' => Category::query()->create([
            'user_id' => $owner->id,
            'name' => 'Seemned',
            'slug' => 'seemned',
            'scope' => Category::SCOPE_SEED,
        ])->id,
        'name' => 'Porgand',
        'amount' => 1,
        'expires_at' => now()->addYear(),
    ]);

    $this->actingAs($other)
        ->post(route('seeds.plant-from-seed', $seed), ['bed_id' => $bed->id])
        ->assertForbidden();
});

test('user can assign seed plant to bed cell without quantity', function () {
    $user = User::factory()->create();
    $plan = GardenPlan::query()->create([
        'user_id' => $user->id,
        'name' => 'Minu aed',
        'width' => 1200,
        'height' => 800,
        'unit' => 'cm',
    ]);
    $bed = Bed::query()->create([
        'user_id' => $user->id,
        'garden_plan_id' => $plan->id,
        'name' => 'Peenar',
        'rows' => 2,
        'columns' => 2,
        'layout' => [[1, 1], [1, 1]],
        'sort_order' => 1,
    ]);
    $plant = Plant::query()->create([
        'user_id' => $user->id,
        'name' => 'Tomat',
        'subtitle' => 'Tomat',
        'quantity' => 0,
        'planted_at' => now(),
    ]);

    $this->actingAs($user)
        ->put("/plants/{$plant->id}", [
            'bed_id' => $bed->id,
            'position_in_bed' => '0,0',
        ])
        ->assertRedirect();

    $plant->refresh();
    expect($plant->bed_id)->toBe($bed->id)
        ->and($plant->position_in_bed)->toBe('0,0')
        ->and($plant->quantity)->toBe(0);
});

test('mark germinated updates linked plants and seed notes', function () {
    $user = User::factory()->create();
    $seed = Seed::query()->create([
        'user_id' => $user->id,
        'category_id' => Category::query()->create([
            'user_id' => $user->id,
            'name' => 'Seemned',
            'slug' => 'seemned',
            'scope' => Category::SCOPE_SEED,
        ])->id,
        'name' => 'Basilik',
        'amount' => 1,
        'expires_at' => now()->addYear(),
        'notes' => 'Algne märge',
    ]);
    $plant = Plant::query()->create([
        'user_id' => $user->id,
        'seed_id' => $seed->id,
        'name' => 'Basilik',
        'subtitle' => 'Basilik',
        'quantity' => 0,
        'planted_at' => now(),
    ]);

    $this->actingAs($user)
        ->post(route('seeds.mark-germinated', $seed), ['germinated_count' => 12])
        ->assertRedirect();

    $plant->refresh();
    $seed->refresh();

    expect($plant->quantity)->toBe(12)
        ->and($seed->notes)->toContain('12 tõusis')
        ->and($seed->notes)->toContain('Kasutatud');
});
