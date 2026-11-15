---
project: menu-maker
version: 1
status: draft                    # draft | active | locked
created: 2026-11-14
updated: 2026-11-15
token_source: resources/css/app.css
primitives_dir: resources/js/Components/ui/
shared_dir: resources/js/Components/Common/   # potwierdzony, D-3
kitchen_sink: resources/js/Pages/Dev/DesignSystem.vue   # potwierdzony, D-4
---

# Design system: Menu Maker

> Wspólny kontrakt wizualny dla wszystkich widoków aplikacji i PDF-u.
> Edit-in-place: ten plik opisuje stan docelowy i kolejność dojścia do niego. Każdy krok rolloutu to osobna zmiana 10x (`/10x-new` → `/10x-research` → `/10x-plan` → `/10x-implement`), widoki przez `/10x-ui`.

## 1. Po co i dla kogo

Menu Maker to narzędzie domowe: rodzic raz w tygodniu dodaje przepisy, generuje jadłospis 7 × 3 i drukuje PDF z listą zakupów. Interfejs ma być:

- **ciepły i spokojny** — kuchnia, nie panel administracyjny; jeden kolor marki, reszta neutralna,
- **czytelny na pierwszy rzut oka** — kategoria posiłku i status generacji rozpoznawalne kolorem *i* tekstem,
- **gęsty tam, gdzie są dane** (siatka tygodnia, lista zakupów, tabela przepisów), przestronny w formularzach,
- **zgodny z PDF-em** — to, co użytkownik widzi na ekranie, rozpoznaje na wydruku,
- **WCAG AA** — kontrast ≥ 4.5:1 dla tekstu, widoczny focus, nazwy kontrolek po polsku.

## 2. Stan wyjściowy (audyt 2026-11-14)

| Obszar | Stan | Skutek |
| --- | --- | --- |
| Tokeny | `resources/css/app.css` ma pełny zestaw tokenów shadcn (`--primary`, `--muted`…), ale w odcieniach czystej szarości (`baseColor: neutral`). | Marka nie istnieje; `--primary` jest prawie czarny. |
| Użycie tokenów | **598** surowych klas palety Tailwinda w 63 plikach `.vue` (374 × `gray`, 109 × `red`, 33 × `blue`, 28 × `indigo`…) wobec **11** użyć tokenów semantycznych. Najgorsze: `Dashboard.vue` (45), `RecipeRow.vue` (34), `MealPlanGrid.vue` (34), `MealPlans/Show.vue` (26). | Zmiana koloru marki wymaga edycji kilkudziesięciu plików. |
| Dark mode | **280** klas `dark:` w widokach, ale nic nie ustawia klasy `.dark` (`@custom-variant dark (&:is(.dark *))`). | Martwy kod, który dubluje każdą klasę koloru i myli agentów. |
| Prymitywy | 15 komponentów shadcn-vue w `Components/ui/` obok 12 komponentów Breeze (`PrimaryButton`, `TextInput`, `Dropdown`, `Modal`…), wciąż importowanych przez 9 plików (Auth, Profile, `AuthenticatedLayout`). | Dwa systemy przycisków i pól; ekrany konta wyglądają inaczej niż reszta. |
| Duplikaty | 4 dialogi potwierdzenia (`MealPlans/ConfirmDialog`, `FamilyMembers/ConfirmDialog`, `recipes/ConfirmDeleteModal`, `recipes/DeleteConfirmationDialog`), 2 × `ServerPagination`, 2 × `SortableHeader` (identyczne 65 linii). | Ten sam wzorzec zachowuje się różnie w zależności od widoku. |
| Kolory domenowe | `CategoryBadge`: śniadanie żółte, obiad niebieski, kolacja zielona; `StatusTag`: szary/niebieski/zielony/czerwony — oba jako literały nadpisujące warianty `Badge`. | Kolory domeny nie mają nazwy, więc PDF i siatka ich nie współdzielą. |
| Ikony | `lucide-vue-next` + ręczne `<svg>` w 11 plikach. | Niespójne rozmiary i grubości kreski. |
| Wartości arbitralne | `w-[120px]`, `max-w-[425px]`, `rounded-[10px]`; `#FF2D20` i cienie w `Welcome.vue` (domyślna strona Laravela pod `/`). | Poza skalą; strona startowa ma branding Laravela. |
| PDF | `resources/views/pdf/meal-plan.blade.php` ma własne szarości w `<style>`. | Brak związku z ekranem. |

