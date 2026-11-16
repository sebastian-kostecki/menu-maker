---
project: menu-maker
version: 3
status: draft                    # draft | active | locked
created: 2026-11-13
updated: 2026-11-16
prd_version: —
main_goal: quality
top_blocker: decisions
milestone_id: editable-meal-plan-draft
milestone_seq: 3
milestone_status: open           # open | done
---

# Roadmap: Menu Maker

> Derived from the user's M-3 description (anchors MS-01…MS-06 below) + auto-researched codebase baseline; `.ai/core/prd.md` is context only.
> Edit-in-place; the next milestone continues this file. Archive only when an independent roadmap replaces it.
> Slices below are listed in dependency order. The "At a glance" table is the index.

## Milestone

**M-3: Jadłospis układany przed wygenerowaniem** — Status: open

- **Intent:** Użytkownik zanim uruchomi skalowanie AI, widzi wylosowany tydzień jako szkic i układa go po swojemu — podmienia przepisy, zostawia posiłki puste, planuje gotowanie raz na dwa dni — a PDF może zawierać pełne przepisy do gotowania.
- **Source materials:** opis użytkownika z 2026-11-16 (dosłownie poniżej), doprecyzowany w rozmowie tego samego dnia (MS-06: przepisy na osobnych stronach, dołączane przełącznikiem). `.ai/core/prd.md` służy tylko jako kontekst.
- **Opis użytkownika (dosłownie):** „najważniejszy na ten momement jest proces tworzenia jadlospisu - musi byc najpierw podglad przed wygenerowaniem; mozliwosc recznej zmiany przepisu; mozliwosc pozostawienia posilku pustego (np. nie robimy wybranego sniadania); mozliwosc zrobienia 2 takich samych obiadow dzien po dniu (zeby zrobic cos raz na 2 dni), albo polaczenia kolacja - sniadanie nastepnego dnia; ponadto w jadlospisie pdf musi byc mozliwosc dodania sposoby wykonania - samego przepisu”
- **Done when:** every F-NN and S-NN below is `done`.
- **Scope anchors:**
  - MS-01: Przed wygenerowaniem użytkownik widzi podgląd jadłospisu.
  - MS-02: Użytkownik może ręcznie zmienić przepis w posiłku.
  - MS-03: Użytkownik może zostawić posiłek pusty (np. nie robimy wybranego śniadania).
  - MS-04: Użytkownik może zaplanować dwa takie same obiady dzień po dniu, żeby gotować raz na dwa dni.
  - MS-05: Użytkownik może połączyć kolację ze śniadaniem następnego dnia.
  - MS-06: PDF jadłospisu może zawierać sposób wykonania — sam przepis: pełne przepisy na osobnych stronach, dołączane przełącznikiem przy pobieraniu.
- **Zmiany względem PRD:** MS-04 i MS-05 zastępują „Losowy dobór przepisów bez powtórzeń w obrębie tygodnia” (PRD §3 pkt 4) — powtórka jest świadomym wyborem użytkownika, losowanie nadal jej nie tworzy. MS-02 i MS-03 zastępują „Brak edycji wygenerowanego jadłospisu” (PRD §4) w zakresie szkicu przed wygenerowaniem. MS-06 zmienia układ PDF z PRD §3 pkt 7 (przepisy poza tabelą).
- **Numbering:** identyfikatory kontynuują numerację M-1 i M-2 (S-17…), żeby nie zderzyć się z wpisami w `## Done`.

## Vision recap

Menu Maker to narzędzie domowe: rodzic raz w tygodniu dodaje przepisy, generuje jadłospis 7 × 3 i drukuje PDF z listą zakupów. Po M-1 i M-2 generowanie działa i wygląda spójnie, ale jest „wszystko albo nic”: aplikacja losuje tydzień, od razu skaluje składniki przez AI i nie pozwala niczego zmienić. Ten kamień milowy wprowadza szkic — wylosowany, jeszcze nieprzeliczony tydzień, który użytkownik może przejrzeć i poprawić — żeby jadłospis odpowiadał temu, jak rodzina naprawdę gotuje: czasem bez śniadania, czasem jeden garnek na dwa dni, czasem kolacja, z której zostaje na rano.

