# Architektura UI dla Menu Maker

## 1. Przegląd struktury UI

Menu Maker to jednojęzyczna (PL) aplikacja SPA oparta o Laravel + Vue 3 + Inertia.js. Interfejs składa się z:

• Lewostronnego, responsywnego sidebara jako głównej nawigacji.
• Nagłówka z szybkim dostępem do profilu i wylogowania.
• Zestawu widoków odpowiadających kluczowym funkcjom: dashboard, przepisy, jadłospisy, członkowie rodziny, uwierzytelnienie.
• Wspólnych komponentów (toasty, modale, pagination, filtry), zapewniających spójne UX, dostępność WCAG AA i bezpieczeństwo (CSRF, polityki dostępu).

## 2. Lista widoków

### 2.1 Dashboard
- **Ścieżka:** `/`
- **Cel:** Szybki przegląd stanu konta i skróty do kluczowych akcji.
- **Kluczowe informacje:** liczba przepisów, liczba jadłospisów, ostatni wygenerowany PDF.
- **Kluczowe komponenty:** `StatCard` × 2, `QuickActionButton` („Dodaj przepis”, „Wygeneruj jadłospis”), `Toast`.
- **UX/Accessibility/Security:** karty w 2-kolumnowej siatce (1 kolumna < lg); przyciski mają aria-label, focus-ring; akcja generacji wymaga potwierdzenia modalem.

### 2.2 Przepisy – lista
- **Ścieżka:** `/recipes`
- **Cel:** Przegląd zapisanych przepisów, filtrowanie i paginacja.
- **Kluczowe informacje:** nazwa, kategoria (badge kolor), kaloryczność, liczba porcji, akcje (podgląd, edycja, usuń).
- **Kluczowe komponenty:** `CategoryFilterDropdown`, `SearchInput`, `ServerPagination`, `RecipeRow`, `LocalStorageStateSync`.
- **UX/A11y/Security:** sortowanie domyślnie alfabetyczne, ustawienia filtrów zapamiętywane w `localStorage`; akcja usunięcia wymaga modalu z potwierdzeniem i policy `delete`.

### 2.3 Przepisy – szczegóły
- **Ścieżka:** `/recipes/:id`
- **Cel:** Wyświetlenie pełnych detali przepisu.
- **Kluczowe informacje:** nazwa, kategoria, lista składników (grid-karty), instrukcje, metadane kaloryczne/porcje.
- **Kluczowe komponenty:** `IngredientCardGrid`, `RecipeMetaBadge`, `ActionBar` (Edytuj / Usuń).
- **UX/A11y/Security:** responsywna siatka składników (1-3 kol.); policy `view` + `delete`; brak edycji inline.

### 2.4 Przepisy – formularz (Dodaj/Edytuj)
- **Ścieżki:** `/recipes/new`, `/recipes/:id/edit`
- **Cel:** Tworzenie lub aktualizacja przepisu.
- **Kluczowe informacje:** wszystkie pola wymagane przez API.
- **Kluczowe komponenty:** `RecipeForm`, `IngredientSelector`, `ValidationErrorList`.
- **UX/A11y/Security:** pojedyncza strona, klient otrzymuje reguły walidacji z FormRequest przez Inertia; CSRF token; focus-first invalid field.

### 2.5 Jadłospisy – lista
- **Ścieżka:** `/meal-plans`
- **Cel:** Historia wszystkich wygenerowanych jadłospisów.
- **Kluczowe informacje:** zakres dat, status, liczba posiłków, akcje (podgląd, pobierz PDF, regeneruj, usuń).
- **Kluczowe komponenty:** `DateRangeFilter`, `StatusTag`, `ServerPagination`, `ActionDropdown`.
- **UX/A11y/Security:** filtry zapisywane w localStorage; policy `view`/`delete`; operacje regeneruj/usuń poprzedza `ConfirmDialog`.

### 2.6 Jadłospis – szczegóły
- **Ścieżka:** `/meal-plans/:id`
- **Cel:** Tabela 7 × 3 z przypisanymi przepisami, akcje dodatkowe.
- **Kluczowe informacje:** komórki dnia/kategorii, link do przepisu, status generacji, przycisk pobrania PDF.
- **Kluczowe komponenty:** `MealPlanGrid`, `CategoryColorLegend`, `ProgressPoller`, `DownloadButton`.
- **UX/A11y/Security:** polling co 3 s statusu; PDF download przy status="done"; przycisk disabled podczas limitu.

### 2.7 Członkowie rodziny
- **Ścieżka:** `/family-members`
- **Cel:** CRUD członków rodziny.
- **Kluczowe informacje:** imię, data urodzenia, płeć.
- **Kluczowe komponenty:** `FamilyMemberTable`, `InlineEditDialog`, `ConfirmDialog`.
- **UX/A11y/Security:** walidacja daty < today; policy owner; brak paginacji (typowo < 10 rekordów).

### 2.8 Uwierzytelnienie
- **Ścieżki:** `/login`, `/register`, `/forgot-password`, `/reset-password/:token`
- **Cel:** Zarządzanie dostępem do aplikacji.
- **Kluczowe informacje:** formularze e-mail/hasło, feedback błędów.
- **Kluczowe komponenty:** `AuthForm`, `PasswordStrengthMeter` (register), `Toast`.
- **UX/A11y/Security:** pola z `autocomplete`; CSRF; rate-limit 5 prób/min; aria-labels.

