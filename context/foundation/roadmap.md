---
project: menu-maker
version: 1
status: draft                    # draft | active | locked
created: 2026-11-13
updated: 2026-11-13
prd_version: —
main_goal: quality
top_blocker: decisions
milestone_id: mvp-meal-plan-to-pdf
milestone_seq: 1
milestone_status: open           # open | done
---

# Roadmap: Menu Maker

> Derived from `.ai/core/prd.md` + auto-researched codebase baseline.
> Edit-in-place; the next milestone continues this file. Archive only when an independent roadmap replaces it.
> Slices below are listed in dependency order. The "At a glance" table is the index.

## Milestone

**M-1: Od przepisów do PDF z listą zakupów** — Status: open

- **Intent:** Rodzina zalogowanego użytkownika dostaje prawdziwy tygodniowy jadłospis z własnych przepisów, ze składnikami przeliczonymi do jej profilu, zsumowaną listą zakupów i jednym PDF-em — a błędy w tej ścieżce są widoczne, zanim zobaczy je użytkownik.
- **Source materials:** `.ai/core/prd.md` (bez numeru wersji). PRD numeruje wymagania funkcjonalne bez prefiksu; w tej mapie `FR-001`…`FR-008` oznaczają punkty 1–8 sekcji „3. Wymagania funkcjonalne”. Historyjki (`US-001`…`US-012`, bez `US-007`) są cytowane dosłownie.
- **Done when:** every F-NN and S-NN below is `done`.
- **Scope anchors:** FR-001…FR-008, US-001…US-006, US-008…US-012.

## Vision recap

Menu Maker pomaga rodzinom zaplanować posiłki na cały tydzień: użytkownik zapisuje własne przepisy, a aplikacja losuje z nich jadłospis 7 dni × 3 posiłki, przelicza składniki przez AI do liczby i profilu domowników i wydaje jeden PDF z jadłospisem i listą zakupów. Problem, który rozwiązuje, to czasochłonne ręczne planowanie, przeliczanie porcji i układanie listy zakupów, które kończy się nadmiarowymi zakupami.

## North star

**S-03: Użytkownik generuje prawdziwy tygodniowy jadłospis ze swoich przepisów** — pierwszy moment, w którym aplikacja robi to, po co istnieje; dziś przepisy, kolejka i widok już są, a brakuje tylko faktycznego doboru przepisów.

> „North star” oznacza tu najmniejszy przepływ end-to-end, którego dostarczenie dowodzi, że produkt działa — dlatego stoi tak wcześnie, jak pozwalają jego warunki wstępne: wszystko inne ma sens tylko wtedy, gdy to działa.

## At a glance

| ID   | Change ID                      | Outcome (user can …)                                                                        | Prerequisites | PRD refs                              | Status   |
| ---- | ------------------------------ | ------------------------------------------------------------------------------------------- | ------------- | ------------------------------------- | -------- |
| F-01 | error-tracking-baseline        | (foundation) błędy aplikacji i zadań w kolejce trafiają do śledzenia błędów z kontekstem   | —             | §6 (0 krytycznych błędów)             | done     |
| F-02 | public-deploy-with-queue-worker | (foundation) aplikacja z workerem kolejki działa publicznie na Mikrusie, wdrażana przez `kostecki-infra` | F-01 | §6 (wdrożenie publiczne)          | ready    |
| S-01 | polish-auth-with-24h-session   | zarejestrować się, zalogować i zresetować hasło po polsku; sesja trwa 24 h                  | —             | US-001, US-002, US-003, FR-001, FR-008 | ready    |
| S-02 | recipe-required-ingredients    | zapisać przepis tylko z kompletem pól, w tym składnikami w g/kg/ml/l/szt.                  | —             | US-005, US-006, FR-003, FR-008        | ready    |
| S-03 | real-weekly-meal-plan          | jednym kliknięciem wygenerować i regenerować tydzień 7×3 z własnych przepisów bez powtórzeń | F-01          | US-008, US-009, FR-004, FR-008        | ready    |
| S-04 | ai-ingredient-scaling          | zobaczyć składniki jadłospisu przeliczone do swojej rodziny i dopracowane przez AI         | S-03          | US-012, US-004, FR-005, FR-002        | proposed |
| S-05 | shopping-list                  | zobaczyć przy jadłospisie zsumowaną, posortowaną listę zakupów z konwersją jednostek        | S-02, S-03    | US-011, FR-006                        | proposed |
| S-06 | meal-plan-pdf-export           | pobrać jeden PDF: najpierw tabela jadłospisu, potem lista zakupów                           | S-05          | US-010, FR-007                        | proposed |

