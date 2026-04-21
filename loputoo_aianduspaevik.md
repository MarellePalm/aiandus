# Kuressaare Ametikool
## IT ja ettevõtluse õppesuund
## Noorem tarkvaraarendaja TA-24

  
  
  
  
  
  
Kadri Kaljo, Marelle Palm, Liisa Rank  
**Aianduspäevik**  
Lõputöö

  
  
Juhendaja:  
Magnar Aruoja

  
  
  
Kuressaare 2026

---

# Sisukord

SISSEJUHATUS

1. PROJEKTI KAVANDAMINE  
1.1. Lähteülesanne  
1.2. Nõuete kirjeldus  
1.3. Kasutajad ja kasutusstsenaariumid  
1.4. Arendusmetoodika  
1.5. Tegevus- ja ajakava  
1.6. Kasutatavad tehnoloogiad ja töövahendid  
1.7. Eelarve

2. RAKENDUSE KAVANDAMINE  
2.1. Süsteemi arhitektuur  
2.2. Andmemudel ja andmebaasi skeem  
2.3. Kasutajaliidese lahendused  
2.4. Skeemid ja prototüübid

3. LAHENDUSE VÄLJATÖÖTAMINE  
3.1. Arendusprotsessi kirjeldus  
3.2. Olulisemad tehnilised lahendused  
3.3. Kasutatud programmeerimiskeeled ja raamistikud  
3.4. Koodi näited

4. TESTIMINE  
4.1. Testimise põhimõtted ja plaan  
4.2. Testjuhtumid  
4.3. Testimise tulemused  
4.4. Leitud vead ja nende parandamine

5. JUURUTAMINE JA KASUTUSELEVÕTT  
5.1. Lahenduse paigaldamine  
5.2. Kasutuselevõtu kirjeldus  
5.3. Kasutuskeskkond

6. DOKUMENTATSIOON  
6.1. Kasutusjuhend  
6.2. Tehniline dokumentatsioon

7. TULEMUSED JA ANALÜÜS  
7.1. Vastavus algsetele nõuetele  
7.2. Võrdlus esialgse plaaniga  
7.3. Lahenduse praktiline kasutatavus  
7.4. Töö kvaliteedi hinnang  
7.5. Jätkusuutlikkus  
7.6. Edasised arendusvõimalused

8. ENESEANALÜÜS

9. MEESKONNATÖÖ KIRJELDUS  
9.1. Meeskonna koosseis  
9.2. Rollid  
9.3. Ülesannete jaotus  
9.4. Koostööprotsessi kirjeldus

KOKKUVÕTE  
KASUTATUD ALLIKAD  
LISAD

---

# SISSEJUHATUS

Digilahenduste kasutamine igapäevaelus on muutunud tavapäraseks ka valdkondades, mida varem peeti pigem traditsioonilisteks ja käsitöönduslikeks. Aiandus on üks sellistest valdkondadest, kus inimeste tegevus põhineb sageli isiklikel märkmetel, kogemustel ja hooajalistel tähelepanekutel. Praktikas tähendab see, et taimede külvamise, hooldamise, väetamise ja saagikoristuse kohta tehtud märkmed võivad jääda paberile, erinevatesse vihikutesse või killustunult telefoni märkmerakendustesse. Selline lähenemine muudab info haldamise ebamugavaks ning raskendab varasemate andmete võrdlemist ja analüüsimist.

Käesoleva lõputöö teemaks on veebirakenduse **Aianduspäevik** kavandamine ja arendamine. Töö eesmärk oli luua kasutajasõbralik digitaalne lahendus, mis võimaldab kasutajal pidada aiandusega seotud tegevuste üle süsteemset päevikut. Rakendus aitab talletada teavet taimede, tööde, kastmise, väetamise, saagikuse ning muude oluliste tegevuste kohta. Lisaks aitab lahendus koondada ühte kohta infot, mida on võimalik hiljem mugavalt üle vaadata, täiendada ja kasutada järgmiste hooaegade planeerimisel.

Probleemi lähteolukord seisnes selles, et aiandushuvilistel puudub sageli üks lihtne ja terviklik süsteem, kuhu oma tegevused kirja panna. Kuigi olemas on mitmeid üldiseid märkmerakendusi ja kalendrilahendusi, ei ole need enamasti kohandatud just aianduse vajadustele. Samuti ei võimalda need sageli siduda taimede infot konkreetsete kuupäevade, tööde või kasvuperioodidega. Meie meeskond nägi selles võimalust luua praktiline rakendus, mis vastaks just aiandusega tegeleva kasutaja ootustele.

Teema valik oli põhjendatud nii praktilise vajaduse kui ka õppetöö eesmärkidega. Valitud projekt võimaldas rakendada tarkvaraarenduse õpingute jooksul omandatud teadmisi kogu arendustsükli ulatuses alates idee sõnastamisest ja nõuete analüüsist kuni disaini, arenduse, testimise ja dokumenteerimiseni. Samuti oli tegemist piisavalt mahuka meeskonnaprojektiga, mis andis võimaluse tööülesandeid jagada, koostööd planeerida ning analüüsida meeskonnatöö toimimist.

Töö on üles ehitatud vastavalt tarkvaraarenduse loogilisele protsessile. Esmalt kirjeldatakse projekti kavandamist, sealhulgas lähteülesannet, nõudeid, sihtrühma ja töökorraldust. Seejärel käsitletakse rakenduse disaini, süsteemi arhitektuuri ja andmemudelit. Edasi antakse ülevaade arendusprotsessist ja tehnilistest lahendustest. Pärast seda kirjeldatakse testimist, juurutamist ja dokumentatsiooni. Lõpuosas analüüsitakse saavutatud tulemusi, hinnatakse töö kvaliteeti ning tuuakse välja edasised arendusvõimalused, eneseanalüüs ja meeskonnatöö kirjeldus.

Käesolev töö on seotud kutsekompetentsidega, mis hõlmavad tarkvaralahenduse kavandamist, disainimist, arendamist, testimist, juurutamist ja dokumenteerimist. Seega annab lõputöö tervikliku ülevaate meie oskustest rakendada õppetöö jooksul omandatud teadmisi reaalse tarkvaraprojekti loomisel.

---

# 1. PROJEKTI KAVANDAMINE

## 1.1. Lähteülesanne

Projekti lähteülesandeks oli luua veebipõhine aianduspäeviku rakendus, mis võimaldab kasutajatel hallata oma aiandustegevustega seotud infot ühes keskkonnas. Rakenduse peamine eesmärk oli lihtsustada taimede kasvatamise ja hooldamisega seotud tegevuste jälgimist ning muuta varasemate märkmete leidmine mugavaks ja loogiliseks.

Lahendus pidi võimaldama kasutajal lisada taimi, pidada päevikut tehtud tööde kohta, salvestada olulisi kuupäevi ning kuvada kogutud infot arusaadaval viisil. Lisaks seati eesmärgiks luua süsteem, mis oleks piisavalt paindlik, et seda saaks tulevikus täiendada näiteks meeldetuletuste, statistika või ilmapõhiste soovitustega.

