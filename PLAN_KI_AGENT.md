# KI-Kursentwurf im Cockpit (Stufe 1) + Weg zum Chat-Agenten (Stufe 2)

## Context
Ein OpenAI-kompatibles LLM (lokal, später auch vom Server aus erreichbar) soll im Cockpit Kursbeschreibungen entwerfen. Eingabe z. B. „2h-Kurs Fischertechnik für 10-Jährige“ → vorausgefülltes Kursformular, das man prüft und speichert.
Jetzt: nur ein Button auf „Kurs anlegen“. Später: ein Chat-Agent mit Tools. Die Architektur von Stufe 1 soll Stufe 2 nicht verbauen, deshalb kommt direkt das offizielle **laravel/ai**-SDK (Agent-Klassen, Structured Output, Tools, Conversations) zum Einsatz.

## Stufe 1 – Umsetzung

### 0. Plan ins Projekt
Dieser komplette Plan, also Stufe 1 und Stufe 2, wird als `PLAN_KI_AGENT.md` im Projekt-Root abgelegt, analog zu `PLAN_AMAZON_BUSINESS.md`. Das ist der erste Schritt nach der Freigabe.

### 1. Paket & Konfiguration
- `composer require laravel/ai` (Freigabe der neuen Abhängigkeit hast du erteilt)
- `php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"` → `config/ai.php` und die Migration für `agent_conversations` / `agent_conversation_messages`. Die Migration wird mit eingespielt, damit Stufe 2 sie direkt nutzen kann. Es sind nur leere Tabellen.
- In `config/ai.php` einen Provider ergänzen:
  ```php
  'local' => ['driver' => 'openai-compatible', 'url' => env('LOCAL_AI_URL'), 'key' => env('LOCAL_AI_API_KEY')],
  ```
  Dazu den Default-Provider auf `local` setzen.
- `.env` enthält bereits `LOCAL_AI_URL=http://alegab-pc:8800/v1` und `LOCAL_AI_API_KEY`. Beides ist aus dem Devcontainer erreichbar und wurde getestet.
- `.env.example` bekommt dieselben Schlüssel ohne Werte, dazu `LOCAL_AI_MODEL_DRAFT`, `LOCAL_AI_MODEL_FAST`, `LOCAL_AI_MODEL_TOOLS` und `LOCAL_AI_TIMEOUT` (Default 180).
  - Server: nur die URL anpassen, z. B. auf einen Tunnel zu alegab-pc. Am Code ändert sich nichts.
- Ist `LOCAL_AI_URL` leer, wird der Button ausgeblendet. Dafür gibt es eine kleine Prüfung, z. B. `CourseDrafter::isAvailable()`.

### 1b. Modelle – das lokale System wechselt selbst anhand des `model`-Felds im Request
Den Endpunkt habe ich bereits geprüft. Dahinter läuft **llama-swap** mit llama.cpp. `GET /v1/models` liefert:

| id | Anzeigename (`name`) | Hinweise |
|---|---|---|
| `qwen3.5-35b-a3b` | Qwen3.5 35B-A3B (MoE) | `function_calling: true`, 128k Kontext |
| `glm-4.7-flash` | GLM-4.7 Flash (MoE) | |
| `qwen3.5-9b` | Qwen3.5 9B (schnell, komplett im VRAM) | |

Jeder Eintrag hat zusätzlich `status.value`, also `loaded` oder `unloaded`.

Probe-Requests gegen `qwen3.5-35b-a3b` mit `response_format: json_schema` (strict):
- **Mit Thinking (Default):** Die gesamte Denkarbeit landet in `message.reasoning_content`, `content` bleibt leer. Bei 300 Tokens kam `finish_reason: length`, also gar kein JSON.
- **Mit `chat_template_kwargs: {enable_thinking: false}`:** sauberes, schemakonformes JSON in `content`, nach ca. 5 s.

Daraus folgt:
- `CourseDrafter` implementiert zusätzlich `HasProviderOptions`. `providerOptions()` gibt für den Provider `local` den Wert `['chat_template_kwargs' => ['enable_thinking' => false]]` zurück. Laut Doku ist das genau der Weg, um zusätzliche Body-Felder an `openai-compatible` zu schicken.
  - Der Schalter lässt sich per Config umstellen: `LOCAL_AI_THINKING=false`. Wer Thinking einschaltet, braucht ein großes `#[MaxTokens]`.