## North star

**S-17: Użytkownik widzi wylosowany tydzień jako szkic, podmienia przepis w komórce i zatwierdza; dopiero wtedy rusza skalowanie AI** — przy celu „quality” to pierwszy przepływ, który sprawdza cały nowy cykl życia planu (szkic → edycja → generowanie) na prawdziwej edycji, a wszystkie pozostałe wycinki M-3 są kolejnymi edycjami tego samego szkicu.

> „North star” oznacza tu najmniejszy przepływ end-to-end, którego dostarczenie dowodzi, że ten kamień milowy działa — dlatego stoi tak wcześnie, jak pozwalają jego warunki wstępne: puste posiłki, powtórki i resztki mają sens tylko wtedy, gdy szkic działa.

## At a glance

| ID   | Change ID                  | Outcome (user can …)                                                                       | Prerequisites | PRD refs     | Status   |
| ---- | -------------------------- | ------------------------------------------------------------------------------------------ | ------------- | ------------ | -------- |
| S-17 | meal-plan-draft-with-swap  | zobaczyć szkic tygodnia, podmienić przepis w komórce i zatwierdzić, zanim ruszy skalowanie AI | —             | MS-01, MS-02 | done |
| S-18 | meal-plan-empty-slot       | zostawić wybrany posiłek pusty, bez składników na liście zakupów                           | S-17          | MS-03        | proposed |
| S-19 | meal-plan-repeat-dinner    | zaplanować ten sam obiad dwa dni z rzędu, gotowany raz                                     | S-17          | MS-04        | proposed |
| S-20 | dinner-to-breakfast-carryover | połączyć kolację ze śniadaniem następnego dnia                                          | S-19          | MS-05        | proposed |
| S-21 | pdf-recipe-pages           | pobrać PDF z pełnymi przepisami na osobnych stronach albo bez nich                         | —             | MS-06        | ready    |

## Streams

Navigation aid — groups items that share a Prerequisites chain. Canonical ordering still lives in the dependency graph below; this table is the proposed reading order across parallel tracks.

| Stream | Theme                    | Chain                    | Note                                                                                               |
| ------ | ------------------------ | ------------------------ | -------------------------------------------------------------------------------------------------- |
| A      | Szkic i edycje komórki   | `S-17` → `S-18`          | Cel „quality”: model szkicu dostaje pełne pokrycie na pierwszej edycji, zanim dojdą kolejne.        |
| B      | Gotowane raz, jedzone dwa razy | `S-19` → `S-20`    | Odgałęzia się od strumienia A przy `S-17`; jeden mechanizm (decyzja 2026-11-16), `S-20` go tylko rozszerza.          |
| C      | PDF z przepisami         | `S-21`                   | Niezależny od szkicu; może iść w osobnej sesji agenta równolegle do strumienia A.                  |

## Baseline

What's already in place in the codebase as of `2026-11-16` (auto-researched + user-confirmed).
Foundations below assume these are present and do NOT re-scaffold them.

- **Frontend:** present — Vue 3 + Inertia + shadcn-vue na tokenach z M-2; generowanie przez `GenerateMealPlanDialog` (tylko data startu), widok planu z odpytywaniem co 3 s i siatką `MealPlanGrid`; komórka nie ma akcji edycji, tylko rozwijane składniki.
- **Backend / API:** present — `POST meal-plans` tworzy plan `pending` i uruchamia `GenerateMealPlanJob`: losowanie (`MealPlanGenerator`, 7 różnych przepisów na kategorię), skalowanie (`MealIngredientScaler` + sterownik AI), status `done`; regeneracja tylko całego planu; brak podglądu, podmiany, powtórek i resztek.
- **Data:** partial wobec M-3 — `meals.recipe_id` jest NOT NULL (pusty posiłek niemożliwy), statusy planu to `pending`/`processing`/`done`/`error` (brak szkicu), lista zakupów liczona z `meal_ingredients`; walidacja wymaga 7 przepisów na kategorię.
- **Auth:** present — polityki zasobów, sesja 24 h; bez zmian w M-3.
- **Deploy / infra:** present — CI z Larastanem, Pestem, E2E i bramką wizualną; worker kolejki w produkcji.
- **Observability:** present — logi i alerty mailowe, `report()` w zadaniu generowania.
- **PDF:** present — dompdf na żądanie; tabela ma kolumnę „Przygotowanie” (40% szerokości) z pełnym tekstem przepisu, potem lista zakupów.

