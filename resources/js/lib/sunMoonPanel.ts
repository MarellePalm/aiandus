// resources/js/lib/sunMoonPanel.ts
import type { MoonInfo } from './moon';
import { moonAdvice } from './moonAdvice';
import { getZodiacInfo } from './zodiac';

export type PanelSection = Readonly<{
  title: string;
  items: string[];
}>;

export type SunMoonPanel = Readonly<{
  title: string; // "Päike ja kuu"
  headerLines: string[]; // "Päike: ...", "Kuu: ...", "Kuufaas: ..."
  focusLine: string; // "Aktiivne kasv: ..."
  sections: PanelSection[]; // Sobib/Tööd/Märksõnad/Vähem sobiv
}>;

function nonEmptySection(title: string, items: string[]): PanelSection | null {
  const cleaned = items.map((x) => x.trim()).filter(Boolean);
  return cleaned.length ? { title, items: cleaned } : null;
}

export function getSunMoonPanel(moon: MoonInfo, date = new Date()): SunMoonPanel {
  const zodiac = getZodiacInfo(date);
  const advice = moonAdvice(moon);

  const headerLines = [
    `Päike: ${zodiac.sunSign}`,
    `Kuu: ${zodiac.moonSign} (${zodiac.biodynamicDayLabel})`,
    `Kuufaas: ${advice.title} • ${advice.subtitle}`,
  ];

  const sectionsRaw: Array<PanelSection | null> = [
    nonEmptySection('Sobib', advice.suitable),
    nonEmptySection('Tööd', advice.tasks),
    nonEmptySection('Märksõnad', advice.keywords),
    nonEmptySection('Vähem sobiv', advice.lessSuitable),
  ];

  return {
    title: 'Päike ja kuu',
    headerLines,
    focusLine: advice.focusLine,
    sections: sectionsRaw.filter((s): s is PanelSection => s !== null),
  };
}
