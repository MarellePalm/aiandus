# Kuu info — üks allikas (single source of truth)

Kogu kuufaasi, biodünaamika päeva ja kuunõuannete loogika on **ainult siin** (`resources/js/lib/moon/`).  
Serveris **ei ole** eraldi Moon API-d ega duplikaatvalemeid — nii jääb arhitektuur puhas ja hooldatav.

| Fail | Roll |
|------|------|
| `moon.ts` | `getMoonInfo`: Meeus keskmine Päike–Kuu elongatsioon (D), JD kohaliku kalendripäeva keskpäeval — sarnane [ilm.pri.ee kuufaaside kalendriga](https://ilm.pri.ee/kuufaaside-kalender), mitte lihtsalt fikseeritud uuskuu + modulo. **Täiskuu** riba kitsendatud (±1 päev tipust t-s). |
| (UI) | `MoonPhaseIcon`: ring stroke; valgus valge, vari `currentColor`; uuskuu / täiskuu erandid. |
| `moonPhaseDisplay.ts` | Sõbralikud nimed kalendris (nt „Kuu loomine“, „Noorkuu“) — vastavus `MoonPhase8`-ile (`moonPhaseDisplayLabel`). |
| `zodiac.ts` | Kuu ekliptika → tähtkuju, biodünaamika päevatüüp (`getZodiacInfo`, `calendarMomentForZodiac`) |
| `moonAdvice.ts` | Kasutajatekst: `moodHeadline`, `leadParagraph`, tööd (`moonAdvice`), `displayTitle` |

**Kasutajaliides:** `moon.vue`, `MoonCalendar.vue`, `MoonZodiacBadge.vue` jms impordivad neid faile.

**Ilm API (`/api/weather`):** võib sisaldada WeatherAPI `astronomy` välja (faas, valgustatus), kuid **kuuplokk ei sõltu sellest** — vältimaks topeltallikaid ja võtme sõltuvust.

**Refaktor / täpsem astronoomia:** muuda valemid siin; UI uueneb automaatselt.
