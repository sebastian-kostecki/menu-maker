---
project: menu-maker
version: 2
status: draft                    # draft | active | locked
created: 2026-11-13
updated: 2026-11-15
prd_version: —
main_goal: quality
top_blocker: none
milestone_id: consistent-design-system
milestone_seq: 2
milestone_status: open           # open | done
---

# Roadmap: Menu Maker

> Derived from `context/foundation/design-system.md` + auto-researched codebase baseline.
> Edit-in-place; the next milestone continues this file. Archive only when an independent roadmap replaces it.
> Slices below are listed in dependency order. The "At a glance" table is the index.

## Milestone

**M-2: Spójny wygląd aplikacji i PDF-u** — Status: open

- **Intent:** Każdy widok aplikacji i PDF korzysta z jednego kontraktu wizualnego — kolorów marki, kolorów pory dnia dla kategorii posiłków i wspólnych komponentów — a CI pilnuje, żeby żaden zmigrowany widok nie wrócił do surowych kolorów.
- **Source materials:** `context/foundation/design-system.md` (v1, 2026-11-14). Kotwice zakresu to kroki rolloutu `DS-01`…`DS-10` z jego sekcji „8. Rollout”; numery sekcji (`§4.2` itd.) odnoszą się do tego samego pliku.
- **Done when:** every F-NN and S-NN below is `done`.
- **Scope anchors:** DS-01…DS-10 (§8), w granicach decyzji D-1…D-8 (§9, wszystkie potwierdzone 2026-11-14). `DS-01` jest tu podzielony na dwa elementy bazowe: F-03 (tokeny i prymitywy) i F-04 (ścieżka weryfikacji z §7).
- **Numbering:** identyfikatory kontynuują numerację M-1 (F-03…, S-07…), żeby nie zderzyć się z wpisami w `## Done`.

## Vision recap

Menu Maker to narzędzie domowe: rodzic raz w tygodniu dodaje przepisy, generuje jadłospis 7 × 3 i drukuje PDF z listą zakupów. Funkcje z M-1 działają, ale wyglądają jak szary panel administracyjny złożony z dwóch systemów komponentów. Ten kamień milowy daje aplikacji ciepły, spokojny wygląd kuchni z jednym kolorem marki (zieleń ziół), czytelnym kodem kolorów pory dnia dla kategorii posiłków i zgodnością ekranu z wydrukiem, przy zachowaniu WCAG AA. Opiera się na tokenach — nazwanych zmiennych kolorów, promieni i cieni w jednym pliku CSS, z których korzystają wszystkie widoki zamiast wpisywać kolory na sztywno.

## North star

**S-09: Użytkownik widzi jadłospis jako siatkę tygodnia w kolorach marki i pory dnia, z listą zakupów obok** — to pierwszy widok, który składa cały kontrakt naraz (tokeny, warianty kategorii i statusów, wspólne komponenty, nowa powłoka) na rdzeniu produktu, więc przy celu „quality” dowodzi, że design system działa, a nie tylko że istnieje.

> „North star” oznacza tu najmniejszy przepływ end-to-end, którego dostarczenie dowodzi, że ten kamień milowy działa — dlatego stoi tak wcześnie, jak pozwalają jego warunki wstępne: migracja pozostałych widoków ma sens tylko wtedy, gdy ten wygląda i zachowuje się dobrze.

## At a glance

