# Plan implementacji widoku: Formularz Przepisu (Dodaj/Edytuj)

## 1. Przegląd
Celem tego widoku jest umożliwienie użytkownikom tworzenia nowych przepisów oraz edytowania już istniejących. Widok będzie zawierał formularz z polami na podstawowe dane przepisu (nazwa, kategoria, instrukcje, etc.) oraz dynamiczny interfejs do zarządzania listą składników. Widok będzie obsługiwał dwa tryby: tworzenia (pusty formularz) i edycji (formularz wypełniony danymi istniejącego przepisu).

## 2. Routing widoku
Widok będzie dostępny pod następującymi ścieżkami:
-   **Tworzenie nowego przepisu:** `/recipes/create`
-   **Edycja istniejącego przepisu:** `/recipes/{id}/edit`

Obie ścieżki będą renderować ten sam komponent Vue, który dostosuje swoje działanie w zależności od obecności danych przepisu przekazanych przez kontroler.

## 3. Struktura komponentów
Hierarchia komponentów została zaprojektowana w celu maksymalizacji reużywalności i separacji odpowiedzialności.

```mermaid
graph TD
    A[Pages/Recipes/Form.vue] --> B(RecipeForm.vue)
    B --> C[Input (Nazwa)]
    B --> D[Select (Kategoria)]
    B --> E[Textarea (Instrukcje)]
    B --> F[Input (Kalorie)]
    B --> G[Input (Porcje)]
    B --> H(IngredientManager.vue)
    B --> I[Button (Zapisz)]
    B --> J[Button (Anuluj)]
    
    subgraph IngredientManager.vue
        direction TB
        K(IngredientRow.vue)
        P[Button (Dodaj składnik)]
        K ~~~ P
    end

    subgraph IngredientRow.vue
        direction LR
        L[Combobox (Składnik)]
        M[Input (Ilość)]
        N[Select (Jednostka)]
        O[Button (Usuń)]
        L --> M --> N --> O
    end

    H --> K
```

## 4. Szczegóły komponentów

### `Pages/Recipes/Form.vue`
-   **Opis:** Główny komponent strony, renderowany przez Inertia. Jego zadaniem jest inicjalizacja hooka `useForm` na podstawie propsów otrzymanych z backendu (`recipe`, `categories`, `ingredients`, `units`) i przekazanie obiektu `form` do komponentu `RecipeForm.vue`.
-   **Główne elementy:** Komponent `RecipeForm`.
-   **Obsługiwane interakcje:** Przekazuje logikę zapisu i anulowania do `RecipeForm`.
-   **Typy:** `RecipeResource`, `Category[]`, `IngredientResource[]`, `UnitResource[]`.
-   **Propsy:**
    -   `recipe: RecipeResource | null`
    -   `categories: { value: string, label: string }[]`
    -   `ingredients: IngredientResource[]`
    -   `units: UnitResource[]`

### `RecipeForm.vue`
-   **Opis:** Sercem widoku, zawiera wszystkie elementy UI formularza. Otrzymuje obiekt `form` z `useForm` i zarządza jego stanem poprzez interakcje użytkownika. Wyświetla błędy walidacji.
-   **Główne elementy:** Komponenty `Input`, `Select`, `Textarea` z biblioteki `shadcn-vue`, komponent `IngredientManager.vue`.
-   **Obsługiwane interakcje:**
    -   `@submit`: Wywołuje metodę `post` lub `put` na obiekcie `form`.
    -   `@cancel`: Powoduje powrót do poprzedniej strony.
-   **Obsługiwana walidacja:** Wyświetla komunikaty o błędach przy polach `name`, `category`, `instructions`, `calories`, `servings` oraz przekazuje błędy dotyczące składników do `IngredientManager`.
-   **Typy:** `RecipeFormViewModel`.
-   **Propsy:**
    -   `form: Object` (obiekt zwrócony przez `useForm` z Inertia).

### `IngredientManager.vue`
-   **Opis:** Komponent do zarządzania dynamiczną listą składników. Renderuje listę komponentów `IngredientRow` i przycisk "Dodaj składnik".
-   **Główne elementy:** `v-for` iterujący po `IngredientRow`, przycisk "Dodaj składnik".
-   **Obsługiwane interakcje:**
    -   Dodawanie nowego składnika do tablicy `form.ingredients`.
    -   Usuwanie składnika z tablicy `form.ingredients`.
-   **Typy:** `IngredientViewModel[]`, `IngredientResource[]`, `UnitResource[]`.
-   **Propsy:**
    -   `ingredients`: `IngredientViewModel[]` (fragment obiektu `form`).
    -   `availableIngredients`: `IngredientResource[]`.
    -   `availableUnits`: `UnitResource[]`.
    -   `errors`: `Object` (błędy walidacji dla składników, np. `ingredients.0.quantity`).

