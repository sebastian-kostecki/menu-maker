# Dokument wymagań produktu (PRD) - Menu Maker

## 1. Przegląd produktu
Menu Maker to aplikacja webowa wspierająca rodziny w planowaniu posiłków na cały tydzień. Umożliwia użytkownikom zapisywanie własnych przepisów kulinarnych i automatyczne losowanie tygodniowego jadłospisu, który przed przeliczeniem użytkownik przegląda i poprawia jako szkic (podmienia przepisy, zostawia posiłki puste, gotuje jedno danie na dwa posiłki). Po zatwierdzeniu szkicu system wykorzystuje sztuczną inteligencję do skalowania ilości składników na podstawie kaloryczności przepisu, liczby porcji i danych o członkach rodziny. Wynikiem jest pojedynczy plik PDF zawierający jadłospis, skonsolidowaną listę zakupów i opcjonalnie pełne przepisy.

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
4. Generowanie tygodniowego jadłospisu (7 dni × 3 posiłki) jako szkicu:
   - Generowanie losuje przepisy według kategorii, bez powtórzeń w obrębie tygodnia, i tworzy szkic: przepisy bez ilości i bez listy zakupów.
   - Gdy w kategorii jest mniej niż 7 przepisów, pozostałe posiłki tej kategorii zostają puste, a szkic wskazuje kategorie z brakami. Generowanie wymaga co najmniej jednego przepisu.
   - Nieograniczona liczba ponownych losowań szkicu.
5. Edycja szkicu przed skalowaniem:
   - Podmiana przepisu w dowolnym posiłku na dowolny inny przepis użytkownika, także z innej kategorii (np. przepis ze śniadania na kolację); pora dnia posiłku, a nie kategoria przepisu, decyduje o udziale kalorii w skalowaniu.
   - Przeciągnięcie posiłku myszą na inny posiłek szkicu zamienia oba przepisy miejscami, a przeciągnięcie na pusty posiłek przenosi przepis i zostawia puste miejsce.
   - Pusty posiłek („nie robimy”) i ponowne wypełnienie pustego posiłku przepisem.
   - Obiad gotowany raz na dwa dni: obiad dnia D+1 może być powtórką obiadu z dnia D.
   - Kolacja na śniadanie: śniadanie dnia D+1 może być resztką kolacji z dnia D; na ekranie i w PDF ma kolor śniadania i dopisek „z kolacji”.
   - Powtórki i resztki tworzy wyłącznie użytkownik (losowanie ich nie tworzy) i obejmują tylko pary posiłków, bez łańcuchów na 3 i więcej dni. Zmiana przepisu posiłku gotowanego opróżnia posiłek, który z niego korzysta; ponowne losowanie usuwa wszystkie powiązania.
   - Zatwierdzenie szkicu (wymaga co najmniej jednego posiłku z przepisem) uruchamia skalowanie. Zatwierdzony jadłospis można cofnąć do szkicu z zachowanymi posiłkami; ponowne zatwierdzenie przelicza cały jadłospis.
6. Skalowanie ilości składników przez AI:
   - Obliczenia bazujące na kaloriach przepisu, liczbie porcji i profilu rodziny.
   - Danie gotowane raz na dwa posiłki jest skalowane na oba posiłki; drugi posiłek nie ma własnych składników.
7. Lista zakupów:
   - Sumowanie składników, konwersja >1000 g → kg oraz >1000 ml → l, zaokrąglenie do 2 miejsc po przecinku.
   - Sortowanie alfabetyczne pozycji.
   - Pusty posiłek nie dodaje składników; danie gotowane raz na dwa posiłki występuje raz, w podwójnej ilości.
8. Eksport PDF:
   - Jeden dokument: najpierw tabela jadłospisu (dzień, kategoria, nazwa, składniki), następnie lista zakupów.
   - Przy pobieraniu użytkownik wybiera, czy dołączyć przepisy. Dołączone przepisy (nazwa, kategoria, składniki z przeskalowanymi ilościami, sposób przygotowania) są na osobnych stronach, każdy raz, nawet gdy występuje w tygodniu kilka razy.
9.  Testy funkcjonalne pokrywające kluczowe ścieżki (rejestracja, logowanie, CRUD przepisów, generowanie jadłospisu).

