---
project: menu-maker
version: 4
status: draft                    # draft | active | locked
created: 2026-11-13
updated: 2026-11-17
prd_version: —
main_goal: quality
top_blocker: decisions
milestone_id: spizarnia-restyle
milestone_seq: 4
milestone_status: open           # open | done
---

# Roadmap: Menu Maker

> Derived from `context/foundation/design-system.md` §10 (decisions D-10…D-14, rollout SP-01…SP-07 in §10.6) + auto-researched codebase baseline; `.ai/core/prd.md` is context only.
> Edit-in-place; the next milestone continues this file. Archive only when an independent roadmap replaces it.
> Slices below are listed in dependency order. The "At a glance" table is the index.

## Milestone

**M-4: Restyle: Spiżarnia** — Status: open

- **Intent:** Każdy ekran aplikacji po zalogowaniu i ekrany gościa wyglądają według kierunku C · Spiżarnia. Kolor niosą posiłki (miód, pomidor, indygo jako pełne bloki), marka cofa się do atramentu, a kafle mają kontur 2px, duże zaokrąglenia i twardy cień. PDF dostaje nowe kolory kategorii.
- **Source materials:** `context/foundation/design-system.md` §10 (wersja z 2026-11-17, commit `fc57ec0`): tokeny §10.2, typografia §10.3, zasady §10.4, widoki §10.5, rollout §10.6 (SP-01…SP-07), decyzje D-10…D-14 w §9. Ekrany: artefakt Design System „Menu Maker — Spiżarnia” (§10.1); przy rozbieżności wygrywa `design-system.md` (D-11).
- **Done when:** every F-NN and S-NN below is `done`.
- **Scope anchors:** SP-01…SP-07 (§10.6), D-10, D-12, D-14 (§9), §10.2–10.5, ekrany 02–15 artefaktu (§10.1). Ekran 01 (strona startowa, D-13) jest poza kamieniem milowym — zob. `## Parked`.
- **Numbering:** identyfikatory kontynuują numerację M-1…M-3 (F-05…, S-22…), żeby nie zderzyć się z wpisami w `## Done`.

## Vision recap

Menu Maker to narzędzie domowe: rodzic raz w tygodniu dodaje przepisy, układa szkic jadłospisu 7 × 3, zatwierdza go i drukuje PDF z listą zakupów. Po M-2 aplikacja ma spójny design system (tokeny, wspólne komponenty, kitchen sink, bramka zrzutów), ale wygląda jak typowy panel w zieleni ziół. Ten kamień milowy podmienia kierunek wyglądu na Spiżarnię bez zmiany zachowania: te same widoki, przepływy i testy E2E, nowy kolor, kształt i krój. Kitchen sink to jedna strona deweloperska (`/dev/design-system`) z wszystkimi tokenami i komponentami w ich stanach; bramka wizualna porównuje jej zrzuty i zrzuty prawdziwych stron z zatwierdzonymi wzorcami.

## North star

**S-22: Użytkownik porusza się po aplikacji w powłoce Spiżarni — pasek górny z pigułkami nawigacji i awatarem, ekrany gościa jako ekran dzielony** — przy celu „quality” to pierwszy widok, w którym nowe tokeny i prymitywy składają się w prawdziwy ekran widoczny na każdej stronie; wszystkie kolejne widoki stoją na tej powłoce.

> „North star” (gwiazda przewodnia) oznacza tu najmniejszy przepływ end-to-end, którego dostarczenie dowodzi, że ten kamień milowy działa — dlatego stoi tak wcześnie, jak pozwalają jego warunki wstępne: restyle widoków ma sens tylko wtedy, gdy powłoka Spiżarni działa i przechodzi bramkę wizualną.

## At a glance

| ID   | Change ID                   | Outcome (user can …)                                                                                       | Prerequisites | PRD refs                          | Status   |
| ---- | --------------------------- | ---------------------------------------------------------------------------------------------------------- | ------------- | --------------------------------- | -------- |
| F-05 | spizarnia-tokens            | (foundation) tokeny, promienie, cienie i krój Spiżarni w `app.css`; lustro PDF z nowymi kolorami kategorii  | —             | SP-01, D-10, D-12, D-14, §10.2–10.3 | done |
| F-06 | spizarnia-primitives        | (foundation) prymitywy `ui/` mają warianty Spiżarni (pigułka, kontur, naklejka, kafel, dialog) w kitchen sinku | F-05          | SP-02, §10.2, §10.4               | done        |
| S-22 | spizarnia-app-shell         | poruszać się po aplikacji w pasku górnym Spiżarni i logować na ekranie dzielonym                           | F-06          | SP-03, §10.5, ekrany 02–04        | done |
| S-23 | spizarnia-dashboard-ui      | zobaczyć pulpit z hero ostatniego jadłospisu, kafelkami statystyk i tygodniem w kolorach pór dnia          | S-22          | SP-04, §10.5, ekrany 05–06        | ready    |
| S-24 | spizarnia-recipes-ui        | przeglądać przepisy jako kafle z filtrem kategorii (pigułki z liczbą), oglądać i edytować przepis w Spiżarni | S-22          | SP-05, §10.5, ekrany 07–09        | ready    |
| S-25 | spizarnia-meal-plans-ui     | przeglądać jadłospisy jako kafle tygodni i układać szkic w siatce kolorowych komórek z dialogami Spiżarni  | S-22          | SP-06, §10.5, ekrany 10–12, 15    | done        |
| S-26 | spizarnia-family-profile-ui | zarządzać członkami rodziny na kartach osób i edytować profil w trzech kaflach                             | S-22          | SP-07, §10.5, ekrany 13–14        | ready    |