### `IngredientRow.vue`
-   **Opis:** Reprezentuje pojedynczy wiersz na liście składników. Umożliwia wybór składnika, podanie jego ilości i jednostki oraz usunięcie całego wiersza.
-   **Główne elementy:** `Combobox`, `Input`, `Select`, `Button` z `shadcn-vue`.
-   **Obsługiwane interakcje:**
    -   Aktualizacja danych pojedynczego składnika w tablicy.
    -   Usunięcie wiersza (emituje zdarzenie do `IngredientManager`).
-   **Obsługiwana walidacja:** Wyświetla błąd przy konkretnym polu w wierszu.
-   **Typy:** `IngredientViewModel`, `IngredientResource[]`, `UnitResource[]`.
-   **Propsy:**
    -   `ingredient`: `IngredientViewModel`.
    -   `index`: `number`.
    -   `availableIngredients`: `IngredientResource[]`.
    -   `availableUnits`: `UnitResource[]`.

## 5. Typy
Do poprawnej implementacji widoku wymagane są następujące typy i modele widoku:

```typescript
// --- Typy danych z API (Props) ---

// Zasób reprezentujący dostępny składnik do wyboru
interface IngredientResource {
  id: number;
  name: string;
}

// Zasób reprezentujący dostępną jednostkę do wyboru
interface UnitResource {
  id: number;
  code: string; // np. 'g', 'kg', 'ml', 'l', 'szt.'
}

// Główny zasób przepisu, przekazywany w trybie edycji
interface RecipeResource {
  id: number;
  name: string;
  category: 'breakfast' | 'supper' | 'dinner';
  instructions: string;
  calories: number;
  servings: number;
  ingredients: { // Składniki przypisane do przepisu
    id: number; // To jest ID składnika (ingredient_id)
    name: string;
    pivot: {
      quantity: number;
      unit_id: number;
    }
  }[];
}

// --- Typy ViewModel (Zarządzanie stanem w formularzu) ---

// Model widoku dla pojedynczego składnika w formularzu
interface IngredientViewModel {
  ingredient_id: number | null;
  quantity: number | string;
  unit_id: number | null;
}

// Główny model widoku dla całego formularza przepisu
interface RecipeFormViewModel {
  name: string;
  category: 'breakfast' | 'supper' | 'dinner' | null;
  instructions: string;
  calories: number | string;
  servings: number | string;
  ingredients: IngredientViewModel[];
}
```

## 6. Zarządzanie stanem
Zarządzanie stanem formularza będzie realizowane za pomocą hooka `useForm` z biblioteki `@inertiajs/vue3`. Zapewnia on reaktywny obiekt `form`, który przechowuje dane, stan przetwarzania (`form.processing`), błędy walidacji (`form.errors`) oraz metody do wysyłania danych (`post`, `put`).

**Inicjalizacja:**
```javascript
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
  recipe: RecipeResource | null;
  // ... inne propsy
}>();

const form = useForm<RecipeFormViewModel>({
  name: props.recipe?.name ?? '',
  category: props.recipe?.category ?? null,
  instructions: props.recipe?.instructions ?? '',
  calories: props.recipe?.calories ?? '',
  servings: props.recipe?.servings ?? '',
  ingredients: props.recipe?.ingredients.map(ing => ({
    ingredient_id: ing.id,
    quantity: ing.pivot.quantity,
    unit_id: ing.pivot.unit_id,
  })) ?? [],
});
```
Nie ma potrzeby tworzenia dodatkowych, niestandardowych hooków.

## 7. Integracja API
Integracja z API odbywa się poprzez metody dostarczone przez hook `useForm`.

-   **Tworzenie przepisu (CREATE):**
    -   **Endpoint:** `POST /recipes`
    -   **Akcja:** `form.post('/recipes')`
    -   **Typ żądania:** `RecipeFormViewModel`
    -   **Typ odpowiedzi (sukces):** Przekierowanie na stronę listy przepisów (`/recipes`).
    -   **Typ odpowiedzi (błąd):** Ten sam widok z obiektem błędów.

-   **Aktualizacja przepisu (UPDATE):**
    -   **Endpoint:** `PUT /recipes/{id}`
    -   **Akcja:** `form.put(`/recipes/${props.recipe.id}`)`
    -   **Typ żądania:** `RecipeFormViewModel`
    -   **Typ odpowiedzi (sukces):** Przekierowanie na stronę szczegółów przepisu (`/recipes/{id}`).
    -   **Typ odpowiedzi (błąd):** Ten sam widok z obiektem błędów.

