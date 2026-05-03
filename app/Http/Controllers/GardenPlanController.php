<?php

namespace App\Http\Controllers;

use App\Models\GardenPlan;
use Illuminate\Http\Request;

class GardenPlanController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
        ]);

        $plan = GardenPlan::query()->create([
            'user_id' => $request->user()->id,
            'name' => trim((string) ($data['name'] ?? '')) ?: 'Minu aed',
            'width' => 1200,
            'height' => 800,
            'unit' => 'cm',
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

        $gardenPlan->update([
            'name' => trim((string) ($data['name'] ?? $gardenPlan->name)) ?: 'Minu aed',
            'width' => $data['width'],
            'height' => $data['height'],
        ]);

        return back()->with('success', 'Aia mõõdud uuendatud.');
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
