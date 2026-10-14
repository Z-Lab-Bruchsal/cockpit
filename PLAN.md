# Plan: Öffentliche Homepage + Kursbuchung aus dem Cockpit

## Context
Das Cockpit (Laravel 13 / Filament 5, Panel bisher auf `/`) zieht nach `/cockpit`. Auf derselben Domain soll unter `/` eine öffentliche, responsive Homepage laufen, die vollständig im Cockpit gepflegt wird. Auf der Homepage sollen Kurse in Liste und Kalender erscheinen und buchbar sein: Eltern melden ein oder mehrere Kinder an (Double-Opt-In, Kapazität, optionale Warteliste), Schulen reservieren Klassentermine, die von Hand bestätigt werden. Damit nichts doppelt gebucht wird, gibt es einen Warenkorb mit zeitlich begrenzter Reservierung. Dazu kommen Eltern-Konten mit Magic-Link (optional), Interessen und Alter der Kinder, Mails an Interessierte bei neuen Terminen sowie die Erfassung, wer an welchem Termin wirklich da war.

Ausgangslage: `Course` (`name`, `duration`), `Kid` (`name`, `email`), Pivot `course_kid` (`date`), `Belt`↔`Course`. Lokal sind alle Tabellen leer. Belts bleiben unverändert und getrennt von den Interessen.

Entscheidungen: Bereiche als Kachel-Hierarchie (eine Ebene pro Seite) · FullCalendar über npm (neue Abhängigkeit, genehmigt) · keine Bezahlung.

Begriffe: **Kurs** = Vorlage · **Durchführung** (CourseInstance) = konkretes Angebot mit Kapazität · **Termin** (CourseDate) = einzelner Tag/Uhrzeit einer Durchführung.

Achtung Namenskonflikt: `Booking` ist schon die Zeiterfassung. Buchungen heißen deshalb `Registration` / `Enrollment`.

---

## Phase 1: Cockpit nach /cockpit, Homepage-Grundgerüst ✅ erledigt

**Eine Domain, Cockpit unter `/cockpit`**
- Das Panel liegt unter `/cockpit` (Login `/cockpit/login`), `/admin` leitet dorthin um.
- Die Homepage-Routen stehen in [routes/web.php](routes/web.php) (`homepage.*`), `/` ist die Startseite. Die Catch-all-Route `/{slug}` schließt die reservierten Pfade aus `Section::RESERVED_SLUGS` aus.
- Die Order-Status-Routen und die Terminal-API bleiben unverändert.

**Bereiche als Kacheln** (`sections`, Model `Section`, `SectionResource` in der Gruppe „Homepage“)
- Die Hierarchie entsteht über `parent_id`. Die Startseite zeigt nur die Hauptbereiche als Kacheln. Jeder Bereich hat eine eigene Seite `/{slug}` mit Breadcrumb, eigenem Inhalt (Blöcke) und den direkten Unterbereichen als Kacheln. Sichtbar ist also immer nur die aktuelle Ebene.
- Kachel: `teaser` und `tile_image_path`. Darstellung über `layout` (enum `SectionLayout`): Standard, Hervorgehoben (farbig), Breit (ganze Zeile).
- `is_standalone` = „Nicht als Kachel anzeigen“ (z. B. Impressum und Datenschutz). Solche Seiten werden über den Footer verlinkt.
- `show_in_navigation`: Der Hauptbereich erscheint als Link in der Kopfzeile.
- Ist ein Bereich ausgeblendet, sind auch alle Bereiche darunter nicht erreichbar (`isPubliclyVisible()`).
- Inhaltsblöcke (`ContentBlocks`, wiederverwendbar): Text, Bild, Text+Bild, Call-to-Action, Kontakt. Die Blöcke **Kursliste** und **Kurskalender** kommen in Phase 2 dazu.