## Streams

Navigation aid — groups items that share a Prerequisites chain. Canonical ordering still lives in the dependency graph below; this table is the proposed reading order across parallel tracks.

| Stream | Theme                       | Chain                       | Note                                                                                       |
| ------ | --------------------------- | --------------------------- | ------------------------------------------------------------------------------------------ |
| A      | Generacja jadłospisu        | `F-01` → `S-03` → `S-04`    | Cel „quality”: generacja w kolejce rusza dopiero, gdy jej błędy są widoczne.               |
| B      | Lista zakupów i PDF         | `S-02` → `S-05` → `S-06`    | Dołącza do strumienia A przy `S-03` (lista potrzebuje wygenerowanego jadłospisu).          |
| C      | Wdrożenie publiczne         | `F-02`                      | Odgałęzia się od `F-01` w strumieniu A; konfiguracja serwera żyje w repo `kostecki-infra`. |
| D      | Konto                       | `S-01`                      | Niezależny; można go zrobić równolegle z czymkolwiek.                                      |

## Baseline

What's already in place in the codebase as of `2026-11-13` (auto-researched + user-confirmed).
Foundations below assume these are present and do NOT re-scaffold them.

- **Frontend:** present — Vue 3 + Inertia + shadcn-vue; strony `Recipes`, `FamilyMembers`, `MealPlans`, `Auth`; nawigacja w `resources/js/Components/Sidebar.vue`. `Dashboard.vue` pokazuje na sztywno wpisane liczby, a jego link `/meal-plans/create` nie ma trasy; przycisk „Generuj” w `MealPlans/Index.vue` nie wysyła POST.
- **Backend / API:** present — kontrolery zasobów z politykami i `authorizeResource` (Recipe, FamilyMember, MealPlan), trasy w `routes/web.php`. Generacja i regeneracja jadłospisu idą przez kolejkę, ale `GenerateMealPlanJob` to atrapa (nie dobiera przepisów, nie tworzy `Meal`). Brak klienta AI, listy zakupów i biblioteki PDF; `downloadPdf` serwuje tylko istniejący plik.
- **Data:** present — PostgreSQL, migracje wszystkich encji z `.ai/core/db-plan.md` (w tym `meal_plans.pdf_path`, `meals`, `logs_meal_plan`).
- **Auth:** partial — rejestracja z auto-logowaniem, logowanie, wylogowanie, reset hasła z linkiem ważnym 60 min. Rozbieżności z PRD: sesja 120 min zamiast 24 h, `MAIL_MAILER=log`, ekrany rejestracji i resetu po angielsku, brak `lang/pl/passwords.php`.
- **Deploy / infra:** partial — CI w GitHub Actions (build, Larastan, Pest, E2E) bez kroku wdrożenia; brak produkcyjnego obrazu; kolejka `database` bez workera poza skryptem deweloperskim.
- **Observability:** absent — domyślne logi Laravela, tylko `/up`; brak śledzenia błędów.

Stan funkcji: przepisy (US-005/006) prawie gotowe (lista składników jest opcjonalna, jednostka `pcs` zamiast `szt.`); profil rodziny (US-004) gotowy, ale brakuje testu `FamilyMemberPolicy` wymaganego przez reguły repozytorium.

## Foundations

### F-01: Bazowe śledzenie błędów

- **Outcome:** (foundation) wyjątki z żądań HTTP i z zadań w kolejce trafiają do narzędzia śledzenia błędów z identyfikatorem użytkownika i jadłospisu; nieudana generacja nie znika po cichu.
- **Change ID:** error-tracking-baseline
- **PRD refs:** §6 „0 krytycznych błędów w podstawowych ścieżkach”
- **Unlocks:** S-03 (generacja działa w kolejce, gdzie błąd nie dociera do użytkownika), S-04 (awarie wywołań AI), F-02 (produkcja bez śledzenia błędów jest ślepa)
- **Prerequisites:** —
- **Parallel with:** S-01, S-02
- **Blockers:** —
- **Unknowns:**
  - Które narzędzie śledzenia błędów (żadne nie jest wskazane w `.ai/core/tech-stack.md`)? — Owner: user. Block: no.
