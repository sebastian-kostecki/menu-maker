# Dokument wymagań produktu (PRD) - Menu Maker

## 1. Przegląd produktu
Menu Maker to aplikacja webowa wspierająca rodziny w planowaniu posiłków na cały tydzień. Umożliwia użytkownikom zapisywanie własnych przepisów kulinarnych, przechowywanie słownika składników oraz automatyczne generowanie tygodniowego jadłospisu dopasowanego do profilu rodziny. System wykorzystuje sztuczną inteligencję do skalowania ilości składników na podstawie kaloryczności przepisu, liczby porcji i danych o członkach rodziny. Wynikiem generacji jest pojedynczy plik PDF zawierający jadłospis oraz skonsolidowaną listę zakupów.

## 2. Problem użytkownika
Rodziny często posiadają ograniczony czas na planowanie posiłków oraz trudności w dostosowywaniu przepisów do liczby domowników i ich indywidualnych potrzeb kalorycznych. Ręczne obliczenia składników i tworzenie listy zakupów są czasochłonne, podatne na błędy i prowadzą do nadmiarowych zakupów. Użytkownicy potrzebują narzędzia, które zautomatyzuje proces planowania, uprości zakupy i zredukuje marnotrawstwo żywności.

## 3. Wymagania funkcjonalne
1. Konto użytkownika oparte na adresie e-mail:
   - Rejestracja, logowanie, wylogowanie.
   - Reset hasła poprzez e-mail.
2. Profil rodziny:
   - Dodawanie, edycja danych członków (imię, data urodzenia, płeć).
3. Zarządzanie przepisami (CRUD):
   - Pola obowiązkowe: nazwa, kategoria (śniadanie/obiad/kolacja), lista składników (nazwa, jednostka g|kg|ml|l|szt., ilość), sposób przygotowania, kaloryczność całkowita, liczba porcji.
4. Generowanie tygodniowego jadłospisu (7 dni × 3 posiłki):
   - Losowy dobór przepisów bez powtórzeń w obrębie tygodnia.
   - Nieograniczona liczba regeneracji jadłospisu.
5. Skalowanie ilości składników przez AI:
   - Obliczenia bazujące na kaloriach przepisu, liczbie porcji i profilu rodziny.
6. Lista zakupów:
   - Sumowanie składników, konwersja >1000 g → kg oraz >1000 ml → l, zaokrąglenie do 2 miejsc po przecinku.
   - Sortowanie alfabetyczne pozycji.
7. Eksport PDF:
   - Jeden dokument: najpierw tabela jadłospisu (dzień, kategoria, nazwa, składniki, przygotowanie), następnie lista zakupów.
8.  Testy funkcjonalne pokrywające kluczowe ścieżki (rejestracja, logowanie, CRUD przepisów, generowanie jadłospisu).

## 4. Granice produktu
- Brak importu przepisów z URL.
- Brak obsługi multimediów (zdjęcia, filmy).
- Brak udostępniania przepisów innym użytkownikom.
- Brak funkcji społecznościowych i kopii zapasowych.
- Hosting i szczegóły infrastruktury poza zakresem MVP.
- Brak edycji wygenerowanego jadłospisu lub listy zakupów.

## 5. Historyjki użytkowników