**Firmeninfos** ([CompanySettingsPage](app/Filament/Pages/CompanySettingsPage.php), Tabs)
- **Firma:** Name, Adresse, Kontakt-E-Mail, Telefon, Logo.
- **Anwendung:** `app_title` setzt zur Laufzeit `app.name` und `mail.from.name` (`CompanySetting::applyToConfig()`). Dazu Cockpit-Farben per Color-Picker (primary, success, warning, danger, info) über `->colors(fn () => CompanySetting::cockpitColors())`.
- **Homepage:** Titel, Slogan, Haupt- und Akzentfarbe per Color-Picker. Die Textfarbe auf farbigen Flächen (schwarz oder weiß) wird automatisch nach WCAG-Kontrast gewählt. Farben werden mit `Str::sanitizeCssColor` abgesichert.
- **Footer:** Copyright-Text, Links (Bereich oder externe URL) und Social-Media-Profile, als Repeater-JSON.
- **Kursbuchung:** `hold_minutes` (15), `confirmation_hours` (48).

**Seeder** `HomepageSeeder` (mehrfach ausführbar): Firmeninfos und Bereichsbaum mit den Inhalten der Z-LAB-Website (Kinder & Eltern, Schulen, Über uns, Kontakt, Impressum, Datenschutz-Platzhalter, Footer-Links, Social Media).

**Frontend:** eigene Vite-Entries `resources/css/homepage.css` und `resources/js/homepage.js`, Layout-Komponente `x-homepage.layout`, mobile-first mit Hamburger-Menü.

**Offen vor dem Livegang:** `php artisan storage:link`, Rechte `*:Section` den Rollen zuweisen, Impressum prüfen, Datenschutzerklärung erstellen, Kachelbilder hochladen.

## Phase 2: Kursmodell + öffentliche Anzeige

**Interessen** (`interests`: `name`, `slug`, `color`, `sort`, `is_active`)
- `InterestResource`, konfigurierbare Liste.

**Darstellungsvorlagen** (`course_templates`, Model `CourseTemplate`)
- Steuern, wie ein Kurs auf der Homepage aussieht (Visitenkarte und Detailseite). Gepflegt im Cockpit über `CourseTemplateResource` (Gruppe „Kurse“).
- Felder:
  - `name`
  - `card_style` (enum `CourseCardStyle`: ImageTop, ImageLeft, Compact)
  - `detail_layout` (enum `CourseDetailLayout`: HeroImage = großes Titelbild, SideImage = Bild neben Text, Minimal = ohne großes Bild)
  - `accent_color` (ColorPicker)
  - Anzeige-Schalter `show_age_range`, `show_interests`, `show_free_seats`
  - `default_card_image_path` / `default_image_path` als Fallback-Bilder
  - `extra_content` (Builder mit denselben Textblöcken wie die Sektionen, z. B. „Was mitbringen“ oder „Hinweise“), wird auf jeder Detailseite mit dieser Vorlage angehängt
- Ein Kurs ohne Vorlage nutzt eine als `is_default` markierte Standardvorlage.
- Blade-Komponenten: `<x-homepage.course-card :course>` und `<x-homepage.course-detail :course>` wählen die Variante anhand der Vorlage (je Stil ein Partial).

**`courses` erweitern**
- `course_template_id`, `slug`
- **Visitenkarte:** `card_image_path` (kleines Bild, Seitenverhältnis fest, z. B. 4:3) und `teaser` (kurzer Text, max. ca. 200 Zeichen)
- **Detailseite:** `image_path` (großes Bild) und `description` (RichEditor)
- `audience` (enum `CourseAudience`: Kids, SchoolClass), `min_age`, `max_age`, `default_capacity`, `default_waitlist_enabled`, `is_published`
- Pivot `course_interest`
- Im Formular gibt es Tabs „Visitenkarte“, „Detailseite“ und „Einstellungen“ mit einer Live-Vorschau der Karte (ViewField).

**Durchführungen** (`course_instances`)
- `course_id`, `title` (optional), `capacity`, `waitlist_enabled`, `waitlist_limit` (nullable), `status` (enum `CourseInstanceStatus`: Draft, Published, Cancelled, Completed), `registration_opens_at`, `registration_closes_at`, `location`, `notes`.

