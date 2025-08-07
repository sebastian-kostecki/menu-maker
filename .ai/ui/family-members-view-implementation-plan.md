# Plan implementacji widoku „Członkowie rodziny”

## 1. Przegląd
Widok umożliwia zalogowanemu użytkownikowi pełne zarządzanie członkami swojej rodziny (CRUD). Lista członków wyświetlana jest w tabeli z możliwością dodawania nowych osób, edycji w trybie inline oraz usuwania z potwierdzeniem. Celem widoku jest dostarczenie prostego, intuicyjnego i szybkiego interfejsu do aktualizacji danych, które będą następnie wykorzystywane do skalowania przepisów oraz generowania jadłospisów.

## 2. Routing widoku
| Metoda | Ścieżka                | Kontroler / Akcja                | Opis                 |
| ------ | ---------------------- | -------------------------------- | -------------------- |
| GET    | `/family-members`      | `FamilyMemberController@index`   | Lista członków       |
| POST   | `/family-members`      | `FamilyMemberController@store`   | Dodanie członka      |
| PUT    | `/family-members/{id}` | `FamilyMemberController@update`  | Aktualizacja członka |
| DELETE | `/family-members/{id}` | `FamilyMemberController@destroy` | Usunięcie członka    |

Frontend: Inertia page `FamilyMembers/Index.vue` renderowana w powyższym GET-route.

## 3. Struktura komponentów
```
FamilyMembers/Index.vue
└── FamilyMemberTable.vue
    ├── InlineEditDialog.vue
    └── ConfirmDialog.vue
```

## 4. Szczegóły komponentów
### FamilyMembers/Index.vue
- **Opis:** Główny widok; pobiera dane z API, zarządza stanem listy i wywołuje mutacje (add, update, delete).
- **Główne elementy:** nagłówek strony, przycisk „Dodaj”, komponent `FamilyMemberTable`.
- **Obsługiwane interakcje:**  
  • klik „Dodaj” → otwiera pusty `InlineEditDialog`  
  • przekazane zdarzenia `saved`, `deleted` z tabeli → aktualizacja lokalnego stanu  
- **Walidacja:** proxy do `InlineEditDialog`.
- **Propsy:** brak — jest widokiem strony.
- **Typy:** `FamilyMember[]`, `FamilyMemberFormState`.

### FamilyMemberTable.vue
- **Opis:** Renderuje tabelę imię | data urodzenia | płeć | akcje.
- **Główne elementy:** `table > tr > td`, przyciski edycji i usunięcia w kolumnie Akcje.
- **Obsługiwane interakcje:**  
  • klik „Edytuj” → otwiera `InlineEditDialog` z danymi rekordu  
  • klik „Usuń” → otwiera `ConfirmDialog`  
  • emituje: `saved(member)`, `deleted(id)`
- **Walidacja:** –.
- **Propsy:**  
  ```ts
  interface Props {
    members: FamilyMember[];
  }
  ```
- **Typy zależne:** `FamilyMember`.

### InlineEditDialog.vue
- **Opis:** Modal formularza tworzenia/edycji. Reużywany; gdy `member` = null ⇒ create.
- **Główne elementy:** `Dialog`, `Form`, `InputText` (imię), `DatePicker` (data ur.), `Select` (płeć), przyciski „Zapisz” / „Anuluj”.
- **Obsługiwane interakcje:**  
  • submit → walidacja → wywołanie API (`POST`/`PUT`)  
  • emituje `saved(member)` po sukcesie
- **Walidacja (frontend + API):**  
  • first_name: wymagane, max 255 znaków  
  • birth_date: data < dziś  
  • gender: male / female
- **Propsy:**  
  ```ts
  interface Props {
    member?: FamilyMember | null;
    open: boolean;
  }
  ```
- **Typy zależne:** `FamilyMember`, `FamilyMemberPayload`.

