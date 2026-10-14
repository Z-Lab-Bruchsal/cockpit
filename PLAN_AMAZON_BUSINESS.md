# Plan: Bestellungen über die Amazon Business Ordering API

## Kontext
Bisher erfasst die Crew Bestellwünsche manuell in [Order](app/Models/Order.php) (Name, URL, Anzahl, Status `erfasst → bestellt → angekommen → genommen`). Die eigentliche Bestellung erledigt dann jemand von Hand bei Amazon. Künftig sollen Berechtigte erfasste Amazon-Bestellungen direkt aus dem Cockpit über die Amazon Business Ordering API auslösen können (`POST /ordering/2022-10-30/orders`, EU-Endpoint `eu.business-api.amazon.com`).

**Externe Voraussetzungen** (ca. 4–6 Wochen Onboarding bei Amazon, der Code wird parallel dazu gebaut):
- Amazon-Business-Konto (DE), Entwickler-Registrierung, App-Client im Solution Provider Portal, LWA-Refresh-Token, Rolle `AmazonBusinessOrderPlacement`.
- Im Konto: eine Kaufgruppe mit hinterlegter Zahlungsart und Lieferadresse, Safeguards (Preis- und Summenlimits), Purchasing-System auf „Active Mode“ stellen.
- Grenzen der API: keine Bundles mit Menge > 1, keine Artikel über 30 kg, max. 50 Positionen bzw. 999 Einheiten pro Bestellung, Teilablehnungen sind möglich.

## Ansatz

### 1. Konfiguration
- `config/services.php`, Block `amazon_business` (Werte aus `.env`, ergänzt in `.env.example`):
  - Secrets: `client_id`, `client_secret`, `refresh_token`
  - `lwa_token_url` (default `https://api.amazon.co.uk/auth/o2/token`)
  - `base_url` (default `https://eu.business-api.amazon.com`)
  - `region` (`DE`)
- Migration auf `company_settings`, damit nicht-geheime Werte im UI pflegbar sind:
  - `amazon_buying_group_id`, `amazon_buyer_email`
  - Lieferadresse: `amazon_ship_full_name`, `…_company`, `…_line1`, `…_line2`, `…_city`, `…_postal_code`, `…_phone`
  - `amazon_trial_mode` (bool, default `true`)
- Eine neue Section „Amazon Business“ in [CompanySettingsPage.php](app/Filament/Pages/CompanySettingsPage.php). Muster wie die bestehenden Felder in [CompanySetting.php](app/Models/CompanySetting.php), Defaults über `$attributes`.

### 2. Datenmodell Order
Migration `add_amazon_fields_to_orders`:
- `asin` (nullable, 10 Zeichen)
- `expected_unit_price` (decimal, nullable), dient als Safeguard
- `amazon_external_id` (nullable, unique): unsere idempotente Bestell-ID, z. B. `ZLAB-{public_uuid}`
- `amazon_order_id` (nullable)
- `amazon_status` (string-Enum, nullable)
- `amazon_response` (json, nullable): Roh-Antwort zum Nachvollziehen
- `amazon_ordered_at` (nullable)

Dazu:
- Neues Enum `app/Enums/AmazonOrderStatus.php`: `Accepted`, `PartiallyAccepted`, `Rejected`, `Failed`, implementiert `HasLabel`/`HasColor`, deutsche Labels, Muster wie [RegistrationStatus.php](app/Enums/RegistrationStatus.php).
- ASIN automatisch aus der URL ziehen (Regex auf `/dp/{ASIN}`, `/gp/product/{ASIN}`), im `saving`-Hook von [OrderObserver.php](app/Observers/OrderObserver.php), sofern `asin` leer ist.
- [OrderForm.php](app/Filament/Resources/Orders/Schemas/OrderForm.php) bekommt die Felder `asin` und `expected_unit_price`.

