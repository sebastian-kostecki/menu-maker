---
project: menu-maker
version: 1
status: draft                    # draft | active | locked
created: 2026-11-14
updated: 2026-11-17
token_source: resources/css/app.css
primitives_dir: resources/js/Components/ui/
shared_dir: resources/js/Components/Common/   # potwierdzony, D-3
kitchen_sink: resources/js/Pages/Dev/DesignSystem.vue   # potwierdzony, D-4
---

# Design system: Menu Maker

> Wspólny kontrakt wizualny dla wszystkich widoków aplikacji i PDF-u.
> Edit-in-place: ten plik opisuje stan docelowy i kolejność dojścia do niego. Każdy krok rolloutu to osobna zmiana 10x (`/10x-new` → `/10x-research` → `/10x-plan` → `/10x-implement`), widoki przez `/10x-ui`.
>
> **2026-11-17: wybrany nowy kierunek wyglądu, C · Spiżarnia (D-10).** Sekcje 4–6 opisują stan wdrożony w M-2 (zieleń ziół, Figtree) i obowiązują, dopóki zmiany z §10.6 ich nie zastąpią. Cel i kolejność wdrożenia są w §10.
>
> **2026-11-17: SP-01 (`spizarnia-tokens`) jest zrobiony.** Tokeny w `app.css`, font i lustro PDF mają już wartości Spiżarni (§10.2–10.3), więc §4.2–4.6 opisują poprzedni stan (Zielnik). Obowiązującą mapę tokenów na klasy Tailwinda opisują §10.2 i §10.3.
>
> **2026-11-17: SP-02 (`spizarnia-primitives`) jest zrobiony.** Prymitywy `ui/` mają już kształt Spiżarni (pigułka z konturem 2px, naklejka, kafel 20px, pola 48px, dialog 24px), dochodzą wzorce `dashed` i `toggle` (pigułka filtra), a kontrakt stanów opisuje §10.4. Warianty i klasy opisuje §5.1; opisy komponentów w §4–6 sprzed SP-02 (np. 1px kontury, `rounded-md`) są nieaktualne.

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
| Duplikaty | 4 dialogi potwierdzenia (`MealPlans/ConfirmDialog`, `FamilyMembers/FamilyMemberDeleteDialog`, `recipes/ConfirmDeleteModal`, `recipes/DeleteConfirmationDialog`), 2 × `ServerPagination` (+ `FamilyMembers/FamilyMemberPagination`), 2 × `SortableHeader` (identyczne 65 linii). | Ten sam wzorzec zachowuje się różnie w zależności od widoku. |
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
- Formatowanie liczb i dat po polsku (`Intl.NumberFormat('pl-PL')`: `1,25 kg`), `resources/js/lib/format.js` (`formatNumber`, `formatDate`, `formatDateRange`, `formatDateTime`, `pluralize(count, { one, few, many })`).

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

> Wartości `.dark` poniżej są historyczne (Zielnik): blok `.dark` został usunięty z `app.css` w SP-01 (D-14). Ciemna Spiżarnia to osobna zmiana po M-4.

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
| Są | alert, alert-dialog, avatar, badge, button, card, checkbox, collapsible, combobox, dialog, dropdown-menu, input, label, pagination, select, separator, sheet, skeleton, sonner, table, textarea, toggle |
| Rozszerzyć | `badge`: warianty `breakfast`, `supper`, `dinner`, `success`, `warning`, `info`, `muted`; `alert`: warianty `warning`, `info`, `success` |

`dropdown-menu`: pozycja (`DropdownMenuItem`) ma wariant `variant: default \| destructive` (S-10, port z new-york-v4; styl na tokenach `destructive` przez `data-[variant=destructive]:`). Akcje destrukcyjne używają wariantu, nie nadpisania koloru w miejscu użycia.

Kształt prymitywów po SP-02 (klasy w `Components/ui/`; widoki nie nadpisują kształtu ani koloru):

| Prymityw | Kontrakt |
| --- | --- |
| `button` | Pigułka z konturem 2px `foreground`, `text-label`. Rozmiary: `default` 44px, `xs` 28px, `sm` 32px, `lg` 52px, `icon` 34px (okrągły). Warianty: `default` (atrament), `destructive`, `outline` (`card` + `shadow-sticker`), `secondary` (okrągły przycisk ikonowy na `muted`, bez konturu), `ghost`, `link`, `dashed` (przerywany kontur, `rounded-md`, przezroczyste tło: „Dodaj…”) |
| `toggle` | Pigułka filtra (`reka-ui` `Toggle`: `aria-pressed`, `data-state=on\|off`): 44px (`size="sm"` 36px), `card` z konturem 2px, wybrana na atramencie. Tony kategorii `variant="breakfast" \| "supper" \| "dinner"` (wypełnienie `meal-*`, tekst `meal-*-foreground`); liczba w `<span>` po etykiecie |
| `badge` | `size="status"` to pigułka statusu: 32px, kontur 2px `foreground`, `text-meta` 700; `variant="draft"` ma kontur przerywany na `card`. Pozostałe warianty (kategorie, `success`, `warning`, `info`, `muted`) bez konturu |
| `card` | Kafel: `rounded-lg` (20px), kontur 2px `foreground`, `shadow-sticker` |
| `input`, `textarea`, `select` (trigger), `combobox` (input) | `rounded-md` (12px), kontur 2px `foreground`, `card`, `text-body`; `input`/`select`/`combobox` mają 48px, `textarea` min. 132px; błąd to `aria-invalid` (kontur `destructive`) |
| `checkbox` | 22px, `rounded-sm`, kontur 2px, wybrany na atramencie |
| `alert` | `rounded-md`, kontur 2px `foreground`; `destructive` to `card` z konturem i tekstem `destructive`; `warning`/`info`/`success` na swoich wypełnieniach |
| Powierzchnie wyskakujące (`select`, `dropdown-menu`, `combobox`) | `rounded-md`, kontur 2px, `bg-popover`, `shadow-sticker` |
| `dialog`, `alert-dialog` | Powłoka `card`, kontur 2px, `rounded-xl` (24px), `shadow-dialog`, padding 26px, tło `bg-overlay`; tytuł na `text-dialog-title`; `DialogContent` ma wbudowany okrągły przycisk zamknięcia (`secondary` `icon`, sr-only „Zamknij”) |
| `avatar` | `variant="sticker"`: `meal-breakfast` z `meal-breakfast-foreground`, kontur 2px `foreground`, waga 700 (znacznik użytkownika w pasku górnym); `default` zostaje na `bg-secondary`; oba warianty obok siebie w sekcji „Ładowanie i rozwijanie” kitchen sinku |
| `sheet` | Tło `bg-overlay`; okrągły przycisk „Zamknij” (`secondary`, `icon`) jak w dialogu; krawędź `border-2 border-foreground` tylko od strony treści; `SheetHeader` to `pr-12 text-left` (treść nie wchodzi pod przycisk zamknięcia), a układ szuflady powłoki definiuje SP-03 |
| `pagination` | Pigułki 44px (`min-w-11`) na `button`: bieżąca strona to wariant `default`, pozostałe `outline` bez cienia i bez ruchu wciskania |

