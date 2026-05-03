<?php

namespace App\Http\Controllers;

use App\Models\GardenObject;
use App\Models\GardenPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GardenObjectController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'garden_plan_id' => ['required', 'integer', 'exists:garden_plans,id'],
            'type' => ['required', 'string', 'in:greenhouse,pond,shed,compost,other'],
            'name' => [
                'nullable',
                'string',
                'max:120',
                Rule::requiredIf($request->input('type') === 'other'),
            ],
            'x' => ['required', 'integer', 'min:0', 'max:10000'],
            'y' => ['required', 'integer', 'min:0', 'max:10000'],
            'width' => ['required', 'integer', 'min:50', 'max:5000'],
            'height' => ['required', 'integer', 'min:50', 'max:5000'],
            'meta' => ['nullable', 'array'],
        ]);

        $plan = GardenPlan::query()->findOrFail($data['garden_plan_id']);
        abort_unless($plan->user_id === $request->user()->id, 403);

        GardenObject::query()->create([
            'garden_plan_id' => $plan->id,
            'type' => $data['type'],
            'name' => trim((string) ($data['name'] ?? '')) ?: $this->defaultName($data['type']),
            'x' => $data['x'],
            'y' => $data['y'],
            'width' => $data['width'],
            'height' => $data['height'],
            'meta' => $data['meta'] ?? null,
        ]);

        return back()->with('success', 'Aiaelement lisatud.');
    }

    public function update(Request $request, GardenObject $gardenObject)
    {
        abort_unless($gardenObject->gardenPlan->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'x' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'y' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'width' => ['sometimes', 'integer', 'min:50', 'max:5000'],
            'height' => ['sometimes', 'integer', 'min:50', 'max:5000'],
            'meta' => ['sometimes', 'nullable', 'array'],
        ]);

        $payload = array_filter($data, fn ($value) => $value !== null);

        if (array_key_exists('name', $data)) {
            $payload['name'] = trim((string) ($data['name'] ?? '')) ?: $gardenObject->name;
        }

        $gardenObject->update($payload);

        return back()->with('success', 'Aiaelement uuendatud.');
    }

    public function destroy(Request $request, GardenObject $gardenObject)
    {
        abort_unless($gardenObject->gardenPlan->user_id === $request->user()->id, 403);
        $gardenObject->delete();

        return back()->with('success', 'Aiaelement eemaldatud.');
    }

    public function duplicate(Request $request, GardenObject $gardenObject)
    {
        abort_unless($gardenObject->gardenPlan->user_id === $request->user()->id, 403);

        GardenObject::query()->create([
            'garden_plan_id' => $gardenObject->garden_plan_id,
            'type' => $gardenObject->type,
            'name' => $gardenObject->name.' koopia',
            'x' => min(10000, $gardenObject->x + 45),
            'y' => min(10000, $gardenObject->y + 45),
            'width' => $gardenObject->width,
            'height' => $gardenObject->height,
            'meta' => $gardenObject->meta,
        ]);

        return back()->with('success', 'Aiaelement dubleeritud.');
    }

    public function rotate(Request $request, GardenObject $gardenObject)
    {
        abort_unless($gardenObject->gardenPlan->user_id === $request->user()->id, 403);

        $meta = is_array($gardenObject->meta) ? $gardenObject->meta : [];
        $rotation = ((int) ($meta['rotation'] ?? 0) + 90) % 360;

        $gardenObject->update([
            'width' => $gardenObject->height,
            'height' => $gardenObject->width,
            'meta' => [
                ...$meta,
                'rotation' => $rotation,
            ],
        ]);

        return back()->with('success', 'Aiaelement pööratud.');
    }

    private function defaultName(string $type): string
    {
        return match ($type) {
            'greenhouse' => 'Kasvuhoone',
            'pond' => 'Tiik',
            'shed' => 'Kuur',
            'compost' => 'Kompost',
            'other' => 'Muu',
            default => 'Aiaelement',
        };
    }
}
