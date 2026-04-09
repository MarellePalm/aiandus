# Deploy juhend

See fail kirjeldab, kuidas uus versioon liigub `main` harust live keskkonda.

## Eeltingimused

- Muudatused on merge'itud `main` harusse
- GitHub Actions kontrollid (`linter`, `tests`) on rohelised
- Vajadusel on tehtud käsitsi funktsionaalne kontroll

## Tavaline deploy voog

1. Ava GitHub repo **Actions**.
2. Vali workflow **Production deploy**.
3. Vajuta **Run workflow** ja vali branch `main`.
4. Oota, kuni `deploy` job lõpeb edukalt.
5. Kontrolli live keskkonnas põhilised vaated/funktsioonid üle.

## Tehniline taust

Deploy workflow kasutab Deployerit (`deploy.yaml`), mis teeb:

- `deploy:prepare`
- `deploy:vendors`
- `npm ci` + `vite build`
- `artisan:storage:link`
- `artisan:optimize:clear`
- `artisan:optimize`
- `deploy:publish`
- `opcache:clear` (edu korral)

## Rollback

Kui viimane merge tekitas probleemi, tee revert `main` harus:

```bash
git checkout main
git pull origin main
git revert -m 1 <merge_commit_hash> --no-edit
git push origin main
```

Seejärel käivita **Production deploy** uuesti.

Kui oli tavaline commit (mitte merge), kasuta:

```bash
git revert <commit_hash> --no-edit
git push origin main
```

## Levinud probleemid ja kontroll

- **Deploy läks läbi, muudatusi ei näe:** kontrolli, et deploy käis õige commit SHA pealt.
- **Actions punane:** ava logid, paranda viga, tee uus commit, merge, deploy uuesti.
- **Branch deploy:** ärge deployge vanu feature branch committe otse live'i.
- **Cache:** vajadusel tee brauseris hard refresh.

## Vastutus

- Kõik live deployd peavad olema seotud commit SHA-ga.
- PR kirjeldus peab sisaldama muudatuste kokkuvõtet ja testimisinfot.
