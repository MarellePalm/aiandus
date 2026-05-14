// resources/js/lib/moon/moon.ts
/**
 * Kuufaasi arvutus: Jean Meeus „Astronomical Algorithms“ keskmine Päikese–Kuu elongatsioon (D),
 * mitte lihtsalt sünoodiline modulo (see triivis aastatega).
 * Kalendripäev = kohalik keskpäev, et kuupäev ei nihkuks UTC tõttu (sarnaselt ilm.pri.ee kalendriga).
 * @see https://ilm.pri.ee/kuufaaside-kalender
 */

export type MoonPhase8 =
    | 'Uuskuu'
    | 'Kasvav sirp'
    | 'Esimene veerand'
    | 'Kasvav kumer kuu'
    | 'Täiskuu'
    | 'Kahanev kumer kuu'
    | 'Viimane veerand'
    | 'Kahanev sirp';

export type MoonInfo = {
    phase: MoonPhase8;
    illumination: number; // 0..1
    phaseIndex: number; // 0..7
    ageDays: number; // 0..29.53
    /** Lunatsiooni osakaal 0..1 (uuskuu→uuskuu); ikooni jaoks üks pidev geomeetria. */
    lunationT: number;
};

/** Sama kalendripäeva kuuinfo ei arvuta uuesti (kalendrivaated korduvad kuupäevadega). */
const moonInfoByLocalDateKey = new Map<string, MoonInfo>();

function moonInfoCacheKey(date: Date): string {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');

    return `${y}-${m}-${d}`;
}

/** Keskmine sünoodiline kuu (päevades); kasutusel ka pärimusliku ajastuse jaoks. */
export const SYNODIC_MONTH = 29.530588853;

/** Juliani päev kohaliku kalendripäeva keskpäeval (vähendab UTC/piirkonna servavigu). */
function julianDayLocalNoon(date: Date): number {
    const noon = new Date(
        date.getFullYear(),
        date.getMonth(),
        date.getDate(),
        12,
        0,
        0,
    );
    const y = noon.getFullYear();
    const m = noon.getMonth() + 1;
    const d =
        noon.getDate() +
        (noon.getHours() + (noon.getMinutes() + noon.getSeconds() / 60) / 60) /
            24;

    const a = Math.floor((14 - m) / 12);
    const y2 = y + 4800 - a;
    const m2 = m + 12 * a - 3;

    return (
        d +
        Math.floor((153 * m2 + 2) / 5) +
        365 * y2 +
        Math.floor(y2 / 4) -
        Math.floor(y2 / 100) +
        Math.floor(y2 / 400) -
        32045
    );
}

/** Keskmine Kuu–Päike elongatsioon kraadides (Meeus, tabel 47.1). */
function meanElongationMoonSunDegrees(jd: number): number {
    const T = (jd - 2451545.0) / 36525.0;
    let D =
        297.8501921 +
        445267.1114034 * T -
        0.0018819 * T * T +
        (T * T * T) / 545868 -
        (T * T * T * T) / 113065000;
    D %= 360;
    if (D < 0) D += 360;
    return D;
}

export function getMoonInfo(date = new Date()): MoonInfo {
    const key = moonInfoCacheKey(date);
    const cached = moonInfoByLocalDateKey.get(key);
    if (cached) {
        return cached;
    }

    const jd = julianDayLocalNoon(date);
    const D = meanElongationMoonSunDegrees(jd);
    /** 0 = uuskuu, 0,5 = täiskuu (keskmine elongatsioon → lunatsiooni asend) */
    const t = D / 360;
    const ageDays = t * SYNODIC_MONTH;

    const illumination = 0.5 * (1 - Math.cos(2 * Math.PI * t));

    let phaseIndex = ((Math.floor(t * 8 + 0.5) % 8) + 8) % 8;
    const fullMoonHalfWidthT = 1 / SYNODIC_MONTH;
    if (phaseIndex === 4 && Math.abs(t - 0.5) > fullMoonHalfWidthT) {
        phaseIndex = t < 0.5 ? 3 : 5;
    }

    const phases: MoonPhase8[] = [
        'Uuskuu',
        'Kasvav sirp',
        'Esimene veerand',
        'Kasvav kumer kuu',
        'Täiskuu',
        'Kahanev kumer kuu',
        'Viimane veerand',
        'Kahanev sirp',
    ];

    const info: MoonInfo = {
        phase: phases[phaseIndex],
        illumination,
        phaseIndex,
        ageDays,
        lunationT: t,
    };
    moonInfoByLocalDateKey.set(key, info);

    return info;
}