Lähteülesande koostamisel lähtusime eeldusest, et lõppkasutajaks on inimene, kellel on huvi aianduse vastu, kuid kelle tehnilised teadmised võivad olla tagasihoidlikud. Sellest tulenevalt pidi rakendus olema lihtsa ülesehituse ja selge kasutusloogikaga.

## 1.2. Nõuete kirjeldus

Rakenduse funktsionaalsete nõuete hulka kuulus kasutaja võimalus lisada ja hallata taimede kirjeid, sisestada aiandustegevuste sissekandeid, vaadata varasemaid märkmeid ning muuta või kustutada lisatud andmeid. Samuti pidi rakendus toetama kasutajakonto loomist ja sisselogimist, et iga kasutaja näeks ainult endale kuuluvat infot.

Mittefunktsionaalsete nõuete hulka kuulus kasutajasõbralikkus, piisav jõudlus tavakasutuse juures, andmete säilimine ning turvaline autentimine. Lisaks oli oluline, et rakendus oleks kasutatav erinevates seadmetes ja ekraanisuurustes, mis tähendas, et liides pidi olema responsiivne.

Nõuete sõnastamisel pöörasime tähelepanu sellele, et rakendus ei muutuks liiga keeruliseks. Kuna tegemist oli õppeprojektiga, seadsime eesmärgiks rakendada põhifunktsionaalsused kvaliteetselt ja läbimõeldult.

## 1.3. Kasutajad ja kasutusstsenaariumid

Rakenduse peamiseks sihtrühmaks on hobiaednikud, koduaedade pidajad ning kasutajad, kes soovivad oma taimede kasvatamist süsteemselt jälgida. Võimaliku sihtrühmana nägime ka väiksemahulisi kogukonnaaedu või õppeotstarbelisi projekte, kus soovitakse dokumenteerida taimede arengut ja tehtud töid.

Üheks peamiseks kasutusstsenaariumiks on olukord, kus kasutaja lisab süsteemi uue taime, märgib selle istutamise kuupäeva ning lisab jooksvalt päevikusse sissekandeid kastmise, väetamise, ümberistutamise või saagikoristuse kohta. Teiseks kasutusstsenaariumiks on varasemate sissekannete ülevaatamine, et võrrelda erinevate taimede kasvutingimusi või hinnata eelmiste hooaegade tulemusi.

Veel üheks oluliseks stsenaariumiks on kasutaja soov uuendada taimede andmeid, näiteks muuta taime asukohta, märkida kasvufaasi muutust või eemaldada kirje, kui taim ei ole enam aktiivne. Sellised stsenaariumid aitasid meil defineerida süsteemi põhifunktsioonid ja kasutajateekonnad.

## 1.4. Arendusmetoodika

Projekti arendamisel kasutasime Agile põhimõtetel põhinevat lähenemist. Kuna meeskond oli väike ja projekti maht mõõdukas, ei rakendanud me rangelt kõiki Scrum-metoodika formaalseid osi, kuid lähtusime iteratiivsest töökorraldusest. Jagasime suuremad eesmärgid väiksemateks ülesanneteks, leppisime kokku tähtajad ning arutasime regulaarselt töö edenemist.

Selline lähenemine võimaldas meil teha töö käigus muudatusi vastavalt sellele, kuidas projekt arenes ja millised tehnilised või sisulised väljakutsed tekkisid. Näiteks saime varakult aru, millised funktsioonid on esmase versiooni jaoks vältimatult vajalikud ja millised sobivad pigem hilisemaks edasiarenduseks.

Agile-lähenemise eeliseks oli ka see, et meeskonnaliikmed said üksteise tööga kursis olla ning vajadusel aidata lahendada probleeme, mis mõjutasid kogu projekti edenemist.

## 1.5. Tegevus- ja ajakava

Projekti tegevus- ja ajakava jaotasime mitmeks etapiks. Esimeses etapis tegelesime idee täpsustamise, nõuete kogumise ja tööjaotuse loomisega. Teises etapis keskendusime süsteemi ja kasutajaliidese kavandamisele ning sobivate tehnoloogiate valikule. Kolmandas etapis toimus arendustöö, mille käigus valmis rakenduse põhifunktsionaalsus. Seejärel viisime läbi testimise, tegime vajalikud parandused ning koostasime lõppdokumentatsiooni.

Ajakava planeerimisel arvestasime nii õppetöö koormuse kui ka meeskonnaliikmete muude kohustustega. Seetõttu oli oluline jaotada töö osadeks, mida sai paralleelselt teha. Näiteks sai üks meeskonnaliige tegeleda kasutajaliidese kirjeldamisega samal ajal, kui teine keskendus andmemudelile ja kolmas testimise ettevalmistamisele.

Kuigi algne ajakava muutus töö käigus mõnevõrra, jäi üldine tegevusplaan paika ning aitas hoida projekti selgelt struktureerituna.

## 1.6. Kasutatavad tehnoloogiad ja töövahendid

Rakenduse arendamisel kasutasime kaasaegseid veebiarenduse tehnoloogiaid, mis võimaldasid luua funktsionaalse ja laiendatava lahenduse. Kasutajaliidese loomisel kasutasime HTML-i, CSS-i ja JavaScripti ning projekti loogika ja andmetöötluse jaoks sobivat raamistikku või serveripoolset tehnoloogiat vastavalt valitud lahendusele. Andmete salvestamiseks kasutasime andmebaasi, mis võimaldas hallata kasutajate, taimede ja päevikusissekannete vahelisi seoseid.

Versioonihalduseks kasutasime Git-i ning koodi hoiustamiseks GitHubi. Meeskonnasisese suhtluse ja tööülesannete koordineerimiseks kasutasime kokkulepitud suhtluskanaleid ning ülesannete nimekirju. Disaini ja kavandamise toetamiseks kasutasime visuaalseid töövahendeid, näiteks wireframe'ide või skeemide koostamise keskkondi.

Tehnoloogiate valikul pidasime oluliseks seda, et need oleksid meile õppetöö raames tuttavad ning võimaldaksid projektis näidata praktilisi tarkvaraarenduse oskusi.

## 1.7. Eelarve

Kuna tegemist oli õppeprojektiga, ei olnud projektil otsest rahalist eelarvet tavapärases ärilises mõttes. Arendus toimus olemasolevate vahendite ja tasuta või hariduslikel eesmärkidel kasutatavate tööriistade abil. Suuremaks ressursiks kujunes meeskonnaliikmete aeg, mida tuli planeerida eesmärgipäraselt ja realistlikult.

Kui käsitleda projekti hüpoteetilise tootearenduse vaates, tuleks eelarvesse arvestada arendustöö maksumus, disaini loomine, testimine, võimalike serveri- ja domeenikulude katmine ning hilisem hooldus. Õppeprojekti kontekstis keskendusime siiski eelkõige sisulisele ja tehnilisele teostusele, mitte ärilisele tasuvusanalüüsile.

---

# 2. RAKENDUSE KAVANDAMINE

## 2.1. Süsteemi arhitektuur

Aianduspäeviku rakendus kavandati mitmekihilise veebilahendusena, kus kasutajaliides, äriloogika ja andmesalvestus on üksteisest loogiliselt eraldatud. Selline ülesehitus aitab muuta süsteemi selgemaks, lihtsamini hallatavaks ja tulevikus paremini laiendatavaks.

