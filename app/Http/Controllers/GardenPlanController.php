<?php

namespace App\Http\Controllers;

use App\Models\GardenPlan;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GardenPlanController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'width' => ['required', 'integer', 'min:1', 'max:100000'],
            'height' => ['required', 'integer', 'min:1', 'max:100000'],
            'center_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'center_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'shape_mask' => ['nullable', 'array'],
            'shape_mask.*' => ['array'],
            'shape_mask.*.*' => ['integer', 'in:0,1'],
            'boundary_polygon' => ['nullable', 'array', 'min:3'],
            'boundary_polygon.*.lat' => ['required_with:boundary_polygon', 'numeric', 'between:-90,90'],
            'boundary_polygon.*.lng' => ['required_with:boundary_polygon', 'numeric', 'between:-180,180'],
        ], [
            'width.required' => 'Aia laius (meetrites) on kohustuslik.',
            'width.integer' => 'Aia laius peab olema täisarv sentimeetrites.',
            'width.min' => 'Laius peab olema vähemalt 1 cm.',
            'width.max' => 'Sisestatud laius on liiga suur. Maksimaalne suurus on 1 km.',
            'height.required' => 'Aia sügavus/kõrgus (meetrites) on kohustuslik.',
            'height.integer' => 'Aia sügavus peab olema täisarv sentimeetrites.',
            'height.min' => 'Sügavus peab olema vähemalt 1 cm.',
            'height.max' => 'Sisestatud kõrgus on liiga suur. Maksimaalne suurus on 1 km.',
        ]);

        $shapeMask = null;
        if (array_key_exists('shape_mask', $data)) {
            $shapeMask = $this->normalizeShapeMask(
                $data['shape_mask'],
                $data['width'],
                $data['height'],
            );
        }

        $plan = GardenPlan::query()->create([
            'user_id' => $request->user()->id,
            'name' => trim((string) ($data['name'] ?? '')) ?: 'Minu aed',
            'width' => $data['width'],
            'height' => $data['height'],
            'unit' => 'cm',
            'shape_mask' => $shapeMask,
            'center_lat' => $this->nullableCoordinate($data['center_lat'] ?? null),
            'center_lng' => $this->nullableCoordinate($data['center_lng'] ?? null),
            'boundary_polygon' => $this->normalizeBoundaryPolygon($data['boundary_polygon'] ?? null),
        ]);

        return redirect()
            ->route('map.show', $plan)
            ->with('success', 'Uus aiaplaan loodud.');
    }

    public function update(Request $request, GardenPlan $gardenPlan)
    {
        abort_unless($gardenPlan->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'width' => ['required', 'integer', 'min:1', 'max:100000'],
            'height' => ['required', 'integer', 'min:1', 'max:100000'],
            'shape_mask' => ['nullable', 'array'],
            'shape_mask.*' => ['array'],
            'shape_mask.*.*' => ['integer', 'in:0,1'],
            'center_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'center_lng' => ['nullable', 'numeric', 'between:-180,180'],
            'boundary_polygon' => ['nullable', 'array', 'min:3'],
            'boundary_polygon.*.lat' => ['required_with:boundary_polygon', 'numeric', 'between:-90,90'],
            'boundary_polygon.*.lng' => ['required_with:boundary_polygon', 'numeric', 'between:-180,180'],
        ], [
            'width.required' => 'Aia laius (meetrites) on kohustuslik.',
            'width.integer' => 'Aia laius peab olema täisarv sentimeetrites.',
            'width.min' => 'Laius peab olema vähemalt 1 cm (null või tühi plaan ei kehti).',
            'width.max' => 'Sisestatud laius on selle vaate jaoks liiga suur. Maksimaalne suurus on 1 km.',
            'height.required' => 'Aia sügavus/kõrgus (meetrites) on kohustuslik.',
            'height.integer' => 'Aia sügavus peab olema täisarv sentimeetrites.',
            'height.min' => 'Sügavus peab olema vähemalt 1 cm (null või tühi plaan ei kehti).',
            'height.max' => 'Sisestatud kõrgus on selle vaate jaoks liiga suur. Maksimaalne suurus on 1 km.',
        ]);

        $updates = [
            'name' => trim((string) ($data['name'] ?? $gardenPlan->name)) ?: 'Minu aed',
            'width' => $data['width'],
            'height' => $data['height'],
            'center_lat' => $this->nullableCoordinate($data['center_lat'] ?? null),
            'center_lng' => $this->nullableCoordinate($data['center_lng'] ?? null),
        ];

        if (array_key_exists('shape_mask', $data)) {
            $updates['shape_mask'] = $this->normalizeShapeMask(
                $data['shape_mask'],
                $data['width'],
                $data['height'],
            );
        }

        if (array_key_exists('boundary_polygon', $data)) {
            $updates['boundary_polygon'] = $this->normalizeBoundaryPolygon(
                $data['boundary_polygon'],
            );
        }

        $gardenPlan->update($updates);

        return back()->with('success', 'Aia mõõdud uuendatud.');
    }

    private function nullableCoordinate(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 7);
    }

    /**
     * @param  array<int, array{lat: mixed, lng: mixed}>|null  $polygon
     * @return array<int, array{lat: float, lng: float}>|null
     */
    private function normalizeBoundaryPolygon(?array $polygon): ?array
    {
        if ($polygon === null) {
            return null;
        }

        $normalized = [];

        foreach ($polygon as $point) {
            if (! is_array($point)) {
                continue;
            }

            $lat = $this->nullableCoordinate($point['lat'] ?? null);
            $lng = $this->nullableCoordinate($point['lng'] ?? null);

            if ($lat === null || $lng === null) {
                continue;
            }

            $normalized[] = ['lat' => $lat, 'lng' => $lng];
        }

        return count($normalized) >= 3 ? $normalized : null;
    }

    /**
     * @param  array<int, array<int, int>>|null  $shapeMask
     * @return array<int, array<int, int>>|null
     */
    private function normalizeShapeMask(?array $shapeMask, int $widthCm, int $heightCm): ?array
    {
        if ($shapeMask === null) {
            return null;
        }

        $rows = max(1, (int) ceil($heightCm / 1000));
        $cols = max(1, (int) ceil($widthCm / 1000));
        $normalized = [];

        for ($y = 0; $y < $rows; $y++) {
            $normalized[$y] = [];
            for ($x = 0; $x < $cols; $x++) {
                $normalized[$y][$x] = (int) (($shapeMask[$y][$x] ?? 1) === 1 ? 1 : 0);
            }
        }

        $hasInactive = false;
        foreach ($normalized as $row) {
            if (in_array(0, $row, true)) {
                $hasInactive = true;
                break;
            }
        }

        if (! $hasInactive) {
            return null;
        }

        $hasActive = false;
        foreach ($normalized as $row) {
            if (in_array(1, $row, true)) {
                $hasActive = true;
                break;
            }
        }

        if (! $hasActive) {
            throw ValidationException::withMessages([
                'shape_mask' => 'Aiaplaanil peab olema vähemalt üks aktiivne ruut.',
            ]);
        }

        return $normalized;
    }

    public function destroy(Request $request, GardenPlan $gardenPlan)
    {
        abort_unless($gardenPlan->user_id === $request->user()->id, 403);

        if (GardenPlan::query()->where('user_id', $request->user()->id)->count() <= 1) {
            return back()->withErrors([
                'garden_plan' => 'Viimast aiaplaani ei saa kustutada. Loo enne teine plaan või tühjenda see käsitsi.',
            ]);
        }

        $nextPlan = GardenPlan::query()
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $gardenPlan->id)
            ->orderBy('id')
            ->first();

        $gardenPlan->delete();

        return redirect()
            ->route('map.show', $nextPlan)
            ->with('success', 'Aiaplaan ja selle peenrad kustutatud.');
    }
}