### 3. Service-Schicht (`app/Services/AmazonBusiness/`)
- `AmazonBusinessClient.php`: holt das Access-Token per Refresh-Token via `Http::asForm()->post(lwa_token_url)`, cached es rund 55 Minuten (`Cache::remember`) und stellt `placeOrder(array $payload): array` bereit (Header `x-amz-access-token`, `x-amz-user-email`). Dazu `getOrder(string $externalId): array`. Mit `Http::retry` bei 429 (Rate-Limit 0,5 req/s). Fehler werden als `AmazonBusinessException` geworfen.
- `AmazonOrderService.php`, `place(Collection $orders, bool $trial): AmazonOrderResult`:
  - baut den Payload: `externalId`, je Order ein `lineItem` mit `externalId = order.public_uuid`, `quantity = count`, `SelectedProductReference` (ASIN), optional `ExpectedUnitPrice` (EUR); auf Bestellebene `Region`, `SelectedPaymentMethodReference` (StoredPaymentMethod), `BuyingGroupReference`, `BuyerReference`, `ShippingAddress`, `PurchaseOrderNumber` (≤ 30 Zeichen) und `TrialMode` bei `$trial`
  - wertet `acceptedItems`/`rejectedItems` je lineItem aus
  - bei Echtbestellung: angenommene Orders bekommen den Status `bestellt` (setzt über den bestehenden Observer `orderdatetime`) plus `amazon_order_id`, `amazon_status` und `amazon_response`; abgelehnte Orders bleiben auf `erfasst` mit `amazon_status = Rejected` und Grund
  - Validierung vorab: ASIN vorhanden, Status `erfasst`, höchstens 50 Positionen
- `AmazonOrderResult.php`: Value-Objekt mit angenommenen und abgelehnten Positionen, Gesamtsumme und Ablehnungsgründen (Muster wie [TerminalResponse.php](app/Services/Terminal/TerminalResponse.php)).

### 4. Filament-UI
- Neue Action `app/Filament/Resources/Orders/Actions/PlaceAmazonOrderAction.php`, als Zeilen- und Bulk-Action in [OrdersTable.php](app/Filament/Resources/Orders/Tables/OrdersTable.php):
  1. Beim Öffnen des Modals läuft immer ein **TrialMode-Aufruf**. Das Modal zeigt Positionen, Preise, Lieferzeitraum, Summe und abgelehnte Artikel.
  2. „Verbindlich bestellen“ sendet den echten Aufruf, außer der globale `amazon_trial_mode` ist aktiv. Danach kommt eine Notification mit dem Ergebnis.
  - Sichtbar nur bei Status `erfasst` und gesetzter ASIN, gated über die neue Berechtigung.
- `amazon_status` als Badge-Spalte in der Tabelle.

### 5. Berechtigung
Neue Custom Permission `Orders:PlaceWithAmazon` in `config/filament-shield.php` (neben `Orders:Export`). Im [ShieldSeeder](database/seeders/ShieldSeeder.php) erhalten Z-Lab-Boss und super_admin diese Berechtigung.

### Bewusst nicht im ersten Schritt
- Status-Sync (Lieferung, Versand) über SNS oder Polling. Amazon schickt keine Versandbestätigungen über diese API. „Angekommen“ bleibt manuell wie bisher.
- Automatisches Bestellen ohne Klick.

## Tests (PHPUnit, `tests/Feature/AmazonBusiness/`)
Durchgehend mit `Http::fake` für den LWA-Token- und den Ordering-Endpoint.
- `AmazonOrderServiceTest`:
  - Payload-Aufbau (ASIN, Menge, Adresse, Gruppe, TrialMode)
  - voll angenommen → Status `bestellt` plus `amazon_order_id`
  - teilweise abgelehnt
  - komplett abgelehnt → bleibt `erfasst`
  - HTTP-Fehler → `Failed`, keine Statusänderung
  - Trial verändert nichts in der DB
  - Validierung: fehlende ASIN, mehr als 50 Positionen
  - Token wird gecacht (nur ein Token-Request bei zwei Bestellungen)
- `AsinExtractionTest`: verschiedene URL-Formen (amazon.de/dp, gp/product, mit Query, keine Amazon-URL).
- `PlaceAmazonOrderActionTest` (Livewire `ListOrders`): Action nur mit Berechtigung sichtbar, Bulk-Aufruf löst den Service aus, Notification erscheint.

## Verifikation
1. `php artisan test --compact tests/Feature/AmazonBusiness`
2. `vendor/bin/pint --dirty --format agent`
3. Nach dem Amazon-Onboarding: echte Credentials in `.env`, `amazon_trial_mode = true` lassen, über die UI eine Testbestellung auslösen und die Trial-Antwort prüfen. Danach den Trial-Mode ausschalten und einen günstigen Artikel echt bestellen.