### 2.9 Strony błędów
- **Ścieżki:** `/*` 404, `/error` 500
- **Cel:** Informowanie o błędach.
- **Kluczowe komponenty:** `ErrorIllustration`, `ReturnHomeButton`.

## 3. Mapa podróży użytkownika

1. **Rejestracja / Logowanie** → `/register`/`/login`
2. **Dashboard** → przegląd statystyk i przycisk „Dodaj przepis”.
3. **Dodanie przepisu**
   ‑ klik „Dodaj przepis” → `/recipes/new` → zapis → redirect `/recipes/:id`.
4. (Opcjonalnie) **Dodanie kilku kolejnych przepisów** przez `/recipes` + `+`.
5. **Generacja jadłospisu**
   ‑ klik na „Wygeneruj jadłospis” (dashboard) → modal potwierdzenia → POST `/meal-plans` (status=pending) → redirect `/meal-plans/:id`.
   ‑ Komponent `ProgressPoller` odświeża status; po "done" aktywuje „Pobierz PDF”.
6. **Pobranie PDF** → klik → GET `/meal-plans/{id}/pdf` (download).
7. **Przegląd historii jadłospisów** → `/meal-plans` → filtr dat → detale.
8. **Zarządzanie rodziną** → `/family-members` → CRUD.
9. **Wylogowanie** → klik w avatar w nagłówku.

## 4. Układ i struktura nawigacji

```
Sidebar (fixed)
├── Dashboard
├── Przepisy
│   ├── Lista
│   └── Formularz / Detal (bez pozycji w menu)
├── Jadłospisy
│   ├── Lista
│   └── Detal (bez pozycji w menu)
└── Członkowie rodziny

Header (sticky)
└── Avatar dropdown (profil, wyloguj)
```

• Sidebar pokazuje ikony+etykiety ≥ 1024 px, tylko ikony < 1024 px, hamburger < 640 px.
• Active route podświetlona; aria-current="page".
• Breadcrumbs na widokach detalu/formularza dla dodatkowej orientacji.

## 5. Kluczowe komponenty (wielokrotnego użycia)

| Komponent               | Opis                                    | UX/A11y                                          |
| ----------------------- | --------------------------------------- | ------------------------------------------------ |
| `Sidebar`               | Nawigacja główna z auto-collapse        | aria-label="Główna nawigacja"; rola="navigation" |
| `StatCard`              | Kafelek z liczbą + ikoną                | kontrast ≥ 4.5:1                                 |
| `QuickActionButton`     | Duże CTA na dashboard                   | focus-ring, aria-pressed                         |
| `CategoryBadge`         | Kolorowy badge kategorii przepisu       | opis kolorów w legendzie                         |
| `ServerPagination`      | Numery + strzałki, auto-scroll top      | aria-label="Paginacja"                           |
| `ConfirmDialog`         | Modal potwierdzający                    | rola="dialog", fokus w pętli                     |
| `Toast`                 | Globalne komunikaty (shadcn‐vue)        | timeout 5 s; role="status"                       |
| `ProgressPoller`        | Polling statusu z API                   | cleanup on unmount                               |
| `LocalStorageStateSync` | Hook do zapisu/odczytu filtrów/sortowań | obsługa JSON parse errors                        |
| `FormField`             | Uniwersalny input z label + error       | aria-describedby                                 |

## 6. Mapowanie historyjek użytkownika → widoki / elementy UI

| ID     | Widok(i)                                                               | Elementy UI                                         |
| ------ | ---------------------------------------------------------------------- | --------------------------------------------------- |
| US-001 | `/register`                                                            | `AuthForm`, `Toast`                                 |
| US-002 | `/login`                                                               | `AuthForm`, `Toast`                                 |
| US-003 | `/forgot-password` `/reset-password`                                   | `AuthForm`                                          |
| US-004 | `/family-members`                                                      | `FamilyMemberTable`, `ConfirmDialog`                |
| US-005 | `/recipes/new`                                                         | `RecipeForm`                                        |
| US-006 | `/recipes/:id` `/recipes/:id/edit`                                     | `IngredientCardGrid`, `RecipeForm`                  |
| US-008 | `/meal-plans/:id`                                                      | `ConfirmDialog`, `ProgressPoller`, `DownloadButton` |
| US-009 | `/meal-plans/:id` (Regeneruj), `/meal-plans` (Lista)                   | `ConfirmDialog`                                     |
| US-010 | `/meal-plans/:id`                                                      | `DownloadButton`                                    |
| US-011 | `/meal-plans/:id` (PDF)                                                | renderowane w back-end, UI zapewnia link            |
| US-012 | Back-end – brak bezpośredniego UI, wyniki widoczne w `/meal-plans/:id` |

## 7. Rozważone przypadki brzegowe i błędy

• Brak przepisów → pusta-state z linkiem „Dodaj przepis”.
• Brak jadłospisów → pusta-state z CTA generacji.
• Przerwane generowanie (status="error") → toast błędu + przycisk „Ponów”.
• Endpoint 4xx/5xx → globalny interceptor toastów.
• Nieważny token resetu hasła → redirect do `/forgot-password` z komunikatem.
• Rate-limit PDF → przycisk disabled przez timer frontend + komunikat API.
• Nieautoryzowany dostęp → redirect `/login` z flash `You need to log in`.

---

Architektura UI spełnia wymagania PRD, wykorzystuje punkty końcowe API zgodnie z planem i uwzględnia notatki z sesji, zapewniając spójne doświadczenie użytkownika, podstawową dostępność WCAG oraz bezpieczeństwo.