## 4. Granice produktu
- Brak importu przepisów z URL.
- Brak obsługi multimediów (zdjęcia, filmy).
- Brak udostępniania przepisów innym użytkownikom.
- Brak funkcji społecznościowych i kopii zapasowych.
- Hosting i szczegóły infrastruktury poza zakresem MVP.
- Brak bezpośredniej edycji zatwierdzonego jadłospisu i listy zakupów: zmiany wymagają cofnięcia do szkicu i ponownego zatwierdzenia (przeliczenia).
- Brak historii poprzednich wersji jadłospisu.

## 5. Historyjki użytkowników

| ID     | Tytuł                      | Opis                                                                                                                                       | Kryteria akceptacji                                                                                                                                                                                              |
| ------ | -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| US-001 | Rejestracja konta          | Jako nowy użytkownik chcę zarejestrować konto przy użyciu adresu e-mail, aby móc korzystać z aplikacji.                                    | 1. Formularz rejestracji wymaga unikalnego adresu e-mail i hasła. 2. Po wysłaniu formularza konto jest tworzone i użytkownik jest automatycznie zalogowany. 3. Nieprawidłowe dane wyświetlają komunikaty błędów. |
| US-002 | Logowanie                  | Jako użytkownik chcę zalogować się przy użyciu e-maila i hasła, aby uzyskać dostęp do swojego konta.                                       | 1. Uwierzytelnienie przy poprawnych danych. 2. Błędne dane zwracają czytelny błąd. 3. Sesja wygasa po 24 h braku aktywności, chyba że użytkownik zaznaczył „Zapamiętaj mnie”.                                                                                     |
| US-003 | Reset hasła                | Jako użytkownik chcę zresetować hasło przez e-mail, jeśli je zapomnę.                                                                      | 1. System wysyła wiadomość z linkiem resetującym. 2. Link jest ważny 1 h. 3. Po zmianie hasła użytkownik może się zalogować nowym hasłem.                                                                        |
| US-004 | Dodawanie członków rodziny | Jako rodzic chcę dodać członków rodziny (imię, data urodzenia, płeć), aby przepisy były skalowane do naszych potrzeb.                      | 1. Formularz waliduje wymagane pola. 2. Dodani członkowie są widoczni w profilu. 3. Dane można edytować lub usuwać.                                                                                              |
| US-005 | Dodawanie przepisu         | Jako użytkownik chcę zapisać nowy przepis z pełnymi detalami, aby móc go wykorzystać później.                                              | 1. Wszystkie wymagane pola są walidowane. 2. Po zapisaniu przepis widnieje w liście przepisów. 3. Brak duplikatów składników nie blokuje zapisu.                                                                 |
| US-006 | Przegląd i edycja przepisu | Jako użytkownik chcę podejrzeć szczegóły przepisu i w razie potrzeby je edytować.                                                          | 1. Widok szczegółów wyświetla wszystkie pola przepisu. 2. Edycja aktualizuje dane i widok. 3. Usunięcie przepisu wymaga potwierdzenia.                                                                           |
| US-008 | Generowanie jadłospisu     | Jako użytkownik chcę jednym kliknięciem wylosować tygodniowy jadłospis bez powtórzeń przepisów i zobaczyć go jako szkic przed przeliczeniem. | 1. System wybiera losowo przepisy według kategorii. 2. Generacja trwa <10 s. 3. Wynik pojawia się w interfejsie jako szkic, bez ilości i listy zakupów. 4. Gdy w kategorii brakuje przepisów, pozostałe posiłki są puste, a szkic wskazuje kategorie z brakami. |
| US-009 | Ponowne losowanie szkicu   | Jako użytkownik chcę móc wielokrotnie wylosować szkic od nowa, jeśli wynik mi nie odpowiada.                                               | 1. Każde kliknięcie „Losuj ponownie” (po potwierdzeniu) tworzy nowy zestaw przepisów. 2. Brak limitu losowań. 3. Poprzednie wersje nie są zapisywane.                                                            |
| US-010 | Pobranie PDF               | Jako użytkownik chcę pobrać jeden plik PDF zawierający jadłospis i listę zakupów, a w razie potrzeby pełne przepisy, aby mieć go offline. | 1. PDF zawiera najpierw tabelę jadłospisu, potem listę zakupów. 2. PDF otwiera się poprawnie w przeglądarce. 3. Dane w PDF odpowiadają ostatniej zatwierdzonej wersji. 4. Przy pobieraniu wybieram „Z przepisami” albo „Bez przepisów”; dołączony przepis jest na osobnej stronie, raz, nawet gdy występuje kilka razy. |
| US-011 | Automatyczna lista zakupów | Jako użytkownik chcę mieć automatycznie zsumowaną listę zakupów z konwersją jednostek, aby szybko zrobić zakupy.                           | 1. Ilości są sumowane według składników. 2. Konwersja jednostek g↔kg i ml↔l >1000 odbywa się poprawnie. 3. Zaokrąglenie wynosi 2 miejsca po przecinku.                                                           |
| US-012 | Skalowanie składników      | Jako użytkownik chcę, aby aplikacja automatycznie dostosowała ilości składników do mojej rodziny, aby posiłki były odpowiednio porcjowane. | 1. Ilości są obliczone na podstawie kaloryczności przepisu i profilu rodziny. 2. Wyniki są prezentowane w przepisach i liście zakupów.                                                                           |
| US-013 | Zatwierdzenie szkicu       | Jako użytkownik chcę zatwierdzić poprawiony szkic, aby dopiero wtedy aplikacja przeliczyła ilości i przygotowała listę zakupów.           | 1. „Zatwierdź” uruchamia skalowanie, a jadłospis kończy się z ilościami, listą zakupów i PDF. 2. Szkic bez żadnego posiłku z przepisem nie może zostać zatwierdzony. 3. „Wróć do szkicu” przywraca zatwierdzony jadłospis do szkicu z tymi samymi posiłkami, a ponowne zatwierdzenie przelicza całość. |
| US-014 | Podmiana przepisu          | Jako użytkownik chcę w szkicu zmienić wylosowany przepis na inny ze swoich przepisów, aby tydzień odpowiadał naszym planom.                | 1. „Zmień” pokazuje wszystkie moje przepisy, z kategorią posiłku na początku i pozostałymi pod nagłówkami, z wyszukiwaniem po nazwie. 2. Przepisy już użyte w tygodniu mają oznaczenie dni, ale można je wybrać. 3. Wybór zapisuje się od razu i przetrwa odświeżenie strony. |
| US-015 | Pusty posiłek              | Jako użytkownik chcę oznaczyć wybrany posiłek jako pusty (np. nie robimy śniadania), aby nie kupować na niego składników.                  | 1. W szkicu mogę wybrać „Nie robimy” albo wypełnić pusty posiłek przepisem. 2. Po zatwierdzeniu pusty posiłek ma na ekranie i w PDF opis „Bez posiłku”. 3. Pusty posiłek nie dodaje składników do listy zakupów. |
| US-016 | Obiad na dwa dni           | Jako użytkownik chcę ugotować obiad raz i zjeść go dwa dni z rzędu, aby gotować co drugi dzień.                                           | 1. W szkicu obiad dnia D+1 mogę ustawić „Jak wczoraj”. 2. Oba dni pokazują to samo danie z oznaczeniami „Na 2 dni” i „Z wczoraj”, na ekranie i w PDF. 3. Ilości dnia D są przeskalowane na oba obiady, a lista zakupów zawiera danie raz, w podwójnej ilości. 4. Zmiana przepisu w dniu D opróżnia obiad dnia D+1. |
| US-017 | Kolacja na śniadanie       | Jako użytkownik chcę połączyć kolację ze śniadaniem następnego dnia, aby wykorzystać to, co zostanie z kolacji.                            | 1. W szkicu śniadanie dnia D+1 mogę ustawić jako resztkę kolacji z dnia D. 2. Śniadanie pokazuje danie z kolacji w kolorze śniadania, z dopiskiem „z kolacji”, na ekranie i w PDF. 3. Ilości kolacji są przeskalowane na oba posiłki, a śniadanie nie ma własnych składników. |
| US-018 | Przeciąganie posiłków      | Jako użytkownik chcę przeciągać posiłki myszą w szkicu, aby szybko zamienić je miejscami lub przenieść na inny dzień.                        | 1. W szkicu (na urządzeniu z myszą) przeciągnięcie posiłku na inny posiłek zamienia oba przepisy, a na pusty posiłek przenosi przepis i opróżnia źródło. 2. Przeniesienie posiłku gotowanego opróżnia posiłek, który z niego korzysta, z informacją o tym; upuszczenie na własną parę, na ten sam posiłek lub na posiłek z tym samym przepisem niczego nie zmienia. 3. Przepis z innej kategorii niż pora dnia ma na ekranie oznaczenie kategorii. 4. Wynik zapisuje się od razu i przetrwa odświeżenie strony. |

## 6. Metryki sukcesu
- Średni czas generowania jadłospisu <1 min.
- 0 krytycznych błędów w podstawowych ścieżkach podczas testów akceptacyjnych.
- MVP wdrożone i dostępne publicznie w ciągu 1 miesiąca od rozpoczęcia prac.
