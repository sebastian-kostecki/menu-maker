### Kompleksowy plan testów dla Menu Maker

- **Cel**: Zapewnienie jakości MVP aplikacji „Menu Maker” w zakresie funkcjonalnym, niefunkcjonalnym i bezpieczeństwa, z pełnym pokryciem kluczowych ścieżek użytkownika.
- **Zakres**: Backend (Laravel 12, PHP 8.2) – testy Pest, Frontend (Vue 3 + Inertia.js) – Vitest + Vue Test Utils, E2E – Playwright; kolejki/job (`GenerateMealPlanJob`), zasoby API (`Resource/Collection`), polityki, walidacja, ograniczenia szybkości (rate limiting), generowanie i pobieranie PDF.

### Założenia i zależności
- Aplikacja uruchamiana w środowisku Docker przez Laravel Sail, testy i komendy przez Sail.
- Testy backendu: Pest (tylko), Larastan, Laravel Pint.
- Testy frontendu: Vitest + Vue Test Utils (Vue 3), ESLint + Prettier (lub Biome) do lint/format.
- E2E: Playwright (multi‑browser) dla krytycznych ścieżek (login, CRUD przepisów, pobranie PDF).
- Baza testowa: `testing` (konfiguracja ENV w `phpunit.xml`), migracje i seedowanie pod testy; wsparcie `--parallel` po stronie runnera.
- Kolejki: dla testów backendu `sync` (z `phpunit.xml`) dla deterministycznego wykonania jobów.
- PDF: obecnie kontroler pobiera z dysku przez `Storage`; generowanie PDF w jobie jest placeholderem.

### Środowisko testowe
- **Wymagane usługi**: MySQL (test DB), Redis (opcjonalnie dla cache/queues), przeglądarki Playwright.
- **Komendy (backend)**:
  - Uruchomienie środowiska:
    ```bash
    ./vendor/bin/sail up -d
    ```
  - Migracje + seed (testy):
    ```bash
    ./vendor/bin/sail artisan migrate:fresh --seed --env=testing
    ```
  - Testy (Pest):
    ```bash
    ./vendor/bin/sail composer test
    ```
  - Pint / PHPStan:
    ```bash
    ./vendor/bin/sail composer pint-test
    ./vendor/bin/sail composer phpstan
    ```
- **Komendy (frontend/E2E)**:
  - Instalacja zależności i Playwright drivers:
    ```bash
    npm ci
    npx playwright install --with-deps
    ```
  - Testy jednostkowe Vue i E2E:
    ```bash
    npm run test:unit
    E2E_BASE_URL=http://localhost npm run test:e2e
    ```

### Strategia i poziomy testów
- **Static analysis**: Larastan (level 5) – brak nowych błędów; dbałość o typy i relacje. ESLint dla frontendu.
- **Style**: Laravel Pint – PSR-12; brak naruszeń. Prettier/Biome dla frontendu.
- **Unit (backend)**:
  - Logika pomocnicza, selektory/modelowe scope’y: `Recipe::scopeSearch`, `scopeByCategory`, `scopeOrderByField`, `MealPlan::scopeOwnedBy`.
  - `RecipeIngredient` casty i mapping.
- **Integration / Feature (backend)**:
  - HTTP ścieżki (Inertia i JSON): kontrolery, walidacja, polityki, rate limiting, paginacja/sortowanie/filtry.
  - Relacje i synchronizacja (pivot `recipe_ingredients`).
  - Kolejki/job: ścieżki sukcesu i porażki, logowanie przebiegu (`LogsMealPlan`).
  - Dostęp do PDF (pobranie/404).
- **Unit (frontend)**:
  - Komponenty Vue (render, props, zdarzenia), composables, formatowanie danych, walidacja formularzy, i18n stringi.
- **E2E (Playwright)**:
  - Krytyczne ścieżki: logowanie, CRUD przepisu, generacja/regeneracja planu, pobranie PDF; Smoke dla nawigacji i autoryzacji.
- **Security**:
  - Autoryzacja przez polityki, właścicielstwo zasobów.
  - Ochrona przed masowym przypisaniem (fillable) – test negatywny.
  - Ograniczenia szybkości (Login, Generate/Regenerate meal plan).
- **Performance (sanity)**:
  - Paginacje (15 domyślnie), filtry i sortowanie na większych zbiorach (np. 300 przepisów).
- **UX/Accessiblity (wyrywkowo)**:
  - Komunikaty walidacyjne (treść, i18n w ramach EN), spójność odpowiedzi Inertia; podstawowe a11y (role/label, focus management).

### Stabilność i deterministyczność testów (backend)
- Czas: `Carbon::setTestNow()` w testach dat (`start_date`, `generated_at`).
- Rate limit: czyszczenie przed/po scenariuszu (`RateLimiter::clear($key)`), osobne testy progu 5/h oraz wyjątek dla `force=true`.
- Kolejki: w testach kontrolerów `Bus::fake()`/`Queue::fake()` do asercji dispatchu; w testach jobów realne wykonanie w trybie `sync`.

### Inertia – kontrakt odpowiedzi
- Dla ścieżek Inertia asercje na nazwę komponentu i strukturę props (np. payload JSON), obok wariantu `expectsJson()`.

### Dane testowe
- Fabryki: `User`, `Recipe`, `Ingredient`, `Unit`, `MealPlan`, `Meal`, `LogsMealPlan`.
- Minimalny seed testowy:
  - `Unit`: `g`, `kg`, `ml`, `l`, `pcs` z `conversion_factor_to_base`.
  - `Ingredient`: min. 10 (różne).
  - `Recipe`: po 5–10 w kategoriach `breakfast`, `supper`, `dinner`, z różnymi kaloriami i porcjami; składniki bez duplikatów.
  - `FamilyMember`: kilka rekordów na usera (różne daty urodzenia i płeć).
  - `MealPlan`: kombinacje statusów: `pending`, `processing`, `done`, `error`.

