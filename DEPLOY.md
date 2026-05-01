# 🚢 Juurutusjuhend

> Kirjeldab, kuidas uus versioon liigub `main` harust test- ja live-keskkonda.

---

## ✅ Eeltingimused

- Muudatused on merge'itud `main` harusse
- GitHub Actionsi kontrollid (`linter`, `tests`) on ✅ rohelised
- Vajadusel on tehtud käsitsi funktsionaalne kontroll

---

## 🔄 Tavaline juurutusvoog

1. Ava GitHubi repositooriumis **Actions**
2. Vali workflow **`Production deploy`**
3. Vajuta **Run workflow** ja vali haru `main`
4. Oota, kuni `deploy` töö lõpeb edukalt
5. Kontrolli live-keskkonnas põhilised vaated ja funktsioonid üle

---

## 🧪 Testkeskkonna paigaldus

Hetkel toimub testkeskkonna uuendamine sama `Production deploy` workflow kaudu.

1. Veendu, et vajalikud muudatused on merge'itud `main` harusse
2. Ava **Actions** ja vali **`Production deploy`**
3. Vajuta **Run workflow** ja vali haru `main`
4. Oota edukat lõppu
5. Kontrolli testkeskkonnas:
   - autentimine
   - taimede ja peenarde vaated
   - kalendrivaade
   - viimase muudatusega seotud kasutusvood

> **Märkus:** testkeskkonda paigaldamisel tuleb alati fikseerida, milline commit SHA keskkonda jõudis.

---

## ⚙️ Tehniline taust

Juurutuse workflow kasutab Deployerit (`deploy.yaml`), mis käivitab järjestikku järgmised sammud:

```text
deploy:prepare
deploy:vendors
npm ci + vite build
artisan:storage:link
artisan:optimize:clear
artisan:optimize
deploy:publish
opcache:clear       ← edu korral
```

---

## ⏪ Rollback

### Merge commit

```bash
git checkout main
git pull origin main
git revert -m 1 <merge_commit_hash> --no-edit
git push origin main
```

### Tavaline commit

```bash
git revert <commit_hash> --no-edit
git push origin main
```

Seejärel käivita **`Production deploy`** uuesti.

---

## 🐛 Levinud probleemid

| Probleem | Lahendus |
|----------|----------|
| `deploy:info` või `deploy:unlock` kukub GitHub Actionsis läbi | **SSH võti:** repodes `Settings → Secrets` → `PRIVATE_KEY` peab olema terve PEM (sh `-----BEGIN ... KEY-----` ja reavahetused). Võti peab vastama serveri `~/.ssh/authorized_keys` kirje avalikule osale. **Tulemüür:** server `ta24palm.itmajakas.ee` peab lubama väljast SSH ühendusi (GitHub runneri IP-d muutuvad). Proovi kohapealt: `ssh -i ~/.ssh/sinu_võti virt137762@ta24palm.itmajakas.ee`. |
| `deploy:unlock` pärast ebaõnnestunud juurutust | Eelmisest jooksust võis jääda lukk. Kui sul on Deployer kohapeal: `php deployer.phar deploy:unlock stage` (sama `deploy.yaml` ja SSH). |
| `deploy.yaml` YAML viga | Kohalik kontroll: lae `deployer.phar`, seejärel `php deployer.phar tree deploy -f deploy.yaml` (ei ühendu serverisse; näitab, kas retsept laeb). |
| Deploy läks läbi, kuid muudatusi ei ole näha | Kontrolli, et juurutus toimus õige commit SHA pealt |
| Actions on punane | Ava logid, paranda viga, tee uus commit, merge'i see ja juuruta uuesti |
| Branch deploy | Väldi vanade feature-branchide commit'ide juurutamist otse live-keskkonda |
| Cache | Tee brauseris hard refresh (`Ctrl+Shift+R`) |

---

## 👤 Vastutus

- Kõik live-juurutused peavad olema seotud commit SHA-ga
- PR-i kirjeldus peab sisaldama muudatuste kokkuvõtet ja testimisinfot