**Termine** (`course_dates`)
- `course_instance_id`, `starts_at`, `ends_at`, `location` (nullable, überschreibt die Durchführung).
- Mehrtägige Kurse mit unterschiedlichen Uhrzeiten werden als mehrere Termine abgebildet.

**Filament**
- `CourseResource` erweitern: Formular, RelationManager „Durchführungen“.
- `CourseInstanceResource` mit Repeater `dates` (`->relationship()`), RelationManager „Anmeldungen“ (Phase 4).
- Die vorhandenen Kurse erscheinen zusätzlich im bestehenden Cockpit-Kalender ([CalendarPage](app/Filament/Pages/CalendarPage.php), guava/calendar).

**Homepage**
- `/kurse`: responsives Raster aus Visitenkarten (1/2/3 Spalten) mit Filtern für Interesse und Alter. Jede Karte zeigt das kleine Bild, den Titel, den Teaser, den nächsten Termin und den Button „Mehr Infos“.
- „Mehr Infos“ führt zu `/kurse/{course:slug}`, gerendert nach der Vorlage: großes Bild, Beschreibung, Extra-Inhalt der Vorlage und alle kommenden Durchführungen. Jede Durchführung listet ihre Termine (Tag und Uhrzeit), freie Plätze bzw. Wartelisten-Status und hat den Button „Anmeldung beginnen“, der den Buchungs-Flow aus Phase 4 startet.
- Der Inhaltsblock „Kursliste“ nutzt dieselbe Karte.
- `/kalender`: FullCalendar (`@fullcalendar/core`, `daygrid`, `list`, `interaction`). Auf Mobile ist die Listenansicht der Default.
- JSON-Endpunkt `/kalender/events?start&end&audience` liefert die Termine mit Status-Farbe: frei / wenige Plätze / ausgebucht / Warteliste / „wird gerade reserviert“ / angefragt.
- Beide Ansichten gibt es zusätzlich als Inhaltsblöcke für die Bereiche (`ContentBlocks`).

**Migration Altbestand**
- Vorhandene `course_kid`-Zeilen werden in eine Durchführung „Altbestand“ je Kurs und Datum mit bestätigten Enrollments überführt.
- Danach wird `course_kid` gelöscht und `Kid::courses()` / `Course::kids()` werden ersetzt. Der bestehende `KidsRelationManager` wird durch den Enrollment-RelationManager ersetzt.

## Phase 3: Kontakte, Kinder, Magic-Link-Konten

**Kontakte** (`contacts`, Model `Contact` extends Authenticatable)
- Felder: `type` (enum `ContactType`: Parent, School), `name`, `email` (unique), `phone`, `street`, `zip`, `city`, `contact_person` (Schulen), `notes`, `email_verified_at`, `interest_mails_consent_at` (Einwilligung, DSGVO), `unsubscribe_token`.
- Ein Konto ist kein Pflichtfeld: Der Kontakt entsteht auch bei einer Gastbuchung. „Registriert“ heißt nur, dass die E-Mail per Magic-Link bestätigt wurde. Es gibt kein Passwort.

**Kinder (`kids` erweitern)**
- `birthdate` (Alter wird berechnet), `notes`.
- Pivots `contact_kid` (mehrere Erziehungsberechtigte möglich) und `interest_kid`.

**Auth**
- Guard `contact` in `config/auth.php`.
- Tabelle `contact_login_tokens` (gehashter Token, `expires_at` 30 min, `used_at`): Die Links sind nur einmal verwendbar.
- Routen: `/anmelden` (E-Mail eingeben, Mail `ContactMagicLink`) und `/login/{token}`. Rate-Limit auf das Formular. Die Antwort ist neutral und verrät nicht, ob die E-Mail existiert.
- Eltern-Bereich `/konto`: Daten pflegen, Kinder mit Geburtsdatum und Interessen anlegen, Anmeldungen ansehen und stornieren, Einwilligung für Kursinfos verwalten.

**Filament**
- `ContactResource` (Gruppe „Kontakte“) mit Tabs Eltern/Schulen und RelationManager Kinder bzw. Anmeldungen.
- `KidResource` erweitern: Interessen, Eltern, Kurshistorie inkl. Anwesenheit.

