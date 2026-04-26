# 🌱 Aiapäevik

Veebipõhine aiandusrakendus taimede, peenarde, kalendrimärkmete, ülesannete ja varude haldamiseks.

Ehitatud **Laravel + Vue + Inertia** tehnoloogiapakiga, PWA-toega.

---

## 🛠 Tehnoloogiad

| Kiht | Tehnoloogia |
|------|------------|
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

Rakendus avaneb aadressil **http://127.0.0.1:8000**

`composer run setup` teeb automaatselt: `composer install` → `.env` loomine → võtme genereerimine → migratsioonid → `npm install` → frontendi build.

---

## 🔧 Samm-sammuline paigaldus

### 1. Paigalda sõltuvused

```bash
composer install
npm install
```

### 2. Keskkonnafail ja rakenduse võti

```bash
cp .env.example .env
php artisan key:generate
```

Soovituslikud `.env` väärtused:

```env
APP_NAME=Aiapäevik
APP_URL=http://127.0.0.1:8000
```

### 3. Andmebaas

**Valik A — MySQL:** täida `.env` failis `DB_*` väljad.

**Valik B — SQLite:**

```bash
touch database/database.sqlite
```

```env
DB_CONNECTION=sqlite
DB_DATABASE=/absoluutne/tee/database/database.sqlite
```

### 4. Migratsioonid

```bash
php artisan migrate
```

### 5. Arendusserverid

```bash
composer run dev
```

Käivitab korraga: Laravel server · queue listener · logi jälgimine · Vite dev server.

---

## 🧰 Kasulikud käsud

```bash
# Frontend
npm run dev        # arendusserver
npm run build      # toodangubuildi
npm run lint       # linti kontroll
npm run format     # koodi formaatimine

# Backend / testid
composer lint      # PHP linti kontroll
php artisan test   # testide käivitamine
./vendor/bin/pest  # Pest otse
```

---

## 🔄 CI/CD

GitHub Actions tööd:

| Workflow | Kirjeldus |
|----------|-----------|
| `linter` | Koodistiili ja frontendi linti kontroll |
| `tests` | Build + Pest testid |
| `Production deploy` | Käsitsi käivitatav deploy (`workflow_dispatch`) |

---

## 🚢 Deploy

Deploy käib GitHub Actions workflow kaudu (`Production deploy`) ja kasutab Deployerit (`deploy.yaml`).
Test-keskkonna paigalduse sammud on kirjeldatud failis [`DEPLOY.md`](./DEPLOY.md).

### Soovituslik voog

1. Loo branch `main` pealt
2. Tee muudatused ja commit
3. Ava PR → `main`
4. Veendu, et `linter` ja `tests` on ✅ rohelised
5. Merge PR
6. Käivita GitHub Actionsis **`Production deploy`** → `Run workflow`
7. Kontrolli live keskkonnas põhifunktsionaalsused üle

### ⏪ Rollback

```bash
git checkout main
git pull origin main
git revert -m 1 <merge_commit_hash> --no-edit
git push origin main
```

Seejärel käivita deploy uuesti.

---

## 📚 Dokumentatsioon

- **Lisadokumentatsioon** (tööde planeerimine, roadmap): Confluence
- **Deploy juhend**: [`DEPLOY.md`](./DEPLOY.md)