- **Risk:** Stoi przed generacją, bo cel to jakość, a generacja jest asynchroniczna; zakres ma pozostać minimalny (zgłaszanie błędów i kontekst), bez metryk i dashboardów.
- **Status:** done

### F-02: Publiczne wdrożenie z workerem kolejki

- **Outcome:** (foundation) aplikacja działa pod publicznym adresem na serwerze Mikrus z działającym workerem kolejki; konfigurację serwera i wdrożenia trzyma osobne repo `kostecki-infra`, a to repo dostarcza artefakt, którego ono oczekuje.
- **Change ID:** public-deploy-with-queue-worker
- **PRD refs:** §6 „MVP wdrożone i dostępne publicznie w ciągu 1 miesiąca” (rozstrzygnięte 2026-11-13: wdrożenie wchodzi w M-1 mimo §4; cel to Mikrus przez `kostecki-infra`, nie DigitalOcean z `.ai/core/tech-stack.md`)
- **Unlocks:** ścieżka weryfikacji „smoke test kluczowych ścieżek na produkcji”; pomiar metryk §6 na prawdziwych użytkownikach
- **Prerequisites:** F-01
- **Parallel with:** S-01, S-02, S-03, S-04, S-05, S-06
- **Blockers:** —
- **Unknowns:**
  - Jakiego artefaktu i kontraktu oczekuje `kostecki-infra` (obraz w rejestrze, plik compose, zmienne środowiskowe, wyzwalacz z CI)? Repo nie jest dostępne w tym workspace — podaj ścieżkę lub URL przy `/10x-plan`. — Owner: user. Block: no.
- **Risk:** Bez workera kolejki w produkcji żaden jadłospis się nie wygeneruje, więc wdrożenie musi obejmować worker od pierwszego dnia; Mikrus to mały VPS, więc pamięć workera i renderowania PDF trzeba sprawdzić na serwerze, nie lokalnie.
- **Status:** ready

## Slices

### S-01: Konto po polsku z 24-godzinną sesją

- **Outcome:** Użytkownik może zarejestrować się, zalogować i zresetować hasło na polskich ekranach; sesja wygasa po 24 h braku aktywności, a link resetu (ważny 1 h) faktycznie dociera e-mailem.
- **Change ID:** polish-auth-with-24h-session
- **PRD refs:** US-001, US-002, US-003, FR-001, FR-008
- **Prerequisites:** —
- **Parallel with:** F-01, F-02, S-02, S-03, S-04, S-05, S-06
- **Blockers:** —
- **Unknowns:**
  - Trasy mają middleware `verified`, a `User` nie implementuje weryfikacji e-mail; PRD chce auto-logowania po rejestracji. Czy weryfikacja e-mail ma zostać trwale wyłączona? — Owner: user. Block: no.
  - Którego dostawcy poczty użyć poza środowiskiem lokalnym? — Owner: user. Block: no (lokalnie wystarczy przechwytywanie poczty; wybór produkcyjny wiąże się z F-02).
- **Risk:** Mały wycinek domykający rozbieżności; testy wygaśnięcia sesji i linku resetu dziś nie istnieją i muszą powstać razem ze zmianą.
- **Status:** ready

### S-02: Przepis z kompletem wymaganych pól

- **Outcome:** Użytkownik może zapisać przepis tylko z nazwą, kategorią, co najmniej jednym składnikiem (nazwa, jednostka g/kg/ml/l/szt., ilość), sposobem przygotowania, kalorycznością i liczbą porcji, a potem przejrzeć, edytować i usunąć go z potwierdzeniem.
- **Change ID:** recipe-required-ingredients
- **PRD refs:** US-005, US-006, FR-003, FR-008
- **Prerequisites:** —
- **Parallel with:** F-01, F-02, S-01, S-03, S-04
- **Blockers:** —
- **Unknowns:**
  - Co z istniejącymi przepisami bez składników po zaostrzeniu walidacji — zostają, czy wymagają uzupełnienia przy edycji? — Owner: user. Block: no.
- **Risk:** Lista zakupów (S-05) i skalowanie (S-04) są bezużyteczne bez składników w spójnych jednostkach, więc ten wycinek musi wyprzedzić listę zakupów.
- **Status:** ready

### S-03: Prawdziwy tygodniowy jadłospis (north star)