## Phase 4: Warenkorb, Reservierung, DOI, Warteliste, Anwesenheit

**Tabellen**
- `carts`: `token`, `contact_id` (nullable), `status` (enum `CartStatus`: Open, CheckedOut, Expired), `expires_at`.
- `registrations` (je Durchführung): `cart_id`, `contact_id`, `course_instance_id`, `status` (enum `RegistrationStatus`: Held, PendingConfirmation, Confirmed, Requested, Declined, Cancelled, Expired), `confirmation_token`, `confirmation_expires_at`, `confirmed_at`, `message`, `school_class_data` (json: Klasse, Schüleranzahl, Lehrkraft), `source` (web, cockpit).
- `enrollments` (je Kind): `registration_id`, `kid_id`, `course_instance_id`, `status` (enum `EnrollmentStatus`: Held, Pending, Confirmed, Waitlisted, Cancelled), `waitlist_position`.
- `attendances`: `enrollment_id`, `course_date_id`, `attended` (bool), `checked_by` (user), `checked_at`.

**Kernlogik** in `app/Services/CourseReservationService.php`
- `hold()` läuft in einer DB-Transaktion mit `lockForUpdate()` auf der Durchführung. Belegt sind Plätze mit Held (nicht abgelaufen), Pending und Confirmed.
  - Genug frei: Status Held, Ablauf nach `hold_minutes`.
  - Sonst und Warteliste aktiv: Status Waitlisted.
  - Sonst Fehler „ausgebucht“.
  - Ein Kind kann nicht doppelt in derselben Durchführung sein (Unique-Prüfung).
- `checkout()`: Held wird zu PendingConfirmation, DOI-Mail `RegistrationConfirmationMail` geht raus, gültig `confirmation_hours`. Ist die E-Mail des Kontakts schon per Magic-Link bestätigt und der Kontakt eingeloggt, wird direkt Confirmed gesetzt.
- `confirm(token)` setzt Confirmed. `cancel()` gibt den Platz frei.
- `promoteWaitlist(instance)`: Bei frei gewordenen Plätzen wird der Erste auf der Warteliste auf Pending gesetzt und bekommt eine Mail „Platz frei – bitte bestätigen“.
- Scheduler `reservations:expire` läuft jede Minute: abgelaufene Holds und unbestätigte DOIs werden auf Expired gesetzt, danach läuft `promoteWaitlist`. Zusätzlich wird beim Zählen immer `expires_at > now()` geprüft, damit es auch ohne Cron korrekt bleibt.

**Homepage-Flow Eltern** (Livewire `BookingWizard`)
1. Auf der Kursseite auf „Anmeldung beginnen“ klicken und Kinder wählen: eingeloggt aus den gespeicherten Kindern, als Gast per Repeater mit Name und Geburtsdatum. Danach werden die Plätze gehalten und ein Countdown erscheint.
2. Optional weitere Kurse in den Warenkorb legen.
3. Kontaktdaten eingeben, Datenschutz-Checkbox, optional Einwilligung für Kursinfos. Honeypot und Rate-Limit schützen das Formular.
4. Die DOI-Mail kommt, der Klick bestätigt die Anmeldung. Bestätigungsseite plus Mail.

Im Kalender erscheinen Plätze von anderen als „im Warenkorb eines anderen Nutzers“, die eigenen über das Cart-Cookie als „in deinem Warenkorb“.

**Cockpit**
- `RegistrationResource` mit Filtern und Aktionen Bestätigen, Stornieren, Von Warteliste nachrücken. Eine manuelle Anmeldung im Cockpit (`source=cockpit`) läuft über denselben Service.
- Anwesenheit: Seite bzw. RelationManager je `CourseDate` mit Liste der bestätigten Kinder und `ToggleColumn attended`, dazu die Bulk-Aktion „Alle anwesend“.

