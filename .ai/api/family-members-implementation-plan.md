# Plan implementacji modułu Członkowie Rodziny (US-004)

## Cel
Zaimplementować kompletną obsługę CRUD dla modelu `FamilyMember`, dzięki której zalogowany użytkownik będzie mógł:
1. dodawać nowych członków rodziny,
2. przeglądać listę oraz szczegóły członków,
3. edytować istniejące dane,
4. usuwać wybrane rekordy.

## Zakres prac backendowych

### 1. Routing
```php
// routes/web.php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('family-members', FamilyMemberController::class)
        ->except(['show'])      // widok szczegółów nie jest wymagany na MVP
        ->names('family-members');
});
```
* Pozostawiamy REST-owe nazewnictwo, co ułatwi implementację testów oraz klienta Inertia.js.

### 2. Kontroler `FamilyMemberController`
Metody:
| Metoda    | Odpowiedzialność  | Szczegóły                                                                           |
| --------- | ----------------- | ----------------------------------------------------------------------------------- |
| `index`   | Lista członków    | Paginowana lista z filtrowaniem (opcjonalnie)                                       |
| `create`  | Formularz dodania | Zwraca pusty model + dane referencyjne (np. allowed genders)                        |
| `store`   | Zapis nowego      | Walidacja przez `FamilyMemberRequest`; przypisanie `user_id` aktualnego użytkownika |
| `edit`    | Formularz edycji  | Autoryzacja przez `Policy`                                                          |
| `update`  | Aktualizacja      | Walidacja + autoryzacja                                                             |
| `destroy` | Usunięcie         | Soft-delete niewymagany – na razie hard delete + autoryzacja                        |

Wszystkie metody zwracają `Inertia::render()` lub odpowiedź JSON (w zależności od call-stacku). Najpierw implementujemy wersję Inertia.

### 3. Form Request `FamilyMemberRequest`
```php
public function rules(): array
{
    $id = $this->route('family_member')?->id;

    return [
        'first_name' => ['required', 'string', 'max:255'],
        'birth_date' => ['required', 'date', 'before:today'],
        'gender'     => ['required', 'in:male,female,other'],
    ];
}
```
* Zapewnia spójność walidacji pomiędzy `store` i `update`.
* Obsługa komunikatów w resources/lang.

### 4. Polityka `FamilyMemberPolicy`
| Akcja              | Reguła                                                                            |
| ------------------ | --------------------------------------------------------------------------------- |
| `viewAny`          | Użytkownik widzi wyłącznie swoje rekordy (w kontrolerze filtrujemy po `user_id`). |
| `create`           | Zalogowany użytkownik zawsze może dodawać.                                        |
| `update`, `delete` | `user_id === auth()->id()`                                                        |

Rejestracja w `AuthServiceProvider`.

### 5. Model
Model `FamilyMember` już istnieje i posiada:
* `protected $fillable` ✅
* `protected $casts` ✅
* Relację `user()` ✅

Do rozważenia:
* dodać `HasFactory` dla testów,
* stosować `Enum` dla płci (PHP 8.2 backed enum + cast).

### 6. Migracja
Jeśli nie istnieje, tworzymy:
```php
Schema::create('family_members', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('first_name');
    $table->date('birth_date');
    $table->string('gender', 20);
    $table->timestamps();
});
```

### 7. Testy
1. **Feature-tests** (`tests/Feature/FamilyMemberTest.php`)
   * `it_stores_family_member`
   * `it_updates_family_member`
   * `it_deletes_family_member`
   * `it_validates_required_fields`
2. **Policy-tests** (jeżeli korzystamy z Larastan, wystarczą feature-tests z policy).
3. **FormRequest-tests** – pokryte w feature.

### 8. Bezpieczeństwo & Jakość
* Użycie `@can`/`Gate::authorize` w kontrolerze.
* Mass-assignment chroniony przez `$fillable`.
* Użycie CSRF + Auth middleware.
* Larastan (level max) i Pint w CI.

### 9. Potencjalne usprawnienia (po MVP)
* Soft deletes (`SoftDeletes`) + możliwość przywracania.
* Enum `Gender` + translation layer.
* Import członków przez CSV.
* Dodatkowe pola (wzrost, waga) do dokładniejszego wyliczania kalorii.