## 8. Interakcje użytkownika
-   **Wprowadzanie danych:** Użytkownik wypełnia pola formularza. Zmiany są automatycznie synchronizowane z obiektem `form` dzięki `v-model`.
-   **Dodawanie składnika:** Kliknięcie przycisku "Dodaj składnik" powoduje dodanie nowego, pustego obiektu `IngredientViewModel` do tablicy `form.ingredients`.
-   **Usuwanie składnika:** Kliknięcie przycisku "Usuń" przy danym wierszu usuwa odpowiedni obiekt z tablicy `form.ingredients` na podstawie jego indeksu.
-   **Zapis formularza:** Kliknięcie przycisku "Zapisz" uruchamia odpowiednią metodę (`post` lub `put`). Przycisk jest nieaktywny (`disabled`) w trakcie przetwarzania żądania (`form.processing`).
-   **Anulowanie:** Kliknięcie "Anuluj" powoduje powrót do poprzedniej strony za pomocą `window.history.back()` lub linku Inertia.

## 9. Warunki i walidacja
Walidacja jest przeprowadzana na backendzie, a frontend jest odpowiedzialny za wyświetlanie zwróconych błędów. Komunikaty o błędach będą dostępne w obiekcie `form.errors`.

-   **`name`**: Wymagane, tekst, max 255 znaków. Błąd wyświetlany pod polem input.
-   **`category`**: Wymagane, musi być jedną z dostępnych opcji. Błąd wyświetlany pod polem select.
-   **`instructions`**: Wymagane, tekst. Błąd wyświetlany pod polem textarea.
-   **`calories`**: Wymagane, numeryczne, min 0. Błąd wyświetlany pod polem input.
-   **`servings`**: Wymagane, całkowite, min 1. Błąd wyświetlany pod polem input.
-   **`ingredients`**: Tablica musi być obecna.
-   **`ingredients.*.ingredient_id`**: Wymagane. Błąd wyświetlany przy odpowiednim wierszu składnika.
-   **`ingredients.*.quantity`**: Wymagane, numeryczne, min 0.01. Błąd wyświetlany przy odpowiednim wierszu składnika.
-   **`ingredients.*.unit_id`**: Wymagane. Błąd wyświetlany przy odpowiednim wierszu składnika.

Stan interfejsu (np. czerwona ramka wokół pola) będzie dynamicznie zmieniany w zależności od obecności błędu dla danego pola w `form.errors`.

## 10. Obsługa błędów
-   **Błędy walidacji (HTTP 422):** Obsługiwane automatycznie przez Inertia. Błędy są przypisywane do `form.errors`, a komponenty UI reagują na te zmiany, wyświetlając komunikaty.
-   **Błędy serwera (HTTP 5xx):** Należy zaimplementować globalną obsługę błędów (np. w `app.js` z użyciem `Inertia.on('error', ...)`), aby wyświetlić użytkownikowi ogólną notyfikację (np. "Wystąpił nieoczekiwany błąd. Spróbuj ponownie później.") za pomocą biblioteki takiej jak `vue-sonner`.
-   **Błędy autoryzacji (HTTP 403):** Inertia domyślnie wyświetli stronę błędu 403. To zachowanie jest wystarczające.

## 11. Kroki implementacji
1.  **Utworzenie plików komponentów:** Stworzyć puste pliki `.vue` dla `Pages/Recipes/Form.vue`, `RecipeForm.vue`, `IngredientManager.vue` i `IngredientRow.vue`.
2.  **Implementacja `Pages/Recipes/Form.vue`:** Zdefiniować propsy i zainicjować hook `useForm`, mapując dane z `props.recipe` w trybie edycji.
3.  **Implementacja `RecipeForm.vue`:** Zbudować layout formularza używając komponentów `shadcn-vue`. Dodać `v-model` do pól, aby połączyć je z obiektem `form`. Dodać obsługę wyświetlania błędów z `form.errors`.
4.  **Implementacja `IngredientManager.vue` i `IngredientRow.vue`:** Zaimplementować logikę dynamicznego dodawania i usuwania składników. Użyć `v-for` do renderowania wierszy. Przekazać `availableIngredients` i `availableUnits` jako propsy.
5.  **Logika zapisu i anulowania:** Dodać metody obsługujące zdarzenie `@submit` na formularzu, które wywołają `form.post` lub `form.put`. Dodać przycisk "Anuluj".
6.  **Stylowanie i dopracowanie UI:** Upewnić się, że formularz jest responsywny i zgodny z resztą aplikacji.
7.  **Testowanie manualne:** Przetestować oba scenariusze (tworzenie i edycja), walidację oraz obsługę błędów.
