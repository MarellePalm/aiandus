<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BedController extends Controller
{
    private const DEFAULT_CELL_SIZE_CM = 30;

    private function defaultGardenPosition(int $bedCount): array
    {
        $columns = 3;
        $column = $bedCount % $columns;
        $row = intdiv($bedCount, $columns);

        return [
            'garden_x' => 48 + ($column * 220),
            'garden_y' => 48 + ($row * 180),
        ];
    }

    private function normalizeLayout(array $layout): array
    {
        return array_map(
            fn ($row) => is_array($row)
                ? array_map(function ($cell) {
                    $v = (int) $cell;

                    return in_array($v, [-1, 0, 1], true) ? $v : 0;
                }, $row)
                : [],
            $layout
        );
    }

    private function validateNormalizedLayout(array $layout): void
    {
        if (empty($layout)) {
            throw ValidationException::withMessages([
                'layout' => 'Peenra ruudustik on kohustuslik.',
            ]);
        }

        $hasActiveCell = collect($layout)->flatten()->contains(fn ($cell) => (int) $cell === 1);

        if (! $hasActiveCell) {
            throw ValidationException::withMessages([
                'layout' => 'Peenras peab olema vähemalt üks aktiivne ruut.',
            ]);
        }
    }

    private function normalizeCellPlants(array $plants): array
    {
        return array_map(function ($plant) {
            return [
                'plant_id' => (int) ($plant['plant_id'] ?? 0),
                'quantity' => max(1, (int) ($plant['quantity'] ?? 1)),
                'size' => isset($plant['size']) ? (string) $plant['size'] : null,
                'note' => isset($plant['note']) ? (string) $plant['note'] : null,
            ];
        }, $plants);
    }

    private function convertCellsToLayout(array $cells): array
    {
        $normalized = collect($cells)
            ->filter(fn ($cell) => is_array($cell))
            ->map(function ($cell) {
                return [
                    'x' => (int) ($cell['x'] ?? 0),
                    'y' => (int) ($cell['y'] ?? 0),
                    'plants' => $this->normalizeCellPlants($cell['plants'] ?? []),
                ];
            })
            ->values();

        if ($normalized->isEmpty()) {
            throw ValidationException::withMessages([
                'cells' => 'Peenras peab olema vähemalt üks ruut.',
            ]);
        }

        $minX = $normalized->min('x');
        $maxX = $normalized->max('x');
        $minY = $normalized->min('y');
        $maxY = $normalized->max('y');

        $rows = ($maxY - $minY) + 1;
        $columns = ($maxX - $minX) + 1;

        $layout = array_fill(0, $rows, array_fill(0, $columns, -1));
        $plantPositions = [];

        foreach ($normalized as $cell) {
            $row = $cell['y'] - $minY;
            $column = $cell['x'] - $minX;
            $layout[$row][$column] = 1;

            foreach ($cell['plants'] as $plant) {
                if (($plant['plant_id'] ?? 0) > 0) {
                    $plantPositions[(int) $plant['plant_id']] = "{$row},{$column}";
                }
            }
        }

        return [
            'layout' => $layout,
            'rows' => $rows,
            'columns' => $columns,
            'plant_positions' => $plantPositions,
        ];
    }

    private function collectPlantPositions(array $cells): array
    {
        $plantPositions = [];

        foreach ($cells as $cell) {
            if (! is_array($cell)) {
                continue;
            }

            $row = (int) ($cell['y'] ?? 0);
            $column = (int) ($cell['x'] ?? 0);

            foreach ($this->normalizeCellPlants($cell['plants'] ?? []) as $plant) {
                if (($plant['plant_id'] ?? 0) > 0) {
                    $plantPositions[(int) $plant['plant_id']] = "{$row},{$column}";
                }
            }
        }

        return $plantPositions;
    }

    private function validatePlantPositionsInLayout(array $plantPositions, array $layout): void
    {
        $rowCount = count($layout);
        $colCount = $rowCount > 0 ? max(array_map('count', $layout)) : 0;

        foreach ($plantPositions as $position) {
            if (! is_string($position) || ! preg_match('/^\d+,\d+$/', $position)) {
                continue;
            }

            [$row, $column] = array_map('intval', explode(',', $position));

            if ($row < 0 || $column < 0 || $row >= $rowCount || $column >= $colCount || ($layout[$row][$column] ?? -1) !== 1) {
                throw ValidationException::withMessages([
                    'cells' => 'Taimed peavad jääma peenraruutude peale.',
                ]);
            }
        }
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'garden_plan_id' => [
                'required',
                'integer',
                Rule::exists('garden_plans', 'id')->where(
                    fn ($q) => $q->where('user_id', $request->user()->id),
                ),
            ],
            'name' => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:5120', 'dimensions:min_width=300,min_height=300,max_width=6000,max_height=6000'],
            'garden_x' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'garden_y' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'cell_size_cm' => ['nullable', 'integer', 'min:10', 'max:200'],
            'cells' => ['nullable', 'array'],
            'cells.*.x' => ['required_with:cells', 'integer'],
            'cells.*.y' => ['required_with:cells', 'integer'],
            'cells.*.plants' => ['nullable', 'array'],
            'cells.*.plants.*.plant_id' => ['nullable', 'integer'],
            'cells.*.plants.*.quantity' => ['nullable', 'integer', 'min:1'],
            'cells.*.plants.*.size' => ['nullable', 'string', 'max:20'],
            'cells.*.plants.*.note' => ['nullable', 'string', 'max:255'],
            'layout' => ['nullable', 'array'],
            'layout.*' => ['array'],
            // -1 = vahekäik / tee / kivi, 0 = tühi, 1 = peenraruut
            'layout.*.*' => ['integer', 'in:-1,0,1'],
        ]);

        $layout = null;
        $rows = 1;
        $columns = 1;

        if (isset($data['layout']) && is_array($data['layout'])) {
            $layout = $this->normalizeLayout($data['layout']);
            $this->validateNormalizedLayout($layout);
            $rows = count($layout);
            $columns = $rows > 0 ? max(array_map('count', $layout)) : 1;
        } elseif (! empty($data['cells']) && is_array($data['cells'])) {
            $converted = $this->convertCellsToLayout($data['cells']);
            $layout = $converted['layout'];
            $rows = $converted['rows'];
            $columns = $converted['columns'];
        }

        $canStoreBedImage = Schema::hasColumn('beds', 'image_url');
        $imagePath = null;
        if ($canStoreBedImage && $request->hasFile('image')) {
            $imagePath = $request->file('image')->store('bed-images', 'public');
        }

        $gardenPlanId = (int) $data['garden_plan_id'];

        $payload = [
            'user_id' => $request->user()->id,
            'garden_plan_id' => $gardenPlanId,
            'name' => $data['name'],
            'location' => $data['location'] ?? null,
            'rows' => $rows,
            'columns' => $columns,
            'layout' => $layout,
            'sort_order' => (Bed::query()->where('garden_plan_id', $gardenPlanId)->max('sort_order') ?? 0) + 1,
        ];

        $defaultPosition = $this->defaultGardenPosition(
            Bed::query()->where('garden_plan_id', $gardenPlanId)->count()
        );
        $payload['garden_x'] = (int) ($data['garden_x'] ?? $defaultPosition['garden_x']);
        $payload['garden_y'] = (int) ($data['garden_y'] ?? $defaultPosition['garden_y']);
        $payload['cell_size_cm'] = (int) ($data['cell_size_cm'] ?? self::DEFAULT_CELL_SIZE_CM);

        if ($canStoreBedImage) {
            $payload['image_url'] = $imagePath ? "/storage/{$imagePath}" : null;
        }

        $bed = Bed::create($payload);

        Session::flash('success', 'Peenar lisatud. Lohista see aiaplaanil õigesse kohta.');

        return redirect()->route('map.show', [
            'gardenPlan' => $gardenPlanId,
            'bed' => $bed->id,
        ]);
    }

    public function update(Request $request, Bed $bed)
    {
        abort_unless($bed->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:5120', 'dimensions:min_width=300,min_height=300,max_width=6000,max_height=6000'],
            'garden_x' => ['sometimes', 'integer', 'min:0', 'max:5000'],
            'garden_y' => ['sometimes', 'integer', 'min:0', 'max:5000'],
            'cell_size_cm' => ['sometimes', 'integer', 'min:10', 'max:200'],
            'cells' => ['nullable', 'array'],
            'cells.*.x' => ['required_with:cells', 'integer'],
            'cells.*.y' => ['required_with:cells', 'integer'],
            'cells.*.plants' => ['nullable', 'array'],
            'cells.*.plants.*.plant_id' => ['nullable', 'integer'],
            'cells.*.plants.*.quantity' => ['nullable', 'integer', 'min:1'],
            'cells.*.plants.*.size' => ['nullable', 'string', 'max:20'],
            'cells.*.plants.*.note' => ['nullable', 'string', 'max:255'],
            'rows' => ['sometimes', 'integer', 'min:1'],
            'columns' => ['sometimes', 'integer', 'min:1'],
            'layout' => ['nullable', 'array'],
            'layout.*' => ['array'],
            // -1 = vahekäik / tee / kivi, 0 = tühi, 1 = peenraruut
            'layout.*.*' => ['integer', 'in:-1,0,1'],
        ]);

        $payload = array_filter($data, fn ($v) => $v !== null);
        if (Schema::hasColumn('beds', 'image_url') && $request->hasFile('image')) {
            $path = $request->file('image')->store('bed-images', 'public');
            $payload['image_url'] = "/storage/{$path}";
        }

        $plantPositions = null;

        if (isset($data['layout']) && is_array($data['layout']) && ! empty($data['layout'])) {
            $normalizedLayout = $this->normalizeLayout($data['layout']);
            $this->validateNormalizedLayout($normalizedLayout);
            $rowCount = count($normalizedLayout);
            $colCount = $rowCount > 0 ? max(array_map('count', $normalizedLayout)) : 0;

            foreach ($bed->plants()->select('id', 'position_in_bed')->get() as $plant) {
                $pos = $plant->position_in_bed;
                if (! is_string($pos) || ! preg_match('/^\d+,\d+$/', $pos)) {
                    continue;
                }

                [$r, $c] = array_map('intval', explode(',', $pos));

                if ($r < 0 || $c < 0 || $r >= $rowCount || $c >= $colCount) {
                    throw ValidationException::withMessages([
                        'layout' => 'Peenrast ei saa eemaldada rida või veergu, kus asub taim.',
                    ]);
                }

                if (($normalizedLayout[$r][$c] ?? -1) !== 1) {
                    throw ValidationException::withMessages([
                        'layout' => 'Taimedega ruudud peavad jääma peenra osaks.',
                    ]);
                }
            }

            $payload['rows'] = count($normalizedLayout);
            $payload['columns'] = max(array_map('count', $normalizedLayout));
            $payload['layout'] = $normalizedLayout;

            if (! empty($data['cells']) && is_array($data['cells'])) {
                $plantPositions = $this->collectPlantPositions($data['cells']);
                $this->validatePlantPositionsInLayout($plantPositions, $normalizedLayout);
            }
        } elseif (! empty($data['cells']) && is_array($data['cells'])) {
            $converted = $this->convertCellsToLayout($data['cells']);
            $payload['rows'] = $converted['rows'];
            $payload['columns'] = $converted['columns'];
            $payload['layout'] = $converted['layout'];
            $plantPositions = $converted['plant_positions'];
        }

        $bed->update($payload);

        if (is_array($plantPositions)) {
            $bedPlants = $bed->plants()->select('id', 'position_in_bed')->get()->keyBy('id');

            foreach ($bedPlants as $plantId => $plant) {
                if (! array_key_exists($plantId, $plantPositions)) {
                    if (is_string($plant->position_in_bed) && preg_match('/^\d+,\d+$/', $plant->position_in_bed)) {
                        throw ValidationException::withMessages([
                            'cells' => 'Peenart ei saa salvestada nii, et olemasolev taim kaotab oma ruudu.',
                        ]);
                    }

                    continue;
                }
            }

            foreach ($plantPositions as $plantId => $position) {
                $plant = $bedPlants->get($plantId);
                if (! $plant) {
                    continue;
                }
                $plant->update(['position_in_bed' => $position]);
            }
        }

        return back();
    }

    public function destroy(Request $request, Bed $bed)
    {
        abort_unless($bed->user_id === $request->user()->id, 403);
        $bed->plants()->update(['bed_id' => null, 'position_in_bed' => null]);
        $bed->delete();

        return back()->with('success', 'Peenar eemaldatud.');
    }
}