## Streams

Navigation aid — groups items that share a Prerequisites chain. Canonical ordering still lives in the dependency graph below; this table is the proposed reading order across parallel tracks.

| Stream | Theme                      | Chain                      | Note                                                                                                     |
| ------ | -------------------------- | -------------------------- | -------------------------------------------------------------------------------------------------------- |
| A      | Fundament i powłoka        | `F-05` → `F-06` → `S-22`   | Cel „quality”: tokeny i prymitywy przechodzą bramkę zrzutów, zanim jakikolwiek widok na nich stanie.      |
| B      | Pulpit                     | `S-23`                     | Dołącza do strumienia A w `S-22`; równolegle do C, D, E.                                                  |
| C      | Przepisy                   | `S-24`                     | Dołącza do strumienia A w `S-22`; równolegle do B, D, E.                                                  |
| D      | Jadłospisy                 | `S-25`                     | Dołącza do strumienia A w `S-22`; równolegle do B, C, E.                                                  |
| E      | Rodzina i profil           | `S-26`                     | Dołącza do strumienia A w `S-22`; równolegle do B, C, D. Wspólny kitchen sink: przy merge'u B–E konflikty w nim rozwiązać, a baseline `design-system.spec.ts` odświeżyć raz, na końcu (§10.6). |

## Baseline

What's already in place in the codebase as of `2026-11-17` (auto-researched + user-confirmed).
Foundations below assume these are present and do NOT re-scaffold them.

- **Frontend:** present — tokeny Zielnika w OKLCH z blokiem `.dark` w `app.css`, promienie liczone od `--radius` (`--radius-md = --radius − 2px`), krój Figtree z bunny.net; 21 prymitywów w `ui/`; w `Common/` `AppSidebar` (panel boczny), `UserMenu`, `PageHeader`, `EmptyState`, `FormField`, `ConfirmDialog`, `ServerPagination`, `SortableHeader`. Brak tokenów `sidebar-active`, `highlight`, `overlay`.
- **Backend / API:** present, partial wobec M-4 — pulpit dostaje liczby i ostatni gotowy plan, ale nie jego posiłki („Ten tydzień”); lista przepisów nie zwraca liczności kategorii (pigułki filtra).
- **Data:** present — bez zmian schematu w M-4.
- **Auth:** present — polityki zasobów, sesja 24 h; bez zmian.
- **Deploy / infra:** present — CI z `lint:tokens`, Larastanem, Pestem, E2E i bramką wizualną (6 speców: kitchen sink, powłoka, konto, lista jadłospisów, przepisy, jadłospis ze szkicem i dialogiem podmiany); brak speca wizualnego dla członków rodziny.
- **Observability:** present — bez zmian.
- **PDF:** present — dompdf; lustro hexów Zielnika w szablonie PDF pilnowane testem palety (`MealPlanPdfPaletteTest`); krój DejaVu Sans zostaje (§10.3).

## Foundations

### F-05: Tokeny Spiżarni

- **Outcome:** (foundation) `app.css` ma wartości Spiżarni z §10.2 w OKLCH (w tym nowe `sidebar*`, `highlight`, `overlay`), jawnie rozpisane promienie 12/20/24 px i pełne, dwa cienie „naklejki” i krój Bricolage Grotesque zamiast Figtree; PDF ma nowe kolory kategorii jako pełne bloki, a test palety pilnuje nowych hexów (D-12); kitchen sink pokazuje nowe kolory i typografię, a baseline'y są świadomie odświeżone.
- **Change ID:** spizarnia-tokens
- **PRD refs:** SP-01, D-10, D-12, D-14, §10.2–10.3
- **Unlocks:** F-06, S-22 (każdy widok czyta te tokeny); decyzja D-14.
- **Prerequisites:** —
- **Parallel with:** —
- **Blockers:** —
- **Unknowns:**
  - D-14: czy blok `.dark` dostaje wartości Spiżarni, czy zostaje usunięty do osobnej zmiany dark mode? — Owner: user. Block: no (domyślnie D-1: MVP tylko jasny; rozstrzygnąć w `/10x-plan`).
