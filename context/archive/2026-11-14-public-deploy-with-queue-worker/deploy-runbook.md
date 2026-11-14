# Pierwszy deploy menu-maker na VPS (runbook)

Cel: `https://menu.kostecki.dev` działa na VPS-ie `halina175` i przechodzi punkty 6.1–6.9 z `plan.md`.
Źródło prawdy dla operatora: `kostecki-dev-infra/docs/menu-maker.md`. Ten plik to konkretna kolejność kroków na ten jeden deploy.

## Stan przed deployem (sprawdzony 2026-11-14)

| Co | Stan |
|---|---|
| `menu-maker` `main` | `dc083ad` (PR #2), treść identyczna z `develop`, CI zielone (4/4 joby, w tym `production-image`) |
| `kostecki-dev-infra` `master` | zawiera PR #3 (`b086f83`, `e9153a7`); Traefik: `websecure`, resolver `letsencrypt`, sieć `proxy`; router `menu` jest unikalny |
| DNS | `menu.kostecki.dev` rozwiązuje się na adresy Cloudflare |
| Repo menu-maker | publiczne → klon przez HTTPS, bez deploy key |
| Dostęp do VPS | `ssh root@halina175.mikrus.xyz -p10175`, wszystko jako root |

### Próba generalna (lokalnie, świeży klon `main` z GitHuba)

Każdy krok poniżej przeszedł na kopii produkcyjnego stacku (osobny projekt compose, sztuczne sekrety):

- preflight odrzuca `.env.prod` bez `APP_KEY`; `run --rm --no-deps app php artisan key:generate --show` zwraca klucz;
- pierwszy `deploy.sh`: 17 migracji, `unaccent`, 5 jednostek, `php`/`queue`/`scheduler` RUNNING, `sail` = uid 1000, `storage` należy do 1000;
- `/login` z nagłówkami proxy → 200 i assety z `https://menu.kostecki.dev/build/...`; gość na `/dashboard` → 302 na `https://menu.kostecki.dev/login`;
- worker kolejki wykonał zadanie (0 failed);
- harmonogram: `backup:*` o 04:15/04:30/04:45 UTC = 05:15/05:30/05:45 Europe/Warsaw (zima);
- drugi `deploy.sh`: backup przed deployem (`.zip`, AES-256), dane zachowane, okno 503 ok. 3 s + ok. 1,5 s braku odpowiedzi przy podmianie kontenera (przez Traefik: krótki 502), potem 200;
- pełny restore z dokumentacji (drop/create DB, load dumpa, `DEPLOY_WITHOUT_BACKUP=1 deploy.sh`) przywrócił stan z backupu;
- zepsuty backup (brak `pg_dump`) → `deploy.sh` kończy się błędem i otwiera **stary** kontener (200);
- pamięć po starcie: app ok. 160 MiB / 768 MiB, pgsql ok. 43 MiB / 512 MiB (bez generowania planu i PDF).

Wykryta i poprawiona rozbieżność w dokumentacji: dump w archiwum nazywa się `db-dumps/postgresql-<DB_DATABASE>.sql`. Poprawka leży lokalnie w `kostecki-dev-infra` na gałęzi `docs/menu-maker-rehearsal` (`287c1ca`), jeszcze nie wypchnięta. Nie blokuje pierwszego deployu.

## 0. Przygotuj przed wejściem na serwer

- [ ] Dane SMTP z innych aplikacji (`MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD`; port 587).
- [ ] Klucz i model OpenRoutera (`OPENROUTER_API_KEY`, `OPENROUTER_MODEL`) albo klucz Anthropic.
- [ ] Adres na alerty (`LOG_ALERT_MAIL_TO`, `BACKUP_NOTIFICATION_MAIL`).
- [ ] Miejsce **poza VPS-em** na `BACKUP_ARCHIVE_PASSWORD` (menedżer haseł). Bez niego backupy są nie do otwarcia.
- [ ] Cloudflare: rekord `menu` jest **Proxied** (pomarańczowa chmurka), SSL/TLS = **Full (strict)**.
- [ ] Ok. 30 minut. Pierwszy build obrazu na VPS-ie trwa kilka minut (apt, composer, npm, vite).

## 1. Kontrole wstępne na VPS (tylko odczyt)

```bash
ssh root@halina175.mikrus.xyz -p10175

docker network ls --format '{{.Name}}' | grep -x proxy          # musi istnieć
docker ps --format '{{.Names}}' | grep -i traefik               # Traefik działa
docker ps -a --format '{{.Names}}' | grep -E '^menu-(app|pgsql)$' || echo "nazwy wolne"
df -h /srv /storage /var/lib/docker                             # min. ~5 GB wolnego na obraz i build
free -m                                                         # build + 768m/512m limitów dla menu-maker
```

Jeśli brakuje `proxy` albo Traefika, przerwij: infra nie jest w stanie z `docs/SETUP.md`.

## 2. Infra i katalogi

```bash
cd /srv/infra && git pull --ff-only
mkdir -p /srv/apps/menu-maker /storage/menu-maker-backups
```

## 3. Klon i `.env.prod`

```bash
git clone https://github.com/sebastian-kostecki/menu-maker.git /srv/apps/menu-maker
cd /srv/apps/menu-maker
git log --oneline -1                     # oczekiwane: dc083ad (albo nowszy commit z main)
cp .env.prod.example .env.prod && chown 1000:root .env.prod && chmod 600 .env.prod   # app user (WWWUSER=1000) must read it

openssl rand -base64 24                  # → DB_PASSWORD
openssl rand -base64 32                  # → BACKUP_ARCHIVE_PASSWORD (od razu skopiuj poza VPS)
nano .env.prod
```

Do uzupełnienia w `.env.prod` (preflight `deploy.sh` odmówi, jeśli czegoś brakuje):

| Klucz | Wartość |
|---|---|
| `DB_PASSWORD` | wygenerowane wyżej |
| `MAIL_HOST`, `MAIL_USERNAME`, `MAIL_PASSWORD` | konto SMTP z innych aplikacji |
| `LOG_ALERT_MAIL_TO`, `BACKUP_NOTIFICATION_MAIL` | Twój adres |
| `BACKUP_ARCHIVE_PASSWORD` | wygenerowane wyżej, kopia poza VPS-em |
| `OPENROUTER_API_KEY`, `OPENROUTER_MODEL` | dla `AI_SCALING_DRIVER=openrouter` (domyślnie) |
| albo `AI_SCALING_DRIVER=anthropic` + `ANTHROPIC_API_KEY` | model i effort zostaw zakomentowane (domyślne z configu) |

Pozostałe wartości z szablonu zostaw bez zmian: `APP_URL`, `SESSION_DOMAIN`, `INERTIA_SSR_ENABLED=false`, `WWWUSER=1000`.

## 4. `APP_KEY`

```bash
docker compose --env-file .env.prod -f docker-compose.prod.yml run --rm --no-deps app php artisan key:generate --show
```

To polecenie buduje obraz za pierwszym razem (kilka minut). Wynik `base64:...` wklej do `APP_KEY=` w `.env.prod`. Plik jest montowany tylko do odczytu, więc `key:generate` nie zapisze go sam.

Sprawdź preflight bez deployu:

```bash
bash -c 'source scripts/lib/production-deploy.sh && production_assert_env_ready .env.prod' && echo "preflight OK"
```

## 5. Deploy

```bash
/srv/infra/scripts/deploy-menu-maker.sh
```

Oczekiwane komunikaty, w tej kolejności: `No running app container (first deployment...)` → `Starting stack` → `Preparing storage permissions` → `Entering maintenance mode in the new container` → migracje → cache → `Leaving maintenance mode` → `Done.`

Przy pierwszym deployu nie ma backupu przed migracją (nie ma jeszcze bazy). To jest zamierzone.

Jeśli skrypt się wyłoży, aplikacja zostaje w trybie maintenance. Przeczytaj błąd i `docker compose --env-file .env.prod -f docker-compose.prod.yml logs --tail=100 app`, popraw, uruchom krok 5 ponownie. Całkowite wycofanie przy pierwszym deployu: `docker compose --env-file .env.prod -f docker-compose.prod.yml down` (bez `-v`, jeśli chcesz zachować bazę).

## 6. Smoke test produkcyjny (punkty 6.1–6.9 w `plan.md`)

```bash
cd /srv/apps/menu-maker
C="docker compose --env-file .env.prod -f docker-compose.prod.yml"
```

- [ ] **6.1** `curl -svI https://menu.kostecki.dev/up 2>&1 | grep -E "HTTP/|server:|cf-ray"` → `HTTP/2 200`, `server: cloudflare`, `cf-ray`. Pierwsze wywołanie może trwać, zanim Let's Encrypt wyda certyfikat.
- [ ] **6.2** `$C exec -T app supervisorctl status` → `php`, `queue`, `scheduler` RUNNING.
- [ ] **6.3** W przeglądarce: rejestracja konta testowego, logowanie, „Nie pamiętasz hasła?”. Mail dochodzi, link zaczyna się od `https://menu.kostecki.dev` i resetuje hasło.
- [ ] **6.4** Minimum 7 przepisów na kategorię i profil rodziny, wygeneruj plan: gotowy w mniej niż minutę, bez komunikatu o fallbacku. Wygeneruj ponownie. Pobierz PDF i sprawdź polskie znaki.
- [ ] **6.5** `./scripts/artisan.sh tinker --execute 'Log::error("menu-maker production alert smoke");'` → dokładnie jeden mail na `LOG_ALERT_MAIL_TO`.
- [ ] **6.6** `./scripts/artisan.sh backup:run --only-db`, potem `ls /storage/menu-maker-backups/menu-maker/` i odszyfruj archiwum według `docs/menu-maker.md` → Decrypt (7z zapyta o hasło; użyj kopii spoza VPS-a).
- [ ] **6.7** W trakcie generowania planu i pobierania PDF: `docker stats --no-stream menu-app menu-pgsql`. Wpisz szczyt w `kostecki-dev-infra/docs/menu-maker.md` w miejsce „Memory peak: to be recorded at first deploy”.
- [ ] **6.8** Drugi raz `/srv/infra/scripts/deploy-menu-maker.sh`: krótkie 503, konto, przepisy i plan zostają, w `/storage/menu-maker-backups/menu-maker/` pojawia się nowy `.zip`.
- [ ] **6.9** Następnego ranka: backup z ok. 05:15, brak maila o błędzie backupu.

## 7. Po deployu

- [ ] Wypchnij `docs/menu-maker-rehearsal` w `kostecki-dev-infra` (poprawka nazwy dumpa) razem z wpisem o szczycie pamięci z 6.7 i zmerguj do `master`.
- [ ] Odhacz 4.6 (opcjonalny), 5.5, 5.6 i 6.1–6.9 w `plan.md`.
- [ ] `/10x-archive public-deploy-with-queue-worker`: zamknie zmianę i ustawi F-02 w roadmapie na `done`.