Kasutaja suhtleb rakendusega veebibrauseri kaudu. Kasutajaliides saadab päringuid serveripoolele, kus töödeldakse sisestatud andmeid, kontrollitakse õiguseid ja tehakse vajalikud andmebaasipäringud. Andmebaas talletab kasutajate, taimede ja päevikusissekannete andmeid. Kui kasutaja lisab uue sissekande või muudab olemasolevat infot, liigub info kasutajaliidesest serverisse ja sealt edasi andmebaasi.

Selline arhitektuur toetab hästi ka edasisi arendusvõimalusi. Näiteks oleks võimalik lisada eraldi statistikamoodul, teavituste süsteem või ilmastikuandmete integratsioon ilma, et kogu rakendust peaks ümber ehitama.

## 2.2. Andmemudel ja andmebaasi skeem

Rakenduse andmemudeli kavandamisel lähtusime põhimõttest, et andmestruktuur peab toetama kasutaja ja tema aiandustegevuste seostamist. Olulisemateks olemiteks kujunesid kasutaja, taim ja päevikusissekanne. Kasutaja olem sisaldab sisselogimiseks ja konto haldamiseks vajalikke andmeid. Taim seostub kindla kasutajaga ning sisaldab infot näiteks taime nime, liigi, istutamise aja, kasvukoha ja staatuse kohta. Päevikusissekanne on seotud konkreetse taimega ning salvestab info tehtud tegevuse, kuupäeva ja märkmete kohta.

Selline andmemudel võimaldab igal kasutajal hallata mitut taime ning lisada iga taime kohta mitmeid sissekandeid. Seoste loomine andmebaasis aitab tagada, et süsteemi andmed oleksid loogiliselt seotud ja hilisemates päringutes hästi kasutatavad.

Andmebaasi skeemi kavandamisel oli oluline vältida liigset keerukust. Kuna lõputöö eesmärk ei olnud luua mahukat ettevõtterakendust, piisas selgelt defineeritud põhistruktuurist, mis toetab rakenduse keskseid funktsioone.

## 2.3. Kasutajaliidese lahendused

Kasutajaliidese kavandamisel pidasime kõige olulisemaks lihtsust ja arusaadavust. Rakenduse sihtrühm ei koosne tingimata tehniliselt väga kogenud kasutajatest, mistõttu pidi liides olema intuitiivne ja loogilise ülesehitusega. Peamised vaated kavandati nii, et kasutaja saaks kiiresti liikuda avalehelt taimede loendisse, konkreetse taime detailvaatesse ning sealt edasi päevikusissekannete lisamise või muutmise juurde.

Visuaalses lahenduses eelistasime rahulikku ja teemaga sobivat kujundust. Aiandusega seotud rakenduse puhul on loomulik kasutada looduslähedasi värvitoone ja puhtaid vorme, mis toetavad kasutusmugavust ega koorma liigselt tähelepanu. Samuti pidasime oluliseks, et vormid ja nupud oleksid hästi märgatavad ning sisestusväljad piisavalt selged.

Responsiivsus oli kasutajaliidese puhul oluline, sest eeldasime, et rakendust võidakse kasutada nii arvutis kui ka mobiilseadmes. Seetõttu kavandasime vaated nii, et need kohanduksid erinevate ekraanisuurustega.

## 2.4. Skeemid ja prototüübid

Rakenduse kavandamise toetamiseks koostasime skeeme ja prototüüpe, mis aitasid enne arenduse alustamist läbi mõelda süsteemi üldise loogika. Skeemide abil kirjeldasime kasutajavoogu, andmete liikumist ning peamiste vaadete ülesehitust. See võimaldas meeskonnal ühtlustada arusaama sellest, kuidas rakendus peaks toimima.

Wireframe'id aitasid visualiseerida peamiste ekraanivaadete paigutust enne detailsema disaini loomist. Nende abil saime arutada, millised funktsioonid peavad olema kasutajale kohe nähtavad ja millised võivad asuda detailvaadetes. See vähendas arenduse käigus tehtavate suuremate ümberkorralduste vajadust.

Skeemid ja prototüübid olid olulised vahendid nii meeskonnasisese suhtluse kui ka töö dokumenteerimise seisukohalt, sest need aitasid teha tehnilised ja visuaalsed otsused läbipaistvamaks.

---

# 3. LAHENDUSE VÄLJATÖÖTAMINE

## 3.1. Arendusprotsessi kirjeldus

Rakenduse arendamine toimus etapiviisiliselt. Esmalt lõime projekti põhistruktuuri ja panime paika peamised vaated ning andmebaasi olemid. Seejärel keskendusime kasutajate autentimisele ja põhifunktsioonidele, nagu taimede lisamine, kirjetega töötamine ja päevikusissekannete haldamine. Kui keskne loogika oli valmis, täiendasime kasutajaliidest ja parandasime kasutusmugavust.

Arendusprotsessi käigus testisime funktsionaalsusi jooksvalt. See aitas avastada probleeme varakult ning vältida olukorda, kus suuremad vead ilmnevad alles lõppfaasis. Kuna tegemist oli meeskonnatööga, oli oluline, et kõik muudatused oleksid dokumenteeritud ja versioonihalduses jälgitavad.

Töö käigus tuli teha ka valikuid selle osas, milliseid funktsioone esimeses versioonis ellu viia. Mõned algselt kaalutud ideed, näiteks keerukam statistika või automaatsed teavitused, jäid teadlikult edasise arenduse võimaluseks.

## 3.2. Olulisemad tehnilised lahendused

Üheks olulisemaks tehniliseks lahenduseks oli kasutajapõhine andmete eristamine. Kuna rakendusel on sisselogimise funktsioon, tuli tagada, et iga kasutaja näeb ainult endaga seotud taimi ja sissekandeid. See lahendati kasutaja identifikaatori sidumise kaudu andmebaasi kirjetega ning vastavate kontrollide rakendamisega serveripoole loogikas.

Teiseks oluliseks lahenduseks oli päevikusissekannete seostamine konkreetsete taimedega. See võimaldab kasutajal vaadata ühe taime kohta kogu ajalugu ning luua selge ülevaate selle arengust ja hooldusest. Selline struktuur muudab rakenduse praktiliseks töövahendiks, mitte lihtsalt üldiseks märkmerakenduseks.

Tähelepanu pöörasime ka vormide valideerimisele. Kasutaja sisestatud andmed pidid olema piisavalt korrektsed, et süsteem töötaks usaldusväärselt. Seetõttu kontrolliti oluliste väljade täitmist ja vajadusel kuvati kasutajale arusaadavaid veateateid.

## 3.3. Kasutatud programmeerimiskeeled ja raamistikud

Rakenduse loomisel kasutasime veebiarenduses laialt levinud tehnoloogiaid. Kasutajaliidese ehitamiseks kasutasime HTML-i, CSS-i ja JavaScripti, mis võimaldasid luua interaktiivse ja kasutajasõbraliku veebikeskkonna. Serveripoole loogika ja andmetöötluse jaoks kasutasime projekti vajadustele sobivat tehnoloogiat, mis võimaldas päringuid töödelda, autentimist hallata ning andmebaasiga suhelda.

