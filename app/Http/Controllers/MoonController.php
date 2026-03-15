<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MoonController extends Controller
{
    private const SYNODIC_MONTH = 29.530588853;

    private const REF_NEW_MOON_UTC = '2000-01-06 18:14:00';

    private const SIDEREAL_MONTH = 27.321661;

    private const TROPICAL_YEAR = 365.2422;

    private const JD_VERNAL_2000 = 2451623.81;

    private const PHASES_ET = [
        'Uuskuu',
        'Kasvav sirp',
        'Esimene veerand',
        'Kasvav kumer kuu',
        'Täiskuu',
        'Kahanev kumer kuu',
        'Viimane veerand',
        'Kahanev sirp',
    ];

    private const ZODIAC_NAMES_ET = [
        'Jäär', 'Sõnn', 'Kaksikud', 'Vähk', 'Lõvi', 'Neitsi',
        'Kaalud', 'Skorpion', 'Ambur', 'Kaljukits', 'Veevalaja', 'Kalad',
    ];

    /** @var array<int, string> leaf|fruit|root|flower */
    private const SIGN_TO_DAY_TYPE = [
        'fruit', 'root', 'flower', 'leaf', 'fruit', 'root',
        'flower', 'leaf', 'fruit', 'root', 'flower', 'leaf',
    ];

    /** @var array<string, array{label: string, description: string}> */
    private const BIODYNAMIC_ET = [
        'leaf' => [
            'label' => 'lehepäev',
            'description' => 'Hea aeg lehtköögiviljade külviks ja korjamiseks, muru niitmiseks ning toataimede kastmiseks ja väetamiseks.',
        ],
        'fruit' => [
            'label' => 'viljapäev',
            'description' => 'Hea aeg viljade ja seemnetaimede hoolduseks, muru külviks ning viljapuude lõikamiseks ja väetamiseks.',
        ],
        'root' => [
            'label' => 'juurepäev',
            'description' => 'Hea aeg juur- ja köögiviljade külviks, kompostimiseks, rohimiseks ja saagi säilitamiseks.',
        ],
        'flower' => [
            'label' => 'õiepäev',
            'description' => 'Hea aeg õistaimede ja ravimtaimede külviks, istutamiseks ja väetamiseks; sobib ka leivaküpsetamiseks.',
        ],
    ];

    /** @var array<string, array{tasks: list<string>, notes?: list<string>}> */
    private const PHASE_RULES = [
        'Uuskuu' => ['tasks' => ['planeeri', 'korrasta', 'valmista peenrad ette']],
        'Kasvav sirp' => [
            'tasks' => ['külv ja istutamine', 'kerge pügamine', 'võsude võtmine'],
            'notes' => ['Eriti hea aeg lehtköögiviljade ja maapealsete taimede jaoks.'],
        ],
        'Esimene veerand' => ['tasks' => ['toesta', 'väeta', 'kasta mõõdukalt', 'niitmine']],
        'Kasvav kumer kuu' => [
            'tasks' => ['toesta', 'väeta', 'hoolda', 'kerge pügamine (kasvu soodustamiseks)'],
            'notes' => ['Istutamine vahetult enne täiskuud võib soodustada juurdumist.'],
        ],
        'Täiskuu' => [
            'tasks' => ['korja saaki', 'vaatle taimi', 'planeeri kahaneva kuu töid'],
            'notes' => ['Hea aeg korjata vilju ja seemneid.'],
        ],
        'Kahanev kumer kuu' => [
            'tasks' => ['püga', 'eemalda taimede haiged osad', 'korista', 'istuta püsikuid'],
            'notes' => ['Sobib töödeks, kus soovid kasvu pidurdada.'],
        ],
        'Viimane veerand' => [
            'tasks' => ['rohi', 'ümber istuta', 'korista ja ladusta', 'väeta mõõdukalt'],
            'notes' => ['Hea aeg ettevalmistuseks enne uuskuu puhkeperioodi.'],
        ],
        'Kahanev sirp' => [
            'tasks' => ['puhasta', 'korrasta peenrad', 'hoolda tööriistu', 'valmista kompost'],
            'notes' => ['Hea aeg lõpetada pooleliolevad tööd ja teha korda aed.'],
        ],
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $dateStr = $request->input('date');
        $date = $dateStr ? Carbon::parse($dateStr) : now();
        $date->setTimezone('UTC');

        $moonInfo = $this->moonInfo($date);
        $advice = $this->moonAdvice($moonInfo['phase']);
        $zodiac = $this->zodiacInfo($date);

        return response()->json([
            'phase' => $moonInfo['phase'],
            'phaseIndex' => $moonInfo['phaseIndex'],
            'illumination' => $moonInfo['illumination'],
            'ageDays' => $moonInfo['ageDays'],
            'title' => $advice['title'],
            'tasks' => $advice['tasks'],
            'textLong' => $advice['textLong'],
            'moonSign' => $zodiac['moonSign'],
            'biodynamicDescription' => $zodiac['biodynamicDescription'],
        ]);
    }

    /**
     * @return array{phase: string, phaseIndex: int, illumination: float, ageDays: float}
     */
    private function moonInfo(Carbon $date): array
    {
        $jd = $this->julianDayUTC($date);
        $ref = Carbon::parse(self::REF_NEW_MOON_UTC, 'UTC');
        $jdRef = $this->julianDayUTC($ref);

        $daysSinceRef = $jd - $jdRef;
        $ageDays = fmod(fmod($daysSinceRef, self::SYNODIC_MONTH) + self::SYNODIC_MONTH, self::SYNODIC_MONTH);
        $t = $ageDays / self::SYNODIC_MONTH;

        $illumination = 0.5 * (1 - cos(2 * M_PI * $t));
        $phaseIndex = (int) (fmod(floor($t * 8 + 0.5) + 8, 8));
        $phase = self::PHASES_ET[$phaseIndex];

        return [
            'phase' => $phase,
            'phaseIndex' => $phaseIndex,
            'illumination' => round($illumination, 4),
            'ageDays' => round($ageDays, 2),
        ];
    }

    /**
     * @return array{title: string, tasks: list<string>, textLong: string}
     */
    private function moonAdvice(string $phase): array
    {
        $rule = self::PHASE_RULES[$phase] ?? self::PHASE_RULES['Uuskuu'];
        $tasks = array_values(array_unique(array_map('trim', array_filter($rule['tasks'] ?? []))));
        $notes = $rule['notes'] ?? [];
        $textLong = implode(' ', array_unique(array_map('trim', array_filter($notes))));

        return [
            'title' => $phase,
            'tasks' => $tasks,
            'textLong' => $textLong,
        ];
    }

    /**
     * @return array{moonSign: string, biodynamicDescription: string}
     */
    private function zodiacInfo(Carbon $date): array
    {
        $moonLon = $this->moonLongitudeDeg($date);
        $signIndex = $this->longitudeToSignIndex($moonLon);
        $moonSign = self::ZODIAC_NAMES_ET[$signIndex];
        $dayType = self::SIGN_TO_DAY_TYPE[$signIndex];
        $biodynamicDescription = self::BIODYNAMIC_ET[$dayType]['description'];

        return [
            'moonSign' => $moonSign,
            'biodynamicDescription' => $biodynamicDescription,
        ];
    }

    private function julianDayUTC(Carbon $date): float
    {
        $y = (int) $date->format('Y');
        $m = (int) $date->format('n');
        $d = (int) $date->format('j')
            + ($date->format('G') + ($date->format('i') + $date->format('s') / 60) / 60) / 24;

        $a = (int) floor((14 - $m) / 12);
        $y2 = $y + 4800 - $a;
        $m2 = $m + 12 * $a - 3;

        return $d
            + floor((153 * $m2 + 2) / 5)
            + 365 * $y2
            + floor($y2 / 4)
            - floor($y2 / 100)
            + floor($y2 / 400)
            - 32045;
    }

    private function sunLongitudeDeg(Carbon $date): float
    {
        $jd = $this->julianDayUTC($date);
        $deg = (($jd - self::JD_VERNAL_2000) / self::TROPICAL_YEAR) * 360;

        return fmod(fmod($deg, 360) + 360, 360);
    }

    private function moonLongitudeDeg(Carbon $date): float
    {
        $jd = $this->julianDayUTC($date);
        $ref = Carbon::parse(self::REF_NEW_MOON_UTC, 'UTC');
        $jdRef = $this->julianDayUTC($ref);
        $sunLonAtRef = $this->sunLongitudeDeg($ref);
        $daysSinceRef = $jd - $jdRef;
        $deg = $sunLonAtRef + ($daysSinceRef / self::SIDEREAL_MONTH) * 360;

        return fmod(fmod($deg, 360) + 360, 360);
    }

    private function longitudeToSignIndex(float $longitudeDeg): int
    {
        $i = (int) floor($longitudeDeg / 30) % 12;

        return $i >= 0 ? $i : $i + 12;
    }
}
