import { SYNODIC_MONTH } from './moon';

/**
 * Pärimuslik „pehme / kõva / mäda / kuiv“ — ühe päeva kohta, Kuu vanuse (ageDays) järgi.
 * Lihtsustatud reeglid (läbipaistev äpp), mitte üks konkreetne rahvakalender.
 */

const SOFT_DAYS = 3;
/** Mäda: vahetult pärast uuskuud, aga ainult pehmema perioodi sees (ei saa olla koos kõvaga). */
const MÄDA_AFTER_NEW_DAYS = 4;
/** Kuiv: noorkuu alguse ja vana kuu lõpu ümbrus (pärimus). */
const KUIV_EDGE_DAYS = 2.5;

export type FolkTimingId = 'pehme' | 'kõva' | 'mäda' | 'kuiv';

export type FolkTimingDayEntry = {
  id: FolkTimingId;
  title: string;
  body: string;
};

const COPY: Record<FolkTimingId, Omit<FolkTimingDayEntry, 'id'>> = {
  pehme: {
    title: 'Pehme aeg',
    body:
      'Pehmet aega on vanarahvas seostanud pehmenemise, puhastumise, juurdumise ja õrnemate töödega. Seda on peetud heaks näiteks pesupesuks, istutamiseks ja teatud hooldustöödeks. Eesti pärimusallikates leidub otseseid märkusi, et pehme aja sees läheb pesu hästi puhtaks ja mõnel pool peeti seda ka istutamiseks heaks ajaks.',
  },
  kõva: {
    title: 'Kõva aeg',
    body:
      'Kõva aeg on seotud tugevuse, kuivuse ja vastupidavusega. Rahvapärases mõtlemises sobib see rohkem töödele, millelt oodatakse püsivust või sitkust. Mõnes pärimustekstis öeldakse, et kui kuul on teravad nurgad, on kõva aeg.',
  },
  mäda: {
    title: 'Mäda aeg',
    body:
      'Mäda aeg on vanades uskumustes pehmema aja sugulane — aeg, mida seostatakse lagunemise, pehmenemise ja teatud tööde vältimisega. Põlva pärimuses on näiteks öeldud, et pärast kuu loomist võivad tulla pehmed või mäda ajad. Seda võiks mõista kui pärimuslikku nimetust, mitte teaduslikku nähtust.',
  },
  kuiv: {
    title: 'Kuiv aeg',
    body:
      'Kuiv aeg on seotud noorkuu alguse ja vana kuu lõpu ümbrusega. Vanarahvas seostas seda mõnikord näiteks puidutööde või vastupidavust vajavate töödega.',
  },
};

/**
 * Mis pärimuslikud ajastuse märgised sellele Kuu vanusele vastavad (kuva eraldi ridadena).
 * - Pehme või kõva: alati üks
 * - Mäda: vahetult pärast uuskuud, kuid ainult kui päev on ka pehme aknas (mäda on pehme „alamärk“).
 * - Kuiv: tsükli alguse ja lõpu ümbruses (võib kattuda pehme või kõvaga — eri mõõde).
 */
export function folkTimingForAgeDays(ageDays: number): FolkTimingDayEntry[] {
  const age = ((ageDays % SYNODIC_MONTH) + SYNODIC_MONTH) % SYNODIC_MONTH;

  const pehme = age <= SOFT_DAYS || age >= SYNODIC_MONTH - SOFT_DAYS;
  const mäda = pehme && age > 0 && age <= MÄDA_AFTER_NEW_DAYS;
  const kuiv = age <= KUIV_EDGE_DAYS || age >= SYNODIC_MONTH - KUIV_EDGE_DAYS;

  const out: FolkTimingDayEntry[] = [];

  if (pehme) {
    out.push({ id: 'pehme', ...COPY.pehme });
  } else {
    out.push({ id: 'kõva', ...COPY.kõva });
  }

  if (mäda) {
    out.push({ id: 'mäda', ...COPY.mäda });
  }
  if (kuiv) {
    out.push({ id: 'kuiv', ...COPY.kuiv });
  }

  return out;
}