| ID   | Change ID                 | Outcome (user can …)                                                                                   | Prerequisites                     | PRD refs                    | Status   |
| ---- | ------------------------- | ------------------------------------------------------------------------------------------------------ | --------------------------------- | --------------------------- | -------- |
| F-03 | design-tokens-foundation  | (foundation) tokeny marki, stanów i domeny w OKLCH, brakujące prymitywy i warianty; reguła w CLAUDE.md | —                                 | DS-01, §4.1–4.6, §5.1, §7.1 | done     |
| F-04 | design-system-visual-gate | (foundation) kitchen sink pod `/dev/design-system`, zrzut Playwright i `lint:tokens` w CI              | F-03                              | DS-01, §7.2–7.4, D-4, D-6   | in-progress |
| S-07 | shared-app-components     | widzieć jeden dialog potwierdzenia, tę samą paginację i sortowanie, kategorie i statusy w nowych kolorach | F-03, F-04                     | DS-02, §5.2, §5.3, D-3, D-8 | proposed |
| S-08 | app-shell-restyle         | poruszać się po aplikacji w nawigacji i menu użytkownika w kolorach marki, także na ekranach gościa    | F-03, F-04                        | DS-03, §5.2                 | proposed |
| S-09 | meal-plan-show-ui         | zobaczyć jadłospis jako siatkę tygodnia w kolorach pory dnia, z listą zakupów obok                     | S-07, S-08                        | DS-04, §5.3, §5.4           | proposed |
| S-10 | meal-plans-index-ui       | przeglądać, filtrować i generować jadłospisy w nowym wyglądzie                                         | S-07                              | DS-05                       | proposed |
| S-11 | recipes-ui                | przeglądać, dodawać i edytować przepisy w nowym wyglądzie                                              | S-07                              | DS-06                       | proposed |
| S-12 | family-members-ui         | zarządzać członkami rodziny w nowym wyglądzie                                                          | S-07                              | DS-07                       | proposed |
| S-13 | auth-profile-ui           | logować się, rejestrować, resetować hasło i edytować profil na tych samych przyciskach i polach co reszta | S-07, S-08                     | DS-08, §5.1                 | proposed |
| S-14 | pdf-design-alignment      | pobrać PDF, w którym kategorie posiłków mają te same kolory co na ekranie                              | F-03                              | DS-09, §6                   | proposed |
| S-15 | legacy-ui-cleanup         | wejść na `/` i trafić do logowania lub aplikacji zamiast domyślnej strony Laravela                     | S-08, S-09, S-10, S-11, S-12, S-13 | DS-10, D-7, §7.4           | proposed |

## Streams

Navigation aid — groups items that share a Prerequisites chain. Canonical ordering still lives in the dependency graph below; this table is the proposed reading order across parallel tracks.

| Stream | Theme                       | Chain                                  | Note                                                                                                   |
| ------ | --------------------------- | -------------------------------------- | ------------------------------------------------------------------------------------------------------ |
| A      | Kontrakt i bramka           | `F-03` → `F-04` → `S-07`               | Cel „quality”: zrzut i `lint:tokens` istnieją, zanim pierwszy widok zostanie zmigrowany.              |
| B      | Powłoka i główny przepływ   | `S-08` → `S-09` → `S-13` → `S-15`      | Dołącza do strumienia A przy `F-04`; `S-09` i `S-13` potrzebują też `S-07`; `S-15` czeka na strumień C. |
| C      | Listy i formularze          | `S-10`, `S-11`, `S-12` (równolegle)    | Odgałęzia się od strumienia A przy `S-07`; trzy niezależne widoki dla osobnych sesji agentów.         |
| D      | PDF                         | `S-14`                                 | Odgałęzia się od strumienia A przy `F-03`; nie potrzebuje bramki ani wspólnych komponentów.           |

## Baseline

What's already in place in the codebase as of `2026-11-15` (auto-researched + user-confirmed).
Foundations below assume these are present and do NOT re-scaffold them.

- **Frontend:** partial — Vue 3 + Inertia + shadcn-vue (15 prymitywów w `resources/js/Components/ui/`, `reka-ui`, `class-variance-authority`, `lucide-vue-next`). Tokeny w `resources/css/app.css` w czystych szarościach (zero wartości OKLCH); surowe klasy palety Tailwinda w 63 plikach `.vue`, 280 martwych klas `dark:`; 12 komponentów Breeze w `resources/js/Components/` wciąż w użyciu; brak `Components/Common/` i strony kitchen sink. Pełny audyt: `design-system.md` §2.
- **Backend / API:** present — kontrolery zasobów z politykami, generacja jadłospisu w kolejce z AI, lista zakupów i PDF przez dompdf (M-1).
- **Data:** present — PostgreSQL, migracje wszystkich encji z `.ai/core/db-plan.md`.
- **Auth:** present — polskie ekrany logowania, rejestracji i resetu, sesja 1440 min (`config/session.php:35`), reset przez e-mail (S-01).
- **Deploy / infra:** present — CI w `.github/workflows/master.yml` (build, Larastan, Pest, E2E) i `scripts/deploy.sh`; wdrożenie publiczne przez repo `kostecki-infra` (F-02).
- **Observability:** present — logi i alerty mailowe (`LOG_STACK=daily,mail_alert` w produkcji), `report()` w `GenerateMealPlanJob` (F-01).