- **Risk:** Pierwszy, bo sama zmiana tokenów przemaluje całą aplikację i unieważni wszystkie zrzuty naraz; zmiana kroju zmienia metryki tekstu, więc każdy baseline trzeba przejrzeć, a spec sprawdzający załadowanie Figtree — przestawić.
- **Status:** done

### F-06: Prymitywy Spiżarni

- **Outcome:** (foundation) prymitywy `ui/` mają warianty Spiżarni: `Button` (pigułka, kontur, naklejka), `Badge` (status z konturem, `draft` przerywany), `Alert`, `Card` (kafel), pola formularza (kontur 2px, promień 12px), `Dialog` (24px, cień, zamknięcie); każdy wariant ze stanami z §5.4 jest w kitchen sinku.
- **Change ID:** spizarnia-primitives
- **PRD refs:** SP-02, §10.2, §10.4
- **Unlocks:** S-22, S-23, S-24, S-25, S-26 — bez wspólnych wariantów cztery równoległe widoki edytowałyby te same pliki `ui/` i rozjechały się wizualnie.
- **Prerequisites:** F-05
- **Parallel with:** —
- **Blockers:** —
- **Unknowns:**
  - Jak wyglądają hover, focus, disabled i ładowanie przycisku-naklejki i kafla? Makiety ich nie pokazują (§10.1). — Owner: user. Block: no (projektuje zmiana według §5.4).
- **Risk:** Zakres ograniczony do wariantów, których używają ekrany 02–15; układy ekranów (kafle przepisów, siatka, karty osób) zostają w wycinkach widoków, żeby fundament nie stał się restyle'em całego UI.
- **Carry-over z F-05 (impl-review):** `rounded-xl` (24 px) jest teraz większy niż domyślny `rounded-2xl` (16 px); jeśli prymityw potrzebuje większego promienia, zdefiniować `--radius-2xl` zamiast sięgać po domyślny.
- **Status:** done

## Slices

### S-22: Powłoka aplikacji Spiżarni (north star)

- **Outcome:** Użytkownik może poruszać się po aplikacji w pasku górnym z pigułkami nawigacji i awatarem (menu użytkownika), poniżej `lg` w szufladzie, a na ekranach gościa (logowanie, rejestracja, reset hasła) widzi ekran dzielony: blok `highlight` z hasłem i trzema blokami kategorii po lewej, formularz po prawej.
- **Change ID:** spizarnia-app-shell
- **PRD refs:** SP-03, §10.5, ekrany 02–04
- **Prerequisites:** F-06
- **Parallel with:** —
- **Blockers:** —
- **Unknowns:**
  - Jak wygląda pasek górny i ekran dzielony na mobile (makiety tylko desktop 1280 px)? — Owner: user. Block: no.
- **Risk:** Zastępuje panel boczny, więc dotyka każdej strony i zrzutów powłoki i konta; idzie przed widokami, żeby SP-04…SP-07 odświeżały zrzuty tylko raz, już w nowej powłoce.
- **Carry-over z F-05 (impl-review):** `--primary` = `--foreground`, więc `hover:text-primary` nic nie robi — logo w `GuestLayout.vue:16` potrzebuje niekolorowej wskazówki hover; aktywna pozycja nawigacji (`AppSidebar.vue:58`) jest na `bg-accent` (żółty = `--warning`), a pozycje mają 12 px zamiast 8 px.
- **Carry-over z F-06:** zdjąć `shadow-none` z `Card` w `GuestLayout.vue:28` (kafel ma już cień naklejki); przycisk zamknięcia `Sheet` (okrągły `secondary` w rogu) ułożyć nad nowym nagłówkiem szuflady; `SheetHeader` wyrównać do `DialogHeader` (`pr-12 text-left`), żeby tytuł nie wchodził pod okrągły przycisk.
- **Status:** done

### S-23: Pulpit Spiżarni

- **Outcome:** Użytkownik może zobaczyć na pulpicie hero `highlight` z ostatnim jadłospisem, kafelki statystyk w kolorach pór dnia i „Ten tydzień” jako posiłki ostatniego planu w kolorach kategorii; nowy użytkownik widzi pusty stan z ekranu 06.
- **Change ID:** spizarnia-dashboard-ui
- **PRD refs:** SP-04, §10.5, ekrany 05–06
- **Prerequisites:** S-22
- **Parallel with:** S-24, S-25, S-26
- **Blockers:** —
- **Unknowns:**
  - Jak „Ten tydzień” pokazuje pusty posiłek, powtórzony obiad i kolację na śniadanie (M-3)? — Owner: user. Block: no (domyślnie tak jak siatka jadłospisu).
