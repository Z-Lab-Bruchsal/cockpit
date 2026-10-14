# Z-Lab Cockpit mit Docker

Es gibt zwei Compose-Dateien:

| Datei | Wofür | Code kommt aus … |
|---|---|---|
| `docker-compose.dev.yml` | Entwicklung / schnell lokal starten | dem eingebundenen Projektverzeichnis (Änderungen wirken sofort) |
| `docker-compose.yml` | produktionsnaher Betrieb | dem gebauten Image (Code ist fest eingebaut) |

Beide bauen ihr Image selbst aus dem `Dockerfile` – ein vorher gebautes oder heruntergeladenes Image ist nicht nötig.

## Voraussetzungen

- Docker mit Compose v2 (`docker compose version`)
- Eine `.env` im Projektverzeichnis:

  ```bash
  cp .env.example .env
  ```

  Danach `APP_KEY` setzen (siehe [Erster Start](#erster-start)).
- `auth.json` mit den Zugangsdaten für das Filament-Composer-Repository. Bei der Dev-Variante nur nötig, wenn `vendor/` noch fehlt oder neu installiert werden muss; bei der produktionsnahen Variante immer.

## Entwicklung: `docker-compose.dev.yml`

### Starten

Im Projektverzeichnis:

```bash
HOST_UID=$(id -u) HOST_GID=$(id -g) docker compose -f docker-compose.dev.yml up --build
```

Die Anwendung ist dann unter <http://localhost:8000> erreichbar, das Admin-Cockpit unter <http://localhost:8000/cockpit>.

Im Hintergrund starten (Terminal bleibt frei):

```bash
HOST_UID=$(id -u) HOST_GID=$(id -g) docker compose -f docker-compose.dev.yml up --build -d
```

`HOST_UID`/`HOST_GID` sorgen dafür, dass Dateien, die der Container schreibt (Logs, Cache, SQLite-Datenbank), deinem Benutzer gehören und nicht root. Ohne Angabe wird `1000` verwendet.

### Anderer Port

Ist Port 8000 belegt (z. B. durch `php artisan serve` oder `composer run dev`):

```bash
APP_PORT=8088 HOST_UID=$(id -u) HOST_GID=$(id -g) docker compose -f docker-compose.dev.yml up --build
```

### Was beim Start passiert

1. **`composer`** – führt `composer install` aus und beendet sich.
2. **`assets`** – führt `npm ci` und `npm run build` aus (schreibt nach `public/build`) und beendet sich.
3. **`app`** – startet erst, wenn beide erfolgreich waren. Vor dem Webserver laufen automatisch `config:clear`, `migrate --force` und `storage:link`.

Alle drei arbeiten direkt im Projektverzeichnis. Gebaut wird nur die Laufzeitumgebung (PHP 8.5, Apache, Erweiterungen) – das Dockerfile-Ziel `dev`.

### Im Alltag

| Was | Befehl |
|---|---|
| Stoppen | `docker compose -f docker-compose.dev.yml down` |
| Logs ansehen | `docker compose -f docker-compose.dev.yml logs -f app` |
| Artisan-Befehl | `docker compose -f docker-compose.dev.yml exec -u www-data app php artisan <befehl>` |
| Shell im Container | `docker compose -f docker-compose.dev.yml exec -u www-data app bash` |
| Assets neu bauen | `docker compose -f docker-compose.dev.yml run --rm assets` |

PHP- und Blade-Änderungen sind sofort sichtbar. Nach Änderungen an CSS/JS die Assets neu bauen (oder Container neu starten).

Tipp: Mit `export COMPOSE_FILE=docker-compose.dev.yml` in der Shell kann man `-f docker-compose.dev.yml` weglassen.

## Produktionsnah: `docker-compose.yml`

```bash
docker compose up --build -d
```

Baut das vollständige Image (Code, `vendor/`, gebaute Assets) und startet drei Dienste mit demselben Image und derselben `.env`:

- **`app`** – Webserver auf <http://localhost:8000>, Healthcheck unter `/up`; führt beim Start die Migrationen aus
- **`queue`** – `php artisan queue:work`
- **`scheduler`** – `php artisan schedule:work`

`storage/` wird aus dem Projektverzeichnis eingebunden, damit Uploads, Logs und die SQLite-Datenbank Neustarts überleben. `auth.json` wird beim Bauen nur als BuildKit-Secret verwendet und landet nicht im Image.

## Erster Start

Einmalig nach dem ersten Start (bei der Dev-Variante jeweils `-f docker-compose.dev.yml` und `-u www-data` ergänzen):

```bash
# App-Key erzeugen, falls APP_KEY in der .env leer ist
docker compose exec app php artisan key:generate

# Ersten Admin-Benutzer anlegen
docker compose exec app php artisan make:filament-user
```

Rollen und Berechtigungen siehe [README.md](../README.md#creating-the-first-user--granting-permissions).

## Probleme

- **`bind: address already in use`** – Port belegt, `APP_PORT` setzen (siehe oben).
- **`composer` schlägt fehl (401/403 bei packages.filamentphp.com)** – `auth.json` fehlt oder ist ungültig.
- **Seite ohne Styles / Vite-Manifest-Fehler** – Assets neu bauen: `docker compose -f docker-compose.dev.yml run --rm assets`.
- **Permission denied in `storage/`** – Stack mit `HOST_UID=$(id -u) HOST_GID=$(id -g)` und `--build` neu starten, damit das Image deine User-ID übernimmt.