Lisaks programmeerimiskeeltele kasutasime raamistikku või teeke, mis lihtsustasid rakenduse ülesehitamist ja kiirendasid arendusprotsessi. Versioonihalduseks kasutasime Git-i, mis võimaldas kõigil meeskonnaliikmetel panustada projekti arendusse kontrollitud ja jälgitaval viisil.

Kasutatud tehnoloogiate valik lähtus peamiselt nende sobivusest õppeprojekti jaoks, meeskonnaliikmete varasemast kokkupuutest ning soovist luua praktiline ja toimiv lahendus.

## 3.4. Koodi näited

Koodi näidete eesmärk on illustreerida rakenduse põhiloogikat. Näiteks võib tuua funktsionaalsuse, mille abil lisatakse andmebaasi uus taim või salvestatakse päevikusissekanne. Samuti sobivad lisadesse autentimise, andmete päringu või vormi valideerimise näited.

Lõputöö põhitekstis ei ole otstarbekas esitada liiga mahukaid koodiplokke, kuna see muudaks töö raskesti loetavaks. Seetõttu on mõistlik tuua põhiosas välja ainult lühemad ja sisuliselt olulised näited ning ülejäänud materjal paigutada lisadesse.

Koodi näited tõendavad, et lahenduse loomisel rakendati reaalseid programmeerimisoskusi ning et projekti tehniline teostus ei jäänud üksnes teoreetilisele tasandile.

---

# 4. TESTIMINE

## 4.1. Testimise põhimõtted ja plaan

Testimise eesmärk oli veenduda, et Aianduspäeviku rakendus töötab vastavalt seatud nõuetele ning pakub kasutajale usaldusväärset kasutuskogemust. Testimisel keskendusime peamiselt funktsionaalsele testimisele, mille käigus kontrollisime, kas kasutaja saab edukalt sisse logida, lisada taimi, muuta andmeid, kustutada kirjeid ning hallata päevikusissekandeid.

Testimisplaan koostati selliselt, et iga olulisem funktsioon läbiti eraldi testjuhtumite abil. Lisaks kontrollisime kasutajaliidese loogikat ja seda, kas veateated kuvatakse arusaadavalt olukordades, kus kasutaja jätab kohustusliku välja täitmata või sisestab sobimatud andmed.

Testimisel lähtusime põhimõttest, et esmalt tuleb tagada rakenduse kesksete funktsioonide töökindlus. Alles seejärel keskendusime väiksematele kasutusmugavuse parandustele ja visuaalsetele detailidele.

## 4.2. Testjuhtumid

Testjuhtumite hulka kuulusid näiteks kasutaja registreerimise ja sisselogimise kontroll, uue taime lisamine, olemasoleva taime andmete muutmine, päevikusissekande lisamine ja kirje kustutamine. Samuti testisime olukordi, kus kasutaja sisestab puudulikke andmeid või proovib teha tegevust ilma autentimata.

Iga testjuhtumi puhul kirjeldasime sisendi, oodatava tulemuse ja tegeliku tulemuse. Selline lähenemine aitas hoida testimise süsteemse ja hiljem ka dokumenteeritava. Lisaks sai testtabelite põhjal kiiresti ülevaate sellest, millised funktsioonid töötasid kohe korrektselt ja millised vajasid parandamist.

Testjuhtumite koostamine oli oluline ka seetõttu, et see sundis meid vaatama rakendust lõppkasutaja vaatenurgast, mitte ainult arendaja loogika põhjal.

## 4.3. Testimise tulemused

Testimise tulemusena selgus, et rakenduse põhifunktsioonid toimisid üldjoontes ootuspäraselt. Kasutaja sai luua konto, sisse logida, taimi lisada ning nende kohta päeviku sissekandeid teha. Andmete muutmine ja kustutamine töötasid samuti vastavalt kavandatud loogikale.

Testimise käigus tuli siiski välja mõningaid puudusi, mis olid seotud näiteks sisestusväljade kontrollimise, veateadete arusaadavuse või kasutajaliidese väiksemate ebamugavustega. Need probleemid ei takistanud rakenduse põhikasutust, kuid nende parandamine muutis lahenduse kvaliteetsemaks ja kasutajasõbralikumaks.

Kokkuvõttes võib öelda, et testimise tulemusena jõudsime toimiva prototüübi või minimaalse elujõulise tooteni, mis vastab lõputöö eesmärgile.

## 4.4. Leitud vead ja nende parandamine

Leitud vigade hulgas esines näiteks olukordi, kus vorm ei kontrollinud kõiki kohustuslikke välju piisavalt rangelt või kus kasutajale kuvatud teated ei olnud piisavalt täpsed. Samuti ilmnes mõningaid kasutajaliidese paigutusprobleeme väiksematel ekraanidel.

Need vead parandati arenduse lõppfaasis. Vormide valideerimist täiendati, teateid muudeti kasutajale arusaadavamaks ning liidese elementide paigutust korrigeeriti. Vajaduse korral vaadati üle ka andmebaasi päringute loogika, et tagada andmete korrektne salvestumine ja kuvamine.

Testimise ja paranduste protsess näitas, kui oluline on tarkvaraarenduses pidev kontroll ja tagasiside, sest ka esmapilgul toimiv lahendus võib reaalses kasutuses vajada mitmeid täiendusi.

---

# 5. JUURUTAMINE JA KASUTUSELEVÕTT

## 5.1. Lahenduse paigaldamine

Rakenduse paigaldamine sõltus kasutatud tehnoloogiast ja keskkonnast, kuid üldjoontes koosnes see projekti lähtekoodi allalaadimisest, vajalike sõltuvuste paigaldamisest, andmebaasi seadistamisest ning rakenduse käivitamisest arenduskeskkonnas või serveris. Paigaldusprotsessi dokumenteerimine oli oluline, et rakendust oleks võimalik hiljem uuesti kasutusele võtta või edasi arendada.

Õppeprojekti kontekstis oli oluline, et paigaldusprotsess oleks võimalikult selge ja korratav. Seetõttu kirjeldasime ära peamised sammud, mida arendaja või hindaja peab tegema, et lahendus tööle saada.

## 5.2. Kasutuselevõtu kirjeldus

Kasutuselevõtt tähendas seda, et rakendus tehti sihtkasutajale või testkasutajale kättesaadavaks. Praktikas võis see toimuda kas lokaalses arenduskeskkonnas, kooli serveris või mõnes pilvekeskkonnas. Oluline oli veenduda, et rakendus oleks pärast paigaldamist reaalselt kasutatav ning et kasutaja saaks põhifunktsioone takistusteta proovida.

Kasutuselevõtu käigus kontrollisime veel kord, kas süsteem töötab tervikuna ning kas andmebaasi ühendus, autentimine ja kasutajaliides toimivad üheskoos ootuspäraselt.

## 5.3. Kasutuskeskkond

Rakenduse kasutuskeskkonnaks sobib veebibrauseriga seade, millel on internetiühendus või ligipääs vastavale serverile. Arenduskeskkond koosnes programmeerimiseks sobivast töövahendist, versioonihaldusest ja andmebaasi haldamise lahendusest.

Kui rakendus paigutada pilve- või serverikeskkonda, tuleb arvestada ka turvalisuse, andmete varundamise ja süsteemi hooldusega. Õppeprojekti raames keskendusime siiski eelkõige sellele, et lahendus oleks tehniliselt toimiv ja näitaks ära tarkvaraarenduse protsessi peamised etapid.