- **Risk:** Jedyna zmiana backendu w tym wycinku to posiłki ostatniego planu w propsach pulpitu, ograniczone do właściciela; reszta to układ.
- **Carry-over z F-05 (impl-review):** krążek ikony `StatCard.vue:15` jest na `bg-accent` (żółty = `--warning`).
- **Carry-over z F-06:** `CardContent` ma `p-6 pt-0` (bez nagłówka karty), więc zawartość `StatCard` nie ma górnego odstępu — dać jej własny padding.
- **Status:** ready

### S-24: Przepisy w Spiżarni

- **Outcome:** Użytkownik może przeglądać przepisy jako kafle po 4 w rzędzie z paskiem kategorii i kcal, filtrować kategorią przez pigułki z liczbą przepisów, oglądać przepis w bloku koloru kategorii (składniki jako żetony, kroki numerowane na `highlight`) i dodawać lub edytować przepis w kaflach z kategorią jako trzy kolorowe kafle-radio.
- **Change ID:** spizarnia-recipes-ui
- **PRD refs:** SP-05, §10.5, ekrany 07–09
- **Prerequisites:** S-22
- **Parallel with:** S-23, S-25, S-26
- **Blockers:** —
- **Unknowns:**
  - Czy liczby przy pigułkach kategorii uwzględniają bieżące wyszukiwanie, czy liczą wszystkie przepisy użytkownika? — Owner: user. Block: no.
  - Czy sortowanie z dzisiejszej tabeli zostaje przy widoku kafli, i w jakiej formie? — Owner: user. Block: no.
- **Risk:** Trzy widoki i liczności kategorii w backendzie; jeśli diff rośnie, `/10x-plan` może podzielić wycinek na listę i szczegóły/formularz (po jednym widoku).
- **Carry-over z F-05 (impl-review):** `RecipesTable.vue:2`, `IngredientCard`, `IngredientRow`, `InstructionsSection` i `Recipes/Form.vue:16` mają `rounded-lg` = 20 px (z `overflow-hidden` przycina rogi nagłówka tabeli); `hover:text-primary` w `RecipeRow.vue:6` nie zmienia koloru (zostaje podkreślenie).
- **Carry-over z F-06:** filtr kategorii przepisów z `Select` na `Toggle` w tonach `breakfast`/`supper`/`dinner` z licznościami; dodawanie składnika na wariant `dashed` przycisku; akcje tabeli z `ghost size="icon"` na `secondary` (okrągły przycisk ikonowy); pigułkowe pole wyszukiwania (zostało poza F-06).
- **Status:** ready

### S-25: Jadłospisy w Spiżarni

- **Outcome:** Użytkownik może przeglądać jadłospisy jako kafle tygodni z paskiem wypełnienia, statusem (pigułka z konturem, szkic przerywany) i filtrem statusu jako pigułki, a w jadłospisie widzi siatkę dni × posiłki z kolorowymi komórkami, w szkicu pigułkę „Zmień” i pusty posiłek jako przerywaną ramkę z „Wybierz”, listę zakupów jako kafel obok oraz dialogi generowania, podmiany i potwierdzenia w stylu Spiżarni.
- **Change ID:** spizarnia-meal-plans-ui
- **PRD refs:** SP-06, §10.5, ekrany 10–12, 15
- **Prerequisites:** S-22
- **Parallel with:** S-23, S-24, S-26
- **Blockers:** —
- **Unknowns:**
  - Jak siatka dni × posiłki układa się na mobile i jak wyglądają w niej oznaczenia powtórki i „z kolacji”, których makiety mogą nie pokazywać? — Owner: user. Block: no.
- **Risk:** Rdzeń produktu z największą liczbą stanów (szkic, w trakcie, gotowy, błąd, pusty posiłek, powiązania) i najwięcej zrzutów do odświeżenia; zachowanie i testy E2E bez zmian.
- **Carry-over z F-05 (impl-review):** przełącznik „Składniki” (`MealPlanGrid.vue:60`, `text-primary`) nie odróżnia się już od tekstu; `MealPlans/Index.vue:18` i `MealPlans/Show.vue:56` mają `rounded-lg` = 20 px; paski kategorii w siatce są już na `border-meal-*` (poprawione w F-05).
- **Carry-over z F-06:** filtr statusu jadłospisów z `Select` na `Toggle`; pusty posiłek i „Wybierz” na wariant `dashed` przycisku.
- **Status:** done

### S-26: Rodzina i profil w Spiżarni