Poza roadmapą trwa zmiana `recipe-markdown-import` (import przepisów z Markdowna, `in-progress`); jej otwarty punkt — tylko 6 kolacji przy wymogu 7 na kategorię — dotyczy S-18.

## Foundations

Brak. Wszystko techniczne, czego wymaga M-3 (status szkicu, posiłek bez przepisu, powiązanie posiłków), wchodzi w pierwszy wycinek, który z tego korzysta — żaden z nich nie staje się niemożliwy do zaplanowania ani zweryfikowania bez wcześniejszego elementu bazowego.

## Slices

### S-17: Szkic tygodnia z podmianą przepisu (north star)

- **Outcome:** Użytkownik może po kliknięciu „Generuj” zobaczyć wylosowany tydzień jako szkic (bez ilości i listy zakupów), podmienić przepis w dowolnej komórce na inny ze swojej listy i zatwierdzić szkic, po czym rusza skalowanie AI i plan przechodzi do dzisiejszego widoku z ilościami i listą zakupów.
- **Change ID:** meal-plan-draft-with-swap
- **PRD refs:** MS-01, MS-02
- **Prerequisites:** —
- **Parallel with:** S-21
- **Blockers:** —
- **Unknowns:**
  - Rozstrzygnięte 2026-11-16: podmiana działa tylko w szkicu. Gotowy plan można cofnąć akcją „Wróć do szkicu” z zachowanymi edycjami; po ponownym zatwierdzeniu cały plan jest skalowany od nowa.
  - Czy obok „Wróć do szkicu” zostaje „Regeneruj” jako losowanie od nowa (gubi edycje)? — Owner: user. Block: no.
  - Czy w podmianie można wybrać tylko przepis z tej samej kategorii? — Owner: user. Block: no.
- **Risk:** Pierwszy, bo zmienia cykl życia planu (nowy stan przed `pending`, zatwierdzenie zamiast natychmiastowego zadania) i dotyka dialogu generowania, widoku planu, E2E i bazowych zrzutów; każdy kolejny wycinek tylko dokłada edycję komórki.
- **Status:** done

### S-18: Pusty posiłek

- **Outcome:** Użytkownik może w szkicu oznaczyć wybrany posiłek jako pusty („nie robimy”); po zatwierdzeniu komórka jest pusta na ekranie i w PDF, a lista zakupów nie zawiera na nią składników. Generowanie nie wymaga już 7 przepisów na kategorię: gdy przepisów jest mniej, losowanie zostawia brakujące komórki puste w szkicu.
- **Change ID:** meal-plan-empty-slot
- **PRD refs:** MS-03
- **Prerequisites:** S-17
- **Parallel with:** S-19, S-21
- **Blockers:** —
- **Unknowns:**
  - Rozstrzygnięte 2026-11-16: wymóg 7 przepisów na kategorię znika; braki zostają pustymi komórkami w szkicu (rozwiązuje też otwarty punkt `recipe-markdown-import`: 6 kolacji).
  - Czy przy zerze przepisów w kategorii generowanie nadal ma odmawiać (dziś komunikat z linkiem „Dodaj przepis”)? — Owner: user. Block: no.
- **Risk:** Wymaga posiłku bez przepisu, czyli zmiany założenia, na którym stoją siatka, skalowanie, lista zakupów i PDF; po S-17, żeby ta zmiana weszła w już działający szkic.
- **Status:** proposed