## 3. Architektura

Trzy warstwy; każda wyższa korzysta tylko z niższych.

```
Tokeny            resources/css/app.css            (:root zmienne → @theme inline → klasy Tailwinda)
  ↓
Prymitywy         resources/js/Components/ui/       (shadcn-vue: Button, Badge, Dialog, Table…)
  ↓
Komponenty app    resources/js/Components/Common/   (PageHeader, ConfirmDialog, EmptyState, FormField…)
  ↓
Komponenty domeny resources/js/Components/<Feature>/ (MealPlanGrid, ShoppingList, CategoryBadge…)
  ↓
Strony            resources/js/Pages/<Feature>/
```

Zasady:

1. **Widok nie zna palety Tailwinda.** W `Pages/` i `Components/<Feature>/` nie ma `gray-*`, `red-*`, hexów ani `dark:` — tylko tokeny (`bg-card`, `text-muted-foreground`, `bg-meal-breakfast`).
2. **Jeden prymityw na rolę.** Przycisk to `ui/button`, pole to `ui/input`, dialog potwierdzenia to `Common/ConfirmDialog`. Komponenty Breeze znikają.
3. **Wariant zamiast nadpisania.** Jeśli `Badge` potrzebuje koloru kategorii, dostaje wariant w `cva`, a nie `class="bg-yellow-100 …"` w miejscu użycia.
4. **Brakujący prymityw dodaje się CLI** (`vendor/bin/sail npx shadcn-vue@2 add <component>`), z przeglądem diffu; nigdy ręcznie pisany drugi `Button`.

## 4. Tokeny

### 4.1 Format

Przejście z `hsl(var(--x))` na pełne wartości OKLCH, zgodnie z obecnym szablonem shadcn-vue dla Tailwind 4:

```css
:root {
  --primary: oklch(0.50 0.12 150);
}
@theme inline {
  --color-primary: var(--primary);
}
```

Nazwy tokenów zostają te same, więc komponenty `ui/` nie wymagają zmian. Usunąć też warstwę zgodności `border-color: var(--color-gray-200, …)` — domyślną ramką staje się `--border`.

### 4.2 Paleta bazowa (light)

Kierunek: ciepłe, kremowe tło (papier, kuchnia), zieleń ziół jako jedyny kolor marki. Kontrast policzony dla wartości poniżej.

| Token | OKLCH | Hex (≈) | Rola | Kontrast |
| --- | --- | --- | --- | --- |
| `--background` | `0.985 0.008 85` | `#fdfaf4` | Tło aplikacji | — |
| `--foreground` | `0.24 0.02 60` | `#261d16` | Tekst główny | 15.8:1 na tle |
| `--card` / `--popover` | `1 0 0` | `#ffffff` | Karty, tabele, menu | — |
| `--muted` / `--secondary` | `0.955 0.012 85` | `#f4f0e7` | Nagłówki tabel, tła sekcji, przycisk drugorzędny | — |
| `--muted-foreground` | `0.50 0.02 60` | `#6c6158` | Metadane, opisy | 5.3:1 na `muted`, 6.0:1 na karcie |
| `--primary` | `0.50 0.12 150` | `#21763c` | CTA, linki, aktywna nawigacja, focus | 5.7:1 na karcie |
| `--primary-foreground` | `0.99 0.005 85` | `#fdfcf8` | Tekst na `primary` | 5.5:1 |
| `--accent` | `0.94 0.03 150` | `#def1e1` | Hover / aktywny element listy | — |
| `--accent-foreground` | `0.32 0.08 150` | `#093e1b` | Tekst na `accent` | 10.4:1 |
| `--destructive` | `0.55 0.20 27` | `#cc2827` | Usuwanie, błędy walidacji | 5.4:1 na karcie |
| `--destructive-foreground` | `0.99 0.005 85` | `#fdfcf8` | Tekst na `destructive` | 5.2:1 |
| `--border` / `--input` | `0.90 0.012 85` | `#e2ded5` | Ramki, separatory | — |
| `--ring` | = `--primary` | | Focus ring | — |

