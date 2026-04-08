// resources/js/lib/moon/moonAdvice.ts
import type { MoonPhase8, MoonInfo } from './moon';
import { moonPhaseDisplayLabel } from './moonPhaseDisplay';

export type MoonAdvice = {
  title: MoonPhase8;
  displayTitle: string;
  /** Lühike meeleoluline pealkiri. */
  moodHeadline: string;
  /** Lühike sissejuhatus „täna“ kaardile. */
  leadParagraph: string;
  /** Pikem tekst päeviku või kalendri vaates. */
  textLong: string;
  tasks: string[];
  /** Mida võiks täna pigem vältida või rahulikumalt võtta. */
  avoid: string[];
  homeTasks: string[];
  /** Lühike astronoomiline märkus. */
  astronomyShort?: string;
  traditionKeyword?: string;
};

type PhaseRule = Readonly<{
  moodHeadline: string;
  leadParagraph: string;
  textLong: string;
  tasks?: readonly string[];
  avoid?: readonly string[];
  homeTasks?: readonly string[];
  astronomyShort?: string;
  traditionKeyword?: string;
}>;

const PHASE_RULES: Record<MoonPhase8, PhaseRule> = {
  Uuskuu: {
    moodHeadline: 'Vaikse alguse aeg.',
    leadParagraph:
      'Uuskuu märgib uue tsükli algust. See on hea aeg planeerimiseks, ettevalmistusteks ja rahulikuks alustamiseks. Aias sobib keskenduda peenarde korrastamisele, mulla ettevalmistusele ja järgmiste tööde läbimõtlemisele.',
    textLong:
      'Vaikse alguse aeg.\n\n' +
      'Uuskuu märgib uue tsükli algust. See on hea aeg planeerimiseks, ettevalmistusteks ja rahulikuks alustamiseks. Aias sobib keskenduda peenarde korrastamisele, mulla ettevalmistusele ja järgmiste tööde läbimõtlemisele.\n\n' +
      'Kodus soosib see aeg puhastamist, korrastamist ja värske rütmi loomist. Astronoomiliselt algab siit uus kuutsükkel.',
    tasks: ['planeerimine', 'peenarde ettevalmistus', 'mulla hooldus', 'rahulik alustamine'],
    avoid: [
      'Suuri istutustöid tasub pigem edasi lükata.',
      'Taimi ei ole mõtet täna liigselt häirida ega ümber istutada.',
    ],
    homeTasks: ['puhastamine', 'korrastamine', 'uue perioodi planeerimine'],
    astronomyShort: 'Astronoomiliselt algab uus kuutsükkel.',
    traditionKeyword: 'Uus tsükkel',
  },

  'Kasvav sirp': {
    moodHeadline: 'Kasvu alguse aeg.',
    leadParagraph:
      'Kasvava sirbi ajal lisandub valgust iga päevaga. Traditsiooniliselt seostatakse seda edenemise, tärkamise ja uute algustega. See on sobiv aeg külvamiseks, istutamiseks ja kasvule hoo andmiseks.',
    textLong:
      'Kasvu alguse aeg.\n\n' +
      'Kasvava sirbi ajal lisandub valgust iga päevaga. Traditsiooniliselt seostatakse seda edenemise, tärkamise ja uute algustega. See on sobiv aeg külvamiseks, istutamiseks ja kasvule hoo andmiseks.\n\n' +
      'Kui lähtud kuuaedniku tavast, siis tasub arvestada ka päeva iseloomuga: juure-, lehe-, õie- või viljapäevaga. Nii saab valida, milliste taimede või töödega on täna kõige parem tegelda.',
    tasks: ['külvamine', 'istutamine', 'kerge hooldus', 'kasvu toetamine'],
    avoid: [
      'Tugevat tagasilõikust tasub pigem vältida.',
      'Kui muld on väga kuiv, ära alusta kõige nõudlikumate töödega.',
    ],
    homeTasks: ['kerged kodused toimetused', 'uute plaanide tegemine'],
    traditionKeyword: 'Kasvav kuu',
  },

  'Esimene veerand': {
    moodHeadline: 'Tegutsemise aeg.',
    leadParagraph:
      'Esimene veerand on aktiivsema liikumise ja edenemise faas. See sobib hästi külvamiseks, istutamiseks ja töödeks, millelt ootad nähtavat arengut.',
    textLong:
      'Tegutsemise aeg.\n\n' +
      'Esimene veerand on aktiivsema liikumise ja edenemise faas. See sobib hästi külvamiseks, istutamiseks ja töödeks, millelt ootad nähtavat arengut.\n\n' +
      'Kui vajad lihtsat suunist, siis see on hea aeg tegutsemiseks ja kasvu käivitamiseks.',
    tasks: ['külvamine', 'istutamine', 'salatite ja ürtide hooldus', 'niitmine'],
    avoid: [
      'Noori taimi ei tasu väetisega üle koormata.',
      'Ümberistutamisel tegutse rahulikult ja ettevaatlikult.',
    ],
    homeTasks: ['energiat vajavad kodused tööd'],
    traditionKeyword: 'Esimene veerand',
  },

  'Kasvav kumer kuu': {
    moodHeadline: 'Jõu kogunemise aeg.',
    leadParagraph:
      'Kasvava kumera kuu ajal on fookus edenemisel ja jõu kogunemisel. Traditsiooniliselt peetakse seda heaks ajaks taimede toetamiseks, istutamiseks ja mulla eest hoolitsemiseks.',
    textLong:
      'Jõu kogunemise aeg.\n\n' +
      'Kasvava kumera kuu ajal on fookus edenemisel ja jõu kogunemisel. Traditsiooniliselt peetakse seda heaks ajaks taimede toetamiseks, istutamiseks ja mulla eest hoolitsemiseks.\n\n' +
      'Biodünaamilises lähenemises täpsustab tööde sobivust ka päeva iseloom: juure-, lehe-, õie- või viljapäev. See aitab valida, millist taimeosa on kõige mõistlikum täna esile tõsta.',
    tasks: ['külv või istutus', 'mulla hooldus', 'kasvu toetamine', 'päeva iseloomu järgimine'],
    avoid: [
      'Ära lähtu ainult kuufaasist, vaid vaata ka päeva iseloomu.',
      'Kuiva ja kuuma ilmaga ära unusta kastmist.',
    ],
    homeTasks: ['edenemist toetavad kodused tööd'],
    traditionKeyword: 'Kasvav kuu',
  },

  Täiskuu: {
    moodHeadline: 'Täisjõu aeg.',
    leadParagraph:
      'Täiskuu ajal on kuu valgus kõige tugevam. Rahvapärimuses peetakse seda intensiivseks ja elavaks ajaks, mis sobib hästi vaatlemiseks, hooldustöödeks ja küpse saagi korjamiseks.',
    textLong:
      'Täisjõu aeg.\n\n' +
      'Täiskuu ajal on kuu valgus kõige tugevam. Rahvapärimuses peetakse seda intensiivseks ja elavaks ajaks, mis sobib hästi vaatlemiseks, hooldustöödeks ja küpse saagi korjamiseks.\n\n' +
      'Paljud eelistavad täiskuu ümbruses teha pigem hooldus- ja jälgimistöid kui istutada õrnu taimi või teha suuremaid ümberkorraldusi.',
    tasks: ['taimede jälgimine', 'saagi korjamine', 'hooldustööd', 'järgmiste tööde planeerimine'],
    avoid: [
      'Õrnu taimi ei tasu täna ümber istutada.',
      'Liigne kastmine või järsud sekkumised võivad teha rohkem kahju kui kasu.',
    ],
    homeTasks: ['suuremat tähelepanu vajavad tööd', 'oluliste asjade lõpetamine'],
    traditionKeyword: 'Täiskuu',
  },

  'Kahanev kumer kuu': {
    moodHeadline: 'Juurdumise ja korrastamise aeg.',
    leadParagraph:
      'Pärast täiskuud hakkab valgus vähenema ning tähelepanu liigub traditsiooniliselt juurtele, mullale ja korrastamisele. See on sobiv aeg rohimiseks, tagasilõikuseks ja juurviljadega seotud töödeks.',
    textLong:
      'Juurdumise ja korrastamise aeg.\n\n' +
      'Pärast täiskuud hakkab valgus vähenema ning tähelepanu liigub traditsiooniliselt juurtele, mullale ja korrastamisele. See on sobiv aeg rohimiseks, tagasilõikuseks ja juurviljadega seotud töödeks.\n\n' +
      'Hästi sobivad ka liigse eemaldamine, kompostiga tegelemine ja peenarde korrastamine.',
    tasks: ['juurviljade hooldus', 'rohimine', 'kärpimine', 'kompostiga tegelemine'],
    avoid: [
      'Liiga tugev tagasilõikus võib taimi liigselt kurnata.',
      'Haiged või kahjustunud osad tasub eemaldada õigel ajal, mitte jätta hilisemaks.',
    ],
    homeTasks: ['eemaldamine', 'korrastamine', 'üleliigse sorteerimine'],
    traditionKeyword: 'Kahanev kuu',
  },

  'Viimane veerand': {
    moodHeadline: 'Puhastamise ja lõpetamise aeg.',
    leadParagraph:
      'Viimane veerand on vaiksem ja kokkuvõtlikum faas. Aias sobib keskenduda hooldus- ja korrastustöödele, rohimisele, harvendamisele ja kuivanud osade eemaldamisele.',
    textLong:
      'Puhastamise ja lõpetamise aeg.\n\n' +
      'Viimane veerand on vaiksem ja kokkuvõtlikum faas. Aias sobib keskenduda hooldus- ja korrastustöödele, rohimisele, harvendamisele ja kuivanud osade eemaldamisele.\n\n' +
      'Kodus on see hea aeg lõpetada pooleliolevaid väikseid asju ja teha ruumi järgmisele etapile.',
    tasks: ['rohimine', 'harvendamine', 'komposteerimine', 'korrastamine'],
    avoid: [
      'Suuri uusi istutusi tasub pigem edasi lükata.',
      'Töid ei ole vaja teha kiirustades ega liigse jõuga.',
    ],
    homeTasks: ['lõpetamine', 'koristamine', 'väikeste asjade korda tegemine'],
    traditionKeyword: 'Viimane veerand',
  },

  'Kahanev sirp': {
    moodHeadline: 'Lahtilaskmise aeg.',
    leadParagraph:
      'Kahanev sirp on kuutsükli lõpuosa, mida seostatakse rahunemise, puhastumise ja ettevalmistusega. Aias sobib see aeg pinnase korrastamiseks, haigete osade eemaldamiseks ja järgmise tsükli ettevalmistamiseks.',
    textLong:
      'Lahtilaskmise aeg.\n\n' +
      'Kahanev sirp on kuutsükli lõpuosa, mida seostatakse rahunemise, puhastumise ja ettevalmistusega. Aias sobib see aeg pinnase korrastamiseks, haigete osade eemaldamiseks ja järgmise tsükli ettevalmistamiseks.\n\n' +
      'Kodus toetab see aeg suurpuhastust, süsteemset korrastamist ja vana lõpetamist enne uut algust.',
    tasks: ['komposteerimine', 'mulla ettevalmistus', 'kahjustunud osade eemaldamine', 'juurtega seotud tööd'],
    avoid: [
      'Kõike ei pea ühe päevaga ära tegema.',
      'Suured uued algused võiks jätta järgmisse tsüklisse.',
    ],
    homeTasks: ['suurpuhastus', 'korrastamine', 'lõpetamine'],
    traditionKeyword: 'Vanakuu',
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
    textLong: rule.textLong,
    tasks: uniq(rule.tasks),
    avoid: uniq(rule.avoid),
    homeTasks: uniq(rule.homeTasks),
    astronomyShort: rule.astronomyShort,
    traditionKeyword: rule.traditionKeyword,
  };
}