Weryfikacja UI: Playwright z czterema specami w `tests/e2e/` opartymi na rolach i tekstach; brak zrzutów ekranu (`toHaveScreenshot`) i skanu literałów kolorów.

## Foundations

### F-03: Tokeny marki i brakujące prymitywy

- **Outcome:** (foundation) `app.css` ma paletę bazową, tokeny stanów (`success`, `warning`, `info`) i domeny (`meal-breakfast`, `meal-supper`, `meal-dinner`) w OKLCH z §4.2–4.4, nowy promień i skalę z §4.5–4.6 oraz blok `.dark` z odpowiednikami; warstwa zgodności `gray-200` jest usunięta; sześć prymitywów z §5.1 jest dodanych przez CLI, a `badge` i `alert` mają warianty stanów i kategorii; `CLAUDE.md` zawiera regułę design systemu z §7.1.
- **Change ID:** design-tokens-foundation
- **PRD refs:** DS-01, §4.1–4.6, §5.1, §7.1, D-1, D-2, D-5, D-8
- **Unlocks:** F-04, S-07, S-08, S-14 (i pośrednio każdy widok — żaden nie ma czego używać zamiast literałów, dopóki tokeny nie istnieją)
- **Prerequisites:** —
- **Parallel with:** —
- **Blockers:** —
- **Unknowns:** —
- **Risk:** zmiana tokenów od razu przemaluje komponenty `ui/` (przyciski staną się zielone) na widokach, które jeszcze mają literały — to zamierzone (§8), ale wymaga sprawdzenia kontrastu z §4.2 po konwersji i przeglądu diffu `package.json` po `shadcn-vue add` (D-5). Tabelę §8 w `design-system.md` trzeba przy tej zmianie zaktualizować o podział DS-01 na F-03 i F-04.
- **Status:** done

### F-04: Bramka wizualna design systemu

- **Outcome:** (foundation) strona kitchen sink — jedna strona pokazująca wszystkie tokeny, typografię i komponenty w ich stanach — działa pod `/dev/design-system` tylko w `local` i `testing`; spec Playwright robi jej zrzut i porównuje z zatwierdzonym wzorcem; `npm run lint:tokens` skanuje listę zmigrowanych plików pod kątem klas palety, hexów, wartości arbitralnych i `dark:` i działa jako krok CI. F-04 dopisuje też do reguły design systemu w `CLAUDE.md` punkt o kitchen sink i `lint:tokens`.
- **Change ID:** design-system-visual-gate
- **PRD refs:** DS-01, §5.4, §7.2–7.4, D-4, D-6
- **Unlocks:** S-07, S-08 i pośrednio S-09…S-13 — każda migracja widoku dopisuje swój plik do listy `lint:tokens` i swój zrzut; ścieżka weryfikacji „widok nie zna palety Tailwinda” (§3, zasada 1).
- **Prerequisites:** F-03
- **Parallel with:** S-14
- **Blockers:** —
- **Unknowns:**
  - Rozwiązane (2026-11-15, plan `design-system-visual-gate`): wzorce są generowane i porównywane wyłącznie w obrazie `mcr.microsoft.com/playwright:v<zainstalowana wersja>-noble`, lokalnie (`npm run test:visual`) i w CI, więc fonty i antyaliasing są te same.
- **Risk:** niestabilne zrzuty zamieniłyby bramkę w szum, który się ignoruje; dlatego bramka powstaje przed pierwszą migracją widoku i na samej stronie kitchen sink, zanim zacznie pilnować widoków z danymi.
- **Status:** in-progress

## Slices

### S-07: Wspólne komponenty aplikacji

