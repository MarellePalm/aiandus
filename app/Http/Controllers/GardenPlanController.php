<?php

namespace App\Http\Controllers;

use App\Models\GardenPlan;
use Illuminate\Http\Request;

class GardenPlanController extends Controller
{
    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'width' => ['required', 'integer', 'min:200', 'max:100000'],
            'height' => ['required', 'integer', 'min:200', 'max:100000'],
        ], [
            'width.required' => 'Aia laius (meetrites) on kohustuslik.',
            'width.integer' => 'Aia laius peab olema täisarv sentimeetrites.',
            'width.min' => 'Laius peab olema vähemalt 2 m (200 cm).',
            'width.max' => 'Sisestatud laius on selle vaate jaoks liiga suur. Maksimaalne suurus on 1 km.',
            'height.required' => 'Aia sügavus/kõrgus (meetrites) on kohustuslik.',
            'height.integer' => 'Aia sügavus peab olema täisarv sentimeetrites.',
            'height.min' => 'Sügavus peab olema vähemalt 2 m (200 cm).',
            'height.max' => 'Sisestatud kõrgus on selle vaate jaoks liiga suur. Maksimaalne suurus on 1 km.',
        ]);

        $plan = GardenPlan::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'name' => 'Minu aed',
                'width' => 1200,
                'height' => 800,
                'unit' => 'cm',
            ],
        );

        $plan->update([
            'name' => trim((string) ($data['name'] ?? $plan->name)) ?: 'Minu aed',
            'width' => $data['width'],
            'height' => $data['height'],
        ]);

        return back()->with('success', 'Aia mõõdud uuendatud.');
    }
}