### 4.3 Tokeny stanów (nowe)

Pary tło/tekst do alertów, toastów i statusów. Każda para ≥ 6.4:1.

| Token | Tło (OKLCH) | Tekst (OKLCH) | Hex tło / tekst (≈) | Użycie |
| --- | --- | --- | --- | --- |
| `--success` / `--success-foreground` | `0.95 0.04 150` | `0.42 0.10 150` | `#dcf7e1` / `#195c2e` | Jadłospis gotowy, zapisano |
| `--warning` / `--warning-foreground` | `0.96 0.06 90` | `0.45 0.10 75` | `#fff1c4` / `#754b00` | AI zawiodło, ilości bez dopracowania (S-04) |
| `--info` / `--info-foreground` | `0.95 0.03 240` | `0.45 0.12 250` | `#ddf2ff` / `#0e5794` | Generowanie w toku |

### 4.4 Tokeny domeny (nowe)

Kategoria posiłku to najważniejszy kod kolorystyczny aplikacji (siatka tygodnia, lista przepisów, filtry, PDF). Kolory idą za porą dnia i nie kolidują z zielenią marki.

| Token | Tło (OKLCH) | Tekst (OKLCH) | Hex tło / tekst | Kategoria (`App\Enums\MealCategory`) |
| --- | --- | --- | --- | --- |
| `--meal-breakfast` | `0.95 0.06 85` | `0.45 0.10 70` | `#ffecc1` / `#784900` | `breakfast` — Śniadanie (miód, poranek) |
| `--meal-supper` | `0.94 0.045 40` | `0.47 0.14 35` | `#ffe2d5` / `#99351b` | `supper` — Obiad (pomidor, południe) |
| `--meal-dinner` | `0.94 0.035 280` | `0.45 0.12 280` | `#e6e9ff` / `#4a4b96` | `dinner` — Kolacja (wieczór) |

Statusy jadłospisu mapują się na tokeny stanów, nie na nowe kolory: `pending` → `muted`, `processing` → `info`, `done` → `success`, `error` → `destructive`.

Kolor nigdy nie jest jedynym nośnikiem informacji: badge zawsze ma etykietę, a siatka ma nagłówki kolumn.

Niektóre tokeny light z 4.3–4.4 (`--warning`, `--info`, `--meal-*` i `--warning-foreground`) leżą nieznacznie poza gamutem sRGB (przekroczenie kanału ≤ 0.08). Przeglądarki na ekranach sRGB je przycinają; kontrast po przycięciu nadal ≥ 5.9:1. PDF (S-14) ma używać wartości hex z przycięcia (kolumny „Hex” w 4.3 i 4.4), a nie przeliczać OKLCH na nowo.

### 4.5 Typografia

- Krój: **Figtree** (już ładowany z bunny.net, ma polskie znaki). Dodać wagę 700 tylko jeśli pojawi się potrzeba; dziś wystarczą 400/500/600.
- Skala ograniczona do pięciu ról:

| Rola | Klasy | Gdzie |
| --- | --- | --- |
| Tytuł strony | `text-2xl font-semibold tracking-tight` | `PageHeader` |
| Tytuł sekcji / karty | `text-lg font-semibold` | Karty, dialogi |
| Tekst | `text-sm` | Domyślny dla UI |
| Etykieta | `text-sm font-medium` | Etykiety pól, nagłówki tabel |
| Metadane | `text-xs text-muted-foreground` | kcal, porcje, daty |

- Liczby w tabelach i na liście zakupów: `tabular-nums`, wyrównane do prawej.
- Formatowanie liczb i dat po polsku (`Intl.NumberFormat('pl-PL')`: `1,25 kg`), `resources/js/lib/format.js` (`formatNumber`, `formatDate`, `pluralize`).

### 4.6 Kształt, odstępy, cienie

