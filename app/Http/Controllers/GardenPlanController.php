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
            'shape_mask_cell_cm' => ['nullable', 'integer', 'min:10', 'max:1000'],
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

        $shapeMaskCellCm = (int) ($data['shape_mask_cell_cm'] ?? 1000);
        $shapeMask = null;
        if (array_key_exists('shape_mask', $data)) {
            $shapeMask = $this->normalizeShapeMask(
                $data['shape_mask'],
                $data['width'],
                $data['height'],
                $shapeMaskCellCm,
            );
        }

        $plan = GardenPlan::query()->create([
            'user_id' => $request->user()->id,
            'name' => trim((string) ($data['name'] ?? '')) ?: 'Minu aed',
            'width' => $data['width'],
            'height' => $data['height'],
            'unit' => 'cm',
            'shape_mask' => $shapeMask,
            'shape_mask_cell_cm' => $shapeMaskCellCm,
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
            'shape_mask_cell_cm' => ['nullable', 'integer', 'min:10', 'max:1000'],
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
            $shapeMaskCellCm = (int) ($data['shape_mask_cell_cm'] ?? $gardenPlan->shape_mask_cell_cm ?? 1000);
            $updates['shape_mask'] = $this->normalizeShapeMask(
                $data['shape_mask'],
                $data['width'],
                $data['height'],
                $shapeMaskCellCm,
            );
            $updates['shape_mask_cell_cm'] = $shapeMaskCellCm;
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
    private function normalizeShapeMask(
        ?array $shapeMask,
        int $widthCm,
        int $heightCm,
        int $cellCm = 1000,
    ): ?array {
        if ($shapeMask === null) {
            return null;
        }

        $cellCm = max(10, min(1000, $cellCm));
        $inRows = count($shapeMask);
        $inCols = count($shapeMask[0] ?? []);
        $expectedCols = max(1, (int) round($widthCm / $cellCm));
        $expectedRows = max(1, (int) round($heightCm / $cellCm));

        if ($inRows < 1 || $inCols < 1) {
            throw ValidationException::withMessages([
                'shape_mask' => 'Aiaplaanil peab olema vähemalt üks aktiivne ruut.',
            ]);
        }

        if (
            $cellCm < 1000
            && abs($inCols - $expectedCols) <= 1
            && abs($inRows - $expectedRows) <= 1
        ) {
            $normalized = [];
            for ($y = 0; $y < $inRows; $y++) {
                $normalized[$y] = [];
                for ($x = 0; $x < $inCols; $x++) {
                    $normalized[$y][$x] = (int) (($shapeMask[$y][$x] ?? 0) === 1 ? 1 : 0);
                }
            }
        } else {
            $rows = $expectedRows;
            $cols = $expectedCols;
            $normalized = [];

            for ($y = 0; $y < $rows; $y++) {
                $normalized[$y] = [];
                $y0 = (int) floor(($y * $inRows) / $rows);
                $y1 = min($inRows, (int) ceil((($y + 1) * $inRows) / $rows));

                for ($x = 0; $x < $cols; $x++) {
                    $x0 = (int) floor(($x * $inCols) / $cols);
                    $x1 = min($inCols, (int) ceil((($x + 1) * $inCols) / $cols));
                    $active = false;

                    for ($sy = $y0; $sy < $y1; $sy++) {
                        for ($sx = $x0; $sx < $x1; $sx++) {
                            if (($shapeMask[$sy][$sx] ?? 0) === 1) {
                                $active = true;
                                break 2;
                            }
                        }
                    }

                    $normalized[$y][$x] = $active ? 1 : 0;
                }
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
