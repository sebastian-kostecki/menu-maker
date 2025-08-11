# Plan implementacji widoku Jadłospisy – lista

## 1. Przegląd
Widok historii wszystkich wygenerowanych jadłospisów użytkownika. Umożliwia przeglądanie listy z paginacją, filtrowanie po statusie, sortowanie po wybranych polach oraz akcje na rekordach: podgląd szczegółów, pobranie PDF (gdy gotowy), regeneracja oraz usunięcie.

## 2. Routing widoku
- Ścieżka: `/meal-plans`
- Inertia Page: `resources/js/Pages/MealPlans/Index.vue`
- Wymagane middleware: `auth`, `verified`

## 3. Struktura komponentów
- `MealPlansIndexPage`
  - `FiltersBar`
    - `StatusFilter`
    - `PerPageSelect`
  - `MealPlanTable`
    - `SortableTableHeader`
    - `MealPlanRow` (×N)
      - `StatusTag`
      - `ActionDropdown`
        - `ConfirmDialog` (lazy mount)
  - `ServerPagination`
  - `Toast` (global)

## 4. Szczegóły komponentów
### MealPlansIndexPage
- Opis: Główna strona listy. Odbiera props z Inertia (`mealPlans`, `filters`, `statuses`). Zarządza stanem filtrów i synchronizacją query params.
- Główne elementy: nagłówek strony, `FiltersBar`, `MealPlanTable`, `ServerPagination`.
- Obsługiwane interakcje:
  - Zmiana statusu filtra → GET z `filter[status]` i zachowanie scroll/replace.
  - Zmiana `perPage` → GET z `perPage`.
  - Klik w paginację → GET z `page`.
  - Klik w sort nagłówka → GET z `sort` i `direction`.
- Obsługiwana walidacja (UI):
  - Walidacja wartości filtra statusu do listy `['pending','processing','done','error']` (z prop `statuses`).
  - Walidacja pola `perPage` do zakresu [5, 100].
- Typy: `MealPlanCollectionProps`, `MealPlanListItem`, `PaginationMeta`, `PaginationLinks`, `FiltersState`, `SortDirection`, `StatusOption`.
- Propsy: `{ mealPlans: MealPlanCollection, filters: { 'filter.status'?: string, sort?: string, direction?: SortDirection, perPage?: number }, statuses: StatusOption[] }`.

### FiltersBar
- Opis: Pasek filtrów nad tabelą.
- Główne elementy: `StatusFilter`, `PerPageSelect`, opcjonalnie licznik wyników.
- Obsługiwane interakcje: zmiana statusu, zmiana `perPage`.
- Walidacja: jak wyżej.
- Typy: `FiltersState`, `StatusOption`.
- Propsy: `{ value: FiltersState, statuses: StatusOption[] }` + emit `update:value`.

### StatusFilter
- Opis: Select (shadcn-vue) z opcjami statusów.
- Główne elementy: `<Select>`, `<SelectItem>`.
- Interakcje: wybór statusu lub „Wszystkie”.
- Walidacja: wartość należy do listy `statuses` lub pusty string.
- Typy: `StatusOption`.
- Propsy: `{ modelValue?: string, options: StatusOption[] }` + emit `update:modelValue`.

### PerPageSelect
- Opis: Select rozmiaru strony (5, 10, 15, 25, 50, 100).
- Interakcje: wybór wartości → reload z query.
- Walidacja: liczba całkowita w dozwolonym zestawie.
- Propsy: `{ modelValue: number }` + emit `update:modelValue`.

### MealPlanTable
- Opis: Tabela rekordów z nagłówkami umożliwiającymi sortowanie.
- Główne elementy: `<table>`, `SortableTableHeader`, `MealPlanRow`.
- Interakcje: klik w nagłówek sortujący.
- Walidacja: pole `sort` musi być jednym z: `['start_date','end_date','status','created_at']`.
- Propsy: `{ items: MealPlanListItem[], sort?: string, direction?: SortDirection }` + emit `sort-change`.

### SortableTableHeader
- Opis: Komponent nagłówka z ikoną sortowania i stanem kierunku.
- Interakcje: klik przełącza `direction` między `asc` i `desc` dla danego pola.
- Propsy: `{ field: string, activeField?: string, direction?: SortDirection, label: string }` + emit `change` z `{ field, direction }`.