Dodanie przez CLI może podbić zależności (`reka-ui` itp.) — to zmiana zależności, wymaga akceptacji (D-5).

Nowe prymitywy pochodzą ze stylu new-york-v4, z importami znormalizowanymi do `@/lib/utils` i `lucide-vue-next` (D-9).

### 5.2 Komponenty aplikacji (`Components/Common/`)

| Komponent | Zastępuje | Kontrakt |
| --- | --- | --- |
| `PageHeader` | Ręczne nagłówki w każdej stronie | Zbudowany równolegle w S-10 i S-11 i scalony przy merge'u S-11. Props `title`, `description?`, `as?: 'h1' \| 'h2'` (domyślnie `h1`; `h2` podaje tylko kitchen sink, by miał jedno `<h1>`; dodane w S-09); sloty `breadcrumbs` (w `<nav aria-label="Ścieżka nawigacji">`), `description` (zastępuje prop, może zawierać odznaki), `actions`; korzeń `<header>` z `min-w-0`, jedno `<h1 class="text-2xl font-semibold tracking-tight">` na stronę, łamane przy długich tytułach. Umiejscowienie jest jedno: `PageHeader` to pierwszy element treści strony, wewnątrz jej kontenera szerokości; pasek górny `AuthenticatedLayout` zawiera tylko nawigację i menu użytkownika, więc „Menu Maker” w pasku nie jest `<h1>` i strona ma jedno `<h1>` |
| `ConfirmDialog` | 4 obecne dialogi | Na `AlertDialog`; `open` (`v-model:open`), `title`, `description`, `confirmLabel`, `variant: default \| destructive`, `processing`; emituje `confirm`; opcjonalny slot domyślny między opisem a stopką (np. pole hasła); fokus na „Anuluj”; zostaje otwarty, dopóki `processing` |
| `ServerPagination` | `recipes/` i `MealPlans/` | Props `meta` (bez `links`); emituje `page-change`; `aria-label="Paginacja"` |
| `SortableHeader` | `recipes/` i `MealPlans/` | `column`, `sort`, `direction`; renderuje `TableHead`; `aria-sort`; emituje `sort-change({column, direction})` |
| `EmptyState` | Ad hoc puste tabele | Zbudowany równolegle w S-10 i S-11 i scalony. Props `icon?` (komponent lucide, `aria-hidden`), `title`, `description?`, `headingLevel?` (2 lub 3, domyślnie 2); CTA w slocie domyślnym („Dodaj przepis”, „Wygeneruj jadłospis”). Bez własnego odstępu pionowego: samodzielny pusty stan dostaje `class="py-12"` od wywołującego, w tabeli siedzi w `TableEmpty` |
| `FormField` | `InputLabel` + `InputError` | Props `id` (id kontrolki), `label`, `error?`, `description?`; etykieta `for=id`, kontrolka w slocie domyślnym ze slot props `{ id, invalid, describedBy }` (wywołanie wiąże `:id`, `:aria-invalid="invalid || undefined"`, `:aria-describedby="describedBy"`), opis `<id>-description` i błąd `<id>-error` |
| `AppBrand` `tone="inverse"` | Znak i nazwa na `highlight` | Znak na `card` z ikoną `foreground` zamiast atramentowego `primary`; używany w panelu `GuestLayout` i w kitchen sinku („Powłoka”) na tle `bg-highlight text-highlight-foreground` |
| `TextLink` | Powtarzane długie klasy linków w stronach Auth | Opakowuje Inertia `Link`, przekazuje wszystkie atrybuty; `rounded-sm font-semibold underline underline-offset-3 hover:text-muted-foreground`, focus: `outline-2 outline-ring outline-offset-2` (kontrakt §10.4); ikona (np. `ArrowLeft`) w slocie przed tekstem |
| `AppShell` (rewizja `Sidebar` + `AuthenticatedLayout`) | Breeze `Dropdown`, ręczny `<svg>` | Zrealizowany jako `Common/AppBrand` (tony `default` i `inverse`) + `Common/MainNav` (orientacja `horizontal` lub `vertical`) + `Common/UserMenu` (trigger to awatar), złożone w `AuthenticatedLayout` jako przyklejony pasek górny na `bg-sidebar`, z szufladą na `ui/sheet` poniżej `lg`. Bez zwijania i bez localStorage; `Common/AppSidebar` usunięty. `AuthenticatedLayout` nie ma slotu `header`, a jego pasek górny zawiera tylko nawigację i menu użytkownika |