---

# 6. DOKUMENTATSIOON

## 6.1. Kasutusjuhend

Kasutusjuhendi eesmärk on aidata lõppkasutajal rakendust kasutada ilma täiendava juhendamiseta. Juhendis kirjeldatakse, kuidas kasutaja loob konto, logib sisse, lisab uusi taimi, teeb päevikusissekandeid ning muudab või kustutab olemasolevaid andmeid. Samuti selgitatakse, kuidas liikuda erinevate vaadete vahel ja kust leida vajalikku infot.

Hea kasutusjuhend peab olema lihtne, selge ja samm-sammuline. Vajaduse korral saab seda täiendada ekraanipiltidega, mis muudavad juhendi veelgi arusaadavamaks. Kasutusjuhend on oluline, sest see aitab hinnata, kas lahendus on kasutajale iseseisvalt mõistetav.

## 6.2. Tehniline dokumentatsioon

Tehniline dokumentatsioon on suunatud arendajale või inimesele, kes soovib projekti hiljem edasi arendada. Selles kirjeldatakse süsteemi ülesehitust, kasutatud tehnoloogiaid, andmebaasi struktuuri, paigaldamise samme ning vajaduse korral ka API või olulisemate moodulite loogikat.

Tehniline dokumentatsioon aitab tagada, et projekt ei jääks arusaadavaks ainult selle algsetele autoritele. Hästi koostatud dokumentatsioon lihtsustab hooldust, paranduste tegemist ja uute funktsioonide lisamist. Õppeprojekti seisukohalt näitab tehnilise dokumentatsiooni olemasolu ka seda, et arendustöö on läbimõeldud ja professionaalselt vormistatud.

---

# 7. TULEMUSED JA ANALÜÜS

## 7.1. Vastavus algsetele nõuetele

Valminud Aianduspäeviku rakendus vastas suuremas osas töö alguses seatud eesmärkidele ja nõuetele. Loodud lahendus võimaldab kasutajal hallata taimi, lisada päevikusissekandeid ning vaadata ja muuta varem sisestatud andmeid. Samuti realiseerus kasutajapõhine ligipääs, mille kaudu iga kasutaja saab kasutada just enda andmeid.

Mõningad soovitud lisafunktsioonid jäid esialgsest versioonist välja, kuid see oli teadlik otsus, et keskenduda kõige olulisemate põhifunktsioonide korrektsele toimimisele. Seega võib öelda, et rakendus vastas algsetele tuumnõuetele.

## 7.2. Võrdlus esialgse plaaniga

Esialgse plaaniga võrreldes püsis projekti üldine suund paigas, kuid töö käigus tuli teha mõningaid täpsustusi. Näiteks selgus arenduse jooksul, et kõiki alguses mõeldud ideid ei ole mõistlik sama projekti raames realiseerida. Selle asemel keskendusime funktsioonidele, mis loovad rakendusele praktilise väärtuse ja moodustavad tervikliku lahenduse.

Planeeritud etapid, nagu kavandamine, disain, arendus, testimine ja dokumentatsioon, said kõik läbitud. Muutused puudutasid pigem töö mahtu ja prioriteete, mitte projekti põhieesmärki.

## 7.3. Lahenduse praktiline kasutatavus

Valminud rakendus on praktiliselt kasutatav lahendus inimesele, kes soovib oma aiandustegevusi süsteemselt talletada. Selle suurim väärtus seisneb info koondamises ühte keskkonda, kus kasutaja saab varasemaid tegevusi jälgida ja planeerida uusi samme teadlikumalt.

Kasutusmugavuse seisukohalt on oluline, et rakendus oleks lihtsasti mõistetav ka kasutajale, kes ei oma sügavaid tehnilisi teadmisi. Selle eesmärgi täitmiseks keskendusime selgele navigeerimisele, loogilistele vaadetele ja arusaadavatele vormidele.

## 7.4. Töö kvaliteedi hinnang

Töö kvaliteeti võib hinnata heaks, arvestades projekti eesmärki, meeskonna suurust ja tegemist õppeprojektiga. Valmis sai toimiv lahendus, mille puhul on läbi mõeldud nii tehniline pool kui ka kasutajakogemus. Lisaks dokumenteeriti töö etapid ning analüüsiti saadud tulemust.

Kõige olulisem kvaliteedinäitaja oli see, et projekt ei jäänud ideetasemele, vaid jõudis toimiva lahenduseni. Samas näitas tööprotsess ka seda, et kvaliteedi tõstmiseks on oluline varuda piisavalt aega testimisele ja detailide viimistlemisele.

## 7.5. Jätkusuutlikkus

Aianduspäeviku lahendus on jätkusuutlik selles mõttes, et selle põhifunktsioonid loovad tugeva aluse edasiseks arenduseks. Süsteemi saab täiendada uute moodulitega ilma, et tuleks kogu rakendus nullist ümber teha. Näiteks on võimalik lisada teavitused, taimede kategooriad, pildifunktsionaalsus, statistikavaated või hooajalised soovitused.

Jätkusuutlikkust toetab ka see, et rakenduse loomisel kasutati üldlevinud veebitehnoloogiaid, millele on olemas lai kogukondlik tugi ja õppematerjalid.

## 7.6. Edasised arendusvõimalused

Tulevikus võiks rakendust täiendada mitmel viisil. Üheks võimaluseks on lisada kalendrivaade ja automaatsed meeldetuletused, mis aitaksid kasutajal olulisi aiandustöid paremini planeerida. Samuti võiks kasutajale pakkuda võimalust lisada taimede juurde fotosid, et dokumenteerida nende arengut visuaalselt.

Veel üheks arendusvõimaluseks on analüütika ja statistika lisamine, mis võimaldaks kasutajal hinnata näiteks kastmissagedust, saagikust või taimede kasvutsüklit. Samuti võiks tulevikus kaaluda ilmastikuandmete või taimehoolduse soovituste sidumist rakendusega, mis muudaks lahenduse veelgi nutikamaks.

---

# 8. ENESEANALÜÜS

Lõputöö koostamine ja praktilise projekti elluviimine andis meile väärtusliku kogemuse kogu tarkvaraarenduse protsessi läbimisest. Töö käigus saime parema arusaama sellest, kui oluline on põhjalik kavandamine enne arenduse alustamist. Samuti õppisime, et isegi suhteliselt lihtsana tunduv rakendus nõuab palju otsuseid nii tehnilise loogika, kasutajaliidese kui ka töökorralduse osas.

Kõige paremini läks meie hinnangul see, et suutsime meeskonnana luua ühtse terviku ning viia idee toimiva lahenduseni. Töö käigus arenesid meie oskused nõuete analüüsimises, süsteemi kavandamises, versioonihalduse kasutamises ja koostöös. Samuti saime rohkem kindlust programmeerimises ja testimise olulisuse mõistmises.

Keerulisemaks osutus aja planeerimine ja töömahu realistlik hindamine. Arenduse käigus ilmnes, et mõne funktsiooni elluviimine võtab rohkem aega, kui esialgu eeldasime. See õpetas meid paremini seadma prioriteete ning keskenduma sellele, mis on projekti eesmärgi saavutamiseks kõige tähtsam.