- Für den Fall, dass ein Modell doch `<think>` in `content` schreibt, werden solche Blöcke vor der Verarbeitung entfernt.
- `#[MaxTokens(4096)]`: Die Beschreibung mit Ablaufplan ist lang. `#[Timeout]` kommt aus der Config (Default 180 s), weil llama-swap beim Modellwechsel erst laden muss. Ein `unloaded`-Modell braucht spürbar länger.

**Modellrollen** stehen in `config/ai.php` unter `'local_models'` und sind per `.env` überschreibbar:
- `draft` = `qwen3.5-35b-a3b`: Default für den Kursentwurf
- `fast` = `qwen3.5-9b`: kleine Aufgaben, später
- `tools` = `glm-4.7-flash`: Kandidat für Stufe 2. Laut `/models` kann allerdings auch Qwen 35B `function_calling`. Das wird in Stufe 2 getestet.

**Service `app/Services/Ai/LocalModels.php`:**
- `available(): array<string, string>` holt `GET {url}/models` mit Bearer-Key und gibt `id => name` zurück. Bei geladenen Modellen kommt „ (geladen)“ an den Namen, damit man sieht, welches ohne Wartezeit läuft.
- Das Ergebnis wird 60 s gecacht. Bei Fehlern gibt es `[]`.
- `forRole(string $role): string`

**Modal der Action:** `Select::make('model')` mit den Optionen aus `available()`, Default `forRole('draft')`. Ist `/models` nicht erreichbar, steht nur der Default zur Auswahl. Der Aufruf lautet `prompt($wish, provider: 'local', model: $model)`.

### 2. Agent `app/Ai/Agents/CourseDrafter.php`
- Erzeugt mit `php artisan make:agent CourseDrafter --structured`.
- Implementiert `Agent` und `HasStructuredOutput` und nutzt `Promptable`.
- `instructions()`: deutscher Systemprompt mit folgenden Vorgaben:
  - Rolle: Kursplaner für das z-lab, ein MINT-Lernort für Kinder
  - Tonalität für Eltern und Kinder
  - Beschreibung als HTML nur mit `h2`, `h3`, `p`, `ul`, `ol`, `strong` und `em` (passend zur RichEditor-Toolbar in [CourseForm.php](app/Filament/Resources/Courses/Schemas/CourseForm.php))
  - Kurztext höchstens 300 Zeichen
  - Ablaufplan mit Zeitblöcken, Materialliste und Lernzielen in der Beschreibung
  - Die Liste der vorhandenen **Interessen** (`Interest::orderBy('sort')`) wird in die Instructions eingebettet. Das LLM darf nur diese Namen wählen.
- `schema()`:
  - `name` (string, required)
  - `teaser` (string, max 300)
  - `description` (string/HTML)
  - `min_age` und `max_age` (int)
  - `duration` (int, Minuten)
  - `default_capacity` (int)
  - `interests` (array of string)
- Eigene Methode `draft(string $wish): array`. Sie ruft `prompt($wish, timeout: config(...))` auf und mappt die Antwort auf Formularfelder:
  - Interessen-Namen werden in IDs übersetzt, unbekannte fallen weg.
  - Zahlen werden auf gültige Bereiche geklemmt.
  - `teaser` wird auf 300 Zeichen gekürzt.
  - `description` wird gefiltert, sodass nur die erlaubten Tags übrig bleiben (`strip_tags` mit Allowlist).
- Für Stufe 2 bleibt der Agent unverändert nutzbar: Tools und `Conversational` kommen später nur dazu.

### 3. Filament-Action `app/Filament/Actions/DraftCourseWithAiAction.php`
- Folgt dem Muster von [HomepagePreviewAction.php](app/Filament/Actions/HomepagePreviewAction.php): eigene Action-Klasse mit `getDefaultName()` und `setUp()`.
- Label „Mit KI entwerfen“, Icon `Heroicon::OutlinedSparkles`. Das Modal enthält ein `Textarea::make('wish')` (required) mit einem Placeholder als Beispiel.
- `->visible(fn () => CourseDrafter::isAvailable())`
- `->action(...)`:
  - `$draft = CourseDrafter::make()->draft($data['wish'])`
  - danach `$livewire->form->fill([...$livewire->form->getRawState(), ...$draft, 'audience' => CourseAudience::Kids])`
  - zum Schluss eine Erfolgs-Notification: „Entwurf eingefügt – bitte prüfen“