### 5.3 Komponenty domeny (po migracji)

- `CategoryBadge` — tylko `<Badge :variant="category">`, bez klas kolorów.
- `StatusTag` — `<Badge :variant size="status">` wg mapowania z 4.4 (pigułka statusu z konturem; `draft` przerywany na `card`), z ikoną spinnera dla `processing`.
- `MealPlanGrid` — komórka z lewą kreską w kolorze kategorii (`border-l-4 border-meal-*`; od SP-01 wypełnienia Spiżarni są nasycone i czytelne jako 4-pikselowa linia, a prawie czarne `meal-*-foreground` zlewałyby trzy kategorie w jedną kreskę), składniki w `Collapsible`, skeleton z `ui/skeleton`.
- `ShoppingList` — `Card` z listą, ilości `tabular-nums` do prawej. Od `xl` stoi obok siatki (`xl:w-72`), poniżej `xl` pod nią.
- `IngredientCard`, `RecipeMetaBadges` — tokeny + ikony lucide zamiast `<svg>`.

### 5.4 Stany, które każdy komponent pokazuje w kitchen sink

default · hover · focus-visible · disabled · loading · error · empty. Formularze dodatkowo: błąd walidacji z serwera. Siatka: `pending`/`processing` (skeleton), `done`, `error` z przyciskiem „Ponów”, komunikat ostrzeżenia AI.

## 6. PDF

`resources/views/pdf/meal-plan.blade.php` (dompdf) nie czyta zmiennych CSS z aplikacji. Kontrakt:

- PDF używa **hexów z tabeli w §10.2** (od SP-01; wcześniej z 4.2–4.4) jako lustra tokenów. Komentarz na początku `<style>` mówi „mirror of resources/css/app.css :root; keep in sync” i wylicza po jednej linii `--token  oklch(...)  #hex` (ten sam wzorzec co `resources/js/lib/mealCategories.js` ↔ `App\Enums\MealCategory`).
- Lustro obejmuje 12 tokenów: `--foreground`, `--muted`, `--muted-foreground`, `--border`, `--warning`, `--warning-foreground` oraz `--meal-breakfast`, `--meal-supper`, `--meal-dinner` wraz z ich `-foreground`. Żaden inny kolor nie jest dozwolony w szablonie, także w `style=""`.
- Papier jest biały (`--card`), nie `--background`: ciepłe tło ekranu nie trafia do druku.
- Kategoria w tabeli PDF: para `--meal-*` (tło i tekst) w komórce „Posiłek” — czytelne też w druku czarno-białym, bo etykieta zostaje.
- Strony przepisów (wariant „z przepisami”) idą po liście zakupów, po jednej na przepis, każda od nowej strony; etykieta kategorii to komórka `td.category.meal-*` z parą `--meal-*`, a bloki „Składniki” i „Przygotowanie” to zwykłe bloki (nie wiersz tabeli), z etykietą 9pt bold (`<strong>`, jak „Etykieta”).
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
2. **Kitchen sink** — strona `Pages/Dev/DesignSystem.vue` pod `/dev/design-system`, rejestrowana tylko w środowiskach `local` i `testing` (D-4). Ma 14 sekcji (Kolory, Typografia, Kształt i cienie, Przyciski, Odznaki, Komunikaty, Formularze, Karta i tabela, Ładowanie i rozwijanie, Paginacja, Nakładki, Powłoka, Komponenty aplikacji, Jadłospis) z komponentami z 5.1 i 5.2 w stanach z 5.4. Nie ma layoutu aplikacji i nie wymaga logowania.
3. **Bramka wizualna** — projekt `visual` w `playwright.config.ts`, uruchamiany przez `npm run test:visual` (`scripts/visual-test.sh`) w obrazie `mcr.microsoft.com/playwright:v<wersja>-noble` przypiętym do zainstalowanej wersji `@playwright/test`, w sieci `menu-maker_sail` (w CI `host`). Spec `tests/e2e/visual/design-system.spec.ts` robi 33 zrzuty `toHaveScreenshot`: 14 sekcji, `focus-visible` (na przycisku `outline`), cztery zrzuty stanów z SP-02 (`button-hover` i `button-pressed` na przycisku `outline`, `input-focus-visible` na `#ds-input-default`, `chip-focus-visible` na pierwszej pigułce filtra), hover i `focus-visible` pozycji nawigacji w pasku górnym (sekcja „Powłoka”: pasek górny i nawigacja szuflady) oraz 12 nakładek (dialog, alert dialog, `ConfirmDialog`, `ConfirmDialog` w toku, `ConfirmDialog` z polem, sheet, menu, menu pobierania PDF, menu użytkownika, select, combobox, toast). Tylko chromium, zero tolerancji na liczbę różniących się pikseli (`maxDiffPixels: 0` w projekcie `visual`) przy minimalnym progu koloru `threshold: 0.005`: przepuszcza on wyłącznie szum ±1 poziomu RGB na wygładzanych krawędziach, który ten sam obraz Dockera daje na różnych procesorach (runner GitHuba vs WSL), a różnicę od ok. 2 poziomów jasności już odrzuca, a spec sprawdza (`expectAppFontLoaded` w `tests/e2e/visual/support/fonts.ts`), że Bricolage Grotesque faktycznie się załadował, w wagach 400, 500, 600, 700 i 800 (każda dokładnie w swojej wadze, razem z podzbiorem `latin-ext` z polskimi znakami). Baseline'y aktualizuje się świadomie, wyłącznie przez wrapper: `npm run test:visual -- --update-snapshots`, i przegląda każdy zmieniony PNG w diffie. Zmiana wysokości sekcji kitchen sinku przesuwa sekcje pod nią, a to może odświeżyć ich zrzuty szumem wygładzania krawędzi (te same wymiary, kilkaset pikseli na zaokrągleniach, bez przesunięcia treści); taki zrzut też się przegląda, zanim się go przyjmie. Spec jest pomijany poza obrazem Dockera. Drugi spec, `tests/e2e/visual/app-shell.spec.ts`, robi 6 zrzutów prawdziwych stron (logowanie, logowanie mobile, pulpit desktop, pusty pulpit desktop, pulpit mobile, szuflada mobile) ze stałym zegarem (`page.clock.setFixedTime`) i zalogowany jako zasiany użytkownik `e2e-planner-visual@example.com` (`E2eMealPlanSeeder`); pusty pulpit loguje jako `e2e-dashboard-empty-visual@example.com`, którego seeder zostawia bez przepisów, jadłospisów i członków rodziny. Trzeci spec, `tests/e2e/visual/account.spec.ts`, robi 3 zrzuty ekranów konta (`guest-register`, `guest-forgot-password`, `profile-desktop`) w tej samej konfiguracji; logowanie współdzielą specy przez `tests/e2e/visual/support/auth.ts`. Czwarty spec, `tests/e2e/visual/meal-plans-index.spec.ts` (S-10), robi 4 zrzuty listy jadłospisów na tych samych zasadach (desktop, mobile, pusty stan po filtrze „Oczekuje”, otwarty dialog generowania), na czterech zasianych jadłospisach tego użytkownika (2 gotowe, 1 w trakcie generowania, 1 z błędem; stałe daty i znaczniki czasu). Piąty spec, `tests/e2e/visual/recipes.spec.ts` (S-11), robi 7 zrzutów widoków przepisów (lista desktop i mobile, lista bez wyników, szczegóły desktop i mobile, formularz tworzenia desktop i mobile); `E2eMealPlanSeeder` zasiewa dla tego użytkownika stałe 21 przepisów (po 7 na kategorię, stałe nazwy, porcje i instrukcje, `created_at` i `updated_at` o 12:00 UTC w kolejnych dniach), żeby zrzuty były identyczne lokalnie i w CI. Szósty spec, `tests/e2e/visual/meal-plan-show.spec.ts` (S-09), robi 5 zrzutów prawdziwej strony jadłospisu (`meal-plan-show-desktop.png` z rozwiniętymi składnikami pierwszej komórki, `meal-plan-show-mobile.png` oraz szkic: `meal-plan-draft-desktop.png`, `meal-plan-draft-mobile.png` i `meal-plan-draft-swap-dialog.png` z dialogiem podmiany przepisu); adres planu czyta z propsów Inertia strony `/meal-plans`, wybierając gotowy jadłospis startujący 2026-06-15 (jeden z fixture'ów listy), który `E2eMealPlanSeeder` buduje deterministycznie z tych samych 21 przypiętych przepisów (po 7 na kategorię w kolejności `created_at desc`) i przelicza `FakeIngredientRefiner`-em; logowanie także przez `tests/e2e/visual/support/auth.ts`. Dwa zrzuty gościa (`guest-login`, `guest-register`) czekają na `<h1>` („Logowanie”, „Rejestracja”), nie na stopkę „© 2026”. Razem bramka ma 58 zrzutów (33 + 6 + 3 + 4 + 7 + 5).
4. **Skan literałów** — `scripts/lint-tokens.sh` (`npm run lint:tokens`, `grep -P`, bez nowych zależności) na liście z `scripts/lint-tokens.paths` (`*.vue`, `*.js`, `*.ts`; `Components/ui/` zawsze pominięte). Flaguje cztery rodzaje literałów: klasy palety Tailwinda (w tym `white`/`black`), hexy, wartości arbitralne i `dark:`. Kody wyjścia: 0 brak trafień, 1 trafienia, 2 błąd konfiguracji (brak pliku listy, pusta lista, nieistniejąca ścieżka). Skaner ma test własny, `scripts/lint-tokens.test.sh`. Oba kroki (test i skan) działają w CI w zadaniu `frontend-build` (D-6). Lista obejmuje całe `resources/js` (poza `Components/ui/`, które skaner pomija).

## 8. Rollout

Kolejność: **tokeny → wspólne komponenty → powłoka → widoki wg ruchu w głównym przepływie → sprzątanie.** Zmiana tokenów sama przemaluje komponenty `ui/` (przyciski staną się zielone), więc widoki można migrować pojedynczo bez okresu „pół na pół” w kolorze marki.

