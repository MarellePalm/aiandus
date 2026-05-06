// resources/js/lib/moon/moonAdvice.ts
import type { MoonInfo, MoonPhase8 } from './moon';
import { moonPhaseDisplayLabel } from './moonPhaseDisplay';

export type MoonAdvice = {
    displayTitle: string;
    moodHeadline: string;
    leadParagraph: string;
    tasks: string[];
    avoid: string[];
};

type PhaseRule = Readonly<{
    moodHeadline: string;
    leadParagraph: string;
    tasks?: readonly string[];
    avoid?: readonly string[];
}>;

const PHASE_RULES: Record<MoonPhase8, PhaseRule> = {
    Uuskuu: {
        moodHeadline: 'Uue ringi algus.',
        leadParagraph:
            'Hea aeg rahulikuks korrastamiseks ja järgmiste tööde planeerimiseks.',
        tasks: ['planeeri töid', 'valmista peenraid ette', 'hoolda mulda'],
        avoid: ['Külvi ja ümberistutamist lükka pigem järgmistele päevadele.'],
    },

    'Kasvav sirp': {
        moodHeadline: 'Kasvu toetav aeg.',
        leadParagraph:
            'Kasvav kuu suunab jõu ülespoole ning toetab aktiivseid istutus- ja külvitöid.',
        tasks: ['külva', 'istuta', 'väeta mõõdukalt'],
        avoid: ['Suurema tagasilõikusega tasub veel oodata.'],
    },

    'Esimene veerand': {
        moodHeadline: 'Aktiivne kasvuaeg.',
        leadParagraph:
            'Taimede kasv on aktiivne ja hea aeg on kasvu toetavateks töödeks.',
        tasks: ['istuta ettekasvatatud taimi', 'väeta', 'toesta võrseid'],
        avoid: ['Õrnu taimi istuta ümber ettevaatlikult.'],
    },

    'Kasvav kumer kuu': {
        moodHeadline: 'Jõudu koguv aeg.',
        leadParagraph:
            'Kasvule suunatud periood: hea aeg taimede tugevdamiseks ja edenemise toetamiseks.',
        tasks: ['istuta', 'väeta', 'seo ja toesta taimi'],
        avoid: ['Kuiva ilmaga kontrolli enne istutamist mulla niiskust.'],
    },

    Täiskuu: {
        moodHeadline: 'Korje ja jälgimise aeg.',
        leadParagraph:
            'Hea aeg saaki korjata, taimi jälgida ja tähelepanekud kirja panna.',
        tasks: ['korja saaki', 'jälgi taimi', 'tee märkmeid'],
        avoid: ['Õrnu taimi ära täna ümber istuta.'],
    },

    'Kahanev kumer kuu': {
        moodHeadline: 'Hoolduse ja korrastamise aeg.',
        leadParagraph:
            'Kahanev kuu suunab jõu juurtele ning soosib hooldus- ja korrastustöid.',
        tasks: ['rohi peenraid', 'harvenda taimi', 'lisa komposti'],
        avoid: ['Väga tugevat tagasilõikust väldi täna.'],
    },

    'Viimane veerand': {
        moodHeadline: 'Puhastamise ja lõpetamise aeg.',
        leadParagraph:
            'Hea aeg võtta rahulikum tempo ja lõpetada pooleli jäänud aiatööd.',
        tasks: ['rohi peenraid', 'harvenda taimi', 'korrasta peenraid'],
        avoid: ['Suured uued algused jäta järgmisse faasi.'],
    },

    'Kahanev sirp': {
        moodHeadline: 'Rahuliku lõpetamise aeg.',
        leadParagraph:
            'Aeg on tsüklit rahulikult lõpetada ning valmistada aeda ette järgmiseks ringiks.',
        tasks: [
            'korrasta peenraid',
            'hoolda mulda',
            'eemalda kahjustunud osad',
        ],
        avoid: ['Uued suuremad algused jäta järgmisse kuutsüklisse.'],
    },
} as const;

function uniq(items?: readonly string[]): string[] {
    const cleaned = (items ?? []).map((s) => s.trim()).filter(Boolean);
    return [...new Set(cleaned)];
}

export function moonAdvice(info: MoonInfo): MoonAdvice {
    const rule = PHASE_RULES[info.phase as MoonPhase8] ?? PHASE_RULES.Uuskuu;

    return {
        displayTitle: moonPhaseDisplayLabel(info.phase as MoonPhase8),
        moodHeadline: rule.moodHeadline,
        leadParagraph: rule.leadParagraph,
        tasks: uniq(rule.tasks),
        avoid: uniq(rule.avoid),
    };
}