- **Outcome:** Użytkownik może zarządzać członkami rodziny na kartach osób (inicjał, wiek, waga, wzrost) z przerywanym kaflem „Dodaj członka rodziny” i edytować profil w trzech kaflach, z usunięciem konta w kolorze `destructive`.
- **Change ID:** spizarnia-family-profile-ui
- **PRD refs:** SP-07, §10.5, ekrany 13–14
- **Prerequisites:** S-22
- **Parallel with:** S-23, S-24, S-25
- **Blockers:** —
- **Unknowns:** —
- **Risk:** Członkowie rodziny nie mają dziś speca wizualnego, więc wycinek dokłada go, żeby restyle był pilnowany jak pozostałe widoki.
- **Carry-over z F-05 (impl-review):** `FamilyMembers/Index.vue:61` ma `rounded-lg overflow-hidden` = 20 px.
- **Carry-over z F-06:** przerywany kafel „Dodaj członka rodziny” zbudować na wariancie `dashed` przycisku.
- **Status:** ready

## Backlog Handoff

| Roadmap ID | Change ID                   | Suggested issue title                                        | Ready for `/10x-plan` | Notes |
| ---------- | --------------------------- | ------------------------------------------------------------ | --------------------- | ----- |
| F-05       | spizarnia-tokens            | Tokeny, krój i kolory PDF kierunku Spiżarnia                 | yes                   | Run `/10x-plan spizarnia-tokens`; rozstrzyga D-14 |
| F-06       | spizarnia-primitives        | Warianty prymitywów `ui/` w stylu Spiżarni                   | no                    | Po F-05 |
| S-22       | spizarnia-app-shell         | Powłoka aplikacji: pasek górny i ekran dzielony gościa       | no                    | North star; po F-06 |
| S-23       | spizarnia-dashboard-ui      | Pulpit w stylu Spiżarni                                      | yes                   | Run `/10x-plan spizarnia-dashboard-ui`; równolegle z S-24…S-26 |
| S-24       | spizarnia-recipes-ui        | Przepisy w stylu Spiżarni (z licznościami kategorii)         | yes                   | Run `/10x-plan spizarnia-recipes-ui`; równolegle z S-23, S-25, S-26 |
| S-25       | spizarnia-meal-plans-ui     | Jadłospisy i dialogi w stylu Spiżarni                        | yes                   | Run `/10x-plan spizarnia-meal-plans-ui`; równolegle z S-23, S-24, S-26 |
| S-26       | spizarnia-family-profile-ui | Rodzina i profil w stylu Spiżarni                            | yes                   | Run `/10x-plan spizarnia-family-profile-ui`; równolegle z S-23…S-25 |

## Open Roadmap Questions

Brak pytań przekrojowych. Pytania bez odpowiedzi należące do jednego elementu (D-14, stany i mobile nieobecne w makietach) są w wycinkach; żadne nie blokuje planowania.

## Parked

- **Strona startowa Spiżarni (ekran 01 artefaktu) pod `/`** — Why parked: D-13 — osobna zmiana po M-4, odwracająca D-7 (dziś `/` przekierowuje na logowanie lub pulpit).
- **Dark mode** — Why parked: D-1 (MVP tylko w trybie jasnym); D-14 rozstrzyga w F-05 tylko los bloku `.dark`, nie wdrożenie trybu ciemnego.
- **Regeneracja starszych komponentów `ui/` i przejście na `@lucide/vue`** — Why parked: D-9 — osobna zmiana; F-06 dokłada warianty, nie regeneruje komponentów CLI.
- **Edycja jadłospisu po wygenerowaniu (status `done`)** — Why parked: M-3 przenosi edycję do szkicu przed skalowaniem; edycja gotowego planu wymaga ponownego skalowania.
- **Losowanie, które samo tworzy powtórki lub resztki** — Why parked: powtórka i resztki to świadomy wybór użytkownika w szkicu (M-3); losowanie nadal bez powtórzeń.
- **Ujednolicenie stron `FamilyMembers` do konwencji `Index/Show/Form.vue`** — Why parked: dług techniczny bez efektu dla użytkownika (przeniesione z M-2).
- **Import przepisów z URL** — Why parked: PRD §4.
- **Zdjęcia i filmy przepisów** — Why parked: PRD §4.
- **Udostępnianie przepisów, funkcje społecznościowe, kopie zapasowe** — Why parked: PRD §4.
- **Historia poprzednich wersji jadłospisu** — Why parked: US-009 kryterium 3 („Poprzednie wersje nie są zapisywane”).
- **Limit generacji PDF i czyszczenie starych plików PDF** — Why parked: `.ai/core/api-plan.md` §3, poza wymaganiami PRD.
- **Pomiar aktywności użytkowników (dawne metryki PRD §6)** — Why parked: narzędzie domowe dla jednej rodziny; metryki usunięte z PRD 2026-11-17. Wraca, jeśli aplikacja zostanie udostępniona innym rodzinom.