- **Outcome:** Użytkownik może jednym kliknięciem wygenerować jadłospis 7 dni × 3 posiłki, w którym przepisy są losowane z jego własnych przepisów według kategorii bez powtórzeń w tygodniu, zobaczyć go w interfejsie i regenerować bez limitu.
- **Change ID:** real-weekly-meal-plan
- **PRD refs:** US-008, US-009, FR-004, FR-008
- **Prerequisites:** F-01
- **Parallel with:** F-02, S-01, S-02
- **Blockers:** —
- **Unknowns:**
  - Co pokazać, gdy w kategorii jest mniej niż 7 przepisów i reguła „bez powtórzeń” jest niewykonalna (blokada z komunikatem czy dopuszczenie powtórzeń)? — Owner: user. Block: no (rozstrzygnąć na początku `/10x-plan`).
- **Risk:** To wycinek, który dowodzi produktu; obejmuje też zepsute wejścia do generacji (przycisk bez POST, martwy link z dashboardu), więc test E2E musi przejść całą ścieżkę od kliknięcia do widoku tygodnia. Dobór przepisów ma się mieścić w <10 s (US-008; rozstrzygnięte 2026-11-13).
- **Status:** ready

### S-04: Składniki skalowane przez AI do rodziny

- **Outcome:** Użytkownik może zobaczyć w wygenerowanym jadłospisie ilości składników przeliczone do swojej rodziny i zamienione przez AI na praktyczne ilości; gdy AI zawiedzie, nadal dostaje jadłospis z ilościami policzonymi bez AI i informację o tym.
- **Change ID:** ai-ingredient-scaling
- **PRD refs:** US-012, US-004, FR-005, FR-002
- **Prerequisites:** S-03
- **Parallel with:** F-02, S-01, S-02, S-05, S-06
- **Blockers:** konto i klucz API dostawcy AI (`.ai/core/tech-stack.md`: OpenRouter)
- **Decisions (2026-11-13):**
  - Reguła skalowania: kod liczy zapotrzebowanie każdej osoby z tabeli norm (wiek + płeć), dzieli je na posiłki 30/40/30 (śniadanie/obiad/kolacja) i wyznacza współczynnik = kcal rodziny na posiłek / kcal przepisu; AI tylko zamienia wynik na praktyczne ilości (np. 2,37 jajka → 2 szt.).
  - Awaria AI: kilka ponowień, potem ilości policzone przez kod bez dopracowania AI, z informacją w UI; błąd trafia do śledzenia błędów (F-01).
  - Czas: pełna generacja z AI średnio <1 min (§6); sam dobór przepisów <10 s (US-008, zob. S-03).
- **Unknowns:**
  - Która tabela norm zapotrzebowania (np. normy żywienia dla populacji Polski, przy umiarkowanej aktywności)? — Owner: user. Block: no (`/10x-plan` proponuje, Ty zatwierdzasz).
- **Risk:** Najbardziej zawodna część produktu; stoi za generacją, żeby skalować działający jadłospis, a nie atrapę. Liczenie w kodzie czyni wynik testowalnym, a AI nie może go zepsuć. Przy okazji dopiąć brakujący test `FamilyMemberPolicy`, bo ten wycinek jako pierwszy konsumuje profil rodziny.
- **Status:** proposed

### S-05: Lista zakupów przy jadłospisie

- **Outcome:** Użytkownik może zobaczyć przy jadłospisie listę zakupów, w której składniki są zsumowane, ilości powyżej 1000 g i 1000 ml zamienione na kg i l, zaokrąglone do 2 miejsc i posortowane alfabetycznie.
- **Change ID:** shopping-list
- **PRD refs:** US-011, FR-006
- **Prerequisites:** S-02, S-03
- **Parallel with:** F-02, S-01, S-04
- **Blockers:** —
- **Unknowns:**
  - Jak sumować ten sam składnik zapisany w niewymienialnych jednostkach (np. `szt.` i `g`)? — Owner: user. Block: no.
- **Risk:** Celowo niezależny od S-04: lista liczy z ilości zapisanych w jadłospisie, więc działa na ilościach z przepisów teraz i na przeskalowanych, gdy S-04 wejdzie (US-012 kryterium 2). Reguły FR-6 są precyzyjne, więc testy jednostkowe zaokrągleń i konwersji są obowiązkowe.
- **Status:** proposed

### S-06: PDF z jadłospisem i listą zakupów