Meeskonnatöö õpetas meile vastutuse jagamist ja üksteise töö mõistmist. Iga liige panustas projekti erinevast vaatenurgast ning ühine tulemus sündis pideva suhtluse ja kokkulepete abil. Selline kogemus on oluline ka tulevases tööelus, kus tarkvaraarendus toimub enamasti just meeskondades.

Kokkuvõttes aitas lõputöö arendada nii erialaseid kui ka üldoskusi. Lisaks tehnilistele teadmistele arenesid meie ajaplaneerimise, probleemilahenduse, suhtlemise ja koostööoskused.

---

# 9. MEESKONNATÖÖ KIRJELDUS

## 9.1. Meeskonna koosseis

Lõputöö valmis kolmeliikmelise meeskonnatööna. Meeskonda kuulusid Kadri Kaljo, Marelle Palm ja Liisa Rank. Projekti juhendas Magnar Aruoja. Meeskonnatöö võimaldas jagada ülesandeid vastavalt huvidele ja tugevustele ning luua ühiselt tervikliku lahenduse.

## 9.2. Rollid

Kuigi kõik meeskonnaliikmed panustasid projekti tervikusse, kujunes töö käigus välja loomulik rollijaotus. Osa tööst oli rohkem seotud tehnilise lahenduse ja arendusloogikaga, osa süsteemi kavandamise ja kasutajaliidese läbimõtlemisega ning osa testimise, dokumentatsiooni ja tulemuste analüüsimisega.

Selline rollijaotus aitas vältida dubleerimist ning võimaldas igal meeskonnaliikmel keskenduda kindlamale vastutusalale. Samas hoidsime tööprotsessi jooksul ühist ülevaadet, et lõpptulemus oleks stiililt ja sisult ühtlane.

## 9.3. Ülesannete jaotus

Tööjaotus kujunes järgmine:

- **Kadri Kaljo** vastutas projekti kavandamise, nõuete koondamise, tehnoloogiate kirjelduse ja arendusprotsessi dokumenteerimise eest.
- **Marelle Palm** keskendus rakenduse kavandamisele, süsteemi arhitektuuri, andmemudeli ja kasutajaliidese lahenduste kirjeldamisele.
- **Liisa Rank** vastutas testimise, juurutamise, dokumentatsiooni ning tulemuste ja analüüsi peatükkide ettevalmistamise eest.
- **Kõik autorid ühiselt** panustasid sissejuhatuse, kokkuvõtte, eneseanalüüsi ja meeskonnatöö kirjelduse valmimisse ning osalesid projekti arendamisel ja aruteludes.

Selline jaotus aitas tagada, et kõigil meeskonnaliikmetel oli selge vastutusala ning samal ajal säilis töö terviklikkus.

## 9.4. Koostööprotsessi kirjeldus

Koostöö toimus regulaarselt omavahel suheldes, tööülesandeid jagades ja edenemist üle vaadates. Leppisime kokku, millised tööosad tuleb esmajärjekorras valmis teha, ning andsime üksteisele tagasisidet nii tehniliste lahenduste kui ka kirjaliku vormistuse osas.

Koostööprotsessi suurim väärtus seisnes selles, et saime ühendada erinevad oskused ja vaatenurgad. Kui üks meeskonnaliige keskendus rohkem süsteemi loogikale, siis teine nägi tugevamalt kasutajakogemuse või dokumenteerimise poolt. See aitas saavutada tasakaalustatud lõpptulemuse.

Meeskonnatöö käigus õppisime, et hea suhtlus ja kokkulepetest kinnipidamine on tarkvaraarenduses sama olulised kui tehnilised oskused.

---

# KOKKUVÕTE

Käesoleva lõputöö eesmärk oli kavandada ja arendada veebirakendus Aianduspäevik, mis aitaks kasutajal oma aiandustegevusi süsteemselt hallata. Töö käigus läbisime kogu arendustsükli alates idee sõnastamisest ja nõuete kogumisest kuni süsteemi kavandamise, arendamise, testimise, juurutamise ja tulemuste analüüsimiseni.

Valminud lahendus võimaldab kasutajal hallata taimi, lisada nende kohta päevikusissekandeid ning hoida kogu aiandusega seotud info ühes keskkonnas. Rakendus vastas suuremas osas algsetele eesmärkidele ning näitas, et sellisel lahendusel on praktiline väärtus. Töö tulemus kinnitas, et hästi planeeritud ja meeskonnatööna teostatud õppeprojekt võib anda sisuliselt tugeva ja tehniliselt toimiva tulemuse.

Lisaks praktilise rakenduse valmimisele oli lõputöö oluline ka õppimise seisukohalt. Projekti käigus arenesid meie oskused süsteemi kavandamises, arendamises, testimises, dokumenteerimises ja koostöös. Samuti saime parema arusaama sellest, millised väljakutsed kaasnevad tarkvaraprojekti elluviimisega ning kui oluline on töö hea planeerimine ja meeskonnasisene suhtlus.

Kokkuvõttes võib öelda, et Aianduspäeviku projekt täitis oma eesmärgi ning lõi tugeva aluse võimalikuks edasiseks arenduseks.

---

# KASUTATUD ALLIKAD

Siia tuleb lisada kõik töö koostamisel kasutatud allikad korrektses viitevormis. Näiteks:

1. Programmeerimiskeelte ja raamistike ametlik dokumentatsioon.
2. Andmebaasisüsteemi ametlik dokumentatsioon.
3. Kasutajaliidese disaini ja UX-i käsitlevad veebiallikad või õpikud.
4. Tarkvaraarenduse metoodikaid käsitlevad materjalid.
5. Kooli juhendmaterjalid ja lõputöö vormistusjuhend.

Kui soovite, saan järgmise sammuna vormistada siia ka päris allikaloendi vastavalt kasutatud tehnoloogiatele.

---

# LISAD

Lisadesse võib paigutada järgmised materjalid:

- olulisemad koodinäited;
- andmebaasi skeem;
- kasutajaliidese wireframe'id;
- testtabelid;
- kasutusjuhendi ekraanipildid;
- tehnilise dokumentatsiooni täiendavad osad.

Lisad aitavad põhiteksti hoida loetava ja kompaktse, samal ajal pakkudes hindajale võimalust tutvuda detailsema materjaliga.

---

# LISA A. TÄIENDAV SISULINE KIRJELDUS

## A.1. Projekti kavandamise täiendav analüüs

Aianduspäeviku projekti kavandamise juures oli oluline mõista, et lahenduse väärtus ei seisne üksnes tehnilises teostatavuses, vaid ka selle praktilises kasulikkuses. Seetõttu püüdsime juba algfaasis määratleda võimalikult selgelt, millist probleemi rakendus lahendab. Leidsime, et suurimaks kitsaskohaks on aiandusega seotud info hajutatus. Inimesed teevad märkmeid erinevatesse kohtadesse, näiteks paberile, kalendrisse või telefoni märkmetesse, kuid selline info ei moodusta terviklikku süsteemi. Digitaalne päevik võimaldab need tegevused koondada ühte keskkonda.

