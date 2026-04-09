# Aiapäevik

Veebipõhine aiandusrakendus taimede, peenarde, kalendrimärkmete, ülesannete ja varude haldamiseks.  
Projekt on ehitatud Laravel + Vue + Inertia tehnoloogiapakiga ning toetab PWA kasutust.

## Tehnoloogiad

- PHP `^8.2` (CI kasutab 8.4/8.5)
- Laravel `^13`
- Vue `^3.5`
- TypeScript `^5`
- Inertia.js (`inertia-laravel` + `@inertiajs/vue3`)
- MySQL (arenduses võib kasutada ka SQLite)
- Vite `^7`
- Tailwind CSS `^4`
- Pest `^4`

## Eeltingimused

Enne käivitamist veendu, et masinas on:

- PHP 8.2+ ja Composer
- Node.js 22+ ja npm
- Andmebaas (MySQL) **või** SQLite fail

## Esmakordne käivitamine lokaalselt

Kiireim viis:

```bash
composer run setup
composer run dev
```

`setup` teeb automaatselt:
- composer install
- `.env` loomine (`.env.example` põhjal)
- app key genereerimine
- migratsioonid
- npm install
- production build

Alternatiivina samm-sammuline paigaldus:

### 1) Paigalda sõltuvused

```bash
composer install
npm install
```

### 2) Loo keskkonnafail ja rakenduse võti

```bash
cp .env.example .env
php artisan key:generate
```

### 3) Seadista andmebaas

Valik A: MySQL  
- täida `.env` failis `DB_*` väljad

Valik B: SQLite

```bash
touch database/database.sqlite
```

ja `.env`:

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absoluutne/tee/database/database.sqlite
```

### 4) Käivita migratsioonid

```bash
php artisan migrate
```

### 5) Käivita arendusserverid

Lihtsaim variant:

```bash
composer run dev
```

See käivitab korraga:
- Laravel serveri
- queue listeneri
- logi jälgimise
- Vite dev serveri

## Kasulikud käsud

### Frontend

```bash
npm run dev
npm run build
npm run lint
npm run format
```

### Backend / testid

```bash
composer lint
php artisan test
./vendor/bin/pest
```

## CI/CD

GitHub Actions workflow'd:

- `linter` – koodistiili ja frontendi linti kontroll
- `tests` – build + Pest testid
- `Production deploy` – käsitsi käivitatav deploy (`workflow_dispatch`)

## Deploy test-/tootmiskeskkonda

Deploy käib GitHub Actions workflow kaudu (`Production deploy`) ja kasutab Deployerit (`deploy.yaml`).

### Soovituslik voog

1. Loo branch `main` pealt
2. Tee muudatused ja commit
3. Ava PR `main`i
4. Veendu, et `linter` ja `tests` on rohelised
5. Merge PR
6. Käivita GitHub Actionsis `Production deploy` (`Run workflow`)
7. Kontrolli live keskkonnas põhifunktsionaalsused üle

### Rollback (kui vaja kiiresti tagasi minna)

Kui viimane merge commit põhjustab vea:

```bash
git checkout main
git pull origin main
git revert -m 1 <merge_commit_hash> --no-edit
git push origin main
```

Seejärel käivita deploy uuesti.

## Projekti dokumentatsioon

Lisadokumentatsioon (tööde planeerimine, roadmap, jms) asub Confluence'is.  
Deploy juhend reposti sees: `DEPLOY.md`.

