<?php

namespace App\Http\Controllers;

use App\Models\Bed;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BedController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'layout' => ['nullable', 'array'],
            'layout.*' => ['array'],
            'layout.*.*' => ['integer', 'in:0,1'],
        ]);

        $layout = $data['layout'] ?? null;
        $rows = 1;
        $columns = 1;
        if (!empty($layout) && is_array($layout)) {
            $rows = count($layout);
            $columns = $rows > 0 ? max(array_map('count', $layout)) : 1;
        }

        $bed = Bed::create([
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'location' => $data['location'] ?? null,
            'rows' => $rows,
            'columns' => $columns,
            'layout' => $layout,
            'sort_order' => Bed::where('user_id', $request->user()->id)->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Peenar lisatud.');
    }

    public function update(Request $request, Bed $bed)
    {
        abort_unless($bed->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:255'],
            'rows' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'columns' => ['sometimes', 'integer', 'min:1', 'max:12'],
            'layout' => ['nullable', 'array'],
            'layout.*' => ['array'],
            'layout.*.*' => ['integer', 'in:0,1'],
        ]);

        $payload = array_filter($data, fn ($v) => $v !== null);
        if (isset($data['layout']) && is_array($data['layout']) && !empty($data['layout'])) {
            $payload['rows'] = count($data['layout']);
            $payload['columns'] = max(array_map('count', $data['layout']));
            $payload['layout'] = $data['layout'];
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
