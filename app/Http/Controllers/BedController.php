<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use App\Models\Plant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BedController extends Controller
{
    private const DEFAULT_CELL_SIZE_CM = 10;

    /** Maks ruute ühes plokis (nt õunapuu ~3 m laius @ 30 cm ruut ≈ 10 ruutu). */
    private const MAX_BRICK_GRID_SPAN = 12;

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
            $quantity = (int) ($plant['quantity'] ?? 1);

            return [
                'plant_id' => (int) ($plant['plant_id'] ?? 0),
                'quantity' => $quantity === 0 ? 0 : max(1, $quantity),
                'size' => isset($plant['size']) ? (string) $plant['size'] : null,
                'note' => isset($plant['note']) ? (string) $plant['note'] : null,
            ];
        }, $plants);
    }

    private function normalizeBrickKind(?string $kind): string
    {
        return in_array($kind, ['plantable', 'walkway', 'empty'], true) ? $kind : 'plantable';
    }

    private function normalizeCellBricks(array $bricks, int $unitCm = self::DEFAULT_CELL_SIZE_CM): array
    {
        $unitCm = max(10, min(200, $unitCm));

        return collect($bricks)
            ->filter(fn ($brick) => is_array($brick))
            ->map(function ($brick) use ($unitCm) {
                $w = max(1, min(self::MAX_BRICK_GRID_SPAN, (int) ($brick['w'] ?? 1)));
                $h = max(1, min(self::MAX_BRICK_GRID_SPAN, (int) ($brick['h'] ?? 1)));
                $widthCm = max(10, min(500, (int) ($brick['width_cm'] ?? $w * $unitCm)));
                $heightCm = max(10, min(500, (int) ($brick['height_cm'] ?? $h * $unitCm)));
                $w = max(1, min(self::MAX_BRICK_GRID_SPAN, (int) ceil($widthCm / $unitCm)));
                $h = max(1, min(self::MAX_BRICK_GRID_SPAN, (int) ceil($heightCm / $unitCm)));

                $normalized = [
                    'x' => (int) ($brick['x'] ?? 0),
                    'y' => (int) ($brick['y'] ?? 0),
                    'w' => $w,
                    'h' => $h,
                    'width_cm' => $widthCm,
                    'height_cm' => $heightCm,
                    'kind' => $this->normalizeBrickKind($brick['kind'] ?? null),
                ];

                if (isset($brick['left_cm'])) {
                    $normalized['left_cm'] = max(0, (int) $brick['left_cm']);
                }

                if (isset($brick['top_cm'])) {
                    $normalized['top_cm'] = max(0, (int) $brick['top_cm']);
                }

                return $normalized;
            })
            ->values()
            ->all();
    }

    private function layoutValueForBrick(array $brick): int
    {
        if (($brick['kind'] ?? 'plantable') === 'walkway') {
            return -1;
        }

        if (($brick['kind'] ?? 'plantable') === 'empty') {
            return 0;
        }

        return 1;
    }

    private function validateCellBricks(array $bricks): void
    {
        $occupied = [];

        foreach ($bricks as $index => $brick) {
            for ($dy = 0; $dy < $brick['h']; $dy++) {
                for ($dx = 0; $dx < $brick['w']; $dx++) {
                    $key = ($brick['y'] + $dy).','.($brick['x'] + $dx);
                    if (isset($occupied[$key])) {
                        throw ValidationException::withMessages([
                            'cell_bricks' => 'Peenra plokid ei tohi üksteist katma.',
                        ]);
                    }
                    $occupied[$key] = $index;
                }
            }
        }
    }

    private function buildLayoutFromCellBricks(
        array $bricks,
        int $unitCm = self::DEFAULT_CELL_SIZE_CM,
    ): array {
        $bricks = $this->normalizeCellBricks($bricks, $unitCm);

        if (empty($bricks)) {
            throw ValidationException::withMessages([
                'cell_bricks' => 'Peenras peab olema vähemalt üks plokk.',
            ]);
        }

        $this->validateCellBricks($bricks);

        $minX = min(array_column($bricks, 'x'));
        $maxX = max(array_map(fn ($brick) => $brick['x'] + $brick['w'] - 1, $bricks));
        $minY = min(array_column($bricks, 'y'));
        $maxY = max(array_map(fn ($brick) => $brick['y'] + $brick['h'] - 1, $bricks));

        $rows = ($maxY - $minY) + 1;
        $columns = ($maxX - $minX) + 1;
        $layout = array_fill(0, $rows, array_fill(0, $columns, 0));

        foreach ($bricks as $brick) {
            $value = $this->layoutValueForBrick($brick);
            for ($dy = 0; $dy < $brick['h']; $dy++) {
                for ($dx = 0; $dx < $brick['w']; $dx++) {
                    $layout[$brick['y'] - $minY + $dy][$brick['x'] - $minX + $dx] = $value;
                }
            }
        }

        return [
            'layout' => $layout,
            'rows' => $rows,
            'columns' => $columns,
            'cell_bricks' => array_map(function ($brick) use ($minX, $minY) {
                $payload = [
                    'x' => $brick['x'] - $minX,
                    'y' => $brick['y'] - $minY,
                    'w' => $brick['w'],
                    'h' => $brick['h'],
                    'width_cm' => $brick['width_cm'],
                    'height_cm' => $brick['height_cm'],
                    'kind' => $brick['kind'],
                ];

                if (isset($brick['left_cm'])) {
                    $payload['left_cm'] = $brick['left_cm'];
                }

                if (isset($brick['top_cm'])) {
                    $payload['top_cm'] = $brick['top_cm'];
                }

                return $payload;
            }, $bricks),
        ];
    }

    private function cellBricksFromLayout(array $layout): array
    {
        $bricks = [];

        foreach ($layout as $y => $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach ($row as $x => $cell) {
                $value = (int) $cell;
                if ($value === 1) {
                    $bricks[] = [
                        'x' => $x,
                        'y' => $y,
                        'w' => 1,
                        'h' => 1,
                        'width_cm' => self::DEFAULT_CELL_SIZE_CM,
                        'height_cm' => self::DEFAULT_CELL_SIZE_CM,
                        'kind' => 'plantable',
                    ];
                } elseif ($value === -1) {
                    $bricks[] = [
                        'x' => $x,
                        'y' => $y,
                        'w' => 1,
                        'h' => 1,
                        'width_cm' => self::DEFAULT_CELL_SIZE_CM,
                        'height_cm' => self::DEFAULT_CELL_SIZE_CM,
                        'kind' => 'walkway',
                    ];
                }
            }
        }

        return $bricks;
    }

    private function convertCellsToLayout(
        array $cells,
        int $unitCm = self::DEFAULT_CELL_SIZE_CM,
    ): array {
        $normalized = collect($cells)
            ->filter(fn ($cell) => is_array($cell))
            ->map(function ($cell) {
                return [
                    'x' => (int) ($cell['x'] ?? 0),
                    'y' => (int) ($cell['y'] ?? 0),
                    'w' => max(1, min(self::MAX_BRICK_GRID_SPAN, (int) ($cell['w'] ?? 1))),
                    'h' => max(1, min(self::MAX_BRICK_GRID_SPAN, (int) ($cell['h'] ?? 1))),
                    'kind' => $this->normalizeBrickKind($cell['kind'] ?? 'plantable'),
                    'plants' => $this->normalizeCellPlants($cell['plants'] ?? []),
                ];
            })
            ->values();

        if ($normalized->isEmpty()) {
            throw ValidationException::withMessages([
                'cells' => 'Peenras peab olema vähemalt üks ruut.',
            ]);
        }

        $bricks = $normalized
            ->map(fn ($cell) => [
                'x' => $cell['x'],
                'y' => $cell['y'],
                'w' => $cell['w'],
                'h' => $cell['h'],
                'kind' => $cell['kind'],
            ])
            ->all();

        $geometry = $this->buildLayoutFromCellBricks($bricks, $unitCm);

        $minX = min(array_column($bricks, 'x'));
        $minY = min(array_column($bricks, 'y'));

        $cellsForPlants = $normalized
            ->map(fn ($cell) => [
                'x' => $cell['x'] - $minX,
                'y' => $cell['y'] - $minY,
                'plants' => $cell['plants'],
            ])
            ->all();
        $plantPositions = $this->collectPlantPositions($cellsForPlants);

        return [
            'layout' => $geometry['layout'],
            'rows' => $geometry['rows'],
            'columns' => $geometry['columns'],
            'cell_bricks' => $geometry['cell_bricks'],
            'plant_positions' => $plantPositions,
        ];
    }

    /**
     * @return array{layout: array, rows: int, columns: int, cell_bricks: array, plant_positions?: array}
     */
    private function resolveBedGeometry(array $data): array
    {
        if (! empty($data['cell_bricks']) && is_array($data['cell_bricks'])) {
            $unitCm = max(10, min(200, (int) ($data['cell_size_cm'] ?? self::DEFAULT_CELL_SIZE_CM)));
            $geometry = $this->buildLayoutFromCellBricks($data['cell_bricks'], $unitCm);
            $plantPositions = ! empty($data['cells']) && is_array($data['cells'])
                ? $this->collectPlantPositions($data['cells'])
                : null;

            if (is_array($plantPositions)) {
                $this->validatePlantPositionsInLayout($plantPositions, $geometry['layout']);
            }

            return [
                ...$geometry,
                'plant_positions' => $plantPositions,
            ];
        }

        if (isset($data['layout']) && is_array($data['layout']) && ! empty($data['layout'])) {
            $layout = $this->normalizeLayout($data['layout']);
            $this->validateNormalizedLayout($layout);
            $cellBricks = $this->cellBricksFromLayout($layout);

            $plantPositions = ! empty($data['cells']) && is_array($data['cells'])
                ? $this->collectPlantPositions($data['cells'])
                : null;

            if (is_array($plantPositions)) {
                $this->validatePlantPositionsInLayout($plantPositions, $layout);
            }

            return [
                'layout' => $layout,
                'rows' => count($layout),
                'columns' => count($layout) > 0 ? max(array_map('count', $layout)) : 1,
                'cell_bricks' => $cellBricks,
                'plant_positions' => $plantPositions,
            ];
        }

        if (! empty($data['cells']) && is_array($data['cells'])) {
            $unitCm = max(10, min(200, (int) ($data['cell_size_cm'] ?? self::DEFAULT_CELL_SIZE_CM)));

            return $this->convertCellsToLayout($data['cells'], $unitCm);
        }

        throw ValidationException::withMessages([
            'layout' => 'Peenra kuju on kohustuslik.',
        ]);
    }

    /**
     * @return list<string>
     */
    private function collectPlantPositions(array $cells): array
    {
        return array_values(array_unique(array_map(
            fn (array $placement) => $placement['position'],
            $this->expandCellPlantPlacements($cells),
        )));
    }

    /**
     * @return list<array{source_plant_id: int, position: string, quantity: int}>
     */
    private function expandCellPlantPlacements(array $cells): array
    {
        $placements = [];

        foreach ($cells as $cell) {
            if (! is_array($cell)) {
                continue;
            }

            $row = (int) ($cell['y'] ?? 0);
            $column = (int) ($cell['x'] ?? 0);

            foreach ($this->normalizeCellPlants($cell['plants'] ?? []) as $plant) {
                $plantId = (int) ($plant['plant_id'] ?? 0);
                if ($plantId > 0) {
                    $placements[] = [
                        'source_plant_id' => $plantId,
                        'position' => "{$row},{$column}",
                        'quantity' => (int) ($plant['quantity'] ?? 1),
                    ];
                }
            }
        }

        return $placements;
    }

    private function syncBedPlantsFromCells(Bed $bed, int $userId, array $cells): void
    {
        $placements = $this->expandCellPlantPlacements($cells);
        $desiredPositions = array_column($placements, 'position');

        Plant::query()
            ->where('user_id', $userId)
            ->where('bed_id', $bed->id)
            ->whereNotNull('position_in_bed')
            ->when(
                $desiredPositions !== [],
                fn ($q) => $q->whereNotIn('position_in_bed', $desiredPositions),
                fn ($q) => $q,
            )
            ->update(['bed_id' => null, 'position_in_bed' => null]);

        foreach ($placements as $placement) {
            $this->placeStockPlantOnBedCell(
                $bed,
                $userId,
                $placement['source_plant_id'],
                $placement['position'],
                $placement['quantity'],
            );
        }
    }

    private function placeStockPlantOnBedCell(
        Bed $bed,
        int $userId,
        int $sourcePlantId,
        string $position,
        int $requestedQty,
    ): void {
        $existing = Plant::query()
            ->where('user_id', $userId)
            ->where('bed_id', $bed->id)
            ->where('position_in_bed', $position)
            ->first();

        if ($existing) {
            if ((int) $existing->quantity !== 0 && $requestedQty > 0) {
                $existing->update(['quantity' => max(1, $requestedQty)]);
            }

            return;
        }

        $stock = $this->findStockPlantForPlacement($userId, $sourcePlantId, $bed->id);
        if (! $stock) {
            return;
        }

        DB::transaction(function () use ($stock, $bed, $position, $requestedQty): void {
            $locked = Plant::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();
            $prevQty = max(0, (int) $locked->quantity);

            $assignQty = $prevQty === 0
                ? max(0, $requestedQty)
                : max(1, min(max(1, $requestedQty), $prevQty));

            $payload = [
                'bed_id' => $bed->id,
                'position_in_bed' => $position,
            ];

            if ($prevQty !== 0 && $assignQty > 0) {
                $payload['quantity'] = $assignQty;
            }

            if ($locked->bed_id === null && $prevQty > 0 && $assignQty < $prevQty) {
                $remainder = $prevQty - $assignQty;
                $remainderPlant = $locked->replicate(['bed_id', 'position_in_bed', 'quantity']);
                $remainderPlant->bed_id = null;
                $remainderPlant->position_in_bed = null;
                $remainderPlant->quantity = $remainder;
                $remainderPlant->save();
            }

            $locked->update($payload);
        });
    }

    private function findStockPlantForPlacement(
        int $userId,
        int $sourcePlantId,
        int $bedId,
    ): ?Plant {
        $source = Plant::query()
            ->where('user_id', $userId)
            ->where('id', $sourcePlantId)
            ->first();

        if (! $source) {
            return null;
        }

        if ($source->bed_id === null) {
            return $source;
        }

        return Plant::query()
            ->where('user_id', $userId)
            ->whereNull('bed_id')
            ->where('name', $source->name)
            ->orderBy('id')
            ->first();
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
            'image' => ['nullable', 'image', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
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
            'cell_bricks' => ['nullable', 'array'],
            'cell_bricks.*.x' => ['required_with:cell_bricks', 'integer', 'min:0'],
            'cell_bricks.*.y' => ['required_with:cell_bricks', 'integer', 'min:0'],
            'cell_bricks.*.w' => ['required_with:cell_bricks', 'integer', 'min:1', 'max:'.self::MAX_BRICK_GRID_SPAN],
            'cell_bricks.*.h' => ['required_with:cell_bricks', 'integer', 'min:1', 'max:'.self::MAX_BRICK_GRID_SPAN],
            'cell_bricks.*.width_cm' => ['nullable', 'integer', 'min:10', 'max:500'],
            'cell_bricks.*.height_cm' => ['nullable', 'integer', 'min:10', 'max:500'],
            'cell_bricks.*.left_cm' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'cell_bricks.*.top_cm' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'cell_bricks.*.kind' => ['nullable', 'string', 'in:plantable,walkway,empty'],
            'cells.*.w' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_BRICK_GRID_SPAN],
            'cells.*.h' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_BRICK_GRID_SPAN],
            'cells.*.kind' => ['nullable', 'string', 'in:plantable,walkway,empty'],
        ]);

        $geometry = $this->resolveBedGeometry($data);
        $layout = $geometry['layout'];
        $rows = $geometry['rows'];
        $columns = $geometry['columns'];
        $cellBricks = $geometry['cell_bricks'];
        $plantPositions = $geometry['plant_positions'] ?? null;

        $canStoreBedImage = Schema::hasColumn('beds', 'image_url');
        $canStoreCellBricks = Schema::hasColumn('beds', 'cell_bricks');
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

        if ($canStoreCellBricks) {
            $payload['cell_bricks'] = $cellBricks;
        }

        $bed = Bed::create($payload);

        if (! empty($data['cells']) && is_array($data['cells'])) {
            $this->syncBedPlantsFromCells($bed, $request->user()->id, $data['cells']);
        } elseif (is_array($plantPositions)) {
            foreach ($plantPositions as $plantId => $position) {
                Plant::query()
                    ->where('user_id', $request->user()->id)
                    ->where('id', (int) $plantId)
                    ->update([
                        'bed_id' => $bed->id,
                        'position_in_bed' => $position,
                    ]);
            }
        }

        $placedOnMap = $request->has('garden_x') && $request->has('garden_y');

        Session::flash(
            'success',
            $placedOnMap
                ? 'Peenar lisatud aiaplaanile. Muuda kuju või lohista paika.'
                : 'Peenar lisatud. Lohista see aiaplaanil õigesse kohta.',
        );
        Session::flash('created_bed_id', $bed->id);

        $redirectParams = ['gardenPlan' => $gardenPlanId];
        if (! $placedOnMap) {
            $redirectParams['bed'] = $bed->id;
        }

        return redirect()->route('map.show', $redirectParams);
    }

    public function update(Request $request, Bed $bed)
    {
        abort_unless($bed->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
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
            'cell_bricks' => ['nullable', 'array'],
            'cell_bricks.*.x' => ['required_with:cell_bricks', 'integer', 'min:0'],
            'cell_bricks.*.y' => ['required_with:cell_bricks', 'integer', 'min:0'],
            'cell_bricks.*.w' => ['required_with:cell_bricks', 'integer', 'min:1', 'max:'.self::MAX_BRICK_GRID_SPAN],
            'cell_bricks.*.h' => ['required_with:cell_bricks', 'integer', 'min:1', 'max:'.self::MAX_BRICK_GRID_SPAN],
            'cell_bricks.*.width_cm' => ['nullable', 'integer', 'min:10', 'max:500'],
            'cell_bricks.*.height_cm' => ['nullable', 'integer', 'min:10', 'max:500'],
            'cell_bricks.*.left_cm' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'cell_bricks.*.top_cm' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'cell_bricks.*.kind' => ['nullable', 'string', 'in:plantable,walkway,empty'],
            'cells.*.w' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_BRICK_GRID_SPAN],
            'cells.*.h' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_BRICK_GRID_SPAN],
            'cells.*.kind' => ['nullable', 'string', 'in:plantable,walkway,empty'],
        ]);

        $payload = array_filter($data, fn ($v) => $v !== null);
        if (Schema::hasColumn('beds', 'image_url') && $request->hasFile('image')) {
            $path = $request->file('image')->store('bed-images', 'public');
            $payload['image_url'] = "/storage/{$path}";
        }

        $plantPositions = null;

        if (
            (isset($data['layout']) && is_array($data['layout']) && ! empty($data['layout']))
            || (! empty($data['cell_bricks']) && is_array($data['cell_bricks']))
            || (! empty($data['cells']) && is_array($data['cells']))
        ) {
            $geometry = $this->resolveBedGeometry($data);
            $normalizedLayout = $geometry['layout'];
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

            $payload['rows'] = $geometry['rows'];
            $payload['columns'] = $geometry['columns'];
            $payload['layout'] = $normalizedLayout;
            if (Schema::hasColumn('beds', 'cell_bricks')) {
                $payload['cell_bricks'] = $geometry['cell_bricks'];
            } else {
                unset($payload['cell_bricks']);
            }
            $plantPositions = $geometry['plant_positions'] ?? null;
        }

        $bed->update($payload);

        if (! empty($data['cells']) && is_array($data['cells'])) {
            $this->syncBedPlantsFromCells($bed, $request->user()->id, $data['cells']);
        } elseif (is_array($plantPositions)) {
            $cellsFromPositions = [];
            foreach ($plantPositions as $plantId => $position) {
                if (! is_string($position) || ! preg_match('/^\d+,\d+$/', $position)) {
                    continue;
                }
                [$row, $column] = array_map('intval', explode(',', $position));
                $cellsFromPositions[] = [
                    'x' => $column,
                    'y' => $row,
                    'plants' => [['plant_id' => (int) $plantId, 'quantity' => 1]],
                ];
            }
            if ($cellsFromPositions !== []) {
                $this->syncBedPlantsFromCells($bed, $request->user()->id, $cellsFromPositions);
            }
        }

        return back();
    }

    public function destroy(Request $request, Bed $bed)
    {
        abort_unless($bed->user_id === $request->user()->id, 403);
        $planId = $bed->garden_plan_id;
        $bed->plants()->update(['bed_id' => null, 'position_in_bed' => null]);
        $bed->delete();

        // Ära suuna tagasi kustutatud peenra lehele (back() → /beds/{id} → 404).
        $target = $planId ? "/map/{$planId}" : route('map');

        return redirect($target)->with('success', 'Peenar eemaldatud.');
    }

    public function toggleFavorite(Request $request, Bed $bed)
    {
        abort_unless($bed->user_id === $request->user()->id, 403);

        $bed->update([
            'is_favorite' => ! $bed->is_favorite,
        ]);

        return back();
    }
}