- **Outcome:** Użytkownik może potwierdzać usuwanie w jednym, takim samym dialogu (fokus na „Anuluj”) w przepisach, jadłospisach i rodzinie, korzystać z tej samej paginacji i sortowania w listach przepisów i jadłospisów oraz rozpoznawać kategorie posiłków i statusy jadłospisu po nowych kolorach pory dnia, zawsze z etykietą.
- **Change ID:** shared-app-components
- **PRD refs:** DS-02, §5.2 (bez `AppShell`), §5.3 (`CategoryBadge`, `StatusTag`), D-3, D-8
- **Prerequisites:** F-03, F-04
- **Parallel with:** S-08, S-14
- **Blockers:** —
- **Unknowns:**
  - `PageHeader`, `EmptyState` i `FormField` bez żadnego wywołania w tej zmianie byłyby kodem na zapas — wprowadzić je tutaj z co najmniej jednym wywołaniem, czy dopiero w pierwszym widoku, który ich używa (S-09, S-13)? — Owner: /10x-plan. Block: no.
- **Risk:** cztery obecne dialogi zachowują się różnie (Breeze `Modal` kontra `ui/dialog`), więc ujednolicenie zmienia obsługę klawiatury i fokusu; specy E2E przepisów i jadłospisów pilnują, że role i teksty zostają.
- **Status:** proposed

### S-08: Powłoka aplikacji w kolorach marki

- **Outcome:** Użytkownik może poruszać się po aplikacji w nawigacji bocznej i menu użytkownika opartych na tokenach marki (aktywny element wyróżniony `primary`/`accent`), a ekrany gościa mają ten sam wygląd i bieżący rok w stopce zamiast „© 2024”.
- **Change ID:** app-shell-restyle
- **PRD refs:** DS-03, §5.2 (`AppShell`)
- **Prerequisites:** F-03, F-04
- **Parallel with:** S-07, S-10, S-11, S-12, S-14
- **Blockers:** —
- **Unknowns:** —
- **Risk:** powłoka otacza każdy widok, więc ta zmiana przesuwa zrzuty wszystkich stron naraz; `Dashboard.vue` dostaje tu tylko tokeny w miejsce literałów (pełny restyle zostaje w Parked), żeby końcowy skan w S-15 przeszedł.
- **Status:** proposed

### S-09: Widok jadłospisu w nowym wyglądzie (north star)

- **Outcome:** Użytkownik może zobaczyć wygenerowany jadłospis jako siatkę tygodnia, w której każda komórka ma kreskę w kolorze kategorii i rozwijane składniki, w trakcie generowania widzi szkielet siatki, przy dopracowaniu bez AI — ostrzeżenie na tokenie `warning`, a obok listę zakupów z ilościami wyrównanymi do prawej i sformatowanymi po polsku.
- **Change ID:** meal-plan-show-ui
- **PRD refs:** DS-04, §5.3 (`MealPlanGrid`, `ShoppingList`, `StatusTag`), §5.4, §4.5
- **Prerequisites:** S-07, S-08
- **Parallel with:** S-10, S-11, S-12, S-13, S-14
- **Blockers:** —
- **Unknowns:** —
- **Risk:** to rdzeń produktu pilnowany przez `meal-plan.spec.ts`; zamiana `<details>` na rozwijany komponent zmienia semantykę, więc nowy selektor w specu jest tu sygnałem zmiany znaczenia, który trzeba świadomie przejrzeć (§8), a nie tylko poprawić.
- **Status:** proposed

### S-10: Lista jadłospisów w nowym wyglądzie

- **Outcome:** Użytkownik może przeglądać listę swoich jadłospisów, filtrować ją i otwierać dialog generowania w nowym wyglądzie, ze statusami na wspólnych wariantach.
- **Change ID:** meal-plans-index-ui
- **PRD refs:** DS-05
- **Prerequisites:** S-07
- **Parallel with:** S-08, S-09, S-11, S-12, S-13, S-14
- **Blockers:** —
- **Unknowns:** —
- **Risk:** dialog generowania uruchamia zadanie w kolejce — zmiana wyglądu nie może zmienić wysyłanego żądania; pilnuje tego `meal-plan.spec.ts`.
- **Status:** proposed

### S-11: Przepisy w nowym wyglądzie

