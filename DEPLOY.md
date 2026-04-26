# 🚢 Deploy juhend

> Kirjeldab, kuidas uus versioon liigub `main` harust test- ja live-keskkonda.

---

## ✅ Eeltingimused

- Muudatused on merge'itud `main` harusse
- GitHub Actions kontrollid (`linter`, `tests`) on ✅ rohelised
- Vajadusel on tehtud käsitsi funktsionaalne kontroll

---

## 🔄 Tavaline deploy voog

1. Ava GitHub repo **Actions**
2. Vali workflow **`Production deploy`**
3. Vajuta **Run workflow** → vali branch `main`
4. Oota, kuni `deploy` job lõpeb edukalt
5. Kontrolli live keskkonnas põhilised vaated ja funktsioonid üle

---

## 🧪 Test-keskkonna paigaldus

Hetkel käib test-keskkonna uuendamine läbi sama `Production deploy` workflow.

1. Veendu, et vajalikud muudatused on merge'itud `main` harusse
2. Ava **Actions** → vali **`Production deploy`**
3. Vajuta **Run workflow** → vali branch `main`
4. Oota edukat lõppu
5. Kontrolli test-keskkonnas:
   - autentimine
   - taimede/peenarde vaated
   - kalendri vaade
   - viimase muudatusega seotud vood

> **Märkus:** test-keskkonda paigaldamisel tuleb alati fikseerida, milline commit SHA keskkonda jõudis.

---

## ⚙️ Tehniline taust

Deploy workflow kasutab Deployerit (`deploy.yaml`), mis käivitab järjestikku:

```
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
| Deploy läks läbi, muudatusi ei näe | Kontrolli, et deploy käis õige commit SHA pealt |
| Actions punane | Ava logid → paranda viga → uus commit → merge → deploy uuesti |
| Branch deploy | Ärge deployge vanu feature branch committe otse live'i |
| Cache | Tee brauseris hard refresh (`Ctrl+Shift+R`) |

---

## 👤 Vastutus

- Kõik live deployd peavad olema seotud commit SHA-ga
- PR kirjeldus peab sisaldama muudatuste kokkuvõtet ja testimisinfot
