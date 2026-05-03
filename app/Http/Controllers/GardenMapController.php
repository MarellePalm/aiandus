<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\GardenObject;
use App\Models\GardenPlan;
use App\Models\Plant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GardenMapController extends Controller
{
    public function redirect(Request $request)
    {
        $user = $request->user();
        $plan = GardenPlan::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->first();

        if (! $plan) {
            $plan = GardenPlan::query()->create([
                'user_id' => $user->id,
                'name' => 'Minu aed',
                'width' => 1200,
                'height' => 800,
                'unit' => 'cm',
            ]);
        }

        return redirect()->route('map.show', $plan);
    }

    public function show(Request $request, GardenPlan $gardenPlan)
    {
        abort_unless($gardenPlan->user_id === $request->user()->id, 403);

        $user = $request->user();

        $gardenPlans = GardenPlan::query()
            ->where('user_id', $user->id)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
            ]);

        $beds = Bed::query()
            ->where('garden_plan_id', $gardenPlan->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->with(['plants' => fn ($q) => $q->select('id', 'name', 'image_url', 'bed_id', 'position_in_bed'),
            ])
            ->get()
            ->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'location' => $b->location,
                'image_url' => $b->image_url,
                'rows' => (int) ($b->rows ?? 3),
                'columns' => (int) ($b->columns ?? 3),
                'garden_x' => (int) ($b->garden_x ?? 0),
                'garden_y' => (int) ($b->garden_y ?? 0),
                'cell_size_cm' => (int) ($b->cell_size_cm ?? 30),
                'layout' => $b->layout,
                'plants' => $b->plants->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'image_url' => $p->image_url,
                    'position_in_bed' => $p->position_in_bed,
                ]),
            ]);

        $plantsWithoutBed = Plant::query()
            ->where('user_id', $user->id)
            ->whereNull('bed_id')
            ->orderBy('name')
            ->with('category:id,name,slug')
            ->get(['id', 'name', 'image_url', 'category_id'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'image_url' => $p->image_url,
                'category' => $p->category ? ['name' => $p->category->name, 'slug' => $p->category->slug] : null,
            ]);

        $gardenObjects = GardenObject::query()
            ->where('garden_plan_id', $gardenPlan->id)
            ->orderBy('type')
            ->orderBy('id')
            ->get()
            ->map(fn ($object) => [
                'id' => $object->id,
                'type' => $object->type,
                'name' => $object->name,
                'x' => (int) $object->x,
                'y' => (int) $object->y,
                'width' => (int) $object->width,
                'height' => (int) $object->height,
                'meta' => $object->meta,
            ]);

        return Inertia::render('map/MapView', [
            'gardenPlans' => $gardenPlans,
            'gardenPlan' => [
                'id' => $gardenPlan->id,
                'name' => $gardenPlan->name,
                'width' => (int) $gardenPlan->width,
                'height' => (int) $gardenPlan->height,
                'unit' => $gardenPlan->unit,
            ],
            'beds' => $beds,
            'gardenObjects' => $gardenObjects,
            'plantsWithoutBed' => $plantsWithoutBed,
        ]);
    }

    public function createBed(Request $request, GardenPlan $gardenPlan)
    {
        abort_unless($gardenPlan->user_id === $request->user()->id, 403);

        $isFirstBed = ! Bed::query()
            ->where('garden_plan_id', $gardenPlan->id)
            ->exists();

        return Inertia::render('map/AddBedPage', [
            'showGuide' => $isFirstBed,
            'gardenPlanId' => $gardenPlan->id,
        ]);
    }
}