### S-19: Ten sam obiad dwa dni z rzędu

- **Outcome:** Użytkownik może w szkicu ustawić, że obiad z danego dnia jest też obiadem następnego dnia (gotowany raz na dwa dni); obie komórki pokazują to samo danie z oznaczeniem powtórki, a ilości i lista zakupów odpowiadają gotowaniu raz.
- **Change ID:** meal-plan-repeat-dinner
- **PRD refs:** MS-04
- **Prerequisites:** S-17
- **Parallel with:** S-18, S-21
- **Blockers:** —
- **Unknowns:**
  - Rozstrzygnięte 2026-11-16: pierwszy dzień ma składniki przeskalowane na oba posiłki, drugi pokazuje „z wczoraj” bez własnych składników; na liście zakupów danie występuje raz, w podwójnej ilości.
  - Rozstrzygnięte 2026-11-16: jeden mechanizm „gotowane raz, jedzone dwa razy” (posiłek wskazuje kolejny posiłek, który z niego korzysta), wspólny z S-20.
  - Co się dzieje z powiązanym dniem, gdy użytkownik podmieni przepis albo opróżni pierwszy posiłek? — Owner: user. Block: no (domyślnie powiązanie znika, drugi posiłek staje się pusty).
- **Risk:** Zmienia zasadę „jeden posiłek = jedno skalowanie” i sumowanie listy zakupów; wprowadza mechanizm powiązania, który S-20 rozszerza na inną kategorię, więc idzie przed nim.
- **Status:** proposed

### S-20: Kolacja na śniadanie następnego dnia

- **Outcome:** Użytkownik może w szkicu połączyć kolację z danego dnia ze śniadaniem następnego dnia; śniadanie pokazuje danie z kolacji z oznaczeniem, a ilości i lista zakupów odpowiadają gotowaniu raz.
- **Change ID:** dinner-to-breakfast-carryover
- **PRD refs:** MS-05
- **Prerequisites:** S-19
- **Parallel with:** S-18, S-21
- **Blockers:** —
- **Unknowns:**
  - Rozstrzygnięte 2026-11-16: ten sam mechanizm co S-19, ilości liczone tak samo (kolacja przeskalowana na oba posiłki, śniadanie bez własnych składników).
  - Jak śniadanie z przepisem kategorii „kolacja” ma wyglądać na ekranie i w PDF (kolor kategorii komórki czy przepisu)? — Owner: user. Block: no.
- **Risk:** Po S-19, bo rozszerza jego mechanizm powiązania o parę posiłków z różnych kategorii i dni; ryzykiem jest tylko prezentacja kategorii, nie ilości.
- **Status:** proposed

### S-21: PDF z przepisami na osobnych stronach

- **Outcome:** Użytkownik może przy „Pobierz PDF” wybrać, czy dołączyć przepisy; tabela jadłospisu nie ma już długiego tekstu przygotowania, a dołączone przepisy (składniki z ilościami i sposób wykonania) są na osobnych stronach, każdy raz, nawet gdy występuje w tygodniu kilka razy.
- **Change ID:** pdf-recipe-pages
- **PRD refs:** MS-06
- **Prerequisites:** —
- **Parallel with:** S-17, S-18, S-19, S-20
- **Blockers:** —
- **Unknowns:**
  - Które ilości ma pokazywać strona przepisu przy przepisie użytym kilka razy: przeskalowane z planu (zsumowane czy osobno na dzień) czy oryginalne z przepisu? — Owner: user. Block: no (domyślnie przeskalowane, osobno na każde użycie).
- **Risk:** Niezależny od szkicu, więc może iść od razu; zmienia układ PDF i jego testy, dlatego po S-18–S-20 ich wycinki muszą tylko dopisać swój stan komórki do tego samego szablonu.
- **Status:** ready

## Backlog Handoff

