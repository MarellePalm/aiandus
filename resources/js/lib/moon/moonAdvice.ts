// resources/js/lib/moonAdvice.ts
import type { MoonPhase8, MoonInfo } from './moon';

export type MoonAdvice = {
  title: MoonPhase8;
  icon: string; // Material Symbols name
  subtitle: string; // e.g. "Valgustatus 62%"
  text: string; // lühike kokkuvõte
  textLong: string; // detailid (ei dubleeri text-i)
  tags: string[]; // UI chips (optional)
};

export type MoonAdviceStructured = MoonAdvice & {
  focusLine: string; // nt "Aktiivne kasv: taime maapealne osa ..."
  suitable: string[]; // "Sobib"
  tasks: string[]; // "Tööd"
  keywords: string[]; // "Märksõnad"
  lessSuitable: string[]; // "Vähem sobiv"
};

type PhaseRule = Readonly<{
  icon: string;
  focus: string;
  definition: string;
  goodFor?: readonly string[];
  avoid?: readonly string[];
  tasks?: readonly string[];
  crops?: readonly string[];
  notes?: readonly string[];
  tags: readonly string[];
}>;

const PHASE_RULES: Record<MoonPhase8, PhaseRule> = {
  Uuskuu: {
    icon: 'brightness_2',
    focus: 'Puhkus ja planeerimine',
    definition: 'energia on madal; sobib rahulikeks ettevalmistusteks ja korrastamiseks.',
    goodFor: ['mulla ettevalmistus', 'koristus', 'inventuur', 'seemnete sorteerimine', 'kompost'],
    avoid: ['rasket külvi', 'istutamist'],
    tasks: ['planeeri', 'korrasta', 'valmista peenrad ette'],
    tags: ['planeeri', 'muld'],
  },
  'Kasvav sirp': {
    icon: 'brightness_3',
    focus: 'Kasv käivitub',
    definition: 'taime maapealne osa ärkab ja kasv kiireneb; hea aeg õrnaks kasvule suunatud tööks.',
    goodFor: ['külv', 'istutamine', 'õrn hooldus'],
    tasks: ['külv', 'istutamine', 'kerge pügamine', 'võsude võtmine'],
    crops: ['lehtköögiviljad', 'maitseroheline', 'kurk', 'teraviljad'],
    notes: ['Sobib eriti maapealsetele/lehttaimedele.'],
    tags: ['külv', 'lehed'],
  },
  'Esimene veerand': {
    icon: 'brightness_4',
    focus: 'Aktiivne kasv',
    definition: 'taime maapealne osa kasvab jõuliselt ja on vastuvõtlik toetamisele ning hooldusele.',
    goodFor: ['toestamine', 'väetamine', 'mõõdukas kastmine'],
    tasks: ['toesta', 'väeta', 'kasta mõõdukalt', 'niitmine'],
    crops: ['lehttaimed', 'viljataimed (tasapisi lisada)'],
    tags: ['väeta', 'toesta'],
  },
  'Kasvav kumer kuu': {
    icon: 'brightness_3',
    focus: 'Maksimaalne kasv',
    definition: 'maapealne kasv on maksimumi lähedal; õitsemine ja viljumine saavad tuge.',
    goodFor: ['viljataimed', 'õitsejad', 'aktiivne hooldus'],
    tasks: ['toesta', 'väeta', 'hoolda', 'kerge pügamine (kasvu soodustamiseks)'],
    crops: ['tomat', 'paprika', 'oad', 'herned', 'kõrvits', 'lilled'],
    notes: ['Istutamine vahetult enne täiskuud võib soodustada juurdumist.'],
    tags: ['viljad', 'õied'],
  },
  Täiskuu: {
    icon: 'brightness_7',
    focus: 'Tipphetk',
    definition: 'vee/liikuvuse tipp; taimed on tundlikumad—eelista korjet ja vaatlust.',
    goodFor: ['saagi korjamine', 'ravim- ja maitsetaimede korje', 'vaatlus'],
    avoid: ['rasket külvi', 'rasket istutamist'],
    tasks: ['korja saaki', 'vaatle taimi', 'planeeri kahaneva kuu töid'],
    notes: ['Hea aeg korjata vilju ja seemneid.'],
    tags: ['saak', 'vaatlus'],
  },
  'Kahanev kumer kuu': {
    icon: 'brightness_3',
    focus: 'Energia liigub juurtesse',
    definition: 'sobib pidurdavaks pügamiseks, kujundamiseks ja juurtega seotud töödeks.',
    goodFor: ['pügamine', 'kujundamine', 'haigete osade eemaldamine', 'juurviljad'],
    tasks: ['püga', 'eemalda haiged osad', 'korista', 'istuta püsikuid'],
    crops: ['juur- ja mugulköögiviljad', 'puud', 'põõsad', 'mitmeaastased'],
    notes: ['Sobib töödeks, kus soovid kasvu pidurdada.'],
    tags: ['püga', 'hooldus'],
  },
  'Viimane veerand': {
    icon: 'brightness_4',
    focus: 'Juurte faas',
    definition: 'juuretegevus on tugev; hea aeg rohimiseks, ümberistutuseks ja ladustamiseks.',
    goodFor: ['juurviljad', 'ümberistutamine', 'rohimine', 'saagi ladustamine'],
    tasks: ['rohi', 'ümber istuta', 'korista ja ladusta', 'väeta mõõdukalt'],
    crops: ['porgand', 'kartul', 'sibul', 'peet', 'küüslauk'],
    notes: ['Hea aeg ettevalmistuseks enne uuskuu puhkeperioodi.'],
    tags: ['juured', 'rohi'],
  },
  'Kahanev sirp': {
    icon: 'brightness_3',
    focus: 'Puhastus ja lõpetamine',
    definition: 'enne uuskuud; sobib lõpetamiseks ja korrastamiseks, külvi pigem väldi.',
    goodFor: ['rohimine', 'koristus', 'kompost', 'peenarde ettevalmistus'],
    avoid: ['külvi (kui võimalik)', 'uusi suuri istutusi'],
    tasks: ['puhasta', 'korrasta peenrad', 'hoolda tööriistu', 'valmista kompost'],
    notes: ['Hea aeg lõpetada pooleliolevad tööd ja teha korda aed.'],
    tags: ['puhasta', 'kompost'],
  },
} as const;