### MealPlanRow
- Opis: Pojedynczy wiersz tabeli z danymi planu.
- Główne elementy: komórki daty (`start_date` – `end_date`), `StatusTag`, liczby (`meals_count`, `logs_count`), `ActionDropdown`.
- Interakcje: akcje w menu (Podgląd, Pobierz PDF, Regeneruj, Usuń).
- Walidacja UI:
  - `Regeneruj` dostępne tylko gdy `status ∈ {'done','error'}`.
  - `Usuń` disabled gdy `status === 'processing'`.
  - `Pobierz PDF` dostępne gdy `status === 'done'`.
- Propsy: `{ item: MealPlanListItem }`.

### ActionDropdown
- Opis: Menu akcji wiersza (shadcn-vue DropdownMenu).
- Interakcje i akcje:
  - Podgląd → nawigacja do `item.links.self`.
  - Pobierz PDF → GET do trasy `meal-plans.pdf` (`/meal-plans/{id}/pdf`) w nowej karcie, aktywne gdy `status==='done'`.
  - Regeneruj → `ConfirmDialog` → PUT `/meal-plans/{id}` z payload `{ regenerate: true }`.
  - Usuń → `ConfirmDialog` → DELETE `/meal-plans/{id}`.
- Walidacja UI: jak w `MealPlanRow`.
- Propsy: `{ item: MealPlanListItem }`.

### ConfirmDialog
- Opis: Modal potwierdzający operacje wrażliwe.
- Interakcje: potwierdź/anuluj; fokus w pętli; ESC zamyka.
- Propsy: `{ title: string, description?: string, confirmText?: string, variant?: 'destructive'|'default' }`.

### ServerPagination
- Opis: Komponent paginacji serwerowej.
- Interakcje: zmiana strony → GET z `page`.
- Propsy: `{ links: PaginationLinks, meta: PaginationMeta }`.

### StatusTag
- Opis: Badge z kolorem wg statusu (`pending` szary, `processing` niebieski, `done` zielony, `error` czerwony).
- Propsy: `{ value: MealPlanStatus }`.

## 5. Typy
```ts
type MealPlanStatus = 'pending' | 'processing' | 'done' | 'error'

interface StatusOption { value: MealPlanStatus; label: string }

interface MealPlanListItem {
  id: number
  start_date: string // 'YYYY-MM-DD'
  end_date: string   // 'YYYY-MM-DD'
  status: MealPlanStatus
  created_at: string
  updated_at: string
  meals_count: number
  logs_count: number
  links: { self: string }
}

interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
  from: number | null
  to: number | null
}

interface PaginationLinks {
  first: string | null
  last: string | null
  prev: string | null
  next: string | null
}

interface MealPlanCollection {
  data: MealPlanListItem[]
  meta: PaginationMeta
  links: PaginationLinks
}

type SortDirection = 'asc' | 'desc'

interface FiltersState {
  status?: MealPlanStatus | ''
  sort?: 'start_date' | 'end_date' | 'status' | 'created_at'
  direction?: SortDirection
  perPage?: number
}

interface MealPlanCollectionProps {
  mealPlans: MealPlanCollection
  filters: {
    'filter.status'?: MealPlanStatus
    sort?: FiltersState['sort']
    direction?: SortDirection
    perPage?: number
  }
  statuses: StatusOption[]
}
```

## 6. Zarządzanie stanem
- Źródłem prawdy listy jest odpowiedź Inertia z backendu (`MealPlanController@index`).
- Lokalne UI-state: `filters` (Status, Sort, Direction, PerPage), synchronizowane z query params i z `localStorage` (klucz: `mm.mealPlans.filters`).
- Zmiany filtrów/sortowania wywołują `router.get(route('meal-plans.index'), query, { preserveScroll: true, replace: true })`.
- Paginacja bazuje na `mealPlans.links` i `mealPlans.meta` (GET na odpowiedni URL z linka).
- Globalne toasty wykorzystują shadcn-vue / Inertia flash messages.

## 7. Integracja API
- Lista (GET): `GET /meal-plans?filter[status]={status}&sort={field}&direction={asc|desc}&perPage={n}&page={n}`
  - Odpowiedź: `MealPlanCollection` zgodnie z `MealPlanCollection::toArray()`.
