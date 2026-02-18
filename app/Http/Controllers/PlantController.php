<?php

namespace App\Http\Controllers;

use App\Models\Plant;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PlantController extends Controller
{
    public function index()
    {
        $categories = [
        ['id' => 1, 'name' => 'Tomatid', 'slug' => 'tomatid', 'count' => 12, 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBiSMT8MRygYSTYMB9DNDMCEvoj0H4EuvzGpTM6jo5yerPI2p4Ksx9M2YLBW4ghQBh3sxQSC5RJ182BuqnUM6NSw2lFp7qV-JdchgoafAOg-4H5u79XUTHBAuyWbdsEeZFA3liIrXgnaPL7_-4M3PVEDjP0dD-D35yfNSXtDbUdJf9b1I-NBsOpkpDiKhaHei3VMSYVm5KAhsmHgL2d6uyknTOqUKRo6ZiPOb6cCF8GkZaZkMSjganwT44GB5k_mcga8ZHOEKa147ib'],
        ['id' => 2, 'name' => 'Kurgid', 'slug' => 'kurgid', 'count' => 5, 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDUxO_J_VQlR5nPBiLjJuKC3TGuOuf5qzHgAlXYpe2LxtRsP8qxIepQdY8oeTGTfVsh1yQ3TqIY12DzHI_9T4hBOuqJIBjSgt1-PtJlwUFWEiU4cuS9sykfT2eU_BWanjrLi5fUSR0btRrvOwnF6ZSlxyZ0qBNZxtPHFugpkQfCfIlYVKYF-fEg1ueCshz2PmVpEv46OHbBJt_wye_-imKohE8jUJXCT9Mr6vKy5WlDvo0JfsmLCGXRV59IK6BZ8oBQioH_yYnwF6jx'],
        ['id' => 3, 'name' => 'Kõrvitsad', 'slug' => 'korvitsad-kabatsokid', 'count' => 8, 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuBOzpo2qyOQ7Vc5aRongVnCq-m8fHbpCNbZtQO-78U8Mqx5dmnmqJSAioFupcpnsx5DCOsdi4J0KwJfcmuQazLhU11YiA4eFJFHlSeX2-FjisRW5mD8EkhHvZa-9KBITgWRU91cVfoJ1kDFymU8Nlaqvy9yAPWtneU2BUP2cEsLAYBa97NBOQ9AWODYUdLwzREdcZtn2jkJJbByxqwujSp9dxp1LqZc5eIx3KR9IqHaJwwA4guIjkGsu78jnYga1ccAuQ_SapVETdte'],
        ['id' => 4, 'name' => 'Lilled', 'slug' => 'lilled', 'count' => 24, 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuDSOpzjcZzmh1GeboogcLyLpgHGXhkN_5aA0qTHmzPXFp7rZO4x1xEGuKhxijAmddUc2gX_Jpbbh3iORfkL9DrYQ9GdOEr_i7VlgU92zanA7TxXfvNzxLvaTVgcTA0u-rxiqbwsCgWos1zSI0mK3k-sdVU-YfCUm3mfttMNErlpdgLRpdxLXrkLtzeYXhkI6Dm_fi7HxEij1B8VdHrOkRboGEd_Xvrji_YxenhpAhZQt7SLvF-RJm9jnc000-0zMRw-cWBO0PsaaZvW'],
        ['id' => 5, 'name' => 'Põõsad', 'slug' => 'poosad', 'count' => 10, 'image' => 'https://lh3.googleusercontent.com/aida-public/AB6AXuAGtzF38dID7SJTIHO8Olk93x3olZEncPqrrOU0OUM2kh_G1mLu0RgLXjtDA13OQtfZxRrOqDvkC4_xzrcpvHy57v9hgyE6nZSul-Vq6vCBYmg0j-hVpjtdt1iV3WZDM75nrOkiiqITm-aM62ZFqvJ-bY31D1swjpBIFcQLKgsiJfWdC38MYCOEWk_KGGWpM9FP2o0eck09i1uuNQ2cSctDjpq0x4t4DzNhVHPc-c3785jZu4qV6CtTZ1cN4ttqVRuTcqwqL4cXzdv1'],
        ['id' => 6, 'name' => 'Maitsetaimed', 'slug' => 'maitsetaimed', 'count' => 15, 'image' => 'https://sofikoh.ee/wp-content/uploads/2025/05/fresh-herbs-basil-marjoram-parsley-rosemary-thyme-sage_large-1024x768.jpg'],
        
    ];


        return Inertia::render('Plants/Index', [
            'categories' => $categories,
        ]);
    }
    
    public function show(Request $request, Plant $plant)
{
    abort_unless($plant->user_id === $request->user()->id, 403);

    return Inertia::render('Plants/Show', [
        'plant' => [
            'id' => $plant->id,
            'name' => $plant->name,
            'subtitle' => $plant->subtitle,
            'image_url' => $plant->image_url,
            'notes' => $plant->notes,
            'tags' => $plant->tags,
            'watering_in_days' => $plant->watering_in_days,
            'fertilizing_frequency' => $plant->fertilizing_frequency,
            'next_fertilizing_label' => $plant->next_fertilizing_label,
        ],
    ]);
}

public function water(Request $request, Plant $plant)
{
    abort_unless($plant->user_id === $request->user()->id, 403);

    $plant->update([
        'last_watered_at' => now(),
    ]);

    // jää detailvaatele
    return redirect()->route('plants.show', $plant->id);
}

public function create()
{
    return Inertia::render('Plants/Create');
}

public function store(Request $request)
{
    $data = $request->validate([
        'name' => ['required', 'string', 'max:120'],
        'subtitle' => ['required', 'string', 'max:160'],
        'planted_at' => ['required', 'date'],
    ]);

    $plant = Plant::create([
        ...$data,
        'user_id' => $request->user()->id,
    ]);

    return redirect()->route('plants.show', $plant->id);
}

public function destroy(Plant $plant)
{
    $plant->delete();
    return redirect('/dashboard'); // või back()
}


}