- `--radius: 0.625rem` (z 0.5rem) — trochę łagodniej; `rounded-lg` dla kart i dialogów, `rounded-md` dla pól i przycisków, `rounded-full` dla badge.
- Odstępy tylko ze skali Tailwinda. Rytm strony: `gap-6` między sekcjami, `gap-4` w kartach, `gap-2` w grupach kontrolek. Zero wartości arbitralnych (`w-[120px]` → `w-30`).
- Cienie: karty bez cienia (ramka `border`), `shadow-sm` dla menu i popoverów, `shadow-lg` tylko dla dialogów.
- Szerokość treści: `max-w-6xl` dla list i siatki, `max-w-2xl` dla formularzy.

### 4.7 Ikony i ruch

- Wyłącznie `lucide-vue-next`. Rozmiary: `size-4` w przyciskach i tekście, `size-5` w nawigacji, `size-10` w stanach pustych. Ikona bez etykiety wymaga `aria-label`.
- Ikony stałe dla domeny: przepis `ChefHat`, jadłospis `CalendarDays`, rodzina `Users`, lista zakupów `ShoppingBasket`, PDF `FileDown`, regeneracja `RefreshCw`.
- Animacje: `transition-colors` na interakcjach, `animate-pulse` dla skeletonów, `animate-spin` dla ikony generowania. Respektować `motion-reduce:`.

### 4.8 Dark mode

Decyzja D-1 (potwierdzona 2026-11-14): **MVP tylko w trybie jasnym.** Martwe klasy `dark:` usuwamy przy migracji każdego widoku; blok `.dark` w `app.css` zostaje z wartościami OKLCH odpowiadającymi palecie z 4.2, żeby dark mode był później zmianą wyłącznie w tokenach i przełączniku, bez dotykania widoków.

Wartości `.dark` (F-03, kontrasty tekst/tło ≥ 4.5:1):

| Token | `.dark` |
| --- | --- |
| `--background` | `oklch(0.20 0.012 60)` |
| `--foreground`, `--card-foreground`, `--popover-foreground`, `--secondary-foreground` | `oklch(0.95 0.01 85)` |
| `--card`, `--popover` | `oklch(0.235 0.012 60)` |
| `--muted`, `--secondary` | `oklch(0.28 0.012 60)` |
| `--muted-foreground` | `oklch(0.74 0.02 70)` |
| `--primary`, `--ring` | `oklch(0.72 0.14 150)` |
| `--primary-foreground` | `oklch(0.20 0.03 150)` |
| `--accent` / `--accent-foreground` | `oklch(0.32 0.05 150)` / `oklch(0.92 0.04 150)` |
| `--destructive` / `--destructive-foreground` | `oklch(0.65 0.19 25)` / `oklch(0.20 0.03 25)` |
| `--border`, `--input` | `oklch(0.32 0.012 60)` |
| `--success` / `-foreground` | `oklch(0.30 0.06 150)` / `oklch(0.86 0.10 150)` |
| `--warning` / `-foreground` | `oklch(0.32 0.06 85)` / `oklch(0.88 0.10 90)` |
| `--info` / `-foreground` | `oklch(0.30 0.06 250)` / `oklch(0.86 0.07 240)` |
| `--meal-breakfast` / `-foreground` | `oklch(0.32 0.06 75)` / `oklch(0.88 0.10 80)` |
| `--meal-supper` / `-foreground` | `oklch(0.32 0.07 35)` / `oklch(0.86 0.06 40)` |
| `--meal-dinner` / `-foreground` | `oklch(0.32 0.06 280)` / `oklch(0.86 0.06 280)` |

## 5. Komponenty

### 5.1 Prymitywy (`Components/ui/`)

| Status | Komponenty |
| --- | --- |
| Są | avatar, badge, button, card, combobox, dialog, dropdown-menu, input, label, select, separator, sheet, sonner, table, textarea |
| Dodać | `alert-dialog` (potwierdzenia), `alert` (komunikaty AI/PDF), `checkbox` (zamiast Breeze, „Zapamiętaj mnie”), `skeleton` (siatka w trakcie generowania), `collapsible` (składniki w komórce siatki zamiast `<details>`), `pagination` (baza dla `ServerPagination`) |
| Rozszerzyć | `badge`: warianty `breakfast`, `supper`, `dinner`, `success`, `warning`, `info`, `muted`; `alert`: warianty `warning`, `info`, `success` |