## Milestone History

- **M-1: Od przepisów do PDF z listą zakupów** (`mvp-meal-plan-to-pdf`) — closed 2026-11-15. Zalogowana rodzina generuje tydzień 7×3 z własnych przepisów, ze składnikami skalowanymi przez AI, zsumowaną listą zakupów i jednym PDF-em; aplikacja z workerem kolejki działa publicznie, a błędy trafiają do śledzenia błędów.
- **M-2: Spójny wygląd aplikacji i PDF-u** (`consistent-design-system`) — closed 2026-11-16. Każdy widok aplikacji (powłoka, pulpit, jadłospisy, przepisy, rodzina, konto) i PDF korzysta z tokenów marki, kolorów pory dnia i wspólnych komponentów; kitchen sink, zrzuty Playwright i `lint:tokens` na całym `resources/js` pilnują tego w CI, a komponenty Breeze są usunięte.
- **M-3: Jadłospis układany przed wygenerowaniem** (`editable-meal-plan-draft`) — closed 2026-11-17. Wylosowany tydzień jest najpierw szkicem: użytkownik podmienia przepisy, zostawia posiłki puste, planuje ten sam obiad dwa dni z rzędu i kolację na śniadanie następnego dnia, a skalowanie AI rusza dopiero po zatwierdzeniu; PDF może dołączać pełne przepisy na osobnych stronach.

## Done