| Roadmap ID | Change ID                     | Suggested issue title                                         | Ready for `/10x-plan` | Notes |
| ---------- | ----------------------------- | ------------------------------------------------------------- | --------------------- | ----- |
| S-17       | meal-plan-draft-with-swap     | Szkic jadłospisu z podmianą przepisu i zatwierdzeniem         | yes                   | North star. Run `/10x-plan meal-plan-draft-with-swap` |
| S-18       | meal-plan-empty-slot          | Pusty posiłek w jadłospisie                                   | no                    | Czeka na S-17 |
| S-19       | meal-plan-repeat-dinner       | Ten sam obiad dwa dni z rzędu                                 | no                    | Czeka na S-17 |
| S-20       | dinner-to-breakfast-carryover | Kolacja na śniadanie następnego dnia                          | no                    | Czeka na S-19 |
| S-21       | pdf-recipe-pages              | PDF z przepisami na osobnych stronach i przełącznikiem        | yes                   | Run `/10x-plan pdf-recipe-pages`; równolegle do S-17 |

## Open Roadmap Questions

1. **Jak mierzyć metryki sukcesu z PRD §6** (90% z profilem rodziny w 7 dni, 75% generuje co tydzień)? Przeniesione z M-2: nic w aplikacji ich dziś nie zbiera i żadne wymaganie funkcjonalne tego nie przewiduje. — Owner: user. Block: roadmap-wide (poza zakresem M-3).
2. **Brak US-007 w PRD** — usunięta historyjka czy luka? Przeniesione z M-1. — Owner: user. Block: none.
3. **Czy `.ai/core/prd.md` zaktualizować o zmiany z karty M-3** (powtórki, edycja szkicu, przepisy w PDF)? Nowe w M-3. — Owner: user. Block: none.

## Parked

- **Edycja jadłospisu po wygenerowaniu (status `done`)** — Why parked: M-3 przenosi edycję do szkicu przed skalowaniem; edycja gotowego planu wymaga ponownego skalowania (S-17, Unknown nr 1).
- **Losowanie, które samo tworzy powtórki lub resztki** — Why parked: MS-04/MS-05 to świadomy wybór użytkownika w szkicu; losowanie nadal bez powtórzeń.
- **Dark mode** — Why parked: D-1 (MVP tylko w trybie jasnym); `design-system.md` §8 przewiduje go jako osobną zmianę, wyłącznie w tokenach i przełączniku.
- **Własna strona startowa pod `/`** — Why parked: D-7 (przekierowanie zamiast landing page; PRD jej nie przewiduje).
- **Ujednolicenie stron `FamilyMembers` do konwencji `Index/Show/Form.vue`** — Why parked: dług techniczny bez efektu dla użytkownika (przeniesione z M-2).
- **Import przepisów z URL** — Why parked: PRD §4.
- **Zdjęcia i filmy przepisów** — Why parked: PRD §4.
- **Udostępnianie przepisów, funkcje społecznościowe, kopie zapasowe** — Why parked: PRD §4.
- **Historia poprzednich wersji jadłospisu** — Why parked: US-009 kryterium 3 („Poprzednie wersje nie są zapisywane”).
- **Limit generacji PDF i czyszczenie starych plików PDF** — Why parked: `.ai/core/api-plan.md` §3, poza wymaganiami PRD.

## Milestone History

- **M-1: Od przepisów do PDF z listą zakupów** (`mvp-meal-plan-to-pdf`) — closed 2026-11-15. Zalogowana rodzina generuje tydzień 7×3 z własnych przepisów, ze składnikami skalowanymi przez AI, zsumowaną listą zakupów i jednym PDF-em; aplikacja z workerem kolejki działa publicznie, a błędy trafiają do śledzenia błędów.
- **M-2: Spójny wygląd aplikacji i PDF-u** (`consistent-design-system`) — closed 2026-11-16. Każdy widok aplikacji (powłoka, pulpit, jadłospisy, przepisy, rodzina, konto) i PDF korzysta z tokenów marki, kolorów pory dnia i wspólnych komponentów; kitchen sink, zrzuty Playwright i `lint:tokens` na całym `resources/js` pilnują tego w CI, a komponenty Breeze są usunięte.

## Done

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