Dodanie przez CLI może podbić zależności (`reka-ui` itp.) — to zmiana zależności, wymaga akceptacji (D-5).

Nowe prymitywy pochodzą ze stylu new-york-v4, z importami znormalizowanymi do `@/lib/utils` i `lucide-vue-next` (D-9).

### 5.2 Komponenty aplikacji (`Components/Common/`)

| Komponent | Zastępuje | Kontrakt |
| --- | --- | --- |
| `PageHeader` | Ręczne nagłówki w każdej stronie i slot `#header` | **Zrealizowany w S-11 (`recipes-ui`)**. Wstawiany w slot `#header` `AuthenticatedLayout`. Props `title`, `description?`; sloty `breadcrumbs` (w `<nav aria-label="Ścieżka nawigacji">`), `description`, `actions`; jedno `<h1 class="text-2xl font-semibold tracking-tight">` na stronę, łamane przy długich tytułach |
| `ConfirmDialog` | 4 obecne dialogi | Na `AlertDialog`; `open` (`v-model:open`), `title`, `description`, `confirmLabel`, `variant: default \| destructive`, `processing`; emituje `confirm`; fokus na „Anuluj”; zostaje otwarty, dopóki `processing` |
| `ServerPagination` | `recipes/` i `MealPlans/` | Props `meta` (bez `links`); emituje `page-change`; `aria-label="Paginacja"` |
| `SortableHeader` | `recipes/` i `MealPlans/` | `column`, `sort`, `direction`; renderuje `TableHead`; `aria-sort`; emituje `sort-change({column, direction})` |
| `EmptyState` | Ad hoc puste tabele | **Zrealizowany w S-11 (`recipes-ui`)**. Props `icon?` (lucide), `title`, `description?`, `headingLevel?` (2 lub 3); slot `action` na CTA („Dodaj przepis”, „Wygeneruj jadłospis”); działa samodzielnie i w komórce tabeli |
| `FormField` | `InputLabel` + `InputError` | **Odroczony** do S-13. Label + kontrolka + błąd, `aria-describedby`, `aria-invalid` |
| `AppShell` (rewizja `Sidebar` + `AuthenticatedLayout`) | Breeze `Dropdown`, ręczny `<svg>` | Zrealizowany jako `Common/AppSidebar` i `Common/UserMenu`, z `AuthenticatedLayout` jako opakowaniem. Menu użytkownika na `ui/dropdown-menu`; nawigacja na tokenach `accent`/`primary`; nawigacja mobilna na `ui/sheet`; zwinięcie panelu zapamiętane w localStorage |

### 5.3 Komponenty domeny (po migracji)

- `CategoryBadge` — tylko `<Badge :variant="category">`, bez klas kolorów.
- `StatusTag` — `<Badge :variant>` wg mapowania z 4.4, z ikoną spinnera dla `processing`.
- `MealPlanGrid` — komórka z lewą kreską w kolorze kategorii (`border-l-4 border-meal-*`), składniki w `Collapsible`, skeleton z `ui/skeleton`.
- `ShoppingList` — `Card` z listą, ilości `tabular-nums` do prawej.
- `IngredientCard`, `RecipeMetaBadges` — tokeny + ikony lucide zamiast `<svg>`.

### 5.4 Stany, które każdy komponent pokazuje w kitchen sink

default · hover · focus-visible · disabled · loading · error · empty. Formularze dodatkowo: błąd walidacji z serwera. Siatka: `pending`/`processing` (skeleton), `done`, `error` z przyciskiem „Ponów”, komunikat ostrzeżenia AI.

## 6. PDF

`resources/views/pdf/meal-plan.blade.php` (dompdf) nie czyta zmiennych CSS z aplikacji. Kontrakt:

- PDF używa **hexów z tabel 4.2–4.4** jako lustra tokenów. Komentarz na początku `<style>` mówi „mirror of resources/css/app.css :root; keep in sync” i wylicza po jednej linii `--token  oklch(...)  #hex` (ten sam wzorzec co `resources/js/lib/mealCategories.js` ↔ `App\Enums\MealCategory`).
- Lustro obejmuje 12 tokenów: `--foreground`, `--muted`, `--muted-foreground`, `--border`, `--warning`, `--warning-foreground` oraz `--meal-breakfast`, `--meal-supper`, `--meal-dinner` wraz z ich `-foreground`. Żaden inny kolor nie jest dozwolony w szablonie, także w `style=""`.
- Papier jest biały (`--card`), nie `--background`: ciepłe tło ekranu nie trafia do druku.
- Kategoria w tabeli PDF: para `--meal-*` (tło i tekst) w komórce „Posiłek” — czytelne też w druku czarno-białym, bo etykieta zostaje.
- Początek dnia oznacza linia 2px w `--muted-foreground` (widoczna w skali szarości); pozostałe ramki to `--border`.
- Ramka komunikatów (`.notices`) na parze `--warning` / `--warning-foreground`.
- Font zostaje `DejaVu Sans` (polskie znaki w dompdf; tylko wagi normal i bold, więc „medium/semibold” z 4.5 to `bold`). Role z 4.5 w pt:

| Rola | Element | Rozmiar |
| --- | --- | --- |
| Tytuł strony | `h1` | 15pt bold |
| Tytuł sekcji | `h2` | 12pt bold |
| Tekst | body | 9pt |
| Etykieta | `th`, `.day` | 9pt bold |
| Metadane | `.meta`, `.note`, kategoria | 8pt (`.meta`, `.note` w `--muted-foreground`) |

- Lustro pilnuje `tests/Unit/Services/MealPlanPdfPaletteTest.php`: każdy token lustra ma w `app.css :root` tę samą wartość OKLCH, każdy hex jest przyciętą wartością swojego OKLCH, szablon nie używa hexa spoza listy ani `rgb()`/`hsl()`/`oklch()`/nazwanych kolorów, a żaden wpis nie jest nieużywany. Zmiana palety w `app.css` bez aktualizacji PDF-u zatrzymuje CI.

## 7. Egzekwowanie

1. **Reguła w `CLAUDE.md`** (sekcja „Structure & Conventions”), dopisana w pierwszej zmianie rolloutu:
   - tokeny w `resources/css/app.css`, opis w `context/foundation/design-system.md`;
   - przed nowym komponentem sprawdź `Components/ui/` i `Components/Common/`;
   - w widokach zakaz klas palety Tailwinda, hexów, wartości arbitralnych i `dark:`;
   - nowy wzorzec trafia do kitchen sink.
