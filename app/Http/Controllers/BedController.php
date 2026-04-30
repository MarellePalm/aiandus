<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BedController extends Controller
{
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

        $rowCount = count($layout);
        $columnCount = max(array_map('count', $layout));

        if ($rowCount > 20 || $columnCount > 20) {
            throw ValidationException::withMessages([
                'layout' => 'Peenra maksimaalne suurus on 20 x 20 ruutu.',
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

        if ($rows > 20 || $columns > 20) {
            throw ValidationException::withMessages([
                'cells' => 'Peenra maksimaalne suurus on 20 x 20 ruutu.',
            ]);
        }

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

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:4096'],
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

        if (!empty($data['cells']) && is_array($data['cells'])) {
            $converted = $this->convertCellsToLayout($data['cells']);
            $layout = $converted['layout'];
            $rows = $converted['rows'];
            $columns = $converted['columns'];
        } elseif (isset($data['layout']) && is_array($data['layout'])) {
            $layout = $this->normalizeLayout($data['layout']);
            $this->validateNormalizedLayout($layout);
            $rows = count($layout);
            $columns = $rows > 0 ? max(array_map('count', $layout)) : 1;
        }

        $canStoreBedImage = Schema::hasColumn('beds', 'image_url');
        $imagePath = null;
        if ($canStoreBedImage && $request->hasFile('image')) {
            $imagePath = $request->file('image')->store('bed-images', 'public');
        }

        $payload = [
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'location' => $data['location'] ?? null,
            'rows' => $rows,
            'columns' => $columns,
            'layout' => $layout,
            'sort_order' => Bed::where('user_id', $request->user()->id)->max('sort_order') + 1,
        ];
        if ($canStoreBedImage) {
            $payload['image_url'] = $imagePath ? "/storage/{$imagePath}" : null;
        }

        $bed = Bed::create($payload);

        Session::flash('success', 'Peenar lisatud.');

        return redirect()->route('beds.show', $bed);
    }

    public function update(Request $request, Bed $bed)
    {
        abort_unless($bed->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:4096'],
            'cells' => ['nullable', 'array'],
            'cells.*.x' => ['required_with:cells', 'integer'],
            'cells.*.y' => ['required_with:cells', 'integer'],
            'cells.*.plants' => ['nullable', 'array'],
            'cells.*.plants.*.plant_id' => ['nullable', 'integer'],
            'cells.*.plants.*.quantity' => ['nullable', 'integer', 'min:1'],
            'cells.*.plants.*.size' => ['nullable', 'string', 'max:20'],
            'cells.*.plants.*.note' => ['nullable', 'string', 'max:255'],
            'rows' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'columns' => ['sometimes', 'integer', 'min:1', 'max:12'],
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

        if (!empty($data['cells']) && is_array($data['cells'])) {
            $converted = $this->convertCellsToLayout($data['cells']);
            $payload['rows'] = $converted['rows'];
            $payload['columns'] = $converted['columns'];
            $payload['layout'] = $converted['layout'];
            $plantPositions = $converted['plant_positions'];
        } elseif (isset($data['layout']) && is_array($data['layout']) && !empty($data['layout'])) {
            $normalizedLayout = $this->normalizeLayout($data['layout']);
            $this->validateNormalizedLayout($normalizedLayout);
            $rowCount = count($normalizedLayout);
            $colCount = $rowCount > 0 ? max(array_map('count', $normalizedLayout)) : 0;

            foreach ($bed->plants()->select('id', 'position_in_bed')->get() as $plant) {
                $pos = $plant->position_in_bed;
                if (!is_string($pos) || !preg_match('/^\d+,\d+$/', $pos)) {
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
        }

        $bed->update($payload);

        if (is_array($plantPositions)) {
            $bedPlants = $bed->plants()->select('id', 'position_in_bed')->get()->keyBy('id');

            foreach ($bedPlants as $plantId => $plant) {
                if (!array_key_exists($plantId, $plantPositions)) {
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
