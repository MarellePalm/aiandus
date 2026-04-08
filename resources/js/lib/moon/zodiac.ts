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

/** „Kuu on …“ — kohakääne (Jääras, Sõnnis, …). */
export const MOON_SIGN_INESSIVE_ET: Record<ZodiacSignName, string> = {
  Jäär: 'Jääras',
  Sõnn: 'Sõnnis',
  Kaksikud: 'Kaksikutes',
  Vähk: 'Vähis',
  Lõvi: 'Lõvis',
  Neitsi: 'Neitsis',
  Kaalud: 'Kaaludes',
  Skorpion: 'Skorpionis',
  Ambur: 'Amburis',
  Kaljukits: 'Kaljukitses',
  Veevalaja: 'Veevalajas',
  Kalad: 'Kalades',
};

/** Biodünaamika päevatüüp kuu tähtkuju järgi (Maria Thun / aiapäevik). */
export type BiodynamicDayType = 'leaf' | 'fruit' | 'root' | 'flower';

type BiodynamicInfoEt = Readonly<{
  label: string;
  element: 'vesi' | 'tuli' | 'maa' | 'õhk';
  crops: readonly string[];
  tasks: readonly string[];
  description: string;
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
    description:
      'Hea aeg lehtköögiviljade külviks ja korjamiseks, muru niitmiseks ning toataimede kastmiseks ja väetamiseks.',
    notes: ['Lehtköögivilju on parem koristada õie- või viljapäeval.'],
  },
  fruit: {
    label: 'viljapäev',
    element: 'tuli',
    crops: ['tomat', 'paprika', 'oad', 'herned', 'mais', 'kõrvits'],
    tasks: ['külv', 'istutamine', 'võrsete näpistamine', 'võsude eemaldamine'],
    description:
      'Hea aeg viljade ja seemnetaimede hoolduseks, muru külviks ning viljapuude lõikamiseks ja väetamiseks.',
    notes: ['Viljapäeval korjatud saak säilib sageli eriti hästi.'],
  },
  root: {
    label: 'juurepäev',
    element: 'maa',
    crops: ['porgand', 'kartul', 'sibul', 'peet', 'küüslauk'],
    tasks: ['külv', 'istutamine', 'muldamine', 'väetamine'],
    description:
      'Hea aeg juur- ja köögiviljade külviks, kompostimiseks, rohimiseks ja saagi säilitamiseks.',
    notes: ['Juurvilju on hea ladustada juurtepäeval.'],
  },
  flower: {
    label: 'õiepäev',
    element: 'õhk',
    crops: ['lilled', 'maitsetaimed'],
    tasks: ['lilleistutused', 'maitsetaimede koristus', 'lõikelillede lõikamine'],
    description:
      'Hea aeg õistaimede ja ravimtaimede külviks, istutamiseks ja väetamiseks; sobib ka leivaküpsetamiseks.',
    notes: ['Õiepäeval lõigatud lilled püsivad kauem.'],
  },
} as const;

function formatHintEt(info: BiodynamicInfoEt): string {
  return `Märksõnad: ${info.element}; ${info.crops.join(', ')}; tööd: ${info.tasks.join(', ')}.`;
}

