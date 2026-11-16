# Import przepisów na produkcję (runbook)

Cel: 33 przepisy z `D:\Menu Maker Project\Przepisy-import` na koncie właściciela na `https://menu.kostecki.dev`.
Komenda: `recipes:import` (ta zmiana). Dane nie są w repozytorium; trafiają na VPS przez `scp` do katalogu `storage/`, który `docker-compose.prod.yml` montuje w kontenerze `app`.

## Stan przed importem (sprawdzony 2026-11-15)

| Co | Stan |
|---|---|
| Pliki | 33 `.md` z `kalorie:`, `porcje:` (szacunki) i `szacunek:`; `RAPORT.txt` z wyliczeniami (importer czyta tylko `*.md`) |
| Próba lokalna | baza `testing` po `migrate:fresh`: 33 zaimportowane, 0 błędów; drugi przebieg: 0 zaimportowanych, 33 pominięte |
| Kategorie po imporcie | śniadanie 17, obiad 10, kolacja **6** (generator planu wymaga 7 w każdej) |

## 0. Przed wejściem na serwer

- [ ] Gałąź z komendą zmergowana do `develop`, potem do `main`, CI zielone.
- [ ] Deploy: `/srv/infra/scripts/deploy-menu-maker.sh` (robi backup przed migracjami; ta zmiana nie ma migracji).
- [ ] Konto właściciela istnieje na produkcji (rejestracja w przeglądarce). Zapisz jego e-mail; niżej `<EMAIL>`.
- [ ] Decyzja o siódmej kolacji (patrz krok 6).

## 1. Skopiuj pliki na VPS (z komputera, WSL)

```bash
scp -P10175 -r "/mnt/d/Menu Maker Project/Przepisy-import" \
    root@halina175.mikrus.xyz:/srv/apps/menu-maker/storage/app/private/import-przepisy
```

## 2. Na VPS: uprawnienia i sprawdzenie

```bash
ssh root@halina175.mikrus.xyz -p10175
cd /srv/apps/menu-maker
chown -R 1000:1000 storage/app/private/import-przepisy
ls storage/app/private/import-przepisy/*.md | wc -l          # oczekiwane: 33
./scripts/artisan.sh list recipes                             # oczekiwane: recipes:import
```

## 3. Backup bazy

```bash
./scripts/artisan.sh backup:run --only-db
ls -t /storage/menu-maker-backups/menu-maker/ | head -1       # nowy .zip
date -u +"%Y-%m-%d %H:%M:%S"                                  # zapisz: czas przed importem (do wycofania)
```

## 4. Próba bez zapisu

```bash
./scripts/artisan.sh recipes:import storage/app/private/import-przepisy --user=<EMAIL> --dry-run
```

Oczekiwane: `Do importu: 33, pominięto: 0, błędy: 0. Nic nie zapisano (--dry-run).` W kolumnie „Uwagi” tylko odnośniki „Według …” i suma oleju w racuchach. Jeśli są „pominięto”, to konto ma już przepisy o tych nazwach (zostaną nietknięte).

## 5. Import

```bash
./scripts/artisan.sh recipes:import storage/app/private/import-przepisy --user=<EMAIL>
```

Oczekiwane: `Zaimportowano: 33, pominięto: 0, błędy: 0.` Komendę można bezpiecznie powtórzyć: przepisy o istniejących nazwach są pomijane.

Sprawdź w przeglądarce: lista przepisów (33), jeden przepis ze składnikami, np. „Potrawka z kurczaka” (12 składników, 2410 kcal, 4 porcje).

## 6. Siódma kolacja

Generator planu odmówi bez 7 kolacji. Jedno z:

- dodaj kolację w aplikacji (formularz przepisu), albo
- zmień kategorię jednego przepisu na „Kolacja” w edycji (np. „Gofry wytrawne”, „Jajecznica”, „Naleśniki zwykłe”).

Potem: profil rodziny → wygeneruj plan → plan gotowy, bez komunikatu o brakujących przepisach.

## 7. Sprzątanie

```bash
rm -r storage/app/private/import-przepisy
```

## Wycofanie

Przed wygenerowaniem pierwszego planu (importowane przepisy nie mają posiłków):

```bash
./scripts/artisan.sh tinker --execute 'echo App\Models\Recipe::query()->whereHas("user", fn ($q) => $q->where("email", "<EMAIL>"))->where("created_at", ">=", "<CZAS Z KROKU 3>")->forceDelete();'
```

Usuwa przepisy z importu razem ze składnikami (`recipe_ingredients` kaskadowo). Nowe nazwy składników zostają w słowniku `ingredients` (nie są katalogowe, więc inni użytkownicy ich nie widzą w podpowiedziach). Po wygenerowaniu planu wycofuj z backupu z kroku 3 (restore z `kostecki-dev-infra/docs/menu-maker.md`).