2. **Kitchen sink** — strona `Pages/Dev/DesignSystem.vue` pod `/dev/design-system`, rejestrowana tylko w środowiskach `local` i `testing` (D-4). Ma 13 sekcji (Kolory, Typografia, Kształt i cienie, Przyciski, Odznaki, Komunikaty, Formularze, Karta i tabela, Ładowanie i rozwijanie, Paginacja, Nakładki, Powłoka, Komponenty aplikacji) z komponentami z 5.1 i 5.2 w stanach z 5.4. Nie ma layoutu aplikacji i nie wymaga logowania.
3. **Bramka wizualna** — projekt `visual` w `playwright.config.ts`, uruchamiany przez `npm run test:visual` (`scripts/visual-test.sh`) w obrazie `mcr.microsoft.com/playwright:v<wersja>-noble` przypiętym do zainstalowanej wersji `@playwright/test`, w sieci `menu-maker_sail` (w CI `host`). Spec `tests/e2e/visual/design-system.spec.ts` robi 26 zrzutów `toHaveScreenshot`: 13 sekcji, `focus-visible` (na przycisku `outline`, bo pierścień przycisku domyślnego ma ten sam kolor co jego tło), hover i `focus-visible` pozycji nawigacji powłoki oraz 10 nakładek (dialog, alert dialog, `ConfirmDialog`, `ConfirmDialog` w toku, sheet, menu, menu użytkownika, select, combobox, toast). Tylko chromium, zero tolerancji (`threshold: 0`, `maxDiffPixels: 0` w projekcie `visual`), a spec sprawdza, że Figtree faktycznie się załadował. Baseline'y aktualizuje się świadomie, wyłącznie przez wrapper: `npm run test:visual -- --update-snapshots`, i przegląda każdy zmieniony PNG w diffie. Spec jest pomijany poza obrazem Dockera. Drugi spec, `tests/e2e/visual/app-shell.spec.ts`, robi 4 zrzuty prawdziwych stron (logowanie, pulpit desktop, pulpit mobile, szuflada mobile) ze stałym zegarem (`page.clock.setFixedTime`) i zalogowany jako zasiany użytkownik `e2e-planner-visual@example.com` (`E2eMealPlanSeeder`). Trzeci spec, `tests/e2e/visual/recipes.spec.ts`, robi 7 zrzutów widoków przepisów (lista desktop i mobile, lista bez wyników, szczegóły desktop i mobile, formularz tworzenia desktop i mobile); `E2eMealPlanSeeder` zasiewa dla tego użytkownika stałe 21 przepisów (po 7 na kategorię, stałe nazwy, porcje i instrukcje, `created_at` o 12:00 UTC w kolejnych dniach), żeby zrzuty były identyczne lokalnie i w CI.
4. **Skan literałów** — `scripts/lint-tokens.sh` (`npm run lint:tokens`, `grep -P`, bez nowych zależności) na liście z `scripts/lint-tokens.paths` (`*.vue`, `*.js`, `*.ts`; `Components/ui/` zawsze pominięte). Flaguje cztery rodzaje literałów: klasy palety Tailwinda (w tym `white`/`black`), hexy, wartości arbitralne i `dark:`. Kody wyjścia: 0 brak trafień, 1 trafienia, 2 błąd konfiguracji (brak pliku listy, pusta lista, nieistniejąca ścieżka). Skaner ma test własny, `scripts/lint-tokens.test.sh`. Oba kroki (test i skan) działają w CI w zadaniu `frontend-build` (D-6). Lista rośnie z każdą zmianą, na końcu obejmuje całe `resources/js` poza `Components/ui/`.

## 8. Rollout

Kolejność: **tokeny → wspólne komponenty → powłoka → widoki wg ruchu w głównym przepływie → sprzątanie.** Zmiana tokenów sama przemaluje komponenty `ui/` (przyciski staną się zielone), więc widoki można migrować pojedynczo bez okresu „pół na pół” w kolorze marki.

| # | Change ID | Zakres | Narzędzie | Prerequisites |
| --- | --- | --- | --- | --- |
| DS-01 / F-03 | `design-tokens-foundation` | Tokeny 4.1–4.6 w `app.css` (OKLCH, stany, domena, radius, `.dark`), usunięcie warstwy `gray-200`, prymitywy z 5.1, warianty `badge`/`alert`, reguła (3 punkty) w `CLAUDE.md` | `/10x-new` → `/10x-plan` | — |
| DS-01 / F-04 | `design-system-visual-gate` | Kitchen sink, bramka zrzutu Playwright, `lint:tokens`, punkt o kitchen sink w regule w `CLAUDE.md` | `/10x-new` → `/10x-plan` | F-03 |
| DS-02 | `shared-app-components` | `Components/Common/*` z 5.2 (`ConfirmDialog`, `ServerPagination`, `SortableHeader`; bez `AppShell`, `PageHeader`, `EmptyState` i `FormField`), migracja wywołań duplikatów (dialogi, paginacja, sortowanie), `CategoryBadge` i `StatusTag` na wariantach | `/10x-new` → `/10x-plan` | DS-01 |
| DS-03 | `app-shell-restyle` | `Sidebar`, `AuthenticatedLayout`, `GuestLayout` (+ „© 2024” → rok bieżący) | `/10x-ui` | DS-01 |
| DS-04 | `meal-plan-show-ui` | `MealPlans/Show`, `MealPlanGrid`, `ShoppingList` + używa `PageHeader`/`EmptyState` z DS-06 (`recipes-ui`) — rdzeń produktu, najwięcej literałów w głównym przepływie | `/10x-ui` | DS-02, DS-03 |
| DS-05 | `meal-plans-index-ui` | `MealPlans/Index`, `MealPlanTable/Row`, `FiltersBar`, `GenerateMealPlanDialog` + używa `PageHeader`/`EmptyState` z DS-06 (`recipes-ui`) | `/10x-ui` | DS-02 |
| DS-06 | `recipes-ui` | `Recipes/Index` (`RecipeRow`, 34 literały), `Show`, `Form`, `IngredientManager` + buduje `PageHeader`/`EmptyState` | `/10x-ui` (po jednym widoku, jeśli diff rośnie) | DS-02 |
| DS-07 | `family-members-ui` | `FamilyMembers/*` (przy okazji parked: `Create`/`Edit` → `Form.vue`, jeśli zmieści się w zakresie) | `/10x-ui` | DS-02 |
| DS-08 | `auth-profile-ui` | `Auth/*`, `Profile/*` — zejście z Breeze na `ui/*` + `FormField` | `/10x-ui` | DS-02, DS-03 |
| DS-09 | `pdf-design-alignment` | PDF wg sekcji 6 | `/10x-new` → `/10x-plan` | DS-01 |
| DS-10 | `legacy-ui-cleanup` | Usunięcie komponentów Breeze, `ApplicationLogo`, decyzja o `Welcome.vue` (D-7), `lint:tokens` na całe `resources/js` w CI | `/10x-new` → `/10x-plan` | DS-03…DS-08 |