- **S-22: Użytkownik może poruszać się po aplikacji w pasku górnym z pigułkami nawigacji i awatarem (menu użytkownika), poniżej `lg` w szufladzie, a na ekranach gościa (logowanie, rejestracja, reset hasła) widzi ekran dzielony: blok `highlight` z hasłem i trzema blokami kategorii po lewej, formularz po prawej.** — Archived 2026-11-17 → `context/archive/2026-11-17-spizarnia-app-shell/`. Lesson: —.
- **F-06: (foundation) prymitywy `ui/` mają warianty Spiżarni: `Button` (pigułka, kontur, naklejka), `Badge` (status z konturem, `draft` przerywany), `Alert`, `Card` (kafel), pola formularza (kontur 2px, promień 12px), `Dialog` (24px, cień, zamknięcie); każdy wariant ze stanami z §5.4 jest w kitchen sinku.** — Archived 2026-11-17 → `context/archive/2026-11-17-spizarnia-primitives/`. Lesson: —.
- **F-05: (foundation) `app.css` ma wartości Spiżarni z §10.2 w OKLCH (w tym nowe `sidebar*`, `highlight`, `overlay`), jawnie rozpisane promienie 12/20/24 px i pełne, dwa cienie „naklejki” i krój Bricolage Grotesque zamiast Figtree; PDF ma nowe kolory kategorii jako pełne bloki, a test palety pilnuje nowych hexów (D-12); kitchen sink pokazuje nowe kolory i typografię, a baseline'y są świadomie odświeżone.** — Archived 2026-11-17 → `context/archive/2026-11-17-spizarnia-tokens/`. Lesson: —.
- **S-20: Użytkownik może w szkicu połączyć kolację z danego dnia ze śniadaniem następnego dnia; śniadanie pokazuje danie z kolacji z oznaczeniem, a ilości i lista zakupów odpowiadają gotowaniu raz.** — Archived 2026-11-17 → `context/archive/2026-11-17-dinner-to-breakfast-carryover/`. Lesson: —.
- **S-19: Użytkownik może w szkicu ustawić, że obiad z danego dnia jest też obiadem następnego dnia (gotowany raz na dwa dni); obie komórki pokazują to samo danie z oznaczeniem powtórki, a ilości i lista zakupów odpowiadają gotowaniu raz.** — Archived 2026-11-17 → `context/archive/2026-11-16-meal-plan-repeat-dinner/`. Lesson: —.
- **S-18: Użytkownik może w szkicu oznaczyć wybrany posiłek jako pusty („nie robimy”); po zatwierdzeniu komórka jest pusta na ekranie i w PDF, a lista zakupów nie zawiera na nią składników. Generowanie nie wymaga już 7 przepisów na kategorię: gdy przepisów jest mniej, losowanie zostawia brakujące komórki puste w szkicu.** — Archived 2026-11-16 → `context/archive/2026-11-16-meal-plan-empty-slot/`. Lesson: —.
- **S-21: Użytkownik może przy „Pobierz PDF” wybrać, czy dołączyć przepisy; tabela jadłospisu nie ma już długiego tekstu przygotowania, a dołączone przepisy (składniki z ilościami i sposób wykonania) są na osobnych stronach, każdy raz, nawet gdy występuje w tygodniu kilka razy.** — Archived 2026-11-16 → `context/archive/2026-11-16-pdf-recipe-pages/`. Lesson: —.
- **S-17: Użytkownik może po kliknięciu „Generuj” zobaczyć wylosowany tydzień jako szkic (bez ilości i listy zakupów), podmienić przepis w dowolnej komórce na inny ze swojej listy i zatwierdzić szkic, po czym rusza skalowanie AI i plan przechodzi do dzisiejszego widoku z ilościami i listą zakupów.** — Archived 2026-11-16 → `context/archive/2026-11-16-meal-plan-draft-with-swap/`. Lesson: —.
- **S-15: Użytkownik może wejść na `/` i trafić do logowania lub do aplikacji zamiast domyślnej strony Laravela, a w aplikacji nie zostaje żaden komponent Breeze; `lint:tokens` obejmuje całe `resources/js` poza `Components/ui/` i blokuje CI przy nowym literale.** — Archived 2026-11-15 → `context/archive/2026-11-15-legacy-ui-cleanup/`. Lesson: —.
- **S-16: Użytkownik może po zalogowaniu zobaczyć na pulpicie prawdziwą liczbę swoich przepisów i jadłospisów oraz ostatni gotowy jadłospis z dostępem do PDF, a z tego samego miejsca dodać przepis albo wygenerować jadłospis; nowy użytkownik bez przepisów widzi stan pusty z pierwszym krokiem zamiast zer.** — Archived 2026-11-15 → `context/archive/2026-11-15-dashboard-ui/`. Lesson: —.
- **S-12: Użytkownik może dodawać, edytować i usuwać członków rodziny w nowym wyglądzie.** — Archived 2026-11-15 → `context/archive/2026-11-15-family-members-ui/`. Lesson: —.
- **S-09: Użytkownik może zobaczyć wygenerowany jadłospis jako siatkę tygodnia, w której każda komórka ma kreskę w kolorze kategorii i rozwijane składniki, w trakcie generowania widzi szkielet siatki, przy dopracowaniu bez AI — ostrzeżenie na tokenie `warning`, a obok listę zakupów z ilościami wyrównanymi do prawej i sformatowanymi po polsku.** — Archived 2026-11-15 → `context/archive/2026-11-15-meal-plan-show-ui/`. Lesson: —.
- **S-11: Użytkownik może przeglądać listę przepisów, oglądać szczegóły przepisu i dodawać lub edytować przepis ze składnikami w nowym wyglądzie.** — Archived 2026-11-15 → `context/archive/2026-11-15-recipes-ui/`. Lesson: —.
- **S-10: Użytkownik może przeglądać listę swoich jadłospisów, filtrować ją i otwierać dialog generowania w nowym wyglądzie, ze statusami na wspólnych wariantach.** — Archived 2026-11-15 → `context/archive/2026-11-15-meal-plans-index-ui/`. Lesson: —.
- **S-13: Użytkownik może logować się, rejestrować, resetować hasło i edytować profil na ekranach z tymi samymi przyciskami, polami i checkboxem „Zapamiętaj mnie” co reszta aplikacji.** — Archived 2026-11-15 → `context/archive/2026-11-15-auth-profile-ui/`. Lesson: —.
- **S-14: Użytkownik może pobrać PDF, w którym kategoria posiłku ma w tabeli to samo tło co na ekranie (z etykietą, czytelną też w druku czarno-białym), komunikaty mają ramkę ostrzeżenia, a hierarchia tekstu odpowiada rolom z §4.5.** — Archived 2026-11-15 → `context/archive/2026-11-15-pdf-design-alignment/`. Lesson: —.
- **S-08: Użytkownik może poruszać się po aplikacji w nawigacji bocznej i menu użytkownika opartych na tokenach marki (aktywny element wyróżniony `primary`/`accent`), a ekrany gościa mają ten sam wygląd i bieżący rok w stopce zamiast „© 2024”.** — Archived 2026-11-15 → `context/archive/2026-11-15-app-shell-restyle/`. Lesson: —.
- **S-07: Użytkownik może potwierdzać usuwanie w jednym, takim samym dialogu (fokus na „Anuluj”) w przepisach, jadłospisach i rodzinie, korzystać z tej samej paginacji i sortowania w listach przepisów i jadłospisów oraz rozpoznawać kategorie posiłków i statusy jadłospisu po nowych kolorach pory dnia, zawsze z etykietą.** — Archived 2026-11-15 → `context/archive/2026-11-15-shared-app-components/`. Lesson: —.
- **F-04: (foundation) strona kitchen sink — jedna strona pokazująca wszystkie tokeny, typografię i komponenty w ich stanach — działa pod `/dev/design-system` tylko w `local` i `testing`; spec Playwright robi jej zrzut i porównuje z zatwierdzonym wzorcem; `npm run lint:tokens` skanuje listę zmigrowanych plików pod kątem klas palety, hexów, wartości arbitralnych i `dark:` i działa jako krok CI. F-04 dopisuje też do reguły design systemu w `CLAUDE.md` punkt o kitchen sink i `lint:tokens`.** — Archived 2026-11-15 → `context/archive/2026-11-15-design-system-visual-gate/`. Lesson: —.
- **F-03: (foundation) `app.css` ma paletę bazową, tokeny stanów (`success`, `warning`, `info`) i domeny (`meal-breakfast`, `meal-supper`, `meal-dinner`) w OKLCH z §4.2–4.4, nowy promień i skalę z §4.5–4.6 oraz blok `.dark` z odpowiednikami; warstwa zgodności `gray-200` jest usunięta; sześć prymitywów z §5.1 jest dodanych przez CLI, a `badge` i `alert` mają warianty stanów i kategorii; `CLAUDE.md` zawiera regułę design systemu z §7.1.** — Archived 2026-11-15 → `context/archive/2026-11-15-design-tokens-foundation/`. Lesson: —.
- **F-01: (foundation) wyjątki z żądań HTTP i z zadań w kolejce trafiają do narzędzia śledzenia błędów z identyfikatorem użytkownika i jadłospisu; nieudana generacja nie znika po cichu.** — Archived 2026-11-13 → `context/archive/2026-11-13-error-tracking-baseline/`. Lesson: —.
- **S-03: Użytkownik może jednym kliknięciem wygenerować jadłospis 7 dni × 3 posiłki, w którym przepisy są losowane z jego własnych przepisów według kategorii bez powtórzeń w tygodniu, zobaczyć go w interfejsie i regenerować bez limitu.** — Archived 2026-11-13 → `context/archive/2026-11-13-real-weekly-meal-plan/`. Lesson: —.
- **S-02: Użytkownik może zapisać przepis tylko z nazwą, kategorią, co najmniej jednym składnikiem (nazwa, jednostka g/kg/ml/l/szt., ilość), sposobem przygotowania, kalorycznością i liczbą porcji, a potem przejrzeć, edytować i usunąć go z potwierdzeniem.** — Archived 2026-11-14 → `context/archive/2026-11-13-recipe-required-ingredients/`. Lesson: —.
- **S-05: Użytkownik może zobaczyć przy jadłospisie listę zakupów, w której składniki są zsumowane, ilości powyżej 1000 g i 1000 ml zamienione na kg i l, zaokrąglone do 2 miejsc i posortowane alfabetycznie.** — Archived 2026-11-14 → `context/archive/2026-11-14-shopping-list/`. Lesson: —.
- **S-04: Użytkownik może zobaczyć w wygenerowanym jadłospisie ilości składników przeliczone do swojej rodziny i zamienione przez AI na praktyczne ilości; gdy AI zawiedzie, nadal dostaje jadłospis z ilościami policzonymi bez AI i informację o tym.** — Archived 2026-11-14 → `context/archive/2026-11-14-ai-ingredient-scaling/`. Lesson: —.
- **S-06: Użytkownik może pobrać jeden PDF, który najpierw zawiera tabelę jadłospisu (dzień, kategoria, nazwa, składniki, przygotowanie), a potem listę zakupów, zgodny z ostatnią wygenerowaną wersją i otwierający się w przeglądarce.** — Archived 2026-11-14 → `context/archive/2026-11-14-meal-plan-pdf-export/`. Lesson: —.
- **S-01: Użytkownik może zarejestrować się, zalogować i zresetować hasło na polskich ekranach; sesja wygasa po 24 h braku aktywności, a link resetu (ważny 1 h) faktycznie dociera e-mailem.** — Archived 2026-11-14 → `context/archive/2026-11-14-polish-auth-with-24h-session/`. Lesson: —.
- **F-02: (foundation) aplikacja działa pod publicznym adresem na serwerze Mikrus z działającym workerem kolejki; konfigurację serwera i wdrożenia trzyma osobne repo `kostecki-infra`, a to repo dostarcza artefakt, którego ono oczekuje.** — Archived 2026-11-14 → `context/archive/2026-11-14-public-deploy-with-queue-worker/`. Lesson: —.
- **S-25: Użytkownik może przeglądać jadłospisy jako kafle tygodni z paskiem wypełnienia, statusem (pigułka z konturem, szkic przerywany) i filtrem statusu jako pigułki, a w jadłospisie widzi siatkę dni × posiłki z kolorowymi komórkami, w szkicu pigułkę „Zmień” i pusty posiłek jako przerywaną ramkę z „Wybierz”, listę zakupów jako kafel obok oraz dialogi generowania, podmiany i potwierdzenia w stylu Spiżarni.** — Archived 2026-11-17 → `context/archive/2026-11-17-spizarnia-meal-plans-ui/`. Lesson: —.
