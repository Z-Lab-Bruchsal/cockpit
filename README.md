# Z-Lab Cockpit

Z-Lab Cockpit is a [Laravel](https://laravel.com) + [Filament](https://filamentphp.com) admin panel for running a martial-arts school: managing kids, courses and belt progressions, tracking equipment orders, assigning todos, and recording staff work time.

## Features

**Kurse und Kinder**
- **Kinder** — manage enrolled kids.
- **Kurse** — manage courses kids attend.
- **Gürtel** — track belt levels tied to courses.

**Bestellungen**
- Track equipment orders through a configurable status pipeline, with email notifications on arrival.

**Todos**
- Create todos assigned to a user or a whole group, with due dates and follow-up reminders. A daily scheduled command emails follow-up reminders once their date is reached.

**Zeiterfassung (work-time tracking)**
- **Dashboard clock widget** — Kommen / Pause / Gehen buttons on the dashboard, enforcing a legal clock state machine (you can't start a break before clocking in, clocking out while on a break automatically closes the break first).
- **Zeiten** — a log of every clock event, correctable after the fact by the user themselves or by a manager, with every change recorded in a visible audit trail (old value, new value, who, when).
- **Zeitmodelle** — weekly-hours profiles (e.g. "Vollzeit 40h") assigned to users with dated validity ranges, so historical over/under-hours stay accurate even as someone's hours change.
- **Pausenregeln** — configurable break-compliance rules matching German labor law (ArbZG §4): a break of at least 30 minutes is required after more than 6 hours worked, 45 minutes after more than 9 hours, and only break segments of at least 15 minutes count. A daily scheduled command emails anyone who didn't take a sufficient break. Restricted to users with the `zeiterfassung-admin` role.
- **Kalender** — a combined calendar of time entries and todo deadlines/reminders, filterable by type (times / todos / both) and by user (limited to whichever users you're allowed to see, see below).

**Verwaltung**
- **Benutzer**, **Gruppen**, **Rollen** — manage accounts, and organize them into groups. Attaching a role to a group makes every member of that role able to see (and, for time entries, correct) the time entries of everyone in that group.

## Tech stack

- PHP 8.5, Laravel 13
- Filament 5 (admin panel), `guava/calendar` (calendar widget)
- SQLite by default (see `.env`)
- Tailwind CSS 4 + Vite

## Installation

### Local

```bash
composer setup   # composer install, .env, app key, migrate, npm install & build
```

Or step by step:

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite   # only needed for the default sqlite connection
php artisan migrate
npm install
npm run build
```

Start the local dev stack (web server, queue listener, log tailing, Vite) in one command:

```bash
composer run dev
```

### Docker

A production-style `docker-compose.yml` is included, with separate `app` (web), `queue` and `scheduler` services sharing the same image and `.env` file:

```bash
docker compose up --build
```

The web service is published on `http://localhost:8000` and exposes a health check at `/up`. Migrations aren't run automatically by the compose file — run them once against the running `app` container:

```bash
docker compose exec app php artisan migrate --force
```

## Creating the first user & granting permissions

There's no self-service registration — accounts are created by an administrator. Create the first user with Filament's built-in command (works the same in Docker via `docker compose exec app ...`):

```bash
php artisan make:filament-user
```

This prompts for a name, email and password and creates an account that can log in to the panel — every registered user can access the panel, so this alone is enough to explore Kurse und Kinder, Bestellungen, Todos and Zeiterfassung's own time clock.

Two things are gated behind specific **roles** instead of a single "admin" flag:

- **`ordermanager`** — order management.
- **`zeiterfassung-admin`** — access to the Pausenregeln settings page.

To grant a role, either:

- In the panel, go to **Verwaltung → Benutzer**, edit the user, and add the role in the *Rollen* field (or go to **Verwaltung → Rollen**, open the role, and attach the user from its relation manager tab), or
- Via Artisan Tinker:

  ```bash
  php artisan tinker --execute '
      $user = App\Models\User::where("email", "someone@example.com")->firstOrFail();
      $role = App\Models\Role::where("name", "zeiterfassung-admin")->firstOrFail();
      $user->roles()->syncWithoutDetaching($role);
  '
  ```

To let someone see (and correct) another group's time entries in Zeiterfassung, open the group or the role in **Verwaltung** and attach the other side via its relation manager tab.

## Development

```bash
php artisan test --compact          # run the test suite
vendor/bin/pint --dirty             # fix code style on changed files
php artisan route:list              # inspect registered routes
```

This project uses [Laravel Boost](https://laravel.com/docs/ai) — see `CLAUDE.md` for the conventions AI coding agents should follow in this codebase.