| # | Change ID | Zakres | Narzędzie | Prerequisites |
| --- | --- | --- | --- | --- |
| DS-01 / F-03 | `design-tokens-foundation` | Tokeny 4.1–4.6 w `app.css` (OKLCH, stany, domena, radius, `.dark`), usunięcie warstwy `gray-200`, prymitywy z 5.1, warianty `badge`/`alert`, reguła (3 punkty) w `CLAUDE.md` | `/10x-new` → `/10x-plan` | — |
| DS-01 / F-04 | `design-system-visual-gate` | Kitchen sink, bramka zrzutu Playwright, `lint:tokens`, punkt o kitchen sink w regule w `CLAUDE.md` | `/10x-new` → `/10x-plan` | F-03 |
| DS-02 | `shared-app-components` | `Components/Common/*` z 5.2 (`ConfirmDialog`, `ServerPagination`, `SortableHeader`; bez `AppShell`, `PageHeader`, `EmptyState` i `FormField`), migracja wywołań duplikatów (dialogi, paginacja, sortowanie), `CategoryBadge` i `StatusTag` na wariantach | `/10x-new` → `/10x-plan` | DS-01 |
| DS-03 | `app-shell-restyle` | `Sidebar`, `AuthenticatedLayout`, `GuestLayout` (+ „© 2024” → rok bieżący) | `/10x-ui` | DS-01 |
| DS-04 | `meal-plan-show-ui` | `MealPlans/Show`, `MealPlanGrid`, `ShoppingList` + używa `PageHeader`/`EmptyState` (scalonych z DS-05 i DS-06) — rdzeń produktu, najwięcej literałów w głównym przepływie | `/10x-ui` | DS-02, DS-03 |
| DS-05 | `meal-plans-index-ui` | `MealPlans/Index`, `MealPlanTable/Row`, `FiltersBar`, `GenerateMealPlanDialog` + buduje `PageHeader`/`EmptyState` równolegle z DS-06 (scalone przy merge'u DS-06) | `/10x-ui` | DS-02 |
| DS-06 | `recipes-ui` | `Recipes/Index` (`RecipeRow`, 34 literały), `Show`, `Form`, `IngredientManager` + buduje `PageHeader`/`EmptyState` równolegle z DS-05 (scalone przy merge'u DS-06) | `/10x-ui` (po jednym widoku, jeśli diff rośnie) | DS-02 |
| DS-07 | `family-members-ui` | `FamilyMembers/*` (przy okazji parked: `Create`/`Edit` → `Form.vue`, jeśli zmieści się w zakresie) | `/10x-ui` | DS-02 |
| DS-08 | `auth-profile-ui` | `Auth/*`, `Profile/*` — zejście z Breeze na `ui/*` + `FormField` | `/10x-ui` | DS-02, DS-03 |
| DS-09 | `pdf-design-alignment` | PDF wg sekcji 6 | `/10x-new` → `/10x-plan` | DS-01 |
| DS-11 | `dashboard-ui` | `Dashboard.vue` na `PageHeader`, `Card` i `EmptyState`: prawdziwe liczby przepisów i jadłospisów (zapytania ograniczone do właściciela, kontroler zamiast domknięcia w trasie), ostatni gotowy jadłospis z „Pobierz PDF” zamiast „Ostatni PDF” (PDF nie jest zapisywany), szybkie akcje „Dodaj przepis” / „Wygeneruj jadłospis” (dialog z DS-05), bez emoji; wg `.ai/core/ui-plan.md` §2.1 | `/10x-ui` | DS-02, DS-03 |
| DS-10 | `legacy-ui-cleanup` | Usunięcie komponentów Breeze, `ApplicationLogo`, decyzja o `Welcome.vue` (D-7), `lint:tokens` na całe `resources/js` w CI | `/10x-new` → `/10x-plan` | DS-03…DS-08, DS-11 |

`Dashboard.vue` dostał tokeny przy okazji DS-03; pełny restyle z prawdziwymi danymi to DS-11 (dodany 2026-11-15, w roadmapie S-16). Poza rolloutem: dark mode (D-1) — osobna zmiana po DS-10.

Testy E2E (`tests/e2e/`) opierają się na rolach i tekstach, nie na klasach — każda zmiana widoku uruchamia je bez modyfikacji; zmiana selektora to sygnał, że zmieniła się semantyka, nie wygląd.

## 9. Decyzje

| ID | Pytanie | Rekomendacja | Owner | Blokuje |
| --- | --- | --- | --- | --- |
| D-1 | Dark mode w MVP? | **Potwierdzone 2026-11-14:** nie; usuwać `dark:` przy migracji, tokeny `.dark` utrzymywać | user | — |
| D-2 | Kolor marki: zieleń ziół (`#21763c`) i ciepłe tło? | **Potwierdzone 2026-11-14:** tak — kuchnia, świeżość, nie koliduje z kolorami posiłków. **Zastąpione 2026-11-17 przez D-10.** | user | — |
| D-3 | Nowy katalog `resources/js/Components/Common/` na komponenty aplikacji? | **Potwierdzone 2026-11-14:** tak (CLAUDE.md wymaga zgody na nowe foldery) | user | — |
| D-4 | Kitchen sink jako trasa `/dev/design-system` tylko w `local`/`testing`? | **Potwierdzone 2026-11-14:** tak — potrzebny bramce Playwright w CI | user | — |
| D-5 | Zgoda na zależności, które przyniesie `shadcn-vue add` dla 6 prymitywów? | **Potwierdzone 2026-11-14:** tak, po przeglądzie diffu `package.json` | user | — |
| D-6 | `lint:tokens` jako krok CI? | **Potwierdzone 2026-11-14:** tak, jako skrypt grep bez nowych zależności | user | — |
| D-7 | `Welcome.vue` (domyślna strona Laravela pod `/`): przekierowanie na `/login`/`/dashboard` czy własna strona startowa? | **Potwierdzone 2026-11-14:** przekierowanie — PRD nie przewiduje landing page. Na razie bez zmian; strona startowa Spiżarni to osobna, późniejsza zmiana (D-13). | user | — |
| D-8 | Zmiana kolorów kategorii (śniadanie żółte → miodowe, obiad niebieski → pomidorowy, kolacja zielona → wieczorny indygo)? | **Potwierdzone 2026-11-14:** tak — zieleń zostaje marce, kolory idą za porą dnia. Odcienie zostają w D-10; tła stają się pełnymi kolorami (§10.2). | user | — |
| D-9 | Nowe prymitywy w new-york-v4 z importami znormalizowanymi do `@/lib/utils` i `lucide-vue-next`; regeneracja 15 starszych komponentów i przejście na `@lucide/vue` to osobna zmiana po F-04. | **Potwierdzone 2026-11-15** | user | — |
| D-10 | Nowy kierunek wyglądu: A · Zielnik (obecny), B · Bistro (karta menu) czy C · Spiżarnia (bloki koloru pór dnia)? | **Potwierdzone 2026-11-17:** C · Spiżarnia (§10). Zielnik i Bistro odrzucone; Bistro zostaje tylko w historii wersji artefaktu. | user | M-4 |
| D-11 | Źródło prawdy dla wyglądu Spiżarni? | **Potwierdzone 2026-11-17:** artefakt Design System „Menu Maker — Spiżarnia” (§10.1) dla ekranów; ten plik (§10) dla wartości i zasad. Przy rozbieżności wygrywa ten plik. | user | — |
| D-12 | Kolory kategorii jako pełne bloki zamiast pasteli, także w PDF? | **Potwierdzone 2026-11-17** (część D-10): tak; lustro w `meal-plan.blade.php` i `MealPlanPdfPaletteTest` dostają nowe hexy. | user | SP-01 |
| D-13 | Strona startowa (ekran 01 artefaktu) w ramach restyle'u? | **Potwierdzone 2026-11-17:** nie; osobna zmiana po M-4, odwracająca D-7. | user | — |
| D-14 | Dark mode dla Spiżarni? | Potwierdzone 2026-11-17: blok `.dark` usunięty w SP-01; ciemna Spiżarnia to osobna zmiana po M-4 (D-1 obowiązuje). | user | — |

## 10. Kierunek C · Spiżarnia (cel M-4)

Kolor oddany posiłkom: miód rano, pomidor w południe, indygo wieczorem — pełne bloki zamiast pastelowych odznak. Marka cofa się do atramentu, a interfejs wygląda jak naklejki na lodówce: kontur 2px, duże zaokrąglenia, twardy cień.

### 10.1 Źródło

- Artefakt Design System: https://claude.ai/artifact/Py7fzUajDk1Ny57FG7oZMx (prywatny; czytać narzędziem Artifact, akcja `read`, zaczynając od `project/README.md`). Zawiera `tokens.json`, zasady i 15 ekranów desktop 1280 px: 01 Welcome, 02 Logowanie, 03 Rejestracja, 04 Nie pamiętasz hasła?, 05 Pulpit, 06 Pulpit pusty, 07 Przepisy, 08 Przepis, 09 Nowy przepis, 10 Jadłospisy, 11 Jadłospis gotowy, 12 Szkic jadłospisu, 13 Członkowie rodziny, 14 Profil, 15 Dialogi.
- Ekrany to statyczne makiety HTML na danych z `E2eMealPlanSeeder`. Nie pokazują mobile ani stanów hover/focus/loading — każda zmiana widoku projektuje je sama według §5.4.

### 10.2 Tokeny (motyw jasny)

Nazwy zostają te same co w §4 (komponenty `ui/` nie wymagają zmian nazw). W `app.css` wartości zapisujemy w OKLCH (§4.1); hex poniżej to wartości z artefaktu i źródło lustra PDF.

| Token | Hex | Rola |
| --- | --- | --- |
| `--background` | `#fff8ef` | Tło strony |
| `--foreground` | `#1d1b2e` | Tekst, kontur 2px, twardy cień |
| `--card` / `--popover` | `#ffffff` | Kafelki, pola, dialogi |
| `--muted` / `--secondary` | `#f6eee3` | Tła sekcji, żetony składników, przycisk ikonowy |
| `--muted-foreground` | `#5f5a6e` | Metadane (≥ 5.7:1 na `muted`) |
| `--primary` | `#1d1b2e` | Atrament: główne CTA, aktywna nawigacja, wybrany filtr |
| `--primary-foreground` | `#fff8ef` | Tekst na `primary` |
| `--accent` / `--accent-foreground` | `#ffe7a3` / `#1d1b2e` | Hover, aktywny element listy |
| `--border` / `--input` | `#e8dccb` | Separatory wewnątrz kafli (kontur kafla to `foreground`) |
| `--ring` | `#4f5bd5` | Focus ring (≥ 5.6:1 na `card`) |
| `--destructive` / `-foreground` | `#c7361b` / `#ffffff` | Usuwanie, błędy |
| `--success` / `-foreground` | `#c9f0d3` / `#12452a` | Gotowy, zapisano |
| `--warning` / `-foreground` | `#ffe7a3` / `#4a3500` | Ilości bez dopracowania AI |
| `--info` / `-foreground` | `#dfe2ff` / `#232a7a` | Szkic (alert), generowanie |
| `--meal-breakfast` / `-foreground` | `#ffd36e` / `#3b2a00` | Śniadanie (≥ 9:1) |
| `--meal-supper` / `-foreground` | `#ff8a65` / `#3a1206` | Obiad (≥ 7:1) |
| `--meal-dinner` / `-foreground` | `#8c93f0` / `#14163d` | Kolacja (≥ 6.2:1) |
| `--sidebar` (nowy) | = `--background` | Pasek nawigacji |
| `--sidebar-foreground` (nowy) | = `--muted-foreground` | Nieaktywne pozycje nawigacji |
| `--sidebar-active` / `-foreground` (nowe) | = `--primary` / `--primary-foreground` | Aktywna pozycja |
| `--highlight` / `-foreground` (nowe) | `#4f5bd5` / `#ffffff` | Jeden wyróżniony blok na ekran: hero pulpitu, panel ekranów gościa, numery kroków |
| `--overlay` (nowy) | `rgba(29, 27, 46, 0.5)` | Tło pod dialogiem i szufladą |

Kształt i cień:

- Promienie: 12px (pola, komórki tygodnia), 20px (kafelki, karty, hero), 24px (dialogi), pełne zaokrąglenie (przyciski, zakładki, filtry, wyszukiwarka, paginacja, odznaki). SP-01 rozpisał promienie jawnie (zmienna `--radius` zniknęła, bo `--radius-md = --radius - 2px` nie dawał 12px przy 20px); 8px dla `sm` (pozycje menu) to dodatek SP-01 do artefaktu.
- Kontur 2px `foreground` na kafelkach, przyciskach drugorzędnych, polach i dialogach.
- Cienie: `0 3px 0 0 foreground` (naklejka: kafelki, przycisk drugorzędny), `0 6px 0 0 foreground` (dialog). Strefa usuwania konta: kontur i cień w `destructive`.

W Tailwindzie (SP-01, `resources/css/app.css`):

- **Kolory:** `bg-sidebar`, `text-sidebar-foreground`, `bg-sidebar-active`, `text-sidebar-active-foreground`, `bg-highlight`, `text-highlight-foreground` i `bg-overlay` (wszystkie przez `--color-*` w `@theme inline`); pozostałe tokeny mają klasy jak dotąd (`bg-meal-breakfast`, `text-meal-breakfast-foreground`…).
- **Promienie** (jawne, bez `calc()`): `rounded-sm` = 8px, `rounded-md` = 12px, `rounded-lg` = 20px, `rounded-xl` = 24px, `rounded-full` = pełne zaokrąglenie.
- **Cienie:** `shadow-sticker` = `0 3px 0 0 foreground`, `shadow-dialog` = `0 6px 0 0 foreground`. Nowy `--shadow-*` trzeba dopisać też do `extendTailwindMerge` w `resources/js/lib/utils.js` (klucz `shadow`), inaczej `cn()` weźmie go za kolor cienia i zgubi.
- `ui/card` został na `rounded-xl` (24px) do SP-02; SP-02 zmienił go w kafel 20px (`rounded-lg`), a 24px (`rounded-xl`) zostało dialogom.

### 10.3 Typografia

Jeden krój: **Bricolage Grotesque** (polskie znaki). Zastępuje Figtree w aplikacji; PDF zostaje na DejaVu Sans (§6). Ładowany z Bunny Fonts w wagach 400–800 (`resources/views/app.blade.php`): statyczne cięcia, bez osi `opsz`.

| Rola | Rozmiar / interlinia | Waga | Gdzie |
| --- | --- | --- | --- |
| Display | 60 / 68 px, −0.035em | 800 | Tylko strona startowa (D-13) |
| Hasło gościa | 48 / 52 px, −0.035em | 800 | Panel `highlight` ekranów gościa (`GuestLayout`) |
| Tytuł strony | 36 / 40 px, −0.03em | 800 | `PageHeader` |
| Liczba w kafelku | 56 / 56 px, −0.04em | 800 | Statystyki pulpitu, `tabular-nums` |
| Tytuł sekcji | 20 / 24 px | 700 | Kafelki, dialogi (24 px) |
| Tekst | 15 / 22 px | 400 | Domyślny |
| Etykieta | 14 / 20 px | 600 | Przyciski, zakładki, etykiety pól |
| Metadane | 13 / 18 px | 500 | kcal, porcje, daty w `muted-foreground` |

W Tailwindzie każda rola to jedna klasa, niosąca rozmiar, interlinię, odstęp liter i wagę z tabeli: `text-display`, `text-hero`, `text-page-title`, `text-stat`, `text-section-title`, `text-body`, `text-label`, `text-meta`. Tytuł dialogu (24 / 28 px, −0.02em, 800) to osobna rola `text-dialog-title` (SP-02), używana przez `DialogTitle` i `AlertDialogTitle`. `body` ma domyślnie `text-body` (15/22, 400). `text-stat` nie zawiera `tabular-nums`; dodaje je wywołujący. `text-meta` nie ustawia koloru: `text-muted-foreground` dopisuje wywołujący. Jawne `text-sm`/`text-xs` zostają 14/12 px. Wyjątek: znak słowny „Menu Maker” w `Common/AppBrand` to `text-section-title font-extrabold` (800 zamiast 700 roli), jak w makiecie. `<h1>` ekranów gościa używa `text-page-title` (36 px), nie 40 px z makiety: jedna rola tytułu dla gościa i aplikacji. Nowa rola trafia do `--text-*` w `app.css` i do listy `text` w `extendTailwindMerge` w `resources/js/lib/utils.js`, inaczej `cn()` weźmie ją za kolor tekstu.

### 10.4 Zasady

- Kolor niosą wyłącznie `meal-*` (bloki, komórki, kafelki, kategorie) i `highlight` (jeden blok na ekran). `primary` to atrament, nie kolor.
- Tekst na tle kategorii zawsze `meal-*-foreground`; na `highlight` zawsze `highlight-foreground`; przycisk na `highlight` to `card` z tekstem `foreground` albo obrys `highlight-foreground`.
- Status jadłospisu: pigułka z konturem; `draft` ma kontur przerywany na `card` („Szkic”), `processing` ma spinner.
- Ikony bez zmian (§4.7), plus pory dnia: `Sun` (Śniadanie), `Soup` (Obiad), `Moon` (Kolacja).
- **Kontrakt stanów kontrolek** (SP-02; makiety nie definiują hover, focus ani disabled):
  - **hover:** kontrolki w stylu karty (`outline`, `secondary`, `ghost`, `dashed`, domyślna pigułka filtra) przechodzą na `accent`; przyciski atramentowe i `destructive` na `/90`; tony kategorii pigułki filtra przyciemnia `brightness-95`.
  - **wciśnięcie (`active`):** tylko kontrolki z cieniem naklejki (`outline`) przesuwają się o 3px w dół i tracą cień, bez ruchu pod `motion-reduce`. Kontrolki płaskie (atrament, `destructive`, pigułki filtra i paginacji) się nie ruszają.
  - **focus-visible:** `outline` 2px w `ring` z odsunięciem 2px (nie `ring`, bo cień naklejki to `box-shadow` i `active:shadow-none` nie może zabierać wskaźnika fokusu).
  - **disabled:** `opacity-50`, brak zdarzeń wskaźnika, bez cienia.
  - **loading:** `disabled` plus spinner `Loader2` w slocie; nie ma propa `loading`.
- §3 (warstwy), §5.4 (stany), §7 (egzekwowanie) i reguły z `CLAUDE.md` obowiązują bez zmian: tylko tokeny, żadnych literałów palety ani hexów w widokach.

### 10.5 Widoki

| Ekran artefaktu | Widok | Co się zmienia względem dziś |
| --- | --- | --- |
| 02–04 | `GuestLayout`, `Auth/*` | Ekran dzielony: blok `highlight` z hasłem i trzema blokami kategorii po lewej, formularz po prawej |
| — | `AuthenticatedLayout`, `AppSidebar`, `UserMenu` | Panel boczny zastąpiony paskiem górnym z pigułkami nawigacji i awatarem (menu użytkownika); poniżej `lg` zostaje `Sheet` |
| 05–06 | `Dashboard` | Hero `highlight` z ostatnim jadłospisem, kafelki statystyk w `meal-breakfast`/`meal-supper`, „Ten tydzień” w kolorach pór dnia (wymaga posiłków ostatniego planu w propsach `DashboardController`) |
| 07 | `Recipes/Index` | Kafelki po 4 w rzędzie z paskiem kategorii i kcal; filtr kategorii jako pigułki z liczbą przepisów (backend zwraca liczności, zapytanie zawężone do właściciela) |
| 08 | `Recipes/Show` | Blok w kolorze kategorii z pigułkami kcal/porcje, składniki jako żetony, kroki z numerami na `highlight` |
| 09 | `Recipes/Form` | Sekcje w kaflach; kategoria jako trzy kolorowe kafle-radio (`radiogroup`) zamiast `Select` |
| 10 | `MealPlans/Index` | Tygodnie jako kafle z paskiem wypełnienia, statusem i akcjami; filtr statusu jako pigułki |
| 11–12 | `MealPlans/Show`, `MealPlanGrid`, `ShoppingList` | Siatka dni × posiłki z kolorowymi komórkami; w szkicu pigułka „Zmień” w komórce, pusty posiłek jako przerywana ramka z „Wybierz”; lista zakupów jako kafel obok |
| 13 | `FamilyMembers/Index` | Karty osób (inicjał, wiek, waga, wzrost) i przerywany kafel „Dodaj członka rodziny” |
| 14 | `Profile/Edit` | Trzy kafle; usunięcie konta w `destructive` |
| 15 | `GenerateMealPlanDialog`, `RecipeSwapDialog`, `ConfirmDialog` | Dialog 24px z konturem, cieniem i przyciskiem zamknięcia; obecna opcja podmiany na tle kategorii |

### 10.6 Proponowany rollout (wejście dla `/10x-roadmap`, M-4)

| # | Change ID | Zakres | Prerequisites | Równolegle z |
| --- | --- | --- | --- | --- |
| SP-01 | `spizarnia-tokens` | §10.2–10.3 w `app.css` (OKLCH, nowe tokeny, promienie, cienie, Bricolage Grotesque), D-14, lustro PDF + `MealPlanPdfPaletteTest` (D-12), kolory i typografia w kitchen sinku, odświeżone baseline'y | — | — |
| SP-02 | `spizarnia-primitives` | Warianty `ui/`: `Button` (pigułka, kontur, naklejka), `Badge` (status z konturem, `draft` przerywany), `Alert`, `Card` (kafel), `Input`/`Select`/`Textarea`/`Checkbox` (kontur 2px, 12px), `Dialog` (24px, cień, zamknięcie); demo w kitchen sinku | SP-01 | — |
| SP-03 | `spizarnia-app-shell` | `AuthenticatedLayout` z paskiem górnym, `UserMenu`, mobilny `Sheet`; `GuestLayout` jako ekran dzielony | SP-02 | — |
| SP-04 | `spizarnia-dashboard-ui` | Ekrany 05–06 | SP-03 | SP-05, SP-06, SP-07 |
| SP-05 | `spizarnia-recipes-ui` | Ekrany 07–09 (z licznościami kategorii w backendzie) | SP-03 | SP-04, SP-06, SP-07 |
| SP-06 | `spizarnia-meal-plans-ui` | Ekrany 10–12 i dialogi z ekranu 15 | SP-03 | SP-04, SP-05, SP-07 |
| SP-07 | `spizarnia-family-profile-ui` | Ekrany 13–14 | SP-03 | SP-04, SP-05, SP-06 |

Po M-4 (poza milestonem): strona startowa z ekranu 01 (D-13). Równoległe zmiany SP-04…SP-07 dotykają wspólnego kitchen sinka (`Pages/Dev/DesignSystem.vue`) — przy merge'u rozwiązać konflikty w nim i odświeżyć baseline `design-system.spec.ts` raz, na końcu.
