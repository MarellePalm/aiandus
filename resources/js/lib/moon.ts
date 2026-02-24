// resources/js/lib/moon.ts
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
};

const SYNODIC_MONTH = 29.530588853; // days (mean synodic month)
// Lunation 0 per moon-time.org: 6 Jan 2000 18:14 UTC
const REF_NEW_MOON_UTC = Date.UTC(2000, 0, 6, 18, 14, 0);
// Kui faas on ~2° ees: kasuta nt -0.16 (2/360 * 29.53 ≈ 0.164 päeva)
const PHASE_CORRECTION_DAYS = 0;

function julianDayUTC(date: Date) {
  const y = date.getUTCFullYear();
  const m = date.getUTCMonth() + 1;
  const d =
    date.getUTCDate() +
    (date.getUTCHours() + (date.getUTCMinutes() + date.getUTCSeconds() / 60) / 60) / 24;

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

export function getMoonInfo(date = new Date()): MoonInfo {
  const jd = julianDayUTC(date);
  const jdRef = julianDayUTC(new Date(REF_NEW_MOON_UTC));

  const daysSinceRef = jd - jdRef;
  let ageDays = ((daysSinceRef % SYNODIC_MONTH) + SYNODIC_MONTH) % SYNODIC_MONTH;
  ageDays = (ageDays + PHASE_CORRECTION_DAYS + SYNODIC_MONTH) % SYNODIC_MONTH; // 0..29.53
  const t = ageDays / SYNODIC_MONTH; // 0..1

  // illumination approx: 0 new, 1 full
  const illumination = 0.5 * (1 - Math.cos(2 * Math.PI * t));

  // 8-phase binning (nice UX)
  const phaseIndex = (Math.floor(t * 8 + 0.5) % 8 + 8) % 8;

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

  return { phase: phases[phaseIndex], illumination, phaseIndex, ageDays };
}
