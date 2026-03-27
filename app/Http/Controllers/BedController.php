<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Schema;

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

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:4096'],
            'layout' => ['nullable', 'array'],
            'layout.*' => ['array'],
            // -1 = vahekäik / tee / kivi, 0 = tühi, 1 = peenraruut
            'layout.*.*' => ['integer', 'in:-1,0,1'],
        ]);

        $layout = isset($data['layout']) && is_array($data['layout'])
            ? $this->normalizeLayout($data['layout'])
            : null;
        $rows = 1;
        $columns = 1;
        if (!empty($layout) && is_array($layout)) {
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
        if (isset($data['layout']) && is_array($data['layout']) && !empty($data['layout'])) {
            $normalizedLayout = $this->normalizeLayout($data['layout']);
            $rowCount = count($normalizedLayout);
            $colCount = $rowCount > 0 ? max(array_map('count', $normalizedLayout)) : 0;

            // Kui peenras on taim kindla positsiooniga, hoia see ruut alati peenrana (1),
            // et taim ei "kaoks" layouti muutmise järel.
            foreach ($bed->plants()->select('id', 'position_in_bed')->get() as $plant) {
                $pos = $plant->position_in_bed;
                if (!is_string($pos) || !preg_match('/^\d+,\d+$/', $pos)) {
                    continue;
                }
                [$r, $c] = array_map('intval', explode(',', $pos));
                if ($r >= 0 && $c >= 0 && $r < $rowCount && $c < $colCount) {
                    if (!isset($normalizedLayout[$r])) {
                        $normalizedLayout[$r] = [];
                    }
                    $normalizedLayout[$r][$c] = 1;
                }
            }

            $payload['rows'] = count($normalizedLayout);
            $payload['columns'] = max(array_map('count', $normalizedLayout));
            $payload['layout'] = $normalizedLayout;
        }
        $bed->update($payload);
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
