# Plan implementacji widoku Lista Przepisów

## 1. Przegląd
Widok "Lista Przepisów" umożliwia użytkownikowi przegląd wszystkich zapisanych w systemie przepisów, ich filtrowanie, sortowanie oraz paginację. Pozwala także na przejście do podglądu, edycji lub usunięcia wybranego przepisu. Widok wykorzystuje mechanizm SSR Inertia.js i komunikuje się z endpointem `RecipeController@index`.

## 2. Routing widoku
- Ścieżka: `/recipes`
- Nazwa routa (frontend, vue-router-like): `recipes.index`
- Dostęp wyłącznie dla użytkowników zalogowanych (middleware `auth`, policy `viewAny` domyślnie sprawdzana w kontrolerze).

## 3. Struktura komponentów
```
AuthenticatedLayout
└── RecipesIndexPage
    ├── RecipesToolbar
    │   ├── CategoryFilterDropdown
    │   ├── SearchInput
    │   └── ResetFiltersButton
    ├── RecipesTable
    │   ├── Thead (sortable headers)
    │   └── Tbody
    │       └── RecipeRow × n
    │           └── RowActionDropdown (Show / Edit / Delete)
    ├── ServerPagination
    └── ConfirmDeleteModal (portal)
```

## 4. Szczegóły komponentów
### RecipesIndexPage
- **Opis:** Główny kontener widoku; odbiera dane z Inertia, inicjuje `LocalStorageStateSync` i przekazuje props do komponentów potomnych.
- **Główne elementy:** `RecipesToolbar`, `RecipesTable`, `ServerPagination`, `<Head>` (SEO).
- **Obsługiwane interakcje:** zmiana filtrów, sortowania, stronicowania, usunięcie przepisu.
- **Walidacja:** brak bezpośredniej – walidacja wejścia odbywa się w podkomponentach.
- **Typy:** `RecipesPageProps`, `FilterState`, `SortState`, `PaginatedRecipes`.
- **Propsy:** otrzymuje cały obiekt `page.props` Inertia (`recipes`, `filters`, `categories`).

### RecipesToolbar
- **Opis:** Pasek z filtrami i wyszukiwarką.
- **Główne elementy:** `CategoryFilterDropdown`, `SearchInput`, przycisk „Wyczyść filtry”.
- **Interakcje:**
  - `onCategoryChange(value: string|null)`
  - `onSearch(value: string)` (debounce 300 ms)
  - `onReset()`
- **Walidacja:** `search` max 50 znaków.
- **Typy:** `ToolbarEvents` (emits).
- **Propsy:** `categories: CategoryOption[]`, `modelValue` (obiekt filtrów) – v-model kompatybilny.

### CategoryFilterDropdown
- **Opis:** Select z listą kategorii (Breakfast, Dinner, Supper) + opcja "Wszystkie".
- **Elementy:** `<Select>`, `<SelectItem>` (Shadcn-vue).
- **Interakcje:** emituje `update:modelValue`.
- **Walidacja:** brak (lista zamknięta – tylko wartości z props).
- **Typy:** `CategoryOption` { value: string, label: string }.
- **Propsy:** `options: CategoryOption[]`, `modelValue: string|null`.

### SearchInput
- **Opis:** Input typu search z ikoną lupy.
- **Elementy:** `<Input>`, `<IconSearch>`.
- **Interakcje:** `update:modelValue` (debounced), `keydown.enter` natychmiast.
- **Walidacja:** maxLength 50.
- **Typy:** `string`.
- **Propsy:** `modelValue: string`.

### RecipesTable
- **Opis:** Tabela responsywna z danymi przepisu oraz akcjami.
- **Elementy:** `<table>`, `<thead>`, `<tbody>`, `RecipeRow`.
- **Interakcje:** sortowanie po kliknięciu nagłówka; propaguje `deleteRequested(id)`.
- **Walidacja:** sortField ∈ {name, category, calories, created_at}.
- **Typy:** `RecipeRowData`.
- **Propsy:** `recipes: RecipeRowData[]`, `sort: SortState`.

### RecipeRow
- **Opis:** Wiersz danych jednego przepisu.
- **Elementy:** `<tr>`, `<td>` × 5, `RowActionDropdown`.
- **Interakcje:** klik w nazwę → navigate do `/recipes/:id`; dropdown akcje.
- **Walidacja:** brak.
- **Typy:** `RecipeRowData` = subset `RecipeResource`.
- **Propsy:** `recipe: RecipeRowData`.

### RowActionDropdown
- **Opis:** Menu kontekstowe z akcjami „Pokaż”, „Edytuj”, „Usuń”.
- **Elementy:** Shadcn `<DropdownMenu>`.
- **Interakcje:**
  - `show(id)` – router push
  - `edit(id)` – router push
  - `delete(id)` – emit do rodzica (RecipesTable)
- **Walidacja:** policy `delete` już po stronie API.
- **Typy:** `{ id: number }`.
- **Propsy:** `id: number`.

### ServerPagination
- **Opis:** Nawigacja po stronach; wysyła zapytanie Inertia z aktualnymi filtrami.
- **Elementy:** `Pagination` z shadcn.
- **Interakcje:** `changePage(page: number)`.
- **Propsy:** `meta: PaginationMeta`, `links: PaginationLinks`.

