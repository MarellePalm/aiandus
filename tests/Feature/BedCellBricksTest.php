<?php

use App\Models\Bed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can save bed with multi cell bricks', function () {
    $user = User::factory()->create();
    $plan = makeGardenPlan($user);

    $this->actingAs($user)
        ->post(route('beds.store'), [
            'garden_plan_id' => $plan->id,
            'name' => 'Suur peenar',
            'cell_size_cm' => 30,
            'cell_bricks' => [
                [
                    'x' => 0,
                    'y' => 0,
                    'w' => 2,
                    'h' => 1,
                    'width_cm' => 60,
                    'height_cm' => 30,
                    'kind' => 'plantable',
                ],
                [
                    'x' => 0,
                    'y' => 1,
                    'w' => 1,
                    'h' => 1,
                    'width_cm' => 30,
                    'height_cm' => 30,
                    'kind' => 'plantable',
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    $bed = Bed::query()->where('user_id', $user->id)->first();

    expect($bed)->not()->toBeNull();
    expect($bed->layout)->toBe([
        [1, 1],
        [1, 0],
    ]);
    expect($bed->cell_bricks)->toBe([
        [
            'x' => 0,
            'y' => 0,
            'w' => 2,
            'h' => 1,
            'width_cm' => 60,
            'height_cm' => 30,
            'kind' => 'plantable',
        ],
        [
            'x' => 0,
            'y' => 1,
            'w' => 1,
            'h' => 1,
            'width_cm' => 30,
            'height_cm' => 30,
            'kind' => 'plantable',
        ],
    ]);
});

test('user can save bed brick up to three meters wide', function () {
    $user = User::factory()->create();
    $plan = makeGardenPlan($user);

    $this->actingAs($user)
        ->post(route('beds.store'), [
            'garden_plan_id' => $plan->id,
            'name' => 'Õunapuu',
            'cell_size_cm' => 30,
            'cell_bricks' => [
                [
                    'x' => 0,
                    'y' => 0,
                    'w' => 10,
                    'h' => 10,
                    'width_cm' => 300,
                    'height_cm' => 300,
                    'kind' => 'plantable',
                ],
            ],
        ])
        ->assertSessionHasNoErrors();

    $bed = Bed::query()->where('user_id', $user->id)->first();

    expect($bed->cell_bricks[0]['width_cm'])->toBe(300);
    expect($bed->cell_bricks[0]['w'])->toBe(10);
});