- **Outcome:** Użytkownik może pobrać jeden PDF, który najpierw zawiera tabelę jadłospisu (dzień, kategoria, nazwa, składniki, przygotowanie), a potem listę zakupów, zgodny z ostatnią wygenerowaną wersją i otwierający się w przeglądarce.
- **Change ID:** meal-plan-pdf-export
- **PRD refs:** US-010, FR-007
- **Prerequisites:** S-05
- **Parallel with:** F-02, S-01, S-04
- **Blockers:** —
- **Unknowns:** —
- **Risk:** PDF musi poprawnie renderować polskie znaki i być unieważniany przy regeneracji (US-009 nie zachowuje poprzednich wersji), inaczej użytkownik pobierze nieaktualny plik.
- **Status:** proposed

## Backlog Handoff

| Roadmap ID | Change ID                       | Suggested issue title                                         | Ready for `/10x-plan` | Notes |
| ---------- | ------------------------------- | ------------------------------------------------------------- | --------------------- | ----- |
| F-01       | error-tracking-baseline         | Śledzenie błędów dla żądań i zadań w kolejce                  | done                  | Zarchiwizowane 2026-11-13 |
| F-02       | public-deploy-with-queue-worker | Publiczne wdrożenie na Mikrusie z workerem kolejki           | yes                   | Przy planowaniu podaj repo `kostecki-infra`. Run `/10x-plan public-deploy-with-queue-worker` |
| S-01       | polish-auth-with-24h-session    | Konto po polsku: sesja 24 h, działający e-mail resetu         | yes                   | Run `/10x-plan polish-auth-with-24h-session` |
| S-02       | recipe-required-ingredients     | Przepis wymaga składników w jednostkach g/kg/ml/l/szt.        | yes                   | Run `/10x-plan recipe-required-ingredients` |
| S-03       | real-weekly-meal-plan           | Generacja prawdziwego jadłospisu 7×3 bez powtórzeń            | yes                   | North star. Run `/10x-plan real-weekly-meal-plan` |
| S-04       | ai-ingredient-scaling           | Skalowanie składników do profilu rodziny z dopracowaniem AI   | no                    | Po S-03; potrzebny klucz API OpenRouter; dopiąć test `FamilyMemberPolicy` |
| S-05       | shopping-list                   | Zsumowana lista zakupów z konwersją jednostek                 | no                    | Po S-02 i S-03 |
| S-06       | meal-plan-pdf-export            | Eksport jadłospisu i listy zakupów do jednego PDF             | no                    | Po S-05 |

## Open Roadmap Questions

1. **Jak mierzyć metryki sukcesu z §6** (90% z profilem rodziny w 7 dni, 75% generuje co tydzień)? Nic w aplikacji ich dziś nie zbiera i żadne wymaganie funkcjonalne tego nie przewiduje. — Owner: user. Block: roadmap-wide (nie blokuje żadnego wycinka, ale bez tego kamień milowy nie powie, czy produkt działa u ludzi).
2. **Brak US-007 w PRD** — usunięta historyjka czy luka? — Owner: user. Block: none.

## Parked

- **Import przepisów z URL** — Why parked: PRD §4.
- **Zdjęcia i filmy przepisów** — Why parked: PRD §4.
- **Udostępnianie przepisów, funkcje społecznościowe, kopie zapasowe** — Why parked: PRD §4.
- **Edycja wygenerowanego jadłospisu lub listy zakupów** — Why parked: PRD §4.
- **Historia poprzednich wersji jadłospisu** — Why parked: US-009 kryterium 3 („Poprzednie wersje nie są zapisywane”).
- **Dashboard z prawdziwymi statystykami** — Why parked: poza PRD; obecne na sztywno wpisane liczby w `Dashboard.vue` to dług UI do usunięcia lub podmiany, gdy ktoś będzie dotykał tego widoku.
- **Ujednolicenie stron `FamilyMembers` do konwencji `Index/Show/Form.vue`** — Why parked: dług techniczny bez efektu dla użytkownika.
- **Limit generacji PDF i czyszczenie starych plików PDF** — Why parked: `.ai/core/api-plan.md` §3, poza wymaganiami PRD.

## Milestone History

## Done

- **F-01: (foundation) wyjątki z żądań HTTP i z zadań w kolejce trafiają do narzędzia śledzenia błędów z identyfikatorem użytkownika i jadłospisu; nieudana generacja nie znika po cichu.** — Archived 2026-11-13 → `context/archive/2026-11-13-error-tracking-baseline/`. Lesson: —.