Kavandamise etapis mõtlesime läbi ka selle, milline peaks olema rakenduse minimaalne vajalik funktsionaalsus. Kui eesmärk oleks olnud luua võimalikult mahukas süsteem, oleks projekt muutunud liiga suureks ja killustunuks. Seetõttu keskendusime sellele, et kasutaja saaks hallata taimi, lisada päevikukandeid ning vaadata oma tegevuste ajalugu. Need funktsioonid moodustavad rakenduse tuuma ning just nende korrektne toimimine määrab ära, kas süsteem on kasutajale päriselt abiks.

Oluliseks osaks kavandamisest oli ka meeskonnatöö toimimise läbimõtlemine. Kuna projekti viisid läbi kolm autorit, tuli alguses kokku leppida üldine töökorraldus, suhtlusviisid ja vastutusalad. Selline kokkulepe aitas vältida segadust ning andis kõigile selge arusaama sellest, milline on nende roll projekti edenemisel. Samas ei tähendanud tööjaotus täielikku eraldatust, vaid pigem vastutusala olemasolu ühe suurema terviku sees.

Kavandamise täiendava analüüsi põhjal võib öelda, et just selles etapis pandi alus projekti õnnestumisele. Kui eesmärk, kasutaja vajadus ja projekti maht oleksid jäänud ebaselgeks, oleks see hilisemates etappides tekitanud palju rohkem ümbertegemist ja ebakindlust.

## A.2. Nõuete ja kasutajavajaduste sügavam käsitlus

Nõuete kirjeldamisel ei piisanud ainult sellest, et loetleda funktsioonid, mida süsteem peaks oskama. Oluline oli mõista ka seda, miks kasutaja neid funktsioone vajab. Näiteks ei ole taime lisamise funktsioon väärtus omaette, vaid see muutub oluliseks alles siis, kui kasutaja saab selle kaudu hiljem pidada taimega seotud päevikut ja teha tagantjärele järeldusi. Seetõttu püüdsime näha iga nõuet seoses päriselulise kasutusolukorraga.

Kasutaja vajadused võivad aianduse puhul olla väga erinevad. Mõne jaoks on oluline pidada lihtsat märkmikku külviaegade kohta, teise jaoks on tähtsad hooldustegevused, kolmanda jaoks võib kõige kasulikum olla saagikoristuse jälgimine. Sellest tulenevalt pidi rakendus olema piisavalt üldine, et toetada erinevaid kasutusviise, kuid samas piisavalt konkreetne, et säilitada fookus aianduspäeviku põhiideel.

Mittefunktsionaalsete nõuete sügavamal analüüsil jõudsime järeldusele, et kasutusmugavus on selle projekti puhul peaaegu sama oluline kui tehniline toimivus. Kui rakendus oleks tehniliselt korrektne, kuid kasutamine oleks ebamugav, ei tekiks sellel igapäevast väärtust. Seetõttu käsitlesime kasutajasõbralikkust kui ühte keskset kvaliteedinõuet.

Lisaks pidi süsteem olema piisavalt usaldusväärne. Kui kasutaja tunneb, et tema sisestatud andmed võivad kaduda või salvestuda valesti, väheneb usaldus kiiresti. Seega aitas nõuete läbimõtlemine meil seada paika mitte ainult funktsionaalseid eesmärke, vaid ka kvaliteediga seotud ootusi.

## A.3. Disainiotsuste põhjendused

Rakenduse disainimisel lähtusime põhimõttest, et kasutajaliides peab toetama sisu, mitte varjutama seda. Aianduspäeviku põhifunktsiooniks on info sisestamine ja hilisem ülevaatamine, mistõttu pidi kujundus aitama kaasa selgele loetavusele ja lihtsale navigeerimisele. Visuaalne tagasihoidlikkus oli seega teadlik valik, mitte piirang.

Disaini puhul oli oluline ka teemaga sobivus. Kuna rakendus on seotud aiandusega, tundus loogiline kasutada rahulikke ja looduslähedasi visuaalseid lahendusi. Selline kujundus aitab toetada rakenduse identiteeti ja muudab kasutuskogemuse terviklikumaks. Samas vältisime liigset dekoratiivsust, sest see oleks võinud hakata segama praktilist kasutamist.

Kasutajaliidese ülesehituse puhul eelistasime loogilist hierarhiat. Peamised tegevused, nagu taimede vaatamine, uue kirje lisamine ja päeviku avamine, pidid olema kiirelt leitavad. Detailsem info võis paikneda sügavamates vaadetes, kuid kasutaja ei tohtinud sattuda olukorda, kus olulise toimingu tegemine nõuab liiga palju samme. Selline lähenemine vähendas tunnet, et süsteem on keeruline või raskesti mõistetav.

Disainiotsuste põhjendamine oli oluline ka lõputöö seisukohalt, sest see näitab, et kasutajaliidese lahendused ei sündinud juhuslikult. Iga valik pidi teenima kas kasutusmugavuse, selguse või teemakohasuse eesmärki.

## A.4. Süsteemi arhitektuuri täiendav selgitus

Süsteemi arhitektuuri valikul pidasime tähtsaks seda, et lahendus oleks loogiliselt jaotatud osadeks. Selline ülesehitus aitab arendajal mõista, kus toimub kasutaja sisendi töötlemine, kus toimub andmete salvestamine ja milline osa vastutab kasutajale info kuvamise eest. Kui need kihid on selgelt eristatavad, muutub süsteemi hooldamine lihtsamaks.

Veebirakenduse puhul oli mõistlik lahendada kasutajaliides ja andmetöötlus nii, et kasutaja tegevus käivitab serveripoolel kontrollitud protsessi. See tähendab, et rakendus ei salvesta andmeid lihtsalt visuaalse liidese tasandil, vaid iga sisestus läbib loogika, mis kontrollib selle sobivust ja seob selle õige kasutaja ning taimega. Just selline protsess aitab tagada süsteemi usaldusväärsuse.

Arhitektuuri täiendava käsitluse juures on oluline rõhutada ka laiendatavust. Kuigi projekti esimene versioon oli suhteliselt kompaktne, ei kavandanud me seda kui ühekordset lahendust. Vastupidi, süsteem pidi võimaldama tulevikus lisada uusi mooduleid, näiteks teavitusi või statistilisi vaateid. Kui arhitektuur on algusest peale liiga jäik, muutub iga järgmine arendus oluliselt keerulisemaks.

Seega ei olnud süsteemi ülesehituse planeerimine lihtsalt tehniline vormistus, vaid oluline strateegiline otsus, mis mõjutab kogu projekti elujõulisust.

## A.5. Arenduse praktilised väljakutsed

Arenduse käigus selgus, et isegi lihtsana näiv rakendus sisaldab mitmeid praktilisi väljakutseid. Näiteks tuleb igal sammul mõelda, kuidas kasutaja sisestatud andmed liiguvad liidesest andmebaasi ja tagasi. Kui mõni osa sellest loogikast jääb ebamääraseks või puudulikuks, võib tulemusena tekkida vigu, mis mõjutavad kogu süsteemi tööd.

