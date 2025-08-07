# Plan implementacji widoku Podgląd przepisu

## 1. Przegląd
Widok „Podgląd przepisu” służy do wyświetlania pełnych informacji o pojedynczym przepisie, w tym listy składników oraz instrukcji przygotowania. Użytkownik może z tego poziomu przejść do edycji przepisu lub go usunąć. Widok jest dostępny dla właściciela przepisu (policy `view` + `delete`).

## 2. Routing widoku
| Akcja            | Ścieżka             | Metoda HTTP | Nazwa routingu |
| ---------------- | ------------------- | ----------- | -------------- |
| Podgląd przepisu | `/recipes/:id`      | GET         | `recipes.show` |
| Edycja (link)    | `/recipes/:id/edit` | GET         | `recipes.edit` |

## 3. Struktura komponentów
```
RecipeShowPage
├── Breadcrumbs
├── ActionBar
│   ├── EditButton
│   └── DeleteButton (otwiera DeleteConfirmationDialog)
├── RecipeHeader
│   └── CategoryBadge
├── RecipeMetaBadges (kalorie, porcje)
├── IngredientCardGrid
│   └── IngredientCard × N
├── InstructionsSection
└── DeleteConfirmationDialog
```

## 4. Szczegóły komponentów
### RecipeShowPage
- **Opis**: Główny komponent strony; odbiera obiekt `recipe` z Inertia i przekazuje go dzieciom.
- **Główne elementy**: `<div>` kontener, nagłówek, grid, przyciski akcji, dialog.
- **Interakcje**:
  - Klik „Edytuj” → nawigacja do `recipes.edit`.
  - Klik „Usuń” → otwarcie modala.
- **Walidacja**: brak (tylko wyświetlanie).
- **Typy**: `RecipeVM` (patrz sekcja 5).
- **Propsy**: `{ recipe: RecipeVM }` (z Inertia).

### ActionBar
- **Opis**: Pasek akcji z przyciskami „Edytuj” i „Usuń”. Wyświetla się tylko dla właściciela.
- **Elementy**: dwa `<Button>` + ikony.
- **Interakcje**: emituje `edit` i `delete-request`.
- **Walidacja**: disabled, gdy brak uprawnień.
- **Typy**: brak dodatkowych.
- **Propsy**: `{ canEdit: boolean }`.

### DeleteConfirmationDialog
- **Opis**: Modal potwierdzający usunięcie przepisu.
- **Elementy**: `<Dialog>` z nagłówkiem, tekstem, przyciskami „Anuluj”/„Usuń”.
- **Interakcje**:
  - `confirm` → emituje `delete-confirmed`.
- **Walidacja**: brak.

### RecipeHeader
- **Opis**: Wyświetla nazwę przepisu i kategorię.
- **Elementy**: `<h1>` + `CategoryBadge`.
- **Interakcje**: brak.
- **Propsy**: `{ name: string, category: RecipeCategory }`.

### CategoryBadge
- **Opis**: Kolorowy badge z kategorią.
- **Propsy**: `{ category: RecipeCategory }`.

### RecipeMetaBadges
- **Opis**: Lista „badge’y” z metadanymi (kalorie, porcje).
- **Elementy**: `<Badge>` ×2.
- **Propsy**: `{ calories: number, servings: number }`.

### IngredientCardGrid
- **Opis**: Grid kart składników.
- **Elementy**: CSS grid responsive (`grid-cols-1 sm:grid-cols-2 lg:grid-cols-3`).
- **Propsy**: `{ ingredients: IngredientPivotVM[] }`.

### IngredientCard
- **Opis**: Pojedyncza karta składnika.
- **Elementy**: nazwa, ilość + jednostka.
- **Propsy**: `{ ingredient: IngredientPivotVM }`.

### InstructionsSection
- **Opis**: Sekcja z instrukcją przygotowania (markdown lub pre-line).
- **Elementy**: `<h2>Instructions</h2>` + `<pre>` lub `<Markdown>`.
- **Propsy**: `{ instructions: string }`.