## Phase 5: Mails an Interessierte
- Action „Interessierte benachrichtigen“ auf `CourseInstance` (Edit und Tabelle) sowie Toggle im Create-Formular.
- Vorauswahl: Interessen des Kurses und Altersspanne, beide anpassbar. Vor dem Senden wird die Empfängerzahl angezeigt.
- Empfänger sind Kontakte mit `interest_mails_consent_at` und mindestens einem Kind, das passende Interessen hat und am ersten Termin im Alter ist.
- Mail `NewCourseInstanceMail` mit Abmeldelink (`/abmelden/{unsubscribe_token}`), gequeued.
- Log-Tabelle `course_instance_notifications` (`instance`, `contact`, `sent_at`) verhindert Doppelversand.
- `.env`: `QUEUE_CONNECTION=database` plus Worker (aktuell `sync`).

## Phase 6: Schulklassen
- Kurse mit `audience=SchoolClass`: Jede Durchführung ist ein reservierbarer Slot, Kapazität 1 Klasse, keine Warteliste.
- Action „Schulen informieren“ (auf Kurs oder Durchführung, auch als Bulk) schickt `SchoolCalendarMail` an alle Schul-Kontakte (Auswahl möglich). Die Mail enthält einen persönlichen Magic-Link, der direkt eingeloggt auf `/schulen/kalender` führt.
- Schulkalender (FullCalendar) zeigt freie Slots, „im Warenkorb eines anderen Nutzers“ sowie „angefragt/reserviert“. Freie Slots können in den Warenkorb (Hold).
- Checkout mit Klasse, Schüleranzahl, Lehrkraft und Nachricht setzt die Anmeldung auf Requested. Requested zählt als belegt, kein DOI nötig, weil die Schule eingeloggt ist.
- Im Cockpit setzen die Aktionen Bestätigen bzw. Ablehnen (mit Notiz) den Status Confirmed bzw. Declined und schicken eine Mail an die Schule. Bei Ablehnung wird der Slot wieder frei.

---

## Querschnitt
- Enums in `app/Enums/`, gleiche Konventionen wie die vorhandenen Enums.
- Policies und Permissions über filament-shield generieren (`shield:generate`).
- Navigationsgruppen: Homepage, Kurse, Kontakte.
- Alle Mailables nach dem Muster von [app/Mail/](app/Mail/), Views unter `resources/views/mail/`.
- Factories für alle neuen Models.
- `search-docs` vor jeder Phase (Builder, Repeater relationship, Panel domain, Livewire 4).

## Verifikation
- Feature-Tests (PHPUnit) je Phase:
  - Routing: `/` liefert die Homepage, `/cockpit` das Panel (Gäste werden auf `/cockpit/login` umgeleitet), die Order-Status-Routen funktionieren weiter.
  - Bereiche: Kachel-Ebenen, Breadcrumbs, Sichtbarkeit, Footer und Seeder ✅
  - Kurskarte und Detailseite je Vorlage: Fallback auf die Standardvorlage, nur veröffentlichte Kurse sichtbar, Detailseite zeigt Termine und Buchungslink.
  - Magic-Link: gültig, abgelaufen, einmalig.
  - `CourseReservationService`: Kapazität, mehrere Kinder, zu wenige Plätze, Warteliste an/aus, Hold-Ablauf, DOI-Bestätigung und -Ablauf, Nachrücken, Doppelanmeldung.
  - Schul-Requested, Bestätigen und Ablehnen.
  - Empfängerauswahl der Interessen-Mail inkl. Einwilligung, Alter und Doppelversand.
  - Anwesenheit abhaken (Filament-Livewire-Tests).
- `vendor/bin/pint --dirty --format agent` und `php artisan test --compact` mit Filtern.
- Manuell: `npm run build`, dann `/` und `/cockpit` aufrufen. Dann Buchung mit zwei Browsern parallel prüfen (der zweite sieht „im Warenkorb eines anderen“), Mails mit `MAIL_MAILER=log` kontrollieren und die Mobile-Ansicht in den Browser-Devtools testen.

Umsetzung phasenweise. Nach jeder Phase kurze Abnahme, bevor die nächste startet.