### ConfirmDialog.vue
- **Opis:** Prostokątny dialog potwierdzający usunięcie.
- **Główne elementy:** tekst ostrzegawczy, przyciski „Usuń” / „Anuluj”.
- **Obsługiwane interakcje:**  
  • potwierdzenie → wywołanie API `DELETE`  
  • emituje `deleted(id)` po sukcesie
- **Walidacja:** –.
- **Propsy:**  
  ```ts
  interface Props {
    memberId: number;
    memberName: string;
    open: boolean;
  }
  ```

## 5. Typy
```ts
type Gender = 'male' | 'female';

interface FamilyMember {
  id: number;
  first_name: string;
  birth_date: string; // ISO, yyyy-MM-dd
  gender: Gender;
  created_at: string;
  updated_at: string;
}

interface FamilyMemberPayload {
  first_name: string;
  birth_date: string; // yyyy-MM-dd
  gender: Gender;
}

interface FamilyMemberFormState extends FamilyMemberPayload {
  errors: Partial<Record<keyof FamilyMemberPayload, string>>;
}
```

## 6. Zarządzanie stanem
- Lokalny stan w `FamilyMembers/Index.vue` przy użyciu `ref`/`reactive`.
- Operacje CRUD aktualizują listę optymistycznie, rollback przy błędzie.
- Brak konieczności global store (lista dotyczy tylko tego widoku).

## 7. Integracja API
- Biblioteka: `@inertiajs/vue3` + `@vueuse/integrations/useAxios`.
- `GET /family-members`  
  • Response: `Paginated<FamilyMember>` → wyświetlenie tabeli, obsługa paginacji (backend zwraca 15 rek./str.).
- `POST /family-members`  
  • Body: `FamilyMemberPayload`  
  • Sukces: `201` + `FamilyMember`.
- `PUT /family-members/{id}`  
  • Body: `FamilyMemberPayload`  
  • Sukces: `200` + zaktualizowany `FamilyMember`.
- `DELETE /family-members/{id}`  
  • Sukces: `204`.

## 8. Interakcje użytkownika
1. Wejście na `/family-members` → tabela wczytuje dane.
2. Klik „Dodaj” → modal formularza, walidacja, zapis → rekord pojawia się w tabeli na górze.
3. Klik „Edytuj” przy rekordzie → modal z danymi, zapis → aktualizacja wiersza.
4. Klik „Usuń” → dialog potwierdzenia, po akceptacji wiersz znika.
5. Paginacja (jeśli >15 rekordów) → pobranie kolejnej strony.

## 9. Warunki i walidacja
- Imię niepuste, ≤255 znaków.
- Data urodzenia < bieżąca data (kontrola w komponencie, `DatePicker` oraz weryfikacja błędów API `422`).
- Płeć z listy stałej.
- Duplikaty imienia dozwolone (brak requ.).

## 10. Obsługa błędów
- Błędy walidacji (`422`): wyświetlenie przy polach w `InlineEditDialog`.
- Błąd `403|404`: toast „Brak dostępu” / „Nie znaleziono”.
- Błędy sieci/500: toast ogólny + rollback optymistycznego UI.
- Retry automatyczny przy timeout (axios retry 1×).

## 11. Kroki implementacji
1. Utworzenie pliku `FamilyMembers/Index.vue` z podstawowym layoutem i pobieraniem danych.
2. Stworzenie komponentu `FamilyMemberTable.vue` z tabelą i paginacją.
3. Implementacja `InlineEditDialog.vue` z formularzem i walidacją (VeeValidate z Yup).
4. Implementacja `ConfirmDialog.vue`.
5. Integracja CRUD z API (axios helper z Inertia headers).
6. Dodanie komunikatów toast (Shadcn-vue `useToast`).
7. Testy jednostkowe komponentów (Vitest) – walidacja i emitowane zdarzenia.
8. Testy e2e (Cypress) – dodanie, edycja, usunięcie rekordu.
9. Review kodu: Pint, ESLint, Larastan (backend).
10. Deploy na środowisko staging, test manualny flow CRUD.

