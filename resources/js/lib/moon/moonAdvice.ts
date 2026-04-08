// resources/js/lib/moon/moonAdvice.ts
import type { MoonPhase8, MoonInfo } from './moon';
import { moonPhaseDisplayLabel } from './moonPhaseDisplay';

/** Pikk selgitus (dokumentatsioon / arendus). */
export const MOON_SOFT_HARD_RULE_DESCRIPTION_ET =
  'Selles äpis on pehme (mäda) ja kõva aeg määratud ühe reegliga: pehme on umbes 3 päeva enne ja 3 päeva pärast astronoomilist noorkuud (Kuu vanus tsüklis kuni ~3 päeva või üle ~26 päeva). Ülejäänud tsükkel on kõva. See on lihtsustus, mida saab igal aastal samamoodi uuesti arvutada.';

export const MOON_TRADITION_SCIENCE_NOTE_ET =
  'Kuufaasidel põhinevad aiandustavad on peamiselt rahvapärimuse ja traditsioonilise aednikutarkuse osa. Tänapäeva teadus ei ole leidnud kindlat tõendit, et Kuu faasid üksi määraksid taimede kasvu edukust — NASA kinnitab kuufaaside astronoomilist järjestust, aga aianduslik tõlgendus pärineb peamiselt traditsioonist.';

export type MoonAdvice = {
  title: MoonPhase8;
  displayTitle: string;
  /** Lühike meeleoluline pealkiri (nt „Kasvu alguse aeg.“). */
  moodHeadline: string;
  /** Lühike sissejuhatus „täna“ kaardile (1–3 lauset). */
  leadParagraph: string;
  /** Päeviku / kalendri täistekst (lõigud). */
  textLong: string;
  tasks: string[];
  homeTasks: string[];
  /** Valikuline lühike astronoomiline märkus (kalendri „Lisa“). */
  astronomyShort?: string;
  traditionKeyword?: string;
};

type PhaseRule = Readonly<{
  moodHeadline: string;
  leadParagraph: string;
  textLong: string;
  tasks?: readonly string[];
  homeTasks?: readonly string[];
  astronomyShort?: string;
  traditionKeyword?: string;
}>;