- Fehler (Timeout, Verbindung, ungültige Antwort) werden abgefangen. Es kommt eine Danger-Notification mit Hinweis, das Formular bleibt unverändert und der Fehler wird geloggt.
- Wird in [CreateCourse.php](app/Filament/Resources/Courses/Pages/CreateCourse.php) unter `getHeaderActions()` vor `HomepagePreviewAction` eingetragen. Optional später auch in `EditCourse`.
- Rechte: Die Action ist nur sichtbar, wenn der Nutzer Kurse anlegen darf (`CourseResource::canCreate()`).

### 4. Tests – `tests/Feature/Courses/DraftCourseWithAiTest.php` (PHPUnit)
Alle Tests nutzen `CourseDrafter::fake([...])->preventStrayPrompts()` und `actingAs` mit einem User, der die nötigen Rechte hat.
- **Happy Path:** Die Action füllt Name, Kurztext, Beschreibung, Alter, Dauer und Interessen-IDs. `assertPrompted` prüft, dass der Wunsch-Text übergeben wurde.
- **Unbekanntes Interesse:** Es wird ignoriert. Werte außerhalb des Bereichs werden geklemmt, unerlaubte HTML-Tags entfernt.
- **Provider-Fehler:** Ein Fake, das eine Exception wirft, führt zur Danger-Notification. Das Formular bleibt leer.
- **Nicht konfiguriert:** Ist `LOCAL_AI_URL` leer, ist die Action unsichtbar (`assertActionHidden`).
- **Validierung:** Ein leerer Wunsch erzeugt einen Formularfehler.
- **Modellwahl:** Das gewählte Modell wird an den Agenten durchgereicht (`assertPrompted`). `LocalModels::available()` wird mit `Http::fake` getestet, sowohl mit Erfolg als auch mit Fehler.
- **Think-Blöcke:** Sie werden aus der Antwort entfernt.
- **Provider-Optionen:** `providerOptions('local')` enthält `enable_thinking: false`.

## Stufe 2 – Chat-Agent (Teil von `PLAN_KI_AGENT.md`)
- Eigene Filament-Seite „KI-Assistent“ mit Chatverlauf (Livewire, ggf. mit Streaming über `->stream()`).
- Der Agent `CourseAssistant` nutzt `Conversational` und `RemembersConversations`. Die Tabellen sind schon migriert. `User` bekommt `HasConversations`.
- Tools (`php artisan make:tool`):
  - `ListInterests`, `ListBelts`
  - `FindSimilarCourses`: existierende Kurse als Stilvorlage
  - `CreateCourseDraft`: legt einen Kurs mit `is_published=false` an und baut intern auf der Mapping-Logik von `CourseDrafter::draft()` auf
  - später `PlanCourseInstance` (Termine)
- Schreibende Tools werden `Approvable`. Der Nutzer bestätigt im Chat, bevor etwas gespeichert wird.
- Konversationen werden pro User autorisiert, denn `continue()` prüft den Besitz nicht.
- Wiederverwendung aus Stufe 1: Provider-Config, Mapping und Sanitizing, Verfügbarkeitsprüfung, Test-Fakes.

## Kritische Dateien
- neu: `config/ai.php`, `app/Services/Ai/LocalModels.php`, `app/Ai/Agents/CourseDrafter.php`, `app/Filament/Actions/DraftCourseWithAiAction.php`, `tests/Feature/Courses/DraftCourseWithAiTest.php`, `PLAN_KI_AGENT.md`
- geändert: `composer.json` und `composer.lock`, `.env.example`, `app/Filament/Resources/Courses/Pages/CreateCourse.php`

## Verifikation
1. `php artisan test --compact tests/Feature/Courses/DraftCourseWithAiTest.php`
2. `vendor/bin/pint --dirty --format agent`
3. Manuell: `.env` auf das lokale LLM zeigen lassen. Vorher die Erreichbarkeit aus dem Devcontainer prüfen: `curl $LOCAL_AI_URL/models`. Danach im Cockpit „Kurs anlegen“ öffnen, „Mit KI entwerfen“ klicken, „2h-Kurs Fischertechnik für 10-Jährige“ eingeben und prüfen, ob die Felder befüllt sind und sich speichern lassen.
4. Fragen, ob die gesamte Test-Suite laufen soll.