- **Outcome:** Użytkownik może przeglądać listę przepisów, oglądać szczegóły przepisu i dodawać lub edytować przepis ze składnikami w nowym wyglądzie.
- **Change ID:** recipes-ui
- **PRD refs:** DS-06
- **Prerequisites:** S-07
- **Parallel with:** S-08, S-09, S-10, S-12, S-13, S-14
- **Blockers:** —
- **Unknowns:**
  - Jedna zmiana czy trzy (lista, szczegóły, formularz)? §8 dopuszcza podział „po jednym widoku, jeśli diff rośnie”. — Owner: /10x-plan. Block: no.
- **Risk:** `RecipeRow` ma najwięcej literałów w listach (34), a formularz z `IngredientManager` jest najbardziej interaktywnym widokiem aplikacji i ma własny spec E2E (`recipe-form.spec.ts`).
- **Status:** proposed

### S-12: Rodzina w nowym wyglądzie

- **Outcome:** Użytkownik może dodawać, edytować i usuwać członków rodziny w nowym wyglądzie.
- **Change ID:** family-members-ui
- **PRD refs:** DS-07
- **Prerequisites:** S-07
- **Parallel with:** S-08, S-09, S-10, S-11, S-13, S-14
- **Blockers:** —
- **Unknowns:**
  - Czy przy okazji ujednolicić `Create`/`Edit` do `Form.vue` (dziś w Parked), jeśli zmieści się w zakresie? — Owner: /10x-plan. Block: no.
- **Risk:** jedyny widok bez własnego specu E2E — weryfikacja opiera się na zrzucie i testach feature kontrolera.
- **Status:** proposed

### S-13: Ekrany konta bez Breeze

- **Outcome:** Użytkownik może logować się, rejestrować, resetować hasło i edytować profil na ekranach z tymi samymi przyciskami, polami i checkboxem „Zapamiętaj mnie” co reszta aplikacji.
- **Change ID:** auth-profile-ui
- **PRD refs:** DS-08, §5.1 (`checkbox`), §5.2 (`FormField`)
- **Prerequisites:** S-07, S-08
- **Parallel with:** S-09, S-10, S-11, S-12, S-14
- **Blockers:** —
- **Unknowns:** —
- **Risk:** pierwszy ekran każdego użytkownika i dwa specy E2E (`login.spec.ts`, `password-reset.spec.ts`); zejście z Breeze nie może zgubić polskich komunikatów walidacji z S-01.
- **Status:** proposed

### S-14: PDF zgodny z ekranem

- **Outcome:** Użytkownik może pobrać PDF, w którym kategoria posiłku ma w tabeli to samo tło co na ekranie (z etykietą, czytelną też w druku czarno-białym), komunikaty mają ramkę ostrzeżenia, a hierarchia tekstu odpowiada rolom z §4.5.
- **Change ID:** pdf-design-alignment
- **PRD refs:** DS-09, §6
- **Prerequisites:** F-03
- **Parallel with:** F-04, S-07, S-08, S-09, S-10, S-11, S-12, S-13, S-15
- **Blockers:** —
- **Unknowns:** —
- **Risk:** generator PDF nie czyta zmiennych CSS aplikacji, więc kolory są ręcznym lustrem tokenów (§6) — bez komentarza synchronizacji rozjadą się przy pierwszej zmianie palety.
- **Status:** proposed

### S-15: Sprzątanie po starym UI

- **Outcome:** Użytkownik może wejść na `/` i trafić do logowania lub do aplikacji zamiast domyślnej strony Laravela, a w aplikacji nie zostaje żaden komponent Breeze; `lint:tokens` obejmuje całe `resources/js` poza `Components/ui/` i blokuje CI przy nowym literale.
- **Change ID:** legacy-ui-cleanup
- **PRD refs:** DS-10, D-7, §7.4
- **Prerequisites:** S-08, S-09, S-10, S-11, S-12, S-13
- **Parallel with:** S-14
- **Blockers:** —
- **Unknowns:** —
- **Risk:** stoi na końcu, bo usuwa komponenty dopiero wtedy, gdy żaden widok ich nie importuje; rozszerzenie skanu na całe `resources/js` ujawni literały, których nie objęła żadna migracja — każdy trzeba wtedy zmigrować albo świadomie wyłączyć.
- **Status:** proposed

## Backlog Handoff