const PHASE_RULES: Record<MoonPhase8, PhaseRule> = {
  Uuskuu: {
    moodHeadline: 'Vaikne algus.',
    leadParagraph:
      'See on uue tsükli algus ja sobib kõige paremini ettevalmistuseks, plaanide tegemiseks ja rahulikuks alustamiseks. Aias on see hea aeg mõelda läbi järgmised sammud, valmistada ette peenrad ja koguda mõtteid uueks kasvuks. Koduses rütmis sobib see puhastamiseks, korrastamiseks ja värske alguse loomiseks.',
    textLong:
      'Vaikne algus.\n\n' +
      'See on uue tsükli algus ja sobib kõige paremini ettevalmistuseks, plaanide tegemiseks ja rahulikuks alustamiseks. Aias on see hea aeg mõelda läbi järgmised sammud, valmistada ette peenrad ja koguda mõtteid uueks kasvuks. Koduses rütmis sobib see puhastamiseks, korrastamiseks ja värske alguse loomiseks.\n\n' +
      'Astronoomiliselt algab siit uus kuutsükkel.',
    tasks: ['planeeri', 'valmista peenrad', 'mulla ettevalmistus', 'külviks mõtteid'],
    homeTasks: ['puhastamine', 'korrastamine', 'värske algus'],
    astronomyShort: 'Astronoomiliselt algab siit uus kuutsükkel.',
    traditionKeyword: 'Uus tsükkel',
  },
  'Kasvav sirp': {
    moodHeadline: 'Kasvu alguse aeg.',
    leadParagraph:
      'Valgus lisandub iga päevaga ning rahvapäraselt seostatakse seda edenemise ja tärkamisega. Kas juured, lehed, õied või viljad — mida täna eelistada, määrab eelkõige kuu tähtkuju (juur-, lehe-, õie- või viljapäev), mitte ainult kuufaas; juurepäeval on mõistlik keskenduda juurtele ja mullale. Hea hetk midagi alustada, külvata ja liikuma panna.',
    textLong:
      'Kasvu alguse aeg.\n\n' +
      'Valgus lisandub iga päevaga ning rahvapäraselt seostatakse seda edenemise ja tärkamisega. Kas juured, lehed, õied või viljad — mida täna eelistada, määrab eelkõige kuu tähtkuju (juur-, lehe-, õie- või viljapäev), mitte ainult kuufaas; juurepäeval on mõistlik keskenduda juurtele ja mullale. Hea hetk midagi alustada, külvata ja liikuma panna.',
    tasks: ['külva', 'istuta', 'kerge hooldus', 'kasvu toetamine'],
    homeTasks: ['kergemad kodused tegevused', 'planeerimine'],
    traditionKeyword: 'Kasvav kuu',
  },
  'Esimene veerand': {
    moodHeadline: 'Tegutsemise ja edenemise aeg.',
    leadParagraph:
      'See on aktiivsem kasvufaas, kus sobivad hästi külvamine, istutamine ja aiatööd, millelt ootad nähtavat arengut. Kui tahad lihtsat tunnetust, siis see on „tee ära“ aeg — pane kasv liikuma.',
    textLong:
      'Tegutsemise ja edenemise aeg.\n\n' +
      'See on aktiivsem kasvufaas, kus sobivad hästi külvamine, istutamine ja aiatööd, millelt ootad nähtavat arengut. Kui tahad lihtsat tunnetust, siis see on „tee ära“ aeg — pane kasv liikuma.',
    tasks: ['külv', 'istutamine', 'salatid ja ürdid', 'niitmine'],
    homeTasks: ['tegevused, mis vajavad hoogu'],
    traditionKeyword: 'Esimene veerand',
  },
  'Kasvav kumer kuu': {
    moodHeadline: 'Jõu kogunemise aeg.',
    leadParagraph:
      'Kasvaval kuul kogub taim hoogu ja nähtavat jõudu. Biodünaamikas ja kuuaedniku traditsioonis täpsustab suunda kuu tähtkuju: juurepäeval (nt Sõnn) tasakaalusta juurte, istutuse ja mullaga; lehe-, õie- või viljapäeval vastavalt taimede osale. Koduses plaanis on see edenemist toetav aeg.',
    textLong:
      'Jõu kogunemise aeg.\n\n' +
      'Kasvaval kuul kogub taim hoogu ja nähtavat jõudu. Biodünaamikas täpsustab suunda kuu tähtkuju: juurepäeval tasakaalusta juurte ja mullaga, lehe-, õie- või viljapäeval vastavalt taimede osale.',
    tasks: ['järgi kuumärki', 'külv või istutus', 'mulla hooldus', 'kasvu toetamine'],
    homeTasks: ['kodused tööd, mis vajavad hoogu'],
    traditionKeyword: 'Kasvav kuu',
  },
  Täiskuu: {
    moodHeadline: 'Haripunkt ja nähtavuse aeg.',
    leadParagraph:
      'Täiskuu on kuutsükli kõige kirkam hetk. Rahvapäraselt seostatakse seda tugeva, aktiivse ja mõjusama ajaga. See on hea hetk vaadata üle, kuidas aed edeneb, märgata taimede seisundit ja hinnata tehtut. Mõnes traditsioonis peetakse seda rohkem vaatlemise ja hoolduse kui õrna istutamise ajaks.',
    textLong:
      'Haripunkt ja nähtavuse aeg.\n\n' +
      'Täiskuu on kuutsükli kõige kirkam hetk. Rahvapäraselt seostatakse seda tugeva, aktiivse ja mõjusama ajaga. See on hea hetk vaadata üle, kuidas aed edeneb, märgata taimede seisundit ja hinnata tehtut. Mõnes traditsioonis peetakse seda rohkem vaatlemise ja hoolduse kui õrna istutamise ajaks.',
    tasks: ['vaata üle', 'korja saaki', 'planeeri järgmist', 'hoolda'],
    homeTasks: ['suuremad kodused tegevused', 'tähelepanu nõudvad tööd'],
    traditionKeyword: 'Täiskuu',
  },
  'Kahanev kumer kuu': {
    moodHeadline: 'Juurdumise ja korrastamise aeg.',
    leadParagraph:
      'Pärast täiskuud hakkab valgus vähenema ning traditsioonis liigub rõhk juurtele, mullaelule ja vähendamisele. See sobib juurviljade, sibullillede, rohimise, tagasi lõikamise ja korrastustöödega. See on hea aeg ka kõige üleliigse eemaldamiseks.',
    textLong:
      'Juurdumise ja korrastamise aeg.\n\n' +
      'Pärast täiskuud hakkab valgus vähenema ning traditsioonis liigub rõhk juurtele, mullaelule ja vähendamisele. See sobib juurviljade, sibullillede, rohimise, tagasi lõikamise ja korrastustöödega. See on hea aeg ka kõige üleliigse eemaldamiseks.',
    tasks: ['juurviljad', 'rohi', 'kärbi', 'komposti'],
    homeTasks: ['eemaldamine', 'koduse korrastuse tööd'],
    traditionKeyword: 'Kahanev kuu',
  },
  'Viimane veerand': {
    moodHeadline: 'Puhastamise ja lõpetamise aeg.',
    leadParagraph:
      'Siin muutub kuutsükkel vaiksemaks. Aias sobivad hästi hooldus- ja korrastustööd, rohimine, harvendamine, kuivanud osade eemaldamine ja kompostiga tegelemine. Kodus on see hea aeg lõpetada pooleliolevad väiksed asjad ja teha ruumi uuele.',
    textLong:
      'Puhastamise ja lõpetamise aeg.\n\n' +
      'Siin muutub kuutsükkel vaiksemaks. Aias sobivad hästi hooldus- ja korrastustööd, rohimine, harvendamine, kuivanud osade eemaldamine ja kompostiga tegelemine. Kodus on see hea aeg lõpetada pooleliolevad väiksed asjad ja teha ruumi uuele.',
    tasks: ['rohi', 'harvenda', 'kompost', 'korista'],
    homeTasks: ['lõpetamine', 'väikesed asjad korda', 'koristus'],
    traditionKeyword: 'Viimane veerand',
  },
  'Kahanev sirp': {
    moodHeadline: 'Lahtilaskmise ja ettevalmistuse aeg.',
    leadParagraph:
      'See on tsükli lõpuosa, mida võib seostada rahunemise, puhastumise ja vana ära andmisega. Aias sobib see pinnase korrastamiseks, kompostiks, haigete osade eemaldamiseks ja järgmise etapi ettevalmistamiseks. Kodus sobib see suurpuhastuseks ja süsteemsemaks korrastamiseks.',
    textLong:
      'Lahtilaskmise ja ettevalmistuse aeg.\n\n' +
      'See on tsükli lõpuosa, mida võib seostada rahunemise, puhastumise ja vana ära andmisega. Aias sobib see pinnase korrastamiseks, kompostiks, haigete osade eemaldamiseks ja järgmise etapi ettevalmistamiseks. Kodus sobib see suurpuhastuseks ja süsteemsemaks korrastamiseks.',
    tasks: ['kompost', 'mulla ettevalmistus', 'kahjurite tõrje', 'juured'],
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
    homeTasks: uniq(rule.homeTasks),
    astronomyShort: rule.astronomyShort,
    traditionKeyword: rule.traditionKeyword,
  };
}