- Podgląd: link `links.self` → `GET /meal-plans/{id}` (Inertia) – nawigacja po stronie klienta.
- Pobierz PDF: `GET /meal-plans/{id}/pdf` (otwarcie w nowej karcie). UWAGA: przycisk aktywny tylko dla `status='done'`.
- Regeneracja: `PUT /meal-plans/{id}` z `application/json` body `{ "regenerate": true, "force"?: boolean }`
  - 202 na sukces (rozpoczęto), 422 na walidacji (np. `status`, `rate_limit`), 409 gdy `processing` (z kontrolera).
- Usuń: `DELETE /meal-plans/{id}` → 204 na sukces; przy `processing` policy/validation zwróci 403/422.

## 8. Interakcje użytkownika
- Zmiana filtra statusu → odświeżenie listy; stan zapisany w `localStorage`.
- Zmiana rozmiaru strony → odświeżenie listy od strony 1.
- Sortowanie po kolumnie → przełączanie kierunku; aktualizacja listy.
- Paginacja → przejście na wskazaną stronę.
- Podgląd → przejście do `/meal-plans/{id}`.
- Pobierz PDF → nowa karta; disabled gdy status ≠ `done`.
- Regeneruj → modal potwierdzenia → żądanie PUT; toast sukcesu/błędu; wiersz może zmienić `status` na `pending` po re-fetch.
- Usuń → modal potwierdzenia → żądanie DELETE; toast i re-fetch (zachowaj bieżącą stronę jeśli możliwe).

## 9. Warunki i walidacja
- Akcje wiersza:
  - `Regeneruj` tylko dla `status ∈ {'done','error'}` (UI i backend `RegenerateMealPlanRequest`).
  - `Usuń` zablokowane dla `status='processing'` (UI + backend policy).
  - `Pobierz PDF` aktywne tylko przy `status='done'`.
- Parametry zapytań:
  - `sort` należy do `['start_date','end_date','status','created_at']`.
  - `direction` ∈ {'asc','desc'}.
  - `perPage` w zakresie [5, 100].
  - `filter.status` ∈ dozwolonych statusów.

## 10. Obsługa błędów
- 401/403: redirect do logowania lub toast „Brak uprawnień”.
- 404 (PDF/rekord): toast „Nie znaleziono zasobu”.
- 409 (regeneracja w trakcie): pokaż toast z treści „Cannot regenerate meal plan while it is being processed.”.
- 422 walidacja:
  - `status` („Can only regenerate completed or failed meal plans.”) → toast i brak akcji.
  - `rate_limit` (limit prób) → toast z liczbą sekund; opcja ukrycia przycisku na czas odliczania.
- 5xx: toast „Wystąpił błąd serwera. Spróbuj ponownie.”.

## 11. Kroki implementacji
1. Utwórz stronę `resources/js/Pages/MealPlans/Index.vue` z odbiorem props: `mealPlans`, `filters`, `statuses`.
2. Zaimplementuj `FiltersBar` (`StatusFilter`, `PerPageSelect`) i integrację z Inertia routerem (GET + query sync, `preserveScroll`, `replace`).
3. Zaimplementuj `MealPlanTable` z nagłówkami sortującymi (`SortableTableHeader`), kolumnami: Zakres dat, Status (`StatusTag`), Liczba posiłków, Logi, Akcje.
4. Zaimplementuj `ActionDropdown` z akcjami: Podgląd, Pobierz PDF (target="_blank"), Regeneruj (PUT `{ regenerate: true }`), Usuń (DELETE). Dodaj `ConfirmDialog` dla Regeneruj/Usuń.
5. Dodaj `ServerPagination` korzystające z `mealPlans.links` i `mealPlans.meta` (Inertia GET zgodnie z linkami).
6. Dodaj lokalną synchronizację filtrów do `localStorage` (np. composable `useLocalStorageState(key, initial)` lub prosty efekt watch).
7. Wprowadź mapowanie statusów na kolory w `StatusTag` (Tailwind). Zapewnij a11y: aria-label, role, focus-ring.
8. Obsłuż błędy żądań (422/409/5xx) poprzez globalny handler/`useToast()`; pokaż komunikaty z payloadu (`rate_limit`, `status`).
9. Dodaj testy komponentów (opcjonalnie): render tabeli, filtrowanie, sortowanie, dostępność akcji per status.
10. Lint i format (Laravel Pint dla PHP — bez zmian; ESLint/Prettier dla frontend), sprawdź brak błędów typów TS.