### Równoległość i strategia baz danych
- Uruchamianie testów backendu z `--parallel` (Pest).
- Strategia DB:
  - Unit: SQLite in‑memory (tam gdzie możliwe) dla szybkości.
  - Feature/HTTP: MySQL (zachowanie constraintów, kolacji i paginacji).
- Izolacja danych: reset migracji i seed dla środowiska `testing` przed kluczowymi zestawami.

### Kryteria wejścia/wyjścia
- **Wejście**: środowisko testowe dostępne, migracje i seed success, Playwright browsers zainstalowane, kolejki w trybie `sync`, konfiguracja ENV w `phpunit.xml` i Pest aktywne.
- **Wyjście**:
  - 100% przejścia testów krytycznych (auth, CRUD przepisów, generacja planów posiłków, PDF).
  - Brak błędów krytycznych Larastan i brak naruszeń Pint/ESLint.
  - Pokrycie kluczowych ścieżek (min. 80% Feature dla MVP) i minimalny próg coverage ogółem (np. 70–80%).

### Scenariusze testowe (przykładowe ID i akceptacja)

- **AUTH**
  - AUTH-001: Login poprawny.
  - AUTH-002: Login błędny + rate limit.
  - AUTH-003: Rejestracja (unikalny email).

- **PROFILE**
  - PROF-001: Update profilu (zmiana email → reset weryfikacji).
  - PROF-002: Usunięcie konta (wymaga hasła, logout, invalidacja sesji).

- **FAMILY MEMBERS**
  - FM-001: Index filtruje po `user_id` (policy).
  - FM-002: Create z walidacją.
  - FM-003: Update/Delete tylko właściciel.

- **RECIPES**
  - REC-001..005: filtry/sort, walidacje, pivot sync, soft deletes.

- **MEAL PLANS – lista i szczegóły**
  - MPLAN-001..003: filtry/sort/paginacja, relacje, JSON/Inertia.

- **MEAL PLANS – generowanie**
  - MPLAN-010..012: store, regeneracja, destroy, rate-limit/processing guards.

- **MEAL PLANS – job i logi**
  - JOB-001..003: sukces/błąd/idempotencja.

- **PDF**
  - PDF-001..003: 404 dla nie‑done/brak pliku, poprawny Content-Type/nazwa.

- **POLICY / SECURITY**
  - SEC-001..004: zasady właścicielstwa i ograniczenia operacji.

- **VALIDATION MESSAGES**
  - VAL-001..002: treść i format błędów (JSON/Inertia).

- **PERFORMANCE (sanity)**
  - PERF-001..002: paginacja i brak N+1.

- **FRONTEND UNIT (Vitest)**
  - FE-UNIT-001..004: komponenty, formularze, composables, a11y.

- **E2E (Playwright)**
  - E2E-001..004: login, CRUD przepisu, generacja/regeneracja, PDF.

### Automatyzacja – struktura testów (propozycja)
- `tests/Feature/Auth/AuthenticationTest.php`
- `tests/Feature/Profile/ProfileTest.php`
- `tests/Feature/FamilyMember/FamilyMemberCrudTest.php`
- `tests/Feature/Recipes/RecipeCrudTest.php`
- `tests/Feature/MealPlans/MealPlanIndexTest.php`
- `tests/Feature/MealPlans/MealPlanShowTest.php`
- `tests/Feature/MealPlans/MealPlanLifecycleTest.php`
- `tests/Feature/MealPlans/MealPlanPdfTest.php`
- `tests/Feature/Policies/AuthorizationTest.php`
- `tests/Unit/Models/RecipeScopesTest.php`
- `tests/Unit/Models/MealPlanScopesTest.php`
- `tests/Unit/Jobs/GenerateMealPlanJobTest.php`
- `tests/e2e/*.spec.ts` (Playwright)
- `tests/unit-frontend/**/*.spec.ts` (Vitest + VTU)

### Matryca śledzenia (traceability)
- jak wyżej, mapowanie SEC/REC/MPLAN/JOB/PDF/VAL/PERF/FE/E2E.

### Raportowanie
- CI: backend (`composer test`, `composer pint-test`, `composer phpstan`), frontend (`npm run lint`, `npm run test:unit`, `npm run build`), E2E (`npm run test:e2e`).
- Artefakty: raporty JUnit (backend i Playwright), coverage (PCOV/Xdebug → `clover.xml` dla backendu; `lcov` dla frontendu), liczba testów.
- Krytyczność: FAIL któregokolwiek etapu blokuje merge PR.

### Ryzyka i mitigacje
- Placeholder generowania PDF/planów: testy weryfikują stan danych i przepływ, nie jakość PDF.
- E2E: minimalna liczba stabilnych testów Playwright; wykorzystanie `storageState` do pre‑autoryzacji dla skrócenia czasu.

### Jak uruchamiać lokalnie (skrót)
- **Start**: `./vendor/bin/sail up -d`
- **Backend (Pest)**: `./vendor/bin/sail composer test`
- **Frontend (Vitest)**: `npm run test:unit`
- **E2E (Playwright)**: `E2E_BASE_URL=http://localhost npm run test:e2e`
- **Jakość**: `./vendor/bin/sail composer pint-test`, `./vendor/bin/sail composer phpstan`, `npm run lint`
