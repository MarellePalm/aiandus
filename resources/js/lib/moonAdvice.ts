// resources/js/lib/moonAdvice.ts
import type { MoonPhase8, MoonInfo } from './moon';

export type MoonAdvice = {
  title: MoonPhase8;
  icon: string; // Material Symbols name
  subtitle: string; // e.g. "Valgustatus 62%"
  text: string; // 1-2 lauset
  tags: string[]; // UI chips (optional)
};

function iconForPhase(phase: MoonPhase8): string {
  // you can refine later
  if (phase === 'Täiskuu') return 'brightness_7';
  if (phase === 'Uuskuu') return 'brightness_2';
  if (phase === 'Esimene veerand' || phase === 'Viimane veerand') return 'brightness_4';
  return 'brightness_3';
}

export function moonAdvice(info: MoonInfo): MoonAdvice {
  const pct = Math.round(info.illumination * 100);
  const base = {
    title: info.phase,
    icon: iconForPhase(info.phase),
    subtitle: `Valgustatus ${pct}%`,
  };

  switch (info.phase) {
    case 'Uuskuu':
      return {
        ...base,
        text: 'Planeeri, korrasta ja tee rahulikumaid töid. Hea aeg mulla ettevalmistuseks.',
        tags: ['planeeri', 'muld'],
      };
    case 'Kasvav sirp':
      return {
        ...base,
        text: 'Soodne külviks ja istutuseks, eriti lehtköögiviljad ja maitsetaimed.',
        tags: ['külv', 'lehed'],
      };
    case 'Esimene veerand':
      return {
        ...base,
        text: 'Kasv on aktiivne: hea toestamiseks, väetamiseks ja mõõdukaks kastmiseks.',
        tags: ['väeta', 'toesta'],
      };
    case 'Kasvav kumer kuu':
      return {
        ...base,
        text: 'Maksimaalne kasv: sobib viljataimede hoolduseks ja istutamiseks, õite toetamiseks.',
        tags: ['viljad', 'õied'],
      };
    case 'Täiskuu':
      return {
        ...base,
        text: 'Tipphetk: hea saagi korjamiseks ja taimede seisundi jälgimiseks.',
        tags: ['saak', 'vaatlus'],
      };
    case 'Kahanev kumer kuu':
      return {
        ...base,
        text: 'Energia taandub: sobib pügamiseks, kujundamiseks ja haigete osade eemaldamiseks.',
        tags: ['püga', 'hooldus'],
      };
    case 'Viimane veerand':
      return {
        ...base,
        text: 'Juurte faas: hea juurviljadega tegelemiseks, ümberistutuseks ja rohimiseks.',
        tags: ['juured', 'rohima'],
      };
    case 'Kahanev sirp':
      return {
        ...base,
        text: 'Puhastuse faas: rohimine, kompost, peenarde koristus ja ettevalmistus.',
        tags: ['puhasta', 'kompost'],
      };
  }
}
