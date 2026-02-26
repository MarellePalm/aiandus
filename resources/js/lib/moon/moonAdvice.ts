// resources/js/lib/moonAdvice.ts
import type { MoonPhase8, MoonInfo } from './moon';

export type MoonAdvice = {
  title: MoonPhase8;
  textLong: string;
  tasks: string[];
};

type PhaseRule = Readonly<{
  tasks?: readonly string[];
  notes?: readonly string[];
}>;

const PHASE_RULES: Record<MoonPhase8, PhaseRule> = {
  Uuskuu: {
    tasks: ['planeeri', 'korrasta', 'valmista peenrad ette'],
  },
  'Kasvav sirp': {
    tasks: ['külv ja istutamine', 'kerge pügamine', 'võsude võtmine'],
    notes: ['Eriti hea aeg lehtköögiviljade ja maapealsete taimede jaoks.'],
  },
  'Esimene veerand': {
    tasks: ['toesta', 'väeta', 'kasta mõõdukalt', 'niitmine'],
  },
  'Kasvav kumer kuu': {
    tasks: ['toesta', 'väeta', 'hoolda', 'kerge pügamine (kasvu soodustamiseks)'],
    notes: ['Istutamine vahetult enne täiskuud võib soodustada juurdumist.'],
  },
  Täiskuu: {
    tasks: ['korja saaki', 'vaatle taimi', 'planeeri kahaneva kuu töid'],
    notes: ['Hea aeg korjata vilju ja seemneid.'],
  },
  'Kahanev kumer kuu': {
    tasks: ['püga', 'eemalda taimede haiged osad', 'korista', 'istuta püsikuid'],
    notes: ['Sobib töödeks, kus soovid kasvu pidurdada.'],
  },
  'Viimane veerand': {
    tasks: ['rohi', 'ümber istuta', 'korista ja ladusta', 'väeta mõõdukalt'],
    notes: ['Hea aeg ettevalmistuseks enne uuskuu puhkeperioodi.'],
  },
  'Kahanev sirp': {
    tasks: ['puhasta', 'korrasta peenrad', 'hoolda tööriistu', 'valmista kompost'],
    notes: ['Hea aeg lõpetada pooleliolevad tööd ja teha korda aed.'],
  },
} as const;

function uniq(items?: readonly string[]): string[] {
  const cleaned = (items ?? [])
    .map((s) => s.trim())
    .filter(Boolean);
  return [...new Set(cleaned)];
}

function formatNotes(rule: PhaseRule): string {
  return rule.notes?.length ? uniq(rule.notes).join(' ') : '';
}

export function moonAdvice(info: MoonInfo): MoonAdvice {
  const rule = PHASE_RULES[(info.phase as MoonPhase8)] ?? PHASE_RULES.Uuskuu;

  return {
    title: info.phase as MoonPhase8,
    textLong: formatNotes(rule),
    tasks: uniq(rule.tasks) ?? [],
  };
}
