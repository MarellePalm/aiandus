# 🌱 Aiapäevik

Veebipõhine aiandusrakendus taimede, peenarde, kalendrimärkmete, ülesannete ja varude haldamiseks.

Rakendus on ehitatud **Laravel + Vue + Inertia** tehnoloogiapakiga ning toetab PWA-d.

---

## 📦 Monorepo: miks backend ja frontend on ühes repositooriumis

Projekt on üles ehitatud **monorepo** põhimõttel: Laraveli backend ja Vue/Inertia frontend asuvad samas Giti repositooriumis, mitte eraldi `api`- ja `web`-repositooriumides.

### Miks see valik sobib sellele rakendusele

- **Inertia.js** ühendab serveri- ja kliendipoole tihedalt: marsruudid, autentimine ja suur osa „lehevastustest” toimivad loomulikult ühe Laraveli rakenduse sees. Eraldi frontend-repositoorium tähendaks rohkem sünkroonimist API-lepingute, autentimise ja marsruutide vahel, ilma et see annaks käesoleva projekti puhul märgatavat lisaväärtust.
- **Väikese meeskonna ja õppeprojekti** puhul on praktiline, et üks pull request võib sisaldada nii kasutajaliidese kui ka serveripoole muudatusi, näiteks uut välja koos migratsiooni, vormi ja kontrolleri muudatustega. See vähendab halduslikku koormust ja hoiab töövoo lihtsana.
- **Juurutamise** seisukohalt avaldatakse toodangusse niikuinii üks terviklik rakendus, kus Laravel serveerib builditud varasid. Seetõttu on ühe repositooriumi kasutamine loomulikum kui kahe eraldi repositooriumi väljalasete koordineerimine.

### Plussid

| Pluss | Lühikirjeldus |
|-------|---------------|
| Aatomilised muudatused | Sama commit või PR võib hõlmata API, vaate ja testide muudatusi, mis vähendab katkise vaheoleku riski. |
| Ühtne versioonihaldus | `main` haru või release-tag kirjeldab kogu rakenduse seisu ühe tervikuna. |
| Lihtsam sisseelamine | Üks `git clone`, `composer install` ja `npm install` annavad uuele arendajale kogu projekti tervikpildi. |
| Jagatud tööriistad | ESLint, Prettier, Pint, GitHub Actions ja dokumentatsioon asuvad kõik ühes kohas. |

### Miinused ja kuidas nendega arvestada

| Miinus | Praktiline tähendus |
|--------|----------------------|
| Suurem töömaht lokaalselt | `node_modules` ja `vendor` muudavad arenduskeskkonna mahukamaks, kuigi neid Gitis ei hoita. |
| Segatud vastutus | PHP- ja TypeScripti-failid paiknevad samas failipuus, mistõttu on oluline selge kaustastruktuur ja distsiplineeritud töövoog. |
| Kahe tööriistakomplekti vajadus | Arendaja peab töötama nii PHP kui ka Node tööriistadega ning CI peab toetama mõlemat. |
| Piiratud skaleeritavus eraldiseisvalt | Kui tulevikus tekib vajadus skaleerida ainult frontend või ainult API, tuleb lisada täiendavad tööriistad ja eraldi buildi- või väljalaskeprotsessid. |

Kokkuvõttes on monorepo käesoleva projekti puhul praktiline vaikimisi valik. Inertia-põhise täisrakenduse ja väikese meeskonna korral vähendab see tarbetut keerukust. Eraldi repositooriume tasuks kaaluda siis, kui tekib selge vajadus sõltumatute väljalasete või eraldi meeskondade järele.

---

## 🛠 Tehnoloogiad

| Kiht | Tehnoloogia |
|------|-------------|
| Backend | PHP `^8.2` · Laravel `^13.0` |
| Frontend | Vue `^3.5.13` · TypeScript `^5.2.2` · Vite `^7.0.4` |
| Routing | Inertia.js `^2.0` (Laravel) · `@inertiajs/vue3 ^2.3.7` |
| Stiil | Tailwind CSS `^4.1.1` |
| Testid | Pest `^4.3` · `pest-plugin-laravel ^4.0` |
| Andmebaas | MySQL (toodang) · SQLite (arendus) |

---

## ✅ Eeltingimused

- PHP 8.2+ ja Composer 2+
- Node.js 22+ ja npm 10+
- MySQL või SQLite

---

## 🚀 Kiirkäivitus

```bash
composer run setup
composer run dev
```

Rakendus avaneb aadressil **http://127.0.0.1:8000**.

Käsk `composer run setup` teeb automaatselt järgmised sammud: `composer install` → `.env` loomine → rakenduse võtme genereerimine → migratsioonid → `npm install` → frontendi build.

---

## 🔧 Samm-sammuline paigaldus

### 1. Paigalda sõltuvused

```bash
composer install
npm install
```

### 2. Loo keskkonnafail ja rakenduse võti

```bash
cp .env.example .env
php artisan key:generate
```

Soovituslikud `.env` väärtused:

```env
APP_NAME=Aiapäevik
APP_URL=http://127.0.0.1:8000
```

### 3. Seadista andmebaas

**Valik A — MySQL:** täida `.env` failis `DB_*` väljad.

**Valik B — SQLite:**

```bash
touch database/database.sqlite
```

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absoluutne/tee/database/database.sqlite
```

### 4. Käivita migratsioonid

```bash
php artisan migrate
```

### 5. Käivita arendusserverid

```bash
composer run dev
```

See käivitab korraga Laraveli serveri, järjekorra kuulaja, logi jälgimise ja Vite’i arendusserveri.

---

## 🧰 Kasulikud käsud

```bash
# Frontend
npm run dev        # arendusserver
npm run build      # toodangubuild
npm run lint       # lintimise kontroll
npm run format     # koodi vormindamine

# Backend / testid
composer lint      # PHP lintimise kontroll
php artisan test   # testide käivitamine
./vendor/bin/pest  # Pest otse
```

---

## 🔄 CI/CD

GitHub Actionsi töövood:

| Workflow | Kirjeldus |
|----------|-----------|
| `linter` | Koodistiili ja frontendi lintimise kontroll |
| `tests` | Build + Pest testid |
| `Production deploy` | Käsitsi käivitatav juurutus (`workflow_dispatch`) |

---

## 🚢 Juurutamine

Juurutamine toimub GitHub Actionsi workflow kaudu (`Production deploy`) ja kasutab Deployerit (`deploy.yaml`).

Testkeskkonna juurutamise sammud on kirjeldatud failis [`DEPLOY.md`](./DEPLOY.md).

### Soovituslik töövoog

1. Loo haru `main` haru pealt
2. Tee muudatused ja commit
3. Ava PR → `main`
4. Veendu, et `linter` ja `tests` on ✅ rohelised
5. Merge’i PR
6. Käivita GitHub Actionsis **`Production deploy`** → `Run workflow`
7. Kontrolli live-keskkonnas põhilised funktsionaalsused üle

### ⏪ Rollback

```bash
git checkout main
git pull origin main
git revert -m 1 <merge_commit_hash> --no-edit
git push origin main
```

Seejärel käivita juurutus uuesti.

---

## 📚 Dokumentatsioon

- **Lisadokumentatsioon** (tööde planeerimine, roadmap): Confluence
- **Deploy-juhend**: [`DEPLOY.md`](./DEPLOY.md)