### ConfirmDeleteModal
- **Opis:** Modal potwierdzający usunięcie; otwierany portalo.
- **Interakcje:** `confirm()`, `cancel()`.
- **Walidacja:** brak.
- **Propsy:** `open: boolean`, `recipeName: string`.

## 5. Typy
```
// Zgodne z TypeScript 5.x
interface RecipeRowData {
  id: number;
  name: string;
  category: string; // "breakfast" | "dinner" | "supper"
  calories: string; // format "123.45"
  servings: number;
  created_at: string; // ISO 8601
}

interface PaginatedRecipes {
  data: RecipeRowData[];
  meta: PaginationMeta;
  links: PaginationLinks;
}

interface FilterState {
  search: string;
  category: string | null;
}

interface SortState {
  field: 'name' | 'created_at' | 'calories' | 'category';
  direction: 'asc' | 'desc';
}

interface RecipesPageProps {
  recipes: PaginatedRecipes;
  filters: Partial<FilterState & SortState>;
  categories: CategoryOption[];
}

interface CategoryOption {
  value: string;
  label: string;
}
```

## 6. Zarządzanie stanem
- Stosujemy lokalny stan komponentu `RecipesIndexPage` dla `filterState`, `sortState`, `currentPage`.
- `LocalStorageStateSync` (komponent/hook) synchronizuje `filterState` oraz `sortState` w `localStorage` pod kluczem `mm.recipes.filters`.
- Każda zmiana stanu wywołuje zapytanie Inertia `visit` (method GET) z parametrami query.
- Brak globalnego store (Pinia) – zakres widoku lokalny.

## 7. Integracja API
| Akcja         | HTTP   | Endpoint        | Query/Payload                                     | Odpowiedź                         |
| ------------- | ------ | --------------- | ------------------------------------------------- | --------------------------------- |
| Pobierz listę | GET    | `/recipes`      | `search`, `category`, `sort`, `direction`, `page` | `PaginatedRecipes` + `categories` |
| Usuń przepis  | DELETE | `/recipes/{id}` | –                                                 | Redirect + flash `success`        |

- Wszystkie wywołania realizowane przez `Inertia.visit` z opcją `preserveScroll`.

## 8. Interakcje użytkownika
1. Wpisanie frazy w `SearchInput` (po 300 ms) → aktualizacja `search`, reset `page=1`, wysłanie zapytania.
2. Wybór kategorii w `CategoryFilterDropdown` → aktualizacja `category`, reset `page`, zapytanie.
3. Klik w nagłówek kolumny → toggluje `direction`, ustawia `sort`, zapytanie.
4. Klik w strzałkę paginacji → zmiana `page`, zapytanie.
5. Klik „Usuń” w `RowActionDropdown` → otwiera `ConfirmDeleteModal`; potwierdzenie wywołuje `DELETE`, po sukcesie reload listy.
6. Klik w nazwę przepisu lub „Pokaż” → router push `/recipes/:id`.
7. Klik „Edytuj” → router push `/recipes/:id/edit`.

## 9. Warunki i walidacja
- `search` ≤ 50 znaków (frontend) – przy przekroczeniu blokada input.
- `category` musi należeć do listy `categories` dostarczonej z backendu.
- `sort.field` ograniczony do dozwolonych pól – kontrolowane przez UI.
- Podczas usuwania przepisu wyświetlamy modal i dopiero po potwierdzeniu wysyłamy `DELETE`.

## 10. Obsługa błędów
- Błędy walidacji (422) po zapytaniach GET/DELETE:
  - Display `Toast` z `page.props.errors` (global handler). UI zachowuje ostatni stan.
- 403 (policy) przy próbie usunięcia przepisu → toast „Nie masz uprawnień”.
- 500/Network – komponent `ErrorBoundary` z komunikatem i opcją ponów.

## 11. Kroki implementacji
1. Utworzenie routingu `/recipes` w pliku routes/web.php (jeśli brak) oraz w `ziggy.js`.
2. Stworzenie pliku `resources/js/Pages/Recipes/Index.vue` z szablonem `RecipesIndexPage`.
3. Zaimportowanie i zbudowanie `RecipesToolbar`, `RecipesTable`, `ServerPagination`, `ConfirmDeleteModal` w katalogu `resources/js/Components/recipes`.
4. Implementacja `LocalStorageStateSync` (lub wykorzystanie istniejącego) dla filtrów i sortowania.
5. Integracja z Inertia: w komponencie `setup()` pobranie `usePage().props` → inicjalizacja stanów.
6. Dodanie reakcji na zmiany filtrów/sortowania/paginacji: `watch` + `Inertia.visit`.
7. Zaimplementowanie sortowalnych nagłówków w `RecipesTable` (komponent `SortableHeader`).
8. Dodanie dropdownu akcji z wykorzystaniem Shadcn-vue.
9. Zaimplementowanie `ConfirmDeleteModal` z portalem (`Teleport`) i obsługą `DELETE`.
10. Dodanie hooka `useDeleteRecipe` (opcjonalnie) enkapsulującego zapytanie DELETE i obsługę toastów.
11. Zaimplementowanie `Toast` (jeśli globalnie – użyć istniejącego).
12. Dodanie testów jednostkowych Vue dla `RecipesToolbar` i `RecipesTable` (Vitest).
13. Dodanie testów e2e (Pest Dusk lub Cypress) scenariusza filtrowania i usuwania przepisu.
14. Review kodu, spełnienie reguł Pint, Larastan i CI.
15. Merge i deploy.
