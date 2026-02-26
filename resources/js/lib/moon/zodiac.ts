// resources/js/lib/zodiac.ts – päikese ja kuu tähtkujud (tropiline sodiaak)
// Biodünaamika päevatüübid: Leaf/Fruit/Root/Flower days

const ZODIAC_NAMES_ET = [
  'Jäär',
  'Sõnn',
  'Kaksikud',
  'Vähk',
  'Lõvi',
  'Neitsi',
  'Kaalud',
  'Skorpion',
  'Ambur',
  'Kaljukits',
  'Veevalaja',
  'Kalad',
] as const;

export type ZodiacSignName = (typeof ZODIAC_NAMES_ET)[number];

/** Biodünaamika päevatüüp kuu tähtkuju järgi (Maria Thun / aiapäevik). */
export type BiodynamicDayType = 'leaf' | 'fruit' | 'root' | 'flower';

type BiodynamicInfoEt = Readonly<{
  label: string;
  element: 'vesi' | 'tuli' | 'maa' | 'õhk';
  crops: readonly string[];
  tasks: readonly string[];
  notes?: readonly string[];
}>;

// Leht (vesi): Vähk, Skorpion, Kalad. Vili (tuli): Jäär, Lõvi, Ambur. Juur (maa): Sõnn, Neitsi, Kaljukits. Õis (õhk): Kaksikud, Kaalud, Veevalaja
const SIGN_TO_DAY_TYPE: BiodynamicDayType[] = [
  'fruit', // 0 Jäär
  'root', // 1 Sõnn
  'flower', // 2 Kaksikud
  'leaf', // 3 Vähk
  'fruit', // 4 Lõvi
  'root', // 5 Neitsi
  'flower', // 6 Kaalud
  'leaf', // 7 Skorpion
  'fruit', // 8 Ambur
  'root', // 9 Kaljukits
  'flower', // 10 Veevalaja
  'leaf', // 11 Kalad
];

const BIODYNAMIC_ET: Record<BiodynamicDayType, BiodynamicInfoEt> = {
  leaf: {
    label: 'lehepäev',
    element: 'vesi',
    crops: ['salat', 'kapsas', 'spinat', 'seller', 'maitseroheline'],
    tasks: ['külv', 'istutamine', 'kastmine', 'niitmine'],
    notes: ['Lehtköögivilju korista pigem õie- või viljapäeval.'],
  },
  fruit: {
    label: 'viljapäev',
    element: 'tuli',
    crops: ['tomat', 'paprika', 'oad', 'herned', 'mais', 'kõrvits'],
    tasks: ['külv', 'istutamine', 'võrsete näpistamine', 'võsude eemaldamine'],
    notes: ['Viljapäeval korjatud saak säilib sageli paremini.'],
  },
  root: {
    label: 'juurepäev',
    element: 'maa',
    crops: ['porgand', 'kartul', 'sibul', 'peet', 'küüslauk'],
    tasks: ['külv', 'istutamine', 'muldamine', 'väetamine'],
    notes: ['Saaki on hea ladustada juurtepäeval.'],
  },
  flower: {
    label: 'õiepäev',
    element: 'õhk',
    crops: ['lilled', 'maitsetaimed'],
    tasks: ['lilleistutused', 'maitsetaimede koristus', 'lõikelillede lõikamine'],
    notes: ['Lõikelilled püsivad kauem. Lehtköögivilju korista pigem õie- või viljapäeval.'],
  },
} as const;

function formatHintEt(info: BiodynamicInfoEt): string {
  return `Märksõnad: ${info.element}; ${info.crops.join(', ')}; tööd: ${info.tasks.join(', ')}.`;
}

function formatDescriptionEt(info: BiodynamicInfoEt): string {
  const notes = info.notes?.length ? ` Märkus: ${info.notes.join(' ')}` : '';
  // Kirjelduses ei korda label'it (nt "õiepäev"), see on juba pealkirja reas.
  return `${info.element} • kultuurid: ${info.crops.join(
    ', ',
  )} • tööd: ${info.tasks.join(', ')}.${notes}`;
}