| Roadmap ID | Change ID                 | Suggested issue title                                             | Ready for `/10x-plan` | Notes |
| ---------- | ------------------------- | ----------------------------------------------------------------- | --------------------- | ----- |
| F-03       | design-tokens-foundation  | Tokeny marki w OKLCH, prymitywy shadcn-vue i reguła design systemu | yes                   | Run `/10x-plan design-tokens-foundation` |
| F-04       | design-system-visual-gate | Kitchen sink, zrzut Playwright i `lint:tokens` w CI               | no                    | Czeka na F-03 |
| S-07       | shared-app-components     | Wspólny dialog, paginacja, sortowanie i warianty kategorii        | no                    | Czeka na F-04 |
| S-08       | app-shell-restyle         | Powłoka aplikacji i ekrany gościa w kolorach marki                | no                    | Czeka na F-04; `/10x-ui` |
| S-09       | meal-plan-show-ui         | Widok jadłospisu: siatka tygodnia i lista zakupów                 | no                    | North star. Czeka na S-07, S-08; `/10x-ui` |
| S-10       | meal-plans-index-ui       | Lista jadłospisów, filtry i dialog generowania                    | no                    | Czeka na S-07; `/10x-ui` |
| S-11       | recipes-ui                | Lista, szczegóły i formularz przepisu                             | no                    | Czeka na S-07; `/10x-ui`, ewentualnie po jednym widoku |
| S-12       | family-members-ui         | Członkowie rodziny w nowym wyglądzie                              | no                    | Czeka na S-07; `/10x-ui` |
| S-13       | auth-profile-ui           | Ekrany konta i profilu bez komponentów Breeze                     | no                    | Czeka na S-07, S-08; `/10x-ui` |
| S-14       | pdf-design-alignment      | PDF z kolorami kategorii i ramką ostrzeżeń jak na ekranie         | no                    | Czeka na F-03 |
| S-15       | legacy-ui-cleanup         | Usunięcie Breeze, przekierowanie `/`, pełny `lint:tokens` w CI    | no                    | Czeka na S-08…S-13 |

## Open Roadmap Questions

1. **Jak mierzyć metryki sukcesu z PRD §6** (90% z profilem rodziny w 7 dni, 75% generuje co tydzień)? Przeniesione z M-1: nic w aplikacji ich dziś nie zbiera i żadne wymaganie funkcjonalne tego nie przewiduje. — Owner: user. Block: roadmap-wide (poza zakresem M-2; kandydat na źródło następnego kamienia milowego).
2. **Brak US-007 w PRD** — usunięta historyjka czy luka? Przeniesione z M-1. — Owner: user. Block: none.

## Parked

- **Dark mode** — Why parked: D-1 (MVP tylko w trybie jasnym); `design-system.md` §8 przewiduje go jako osobną zmianę po DS-10, wyłącznie w tokenach i przełączniku.
- **Pełny restyle `Dashboard.vue` i dashboard z prawdziwymi statystykami** — Why parked: poza PRD i poza rolloutem (§8); w M-2 dashboard dostaje tylko tokeny w S-08.
- **Własna strona startowa pod `/`** — Why parked: D-7 (przekierowanie zamiast landing page; PRD jej nie przewiduje).
- **Ujednolicenie stron `FamilyMembers` do konwencji `Index/Show/Form.vue`** — Why parked: dług techniczny bez efektu dla użytkownika; S-12 może go wchłonąć, jeśli zmieści się w zakresie.
- **Import przepisów z URL** — Why parked: PRD §4.
- **Zdjęcia i filmy przepisów** — Why parked: PRD §4.
- **Udostępnianie przepisów, funkcje społecznościowe, kopie zapasowe** — Why parked: PRD §4.
- **Edycja wygenerowanego jadłospisu lub listy zakupów** — Why parked: PRD §4.
- **Historia poprzednich wersji jadłospisu** — Why parked: US-009 kryterium 3 („Poprzednie wersje nie są zapisywane”).
- **Limit generacji PDF i czyszczenie starych plików PDF** — Why parked: `.ai/core/api-plan.md` §3, poza wymaganiami PRD.

## Milestone History