| ID     | Tytuł                      | Opis                                                                                                                                       | Kryteria akceptacji                                                                                                                                                                                              |
| ------ | -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| US-001 | Rejestracja konta          | Jako nowy użytkownik chcę zarejestrować konto przy użyciu adresu e-mail, aby móc korzystać z aplikacji.                                    | 1. Formularz rejestracji wymaga unikalnego adresu e-mail i hasła. 2. Po wysłaniu formularza konto jest tworzone i użytkownik jest automatycznie zalogowany. 3. Nieprawidłowe dane wyświetlają komunikaty błędów. |
| US-002 | Logowanie                  | Jako użytkownik chcę zalogować się przy użyciu e-maila i hasła, aby uzyskać dostęp do swojego konta.                                       | 1. Uwierzytelnienie przy poprawnych danych. 2. Błędne dane zwracają czytelny błąd. 3. Sesja wygasa po 24 h braku aktywności.                                                                                     |
| US-003 | Reset hasła                | Jako użytkownik chcę zresetować hasło przez e-mail, jeśli je zapomnę.                                                                      | 1. System wysyła wiadomość z linkiem resetującym. 2. Link jest ważny 1 h. 3. Po zmianie hasła użytkownik może się zalogować nowym hasłem.                                                                        |
| US-004 | Dodawanie członków rodziny | Jako rodzic chcę dodać członków rodziny (imię, data urodzenia, płeć), aby przepisy były skalowane do naszych potrzeb.                      | 1. Formularz waliduje wymagane pola. 2. Dodani członkowie są widoczni w profilu. 3. Dane można edytować lub usuwać.                                                                                              |
| US-005 | Dodawanie przepisu         | Jako użytkownik chcę zapisać nowy przepis z pełnymi detalami, aby móc go wykorzystać później.                                              | 1. Wszystkie wymagane pola są walidowane. 2. Po zapisaniu przepis widnieje w liście przepisów. 3. Brak duplikatów składników nie blokuje zapisu.                                                                 |
| US-006 | Przegląd i edycja przepisu | Jako użytkownik chcę podejrzeć szczegóły przepisu i w razie potrzeby je edytować.                                                          | 1. Widok szczegółów wyświetla wszystkie pola przepisu. 2. Edycja aktualizuje dane i widok. 3. Usunięcie przepisu wymaga potwierdzenia.                                                                           |
| US-008 | Generowanie jadłospisu     | Jako użytkownik chcę jednym kliknięciem wygenerować tygodniowy jadłospis bez powtórzeń przepisów.                                          | 1. System wybiera losowo przepisy według kategorii. 2. Generacja trwa <10 s. 3. Wynik pojawia się w interfejsie z opcją pobrania PDF.                                                                            |
| US-009 | Regeneracja jadłospisu     | Jako użytkownik chcę móc wielokrotnie regenerować jadłospis, jeśli wynik mi nie odpowiada.                                                 | 1. Każde kliknięcie "Regeneruj" tworzy nowy zestaw przepisów. 2. Brak limitu regeneracji. 3. Poprzednie wersje nie są zapisywane.                                                                                |
| US-010 | Pobranie PDF               | Jako użytkownik chcę pobrać jeden plik PDF zawierający jadłospis i listę zakupów, aby mieć go offline.                                     | 1. PDF zawiera najpierw tabelę jadłospisu, potem listę zakupów. 2. PDF otwiera się poprawnie w przeglądarce. 3. Dane w PDF odpowiadają ostatniej wygenerowanej wersji.                                           |
| US-011 | Automatyczna lista zakupów | Jako użytkownik chcę mieć automatycznie zsumowaną listę zakupów z konwersją jednostek, aby szybko zrobić zakupy.                           | 1. Ilości są sumowane według składników. 2. Konwersja jednostek g↔kg i ml↔l >1000 odbywa się poprawnie. 3. Zaokrąglenie wynosi 2 miejsca po przecinku.                                                           |
| US-012 | Skalowanie składników      | Jako użytkownik chcę, aby aplikacja automatycznie dostosowała ilości składników do mojej rodziny, aby posiłki były odpowiednio porcjowane. | 1. Ilości są obliczone na podstawie kaloryczności przepisu i profilu rodziny. 2. Wyniki są prezentowane w przepisach i liście zakupów.                                                                           |

## 6. Metryki sukcesu
- 90 % aktywnych użytkowników ma wypełniony profil rodziny w ciągu 7 dni od rejestracji.
- 75 % użytkowników generuje co najmniej jeden jadłospis tygodniowo.
- Średni czas generowania jadłospisu <1 min.
- 0 krytycznych błędów w podstawowych ścieżkach podczas testów akceptacyjnych.
- MVP wdrożone i dostępne publicznie w ciągu 1 miesiąca od rozpoczęcia prac.