## 5. Typy
```ts
// Recipe category enum
export type RecipeCategory = 'breakfast' | 'supper' | 'dinner';

// VM otrzymywany z Inertia
export interface RecipeVM {
  id: number;
  name: string;
  category: RecipeCategory;
  calories: number; // decimal(10,2)
  servings: number;
  instructions: string;
  ingredients: IngredientPivotVM[];
  // timestamps pomijamy w UI
}

export interface IngredientPivotVM {
  id: number;         // id składnika
  name: string;       // nazwa składnika
  quantity: number;   // pivot.quantity
  unit: UnitVM;       // pivot.unit_id
}

export interface UnitVM {
  id: number;
  code: string; // g, kg, ml, l, pcs
}
```

## 6. Zarządzanie stanem
- **UsePage** (Inertia) — dostarcza `recipe` w propsach.
- **Ref/Reactive**: `showDeleteDialog: boolean`.
- **Brak globalnego store** — pojedynczy widok.
- Customowy hook: `useDeleteRecipe` (wrappa axios/inertia.delete + isLoading + error).

## 7. Integracja API
| Akcja                 | Endpoint       | Metoda | Dane wejściowe | Sukces                                   | Błąd                                       |
| --------------------- | -------------- | ------ | -------------- | ---------------------------------------- | ------------------------------------------ |
| Pobierz dane przepisu | `/recipes/:id` | GET    | –              | `RecipeResource` → mapped to `RecipeVM`  | Toast z informacją o błędzie, redirect 404 |
| Usuń przepis          | `/recipes/:id` | DELETE | –              | Redirect do `/recipes` + flash `success` | Toast `error`, modal zostaje otwarty       |

## 8. Interakcje użytkownika
1. **Wejście na `/recipes/42`** → render `RecipeShowPage` z danymi.
2. **Klik „Edytuj”** → nawigacja do `/recipes/42/edit`.
3. **Klik „Usuń”** → otwiera się `DeleteConfirmationDialog`.
4. **Potwierdź usunięcie** → żądanie DELETE, po sukcesie redirect z flashem.
5. **Anuluj w dialogu** → zamknięcie modala, brak żądań.

## 9. Warunki i walidacja
- Widok tylko przy policy `view` — backend zwróci 403/404, obsłużyć redirect.
- Przycisk „Edytuj” / „Usuń” widoczny wyłącznie, gdy `canEdit === true` (dostarczone w propsach lub z policy/Resource).

## 10. Obsługa błędów
- **404/403** podczas pobierania danych → redirect do listy przepisów + toast `error`.
- **DELETE** zwraca błąd (np. 403) → toast `error`, modal pozostaje otwarty.
- Globalny `ErrorBoundary` Inertia do przechwycenia nieoczekiwanych błędów sieci.

## 11. Kroki implementacji
1. **Routing**: dodaj wpis w `routes/web.php` (jeżeli nie istnieje) oraz link w menu.
2. **Strona**: utwórz `resources/js/Pages/Recipes/Show.vue` jako `RecipeShowPage`.
3. **Typy**: dodaj plik `types/recipe.ts` z interfejsami z sekcji 5.
4. **Komponenty UI**: zaimplementuj `CategoryBadge`, `RecipeMetaBadges`, `IngredientCard`, `IngredientCardGrid`, `DeleteConfirmationDialog`, `ActionBar`.
5. **Integracja Inertia**: w `Show.vue` użyj `defineProps<{ recipe: RecipeVM }>()`.
6. **Hook useDeleteRecipe**: wrapper dla `Inertia.delete` z loading state.
7. **Styling**: korzystaj z Tailwind + shadcn-vue komponentów (`Button`, `Badge`, `Dialog`).
8. **Dostępność**: aria-label na przyciskach, focus trap w dialogu.
9. **Testy**: napisz testy jednostkowe dla `CategoryBadge` oraz e2e (Cypress/Playwright) dla happy flow usunięcia.
10. **Code review & Pint/Larastan**: uruchom pipeline CI.