Poza rolloutem: `Dashboard.vue` (45 literałów) jest w roadmapie w „Parked” — dostaje tokeny przy okazji DS-03, a pełny restyle dopiero gdy wycinek dashboardu zostanie odblokowany. Dark mode (D-1) — osobna zmiana po DS-10.

Testy E2E (`tests/e2e/`) opierają się na rolach i tekstach, nie na klasach — każda zmiana widoku uruchamia je bez modyfikacji; zmiana selektora to sygnał, że zmieniła się semantyka, nie wygląd.

## 9. Decyzje

| ID | Pytanie | Rekomendacja | Owner | Blokuje |
| --- | --- | --- | --- | --- |
| D-1 | Dark mode w MVP? | **Potwierdzone 2026-11-14:** nie; usuwać `dark:` przy migracji, tokeny `.dark` utrzymywać | user | — |
| D-2 | Kolor marki: zieleń ziół (`#21763c`) i ciepłe tło? | **Potwierdzone 2026-11-14:** tak — kuchnia, świeżość, nie koliduje z kolorami posiłków | user | — |
| D-3 | Nowy katalog `resources/js/Components/Common/` na komponenty aplikacji? | **Potwierdzone 2026-11-14:** tak (CLAUDE.md wymaga zgody na nowe foldery) | user | — |
| D-4 | Kitchen sink jako trasa `/dev/design-system` tylko w `local`/`testing`? | **Potwierdzone 2026-11-14:** tak — potrzebny bramce Playwright w CI | user | — |
| D-5 | Zgoda na zależności, które przyniesie `shadcn-vue add` dla 6 prymitywów? | **Potwierdzone 2026-11-14:** tak, po przeglądzie diffu `package.json` | user | — |
| D-6 | `lint:tokens` jako krok CI? | **Potwierdzone 2026-11-14:** tak, jako skrypt grep bez nowych zależności | user | — |
| D-7 | `Welcome.vue` (domyślna strona Laravela pod `/`): przekierowanie na `/login`/`/dashboard` czy własna strona startowa? | **Potwierdzone 2026-11-14:** przekierowanie — PRD nie przewiduje landing page | user | — |
| D-8 | Zmiana kolorów kategorii (śniadanie żółte → miodowe, obiad niebieski → pomidorowy, kolacja zielona → wieczorny indygo)? | **Potwierdzone 2026-11-14:** tak — zieleń zostaje marce, kolory idą za porą dnia | user | — |
| D-9 | Nowe prymitywy w new-york-v4 z importami znormalizowanymi do `@/lib/utils` i `lucide-vue-next`; regeneracja 15 starszych komponentów i przejście na `@lucide/vue` to osobna zmiana po F-04. | **Potwierdzone 2026-11-15** | user | — |