function formatDescriptionEt(info: BiodynamicInfoEt): string {
  // Ainult peamine lause; märkused jäävad kuufaasi "Märkused:" plokki, et ei kordaks.
  return info.description;
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

/**
 * Hetk, mille järgi arvutada Kuu tähtkuju ja biodünaamika päeva tüüpi kalendripäeva kohta.
 * - Sama päeva jaoks mis “täna”: praegune kellaaeg (ühtib dashboardi “praegu” plokiga).
 * - Muul päeval: kell 12:00 kohalikku aega — väldib 00:00 probleemi (UTC päeva nihkumist)
 *   ja on üks stabiilne punkt ööpäeva sees (erinevalt “öö algusest”).
 */
export function calendarMomentForZodiac(d: Date): Date {
  const now = new Date();
  const y = d.getFullYear();
  const m = d.getMonth();
  const day = d.getDate();
  if (y === now.getFullYear() && m === now.getMonth() && day === now.getDate()) {
    return now;
  }
  return new Date(y, m, day, 12, 0, 0, 0);
}

/** Päikese tähtkuju antud kuupäeva järgi (eesti keeles). */
function getSunSign(date = new Date()): ZodiacSignName {
  return ZODIAC_NAMES_ET[longitudeToSignIndex(sunLongitudeDeg(date))];
}

/** Kuu tähtkuju antud kuupäeva järgi (eesti keeles). */
function getMoonSign(date = new Date()): ZodiacSignName {
  return ZODIAC_NAMES_ET[longitudeToSignIndex(moonLongitudeDeg(date))];
}

/** Biodünaamika päevatüüp kuu tähtkuju põhjal. */
function getBiodynamicDayType(date = new Date()): BiodynamicDayType {
  const signIndex = longitudeToSignIndex(moonLongitudeDeg(date));
  return SIGN_TO_DAY_TYPE[signIndex];
}

/** „Kui Kuu on selles märgis“ — soe lühitekst (traditsioon, mitte tehniline). */
export const ZODIAC_NARRATIVE_UI_ET: Record<ZodiacSignName, string> = {
  Jäär:
    'Aktiivne ja kiire aeg. Sobib alustamiseks, külvamiseks ja viljataimedele tähelepanu andmiseks. Hea päev, kui tahad midagi käima lükata. Biodünaamilises käsitluses kuulub Jäär vilja- ja seemnepäevade hulka.',
  Sõnn:
    'Rahulik, viljakas ja maandatud aeg. Sobib juurdumiseks, istutamiseks ja kõigele, mis vajab stabiilsust. Biodünaamikas juurepäev — kui kuu on Sõnnis, tasakaalustab see kasvava kuu „hoogu“ pigem juurte ja mullaga kui ainult ülespoole kasvuga.',
  Kaksikud:
    'Kerge ja liikuv aeg. Sobib lilledele, kergematele töödele ja mitme asja vahel toimetamiseks. Õiepäev.',
  Vähk:
    'Pehme ja kasvule suunatud aeg. Sobib lehtköögiviljadele, ürtidele ja rohelisele kasvule. Lehepäev.',
  Lõvi:
    'Tugev ja nähtav aeg. Sobib vilja, seemnete ja küpsemise teemaga seotud töödeks. Viljapäev.',
  Neitsi:
    'Korralik ja praktiline aeg. Sobib juurviljadele, ümberistutamiseks ja mulla eest hoolitsemiseks. Juurepäev.',
  Kaalud:
    'Tasakaalu ja ilu aeg. Sobib lilledele, ilutaimedele ja harmooniat loovatele aiatöödele. Õiepäev.',
  Skorpion:
    'Sügav ja jõuline kasvuaeg. Sobib lehtedele, mahlasele rohelusele ja elujõulistele taimedele. Lehepäev.',
  Ambur:
    'Edasi liikuva energiaga aeg. Sobib viljataimedele, seemnetele ja kasvamist toetavatele töödele. Viljapäev.',
  Kaljukits:
    'Töökas ja vastupidav aeg. Sobib juurtele, tugevdamisele ja pika tulemusega töödele. Juurepäev.',
  Veevalaja:
    'Õhuline ja veidi katsetuslik aeg. Sobib lilledele ja loovamatele aiatöödele. Õiepäev.',
  Kalad:
    'Pehme, voolav ja hoolitsev aeg. Sobib lehtköögiviljadele, ürtidele ja rohelisele kasvule. Lehepäev.',
};

/** Tähtkujud + biodünaamika info (UI jaoks). */
export function getZodiacInfo(date = new Date()) {
  const moonSign = getMoonSign(date);
  const biodynamicDay = getBiodynamicDayType(date);
  const info = BIODYNAMIC_ET[biodynamicDay];

  return {
    sunSign: getSunSign(date),
    moonSign,
    moonSignInessive: MOON_SIGN_INESSIVE_ET[moonSign],
    biodynamicDayType: biodynamicDay,
    biodynamicDayLabel: info.label,
    biodynamicHint: formatHintEt(info),
    biodynamicDescription: formatDescriptionEt(info),
    signNarrative: ZODIAC_NARRATIVE_UI_ET[moonSign],
    element: info.element,
    crops: info.crops,
    tasks: info.tasks,
  };
}