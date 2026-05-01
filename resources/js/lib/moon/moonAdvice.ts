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
        moodHeadline: 'Vaikse alguse aeg.',
        leadParagraph:
            'Uuskuu tähistab uue kuutsükli algust. See on hea aeg rahulikuks alustamiseks, planeerimiseks ja ettevalmistusteks. Aias sobib tähelepanu pöörata peenarde korrastamisele, mulla ettevalmistusele ja järgmiste tööde läbimõtlemisele.',
        tasks: [
            'planeerimine',
            'peenarde ettevalmistus',
            'mulla hooldus',
            'rahulik alustamine',
        ],
        avoid: [
            'Praegu on parem suuremate istutustega veidi oodata.',
            'Taimi tasub täna võimalikult vähe häirida ja mitte ümber istutada.',
        ],
    },

    'Kasvav sirp': {
        moodHeadline: 'Kasvu alguse aeg.',
        leadParagraph:
            'Kasvava sirbi ajal lisandub kuuvalgust iga päevaga. Seda aega seostatakse edenemise, tärkamise ja uute algustega. Aias sobib see hästi külvamiseks, istutamiseks ja taimede kasvule hoo andmiseks.',
        tasks: ['külvamine', 'istutamine', 'kerge hooldus', 'kasvu toetamine'],
        avoid: [
            'Tugevama tagasilõikusega tasub veel veidi oodata.',
            'Kui muld on väga kuiv, siis ära alusta kõige nõudlikumatest töödest.',
        ],
    },

    'Esimene veerand': {
        moodHeadline: 'Tegutsemise aeg.',
        leadParagraph:
            'Esimene veerand on aktiivsem ja liikuvam faas. See sobib hästi külvamiseks, istutamiseks ja töödeks, millelt ootad nähtavat edenemist.',
        tasks: [
            'külvamine',
            'istutamine',
            'salatite ja ürtide hooldus',
            'niitmine',
        ],
        avoid: [
            'Noori taimi ei tasu väetisega üle koormata.',
            'Ümberistutamisel tegutse rahulikult ja ettevaatlikult.',
        ],
    },

    'Kasvav kumer kuu': {
        moodHeadline: 'Jõu kogunemise aeg.',
        leadParagraph:
            'Kasvava kumera kuu ajal on fookuses edenemine ja jõu kogunemine. Seda peetakse heaks ajaks taimede toetamiseks, istutamiseks ja mulla eest hoolitsemiseks.',
        tasks: [
            'külv või istutus',
            'mulla hooldus',
            'kasvu toetamine',
            'päeva iseloomu järgimine',
        ],
        avoid: ['Kuiva ja kuuma ilmaga ära unusta taimi kasta.'],
    },

    Täiskuu: {
        moodHeadline: 'Täisjõu aeg.',
        leadParagraph:
            'Täiskuu ajal on kuuvalgus kõige tugevam. Seda aega peetakse intensiivseks ja elavaks, mistõttu sobib see hästi vaatlemiseks, hooldustöödeks ja küpse saagi korjamiseks.',
        tasks: ['korja saagi', 'planeeri järgmised tööd'],
        avoid: [
            'Õrnu taimi ei tasu täna ümber istutada.',
            'Liigne kastmine või järsud sekkumised võivad taimedele halvasti mõjuda.',
        ],
    },

    'Kahanev kumer kuu': {
        moodHeadline: 'Juurdumise ja korrastamise aeg.',
        leadParagraph:
            'Pärast täiskuud hakkab kuuvalgus vähenema ja tähelepanu liigub juurtele, mullale ning korrastamisele. See on sobiv aeg rohimiseks, tagasilõikuseks ja juurviljadega seotud töödeks.',
        tasks: ['rohi', 'kärbi', 'komposti'],
        avoid: [
            'Liiga tugev tagasilõikus võib taimi liigselt kurnata.',
            'Eemalda täna taimedelt haiged ja kahjustunud osad.',
        ],
    },

    'Viimane veerand': {
        moodHeadline: 'Puhastamise ja lõpetamise aeg.',
        leadParagraph:
            'Viimane veerand on rahulikum ja kokkuvõtlikum faas. Aias sobib see aeg hooldus- ja korrastustöödeks, rohimiseks, harvendamiseks ja kuivanud osade eemaldamiseks.',
        tasks: ['rohi', 'harvenda', 'komposti', 'korrasta'],
        avoid: ['Võta täna aias rahulikult.'],
    },

    'Kahanev sirp': {
        moodHeadline: 'Lahtilaskmise aeg.',
        leadParagraph:
            'Kahanev sirp on kuutsükli lõpuosa, mida seostatakse rahunemise, puhastumise ja ettevalmistusega. Aias sobib see aeg pinnase korrastamiseks, haigete osade eemaldamiseks ja järgmise tsükli ettevalmistamiseks.',
        tasks: ['komposti', 'eemalda kahjustunud taimeosad'],
        avoid: [
            'Kõike ei pea ühe päevaga ära tegema.',
            'Suuremad uued algused jäta järgmisse kuutsüklisse.',
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