// Tropiline aasta (päevades)
const TROPICAL_YEAR = 365.2422;
// Kuu sideraalkuu (päevades; 360° ekliptikal)
const SIDEREAL_MONTH = 27.321661;
// Viide: uuskuu 6. Jan 2000 18:14 UTC
const REF_NEW_MOON_UTC = Date.UTC(2000, 0, 6, 18, 14, 0);
// 2000. aasta kevadine pööripäev – JD 2451623.81
const JD_VERNAL_2000 = 2451623.81;

function julianDayUTC(date: Date): number {
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

/** Päikese ekliptikapikkus (0..360), tropiline sodiaak. */
function sunLongitudeDeg(date: Date): number {
  const jd = julianDayUTC(date);
  const deg = ((jd - JD_VERNAL_2000) / TROPICAL_YEAR) * 360;
  return ((deg % 360) + 360) % 360;
}

/** Kuu ekliptikapikkus (0..360). */
function moonLongitudeDeg(date: Date): number {
  const jd = julianDayUTC(date);
  const jdRef = julianDayUTC(new Date(REF_NEW_MOON_UTC));
  const sunLonAtRef = sunLongitudeDeg(new Date(REF_NEW_MOON_UTC));
  const daysSinceRef = jd - jdRef;
  const deg = sunLonAtRef + (daysSinceRef / SIDEREAL_MONTH) * 360;
  return ((deg % 360) + 360) % 360;
}

function longitudeToSignIndex(longitudeDeg: number): number {
  // 0° = Jäär, 30° = Sõnn, ...
  const i = Math.floor(longitudeDeg / 30) % 12;
  return i >= 0 ? i : i + 12;
}

/** Päikese tähtkuju antud kuupäeva järgi (eesti keeles). */
export function getSunSign(date = new Date()): ZodiacSignName {
  return ZODIAC_NAMES_ET[longitudeToSignIndex(sunLongitudeDeg(date))];
}

/** Kuu tähtkuju antud kuupäeva järgi (eesti keeles). */
export function getMoonSign(date = new Date()): ZodiacSignName {
  return ZODIAC_NAMES_ET[longitudeToSignIndex(moonLongitudeDeg(date))];
}

/** Biodünaamika päevatüüp kuu tähtkuju põhjal. */
export function getBiodynamicDayType(date = new Date()): BiodynamicDayType {
  const signIndex = longitudeToSignIndex(moonLongitudeDeg(date));
  return SIGN_TO_DAY_TYPE[signIndex];
}

/** Biodünaamika päevatüübi eestikeelne nimetus. */
export function getBiodynamicDayLabel(dayType: BiodynamicDayType): string {
  return BIODYNAMIC_ET[dayType].label;
}

/** Lühike aiandusnõuanne (märksõnad). */
export function getBiodynamicHint(dayType: BiodynamicDayType): string {
  return formatHintEt(BIODYNAMIC_ET[dayType]);
}

/** Lühike kirjeldus (märksõnade-põhine). */
export function getBiodynamicDescription(dayType: BiodynamicDayType): string {
  return formatDescriptionEt(BIODYNAMIC_ET[dayType]);
}

/** Märksõnad UI jaoks (struktureeritult). */
export function getBiodynamicKeywords(dayType: BiodynamicDayType): BiodynamicInfoEt {
  return BIODYNAMIC_ET[dayType];
}

/** Tähtkujud + biodünaamika info (UI jaoks). */
export function getZodiacInfo(date = new Date()) {
  const moonSign = getMoonSign(date);
  const biodynamicDay = getBiodynamicDayType(date);
  const info = BIODYNAMIC_ET[biodynamicDay];

  return {
    sunSign: getSunSign(date),
    moonSign,
    biodynamicDayType: biodynamicDay,
    biodynamicDayLabel: info.label,
    biodynamicHint: formatHintEt(info),
    biodynamicDescription: formatDescriptionEt(info),
  };
}