function uniq(items?: readonly string[]): string[] {
  const cleaned = (items ?? [])
    .map((s) => s.trim())
    .filter(Boolean);
  return [...new Set(cleaned)];
}

function sentenceList(items?: readonly string[]): string {
  return uniq(items).join(', ');
}

function formatShort(rule: PhaseRule): string {
  const a = sentenceList(rule.goodFor);
  const b = sentenceList(rule.avoid);

  // hoia lühike ja “skänniv”
  if (a && b) return `${rule.focus}: sobib ${a}; väldi ${b}.`;
  if (a) return `${rule.focus}: sobib ${a}.`;
  if (b) return `${rule.focus}: väldi ${b}.`;
  return `${rule.focus}.`;
}

/** Detailid, mis ei dubleeri `text` kokkuvõtet (väldi-osa on juba short-is + lessSuitable väljas). */
function formatLong(rule: PhaseRule): string {
  const parts: string[] = [];
  const crops = sentenceList(rule.crops);
  const tasks = sentenceList(rule.tasks);
  const notes = sentenceList(rule.notes);

  if (crops) parts.push(`Kultuurid: ${crops}.`);
  if (tasks) parts.push(`Tööd: ${tasks}.`);
  if (notes) parts.push(`Märkus: ${notes}.`);

  // kui lisadetaile pole, anna vähemalt definitsioon (et textLong poleks tühi)
  return parts.length ? parts.join(' ') : rule.definition;
}

export function moonAdvice(info: MoonInfo): MoonAdviceStructured {
  const pct = Math.round(info.illumination * 100);

  // runtime fallback (juhuks kui phase tuleb valesti)
  const rule = PHASE_RULES[(info.phase as MoonPhase8)] ?? PHASE_RULES.Uuskuu;

  const suitable = uniq(rule.goodFor);
  const tasks = uniq(rule.tasks);
  const lessSuitable = uniq(rule.avoid);
  const tags = uniq(rule.tags);
  const keywords = uniq([...tags, ...(rule.crops ?? [])]);

  return {
    title: info.phase as MoonPhase8,
    icon: rule.icon,
    subtitle: `Valgustatus ${pct}%`,
    text: formatShort(rule),
    textLong: formatLong(rule),
    tags,

    focusLine: `${rule.focus}: ${rule.definition}`,
    suitable,
    tasks,
    keywords,
    lessSuitable,
  };
}