- **M-1: Od przepisów do PDF z listą zakupów** (`mvp-meal-plan-to-pdf`) — closed 2026-11-15. Zalogowana rodzina generuje tydzień 7×3 z własnych przepisów, ze składnikami skalowanymi przez AI, zsumowaną listą zakupów i jednym PDF-em; aplikacja z workerem kolejki działa publicznie, a błędy trafiają do śledzenia błędów.

## Done

- **F-03: (foundation) `app.css` ma paletę bazową, tokeny stanów (`success`, `warning`, `info`) i domeny (`meal-breakfast`, `meal-supper`, `meal-dinner`) w OKLCH z §4.2–4.4, nowy promień i skalę z §4.5–4.6 oraz blok `.dark` z odpowiednikami; warstwa zgodności `gray-200` jest usunięta; sześć prymitywów z §5.1 jest dodanych przez CLI, a `badge` i `alert` mają warianty stanów i kategorii; `CLAUDE.md` zawiera regułę design systemu z §7.1.** — Archived 2026-11-15 → `context/archive/2026-11-15-design-tokens-foundation/`. Lesson: —.
- **F-01: (foundation) wyjątki z żądań HTTP i z zadań w kolejce trafiają do narzędzia śledzenia błędów z identyfikatorem użytkownika i jadłospisu; nieudana generacja nie znika po cichu.** — Archived 2026-11-13 → `context/archive/2026-11-13-error-tracking-baseline/`. Lesson: —.
- **S-03: Użytkownik może jednym kliknięciem wygenerować jadłospis 7 dni × 3 posiłki, w którym przepisy są losowane z jego własnych przepisów według kategorii bez powtórzeń w tygodniu, zobaczyć go w interfejsie i regenerować bez limitu.** — Archived 2026-11-13 → `context/archive/2026-11-13-real-weekly-meal-plan/`. Lesson: —.
- **S-02: Użytkownik może zapisać przepis tylko z nazwą, kategorią, co najmniej jednym składnikiem (nazwa, jednostka g/kg/ml/l/szt., ilość), sposobem przygotowania, kalorycznością i liczbą porcji, a potem przejrzeć, edytować i usunąć go z potwierdzeniem.** — Archived 2026-11-14 → `context/archive/2026-11-13-recipe-required-ingredients/`. Lesson: —.
- **S-05: Użytkownik może zobaczyć przy jadłospisie listę zakupów, w której składniki są zsumowane, ilości powyżej 1000 g i 1000 ml zamienione na kg i l, zaokrąglone do 2 miejsc i posortowane alfabetycznie.** — Archived 2026-11-14 → `context/archive/2026-11-14-shopping-list/`. Lesson: —.
- **S-04: Użytkownik może zobaczyć w wygenerowanym jadłospisie ilości składników przeliczone do swojej rodziny i zamienione przez AI na praktyczne ilości; gdy AI zawiedzie, nadal dostaje jadłospis z ilościami policzonymi bez AI i informację o tym.** — Archived 2026-11-14 → `context/archive/2026-11-14-ai-ingredient-scaling/`. Lesson: —.
- **S-06: Użytkownik może pobrać jeden PDF, który najpierw zawiera tabelę jadłospisu (dzień, kategoria, nazwa, składniki, przygotowanie), a potem listę zakupów, zgodny z ostatnią wygenerowaną wersją i otwierający się w przeglądarce.** — Archived 2026-11-14 → `context/archive/2026-11-14-meal-plan-pdf-export/`. Lesson: —.
- **S-01: Użytkownik może zarejestrować się, zalogować i zresetować hasło na polskich ekranach; sesja wygasa po 24 h braku aktywności, a link resetu (ważny 1 h) faktycznie dociera e-mailem.** — Archived 2026-11-14 → `context/archive/2026-11-14-polish-auth-with-24h-session/`. Lesson: —.
- **F-02: (foundation) aplikacja działa pod publicznym adresem na serwerze Mikrus z działającym workerem kolejki; konfigurację serwera i wdrożenia trzyma osobne repo `kostecki-infra`, a to repo dostarcza artefakt, którego ono oczekuje.** — Archived 2026-11-14 → `context/archive/2026-11-14-public-deploy-with-queue-worker/`. Lesson: —.