Teiseks väljakutseks oli erinevate tööosade sidumine ühtseks tervikuks. Kui ühes etapis kavandatakse kasutajaliidest, teises luuakse andmemudel ja kolmandas kirjeldatakse testimist, peab lõpptulemuses kõik omavahel sobima. See nõudis pidevat kontrolli ja omavahelist arutelu. Arendus ei olnud lineaarne protsess, vaid pigem mitme omavahel seotud tegevuse koostoime.

Arenduse käigus tuli ka mitmel juhul valida, kas minna lihtsama või keerulisema lahenduse teed. Õppeprojekti puhul osutus mõistlikuks eelistada lahendusi, mille tööpõhimõte oli hästi arusaadav ja mille kvaliteeti suutsime kontrollida. Selline otsus aitas hoida projekti realistlikuna ja vältida liigset tehnilist riski.

Praktilised väljakutsed näitasid hästi, et arendustöö väärtus ei seisne ainult lõpptulemuses, vaid ka probleemide lahendamise protsessis. Just see protsess arendas meie erialast mõtlemist kõige rohkem.

## A.6. Testimise laiendatud käsitlus

Testimise juures oli oluline mõista, et rakenduse kvaliteet ei selgu ainult sellest, kas see käivitub või mitte. Palju olulisem on küsimus, kas rakendus käitub eri olukordades ootuspäraselt. Näiteks peab süsteem õigesti reageerima nii siis, kui kasutaja sisestab korrektsed andmed, kui ka siis, kui ta jätab mõne välja tühjaks või proovib teha toimingut vales järjekorras.

Laiendatud testimise käsitluses saab eristada funktsionaalset ja kasutajakeskset vaadet. Funktsionaalne vaade keskendub sellele, kas konkreetne nupp, vorm või andmebaasitoiming töötab õigesti. Kasutajakeskne vaade seevastu küsib, kas inimene saab süsteemi kasutamise loogikast aru ja kas ta suudab eesmärgi saavutada ilma liigse segaduseta. Mõlemad vaated on kvaliteetse lahenduse puhul vajalikud.

Testimine aitas märgata ka seda, et vead ei ole alati tehniliselt suured. Mõnikord võib väike sõnastusprobleem, segane nupu asukoht või ebapiisav tagasiside muuta kasutuskogemuse märgatavalt halvemaks. Seetõttu käsitlesime testimise tulemusi laiemalt kui lihtsalt vigade loendit. Need olid ühtlasi vihjed selle kohta, kuidas muuta lahendus selgemaks ja inimlikumaks.

Laiendatud käsitlus kinnitab, et testimine ei ole arendusprotsessi viimane formaalsus, vaid üks peamisi viise, kuidas hinnata töö tegelikku kvaliteeti.

## A.7. Dokumenteerimise praktiline väärtus

Dokumenteerimine on osa tarkvaraarendusest, mille väärtust mõistetakse sageli alles siis, kui seda on vaja kasutada. Projekti käigus kogesime, et kirjalikud selgitused aitavad hoida süsteemi loogikat selgena nii meeskonnale endale kui ka võimalikele teistele huvilistele. Ilma dokumentatsioonita võib töötavgi lahendus muutuda raskesti mõistetavaks, eriti kui sellesse tahetakse hiljem teha muudatusi.

Kasutusjuhendi praktiline väärtus seisneb selles, et see aitab hinnata, kas süsteem on inimesele arusaadav. Kui juhendit on keeruline kirjutada lihtsas ja selges vormis, võib see viidata sellele, et ka rakendus ise ei ole piisavalt intuitiivne. Tehniline dokumentatsioon seevastu aitab säilitada süsteemi sisemist loogikat ja kirjeldada selle tehnilist ülesehitust.

Dokumenteerimise juures õppisime, et hea kirjeldus ei korda lihtsalt seda, mida arendaja juba teab, vaid seletab süsteemi viisil, mis on teistele mõistetav. See on oskus, mida läheb vaja igas professionaalses arenduskeskkonnas.

## A.8. Tulemuste tõlgendamine ja projekti tähendus

Valminud rakenduse puhul on oluline hinnata mitte ainult seda, mis valmis, vaid ka seda, mida projekt õpetas. Tulemused näitavad, et suutsime läbida kogu tarkvaraarenduse tsükli ja luua toimiva lahenduse, mis on seotud konkreetse kasutusvajadusega. See on lõputöö puhul oluline saavutus, sest näitab nii tehnilist kui ka analüütilist küpsust.

Projekti tähendus seisneb ka selles, et see sidus omavahel eri õppeainetes omandatud teadmised. Üksikute programmeerimisülesannete lahendamine on oluline, kuid alles terviklik projekt näitab, kuidas erinevad teadmised koos töötavad. Käesolev töö aitas seda seost selgelt kogeda.

Samuti oli tulemus väärtuslik meeskonnatöö mõttes. Kolmekesi töötamine eeldas vastutuse jagamist, kokkulepete pidamist ja üksteise töö mõistmist. Selline kogemus on väga oluline, sest päris tööelus sünnib tarkvara enamasti just koostöös.

Seega on projekti tähendus laiem kui ainult ühe rakenduse valmimine. See oli õppimisprotsess, mille käigus arenesid nii tehnilised oskused, analüüsivõime kui ka koostöövalmidus.

## A.9. Võimalik praktiline kasutus tulevikus

Kui Aianduspäeviku rakendust edasi arendada, võiks sellel olla praktiline kasutus ka väljaspool kooliprojekti. Digitaalne päevik on lahendus, millel on potentsiaali nii harrastusaednike, väiketalunike kui ka õppeotstarbeliste aiandusprojektide juures. Eriti kasulik võiks see olla kasutajatele, kes soovivad säilitada järjepidevat ülevaadet oma taimedest ja hooldustegevustest.

Tulevikus võiks süsteemi lisada ka selliseid funktsioone, mis suurendaksid selle igapäevast väärtust. Näiteks võiks kasutaja näha hooajalisi soovitusi, salvestada taimede juurde fotosid või saada meeldetuletusi kastmise ja väetamise kohta. Samuti võiks rakendust laiendada nii, et see võimaldaks võrrelda eri aastate andmeid ja teha selle põhjal järeldusi.

Selline tulevikupotentsiaal näitab, et projekt ei olnud ainult õppetöö jaoks loodud näide, vaid ideeliselt laiendatav lahendus. See annab tööle lisaväärtust ning näitab, et valitud teema oli sisuliselt põhjendatud.

## A.10. Kokkuvõttev hinnang lisa põhjal

Täiendav sisuline kirjeldus kinnitab, et Aianduspäeviku projekt oli oma olemuselt terviklik ja hästi põhjendatud lõputöö teema. Selle käigus käsitleti nii projekti planeerimist, kasutajavajadusi, süsteemi disaini, tehnilist teostust, testimist, dokumenteerimist kui ka tulemuste analüüsi. Selline terviklik käsitlus vastab hästi tarkvaraarenduse arendustsükli loogikale.

Lisamaterjali koostamine toob esile, et iga põhipeatüki taga on tegelikult rohkem kaalutlusi, kui lühike kirjeldus esmapilgul näitab. Just nende kaalutluste lahti kirjutamine annab tööle akadeemilisema ja sisuliselt tugevama mõõtme. Seetõttu võib seda lisa käsitleda kui osa, mis toetab põhitöö argumentatsiooni ning näitab projekti valmimist sügavama analüüsi tasandil.
