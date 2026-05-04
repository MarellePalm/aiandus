// resources/js/lib/moon/moonAdvice.ts
import type { MoonInfo, MoonPhase8 } from './moon';
import { moonPhaseDisplayLabel } from './moonPhaseDisplay';

export type MoonAdvice = {
    title: MoonPhase8;
    displayTitle: string;
    /** Lühike meeleoluline pealkiri. */
    moodHeadline: string;
    /** Lühike sissejuhatus „täna“ kaardile. */
    leadParagraph: string;
    tasks: string[];
    /** Mida võiks täna pigem vältida või rahulikumalt võtta. */
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
        moodHeadline: 'Rahulik aeg uueks ringiks.',
        leadParagraph:
            'Noorkuu paiku tasub tempo maha võtta. Hea aeg peenarde korrastamiseks, mulla ettevalmistuseks ja järgmiste tööde läbimõtlemiseks.',
        tasks: [
            'planeerimine',
            'peenarde ettevalmistus',
            'mulla hooldus',
            'korrastamine',
        ],
        avoid: [
            'Suuremad istutused jäta pigem järgmisteks päevadeks.',
            'Taimi tasub täna võimalikult vähe häirida.',
        ],
    },

    'Kasvav sirp': {
        moodHeadline: 'Kasvu toetav aeg.',
        leadParagraph:
            'Kasvava sirbi ajal on hea keskenduda külvile ja uuele kasvule. Sobib hästi noorte taimede toetamiseks ja pehmemateks aiatöödeks.',
        tasks: ['külvamine', 'istutamine', 'kerge hooldus', 'kastmise kontroll'],
        avoid: [
            'Tugevama tagasilõikusega tasub veel veidi oodata.',
            'Kuiva mullaga ära alusta kõige nõudlikumatest töödest.',
        ],
    },

    'Esimene veerand': {
        moodHeadline: 'Aktiivne kasvuaeg.',
        leadParagraph:
            'Esimene veerand sobib aktiivseteks aiatöödeks. Hea aeg külvamiseks, istutamiseks ja töödeks, kus tahad näha kiiremat edenemist.',
        tasks: [
            'külvamine',
            'istutamine',
            'taimede toestamine',
            'peenarde hooldus',
        ],
        avoid: [
            'Noori taimi ei tasu väetisega üle koormata.',
            'Ümberistutamisel tegutse rahulikult ja ettevaatlikult.',
        ],
    },

    'Kasvav kumer kuu': {
        moodHeadline: 'Jõudu koguv aeg.',
        leadParagraph:
            'Kasvava kumera kuu ajal koguneb taimedes jõudu. Hea aeg kasvule kaasa aidata ja peenraid hooldada.',
        tasks: [
            'istutamine',
            'mulla hooldus',
            'kasvu toetamine',
            'väetamise planeerimine',
        ],
        avoid: ['Kuiva ilmaga ära unusta kastmist.'],
    },

    Täiskuu: {
        moodHeadline: 'Jälgimise ja korje aeg.',
        leadParagraph:
            'Täiskuu ajal tasub aias rohkem jälgida kui suruda peale suuri muutusi. Hea aeg saagi korjamiseks ja märkmete tegemiseks.',
        tasks: ['saagi koristamine', 'taimede jälgimine', 'märkmete tegemine'],
        avoid: [
            'Õrnu taimi ei tasu täna ümber istutada.',
            'Tugevamad hooldustööd võiks jätta hilisemaks.',
        ],
    },

    'Kahanev kumer kuu': {
        moodHeadline: 'Hoolduse ja korrastamise aeg.',
        leadParagraph:
            'Pärast täiskuud liigub rõhk rohkem hooldusele ja korrastamisele. See on hea aeg rohimiseks, kärpimiseks ja peenarde puhastamiseks.',
        tasks: ['rohimine', 'kärpimine', 'kompostimine'],
        avoid: [
            'Liiga tugeva tagasilõikusega tasub piiri pidada.',
            'Haiged või kuivanud osad eemalda rahulikult.',
        ],
    },

    'Viimane veerand': {
        moodHeadline: 'Puhastamise ja lõpetamise aeg.',
        leadParagraph:
            'Viimane veerand sobib hooldus- ja korrastustöödeks. Hea aeg rohida, harvendada ja lõpetada töid, mis on mõnda aega oodanud.',
        tasks: ['rohimine', 'harvendamine', 'korrastamine', 'kompostimine'],
        avoid: ['Suured uued algused võiks jätta järgmisse faasi.'],
    },

    'Kahanev sirp': {
        moodHeadline: 'Rahuliku lõpetamise aeg.',
        leadParagraph:
            'Kahanev sirp on kuutsükli lõpuosa. Sobib peenarde rahulikuks korrastamiseks ja järgmise ringi ettevalmistamiseks.',
        tasks: ['kompostimine', 'kahjustunud osade eemaldamine', 'peenarde korrastus'],
        avoid: [
            'Kõiki töid ei pea ühe päevaga lõpetama.',
            'Uued suuremad algused jäta järgmisse kuutsüklisse.',
        ],
    },
} as const;

function uniq(items?: readonly string[]): string[] {
    const cleaned = (items ?? []).map((s) => s.trim()).filter(Boolean);
    return [...new Set(cleaned)];
}

export function moonAdvice(info: MoonInfo): MoonAdvice {
    const rule = PHASE_RULES[info.phase as MoonPhase8] ?? PHASE_RULES.Uuskuu;

    return {
        title: info.phase as MoonPhase8,
        displayTitle: moonPhaseDisplayLabel(info.phase as MoonPhase8),
        moodHeadline: rule.moodHeadline,
        leadParagraph: rule.leadParagraph,
        tasks: uniq(rule.tasks),
        avoid: uniq(rule.avoid),
    };
}
