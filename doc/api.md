# API

# Anbindung Zeiterfassungsterminals

Das Terminal meldet sich per BASIC-Authentication am Server an (nur über HTTPS).
Zugangsdaten werden pro Terminal im Cockpit unter Zeiterfassung → Terminals angelegt.

https://cockpit.z-lab-bruchsal.de/api/v1/book/[ID_CHIP]

https://cockpit.z-lab-bruchsal.de/api/v1/bookinginfo/[ID_CHIP]

`book` stempelt: offene Buchung → Gehen, sonst → Kommen.
`bookinginfo` (Info-Taste) ändert nichts und liefert den aktuellen Stand.

ID_CHIP: Chip-UID als Hex, Groß-/Kleinschreibung und Trenner `:`/`-` egal (`04:a1:b2:c3` = `04A1B2C3`).

Antwort: JSON mit 3 Feldern:
status: {ok, warning, error}
subject: Text
text: text

Beispiele:

| Fall                         | status  | subject            | text                                          |
|------------------------------|---------|--------------------|-----------------------------------------------|
| Kommen                       | ok      | Hallo Max          | Kommen 07:58 · heute 0:00 h                   |
| Gehen                        | ok      | Tschüss Max        | Gehen 16:30 · heute 8:32 h                    |
| erneut innerhalb 60 s        | warning | Bereits gestempelt | Letzte Buchung um 07:58                       |
| Chip keinem User zugeordnet  | error   | Chip unbekannt     | Bitte im Cockpit zuordnen                     |
| Chip gesperrt                | error   | Chip gesperrt      | Bitte an die Verwaltung wenden                |
| Info, eingestempelt          | ok      | Max                | Anwesend seit 07:58 (1:12 h) · heute 1:12 h   |
| Info, nicht eingestempelt    | ok      | Max                | Nicht eingestempelt · heute 8:32 h            |

HTTP-Statuscodes:
* 200: alle fachlichen Ergebnisse (auch warning/error), Terminal zeigt subject/text an
* 401: Terminal unbekannt, Passwort falsch oder Terminal deaktiviert
* 404: ungültige Chip-ID (keine Hex-Zeichen)
* 429: zu viele Anfragen (max. 60/min pro Terminal)
* 5xx / Timeout: Server-Problem, Terminal zeigt Fehler an

Gebucht wird immer mit der Serverzeit beim Eingang des Requests. Offline-Puffern ist daher (noch) nicht vorgesehen.

# Cockpit

* Zeiterfassung → Terminals: Terminals mit eigenen Zugangsdaten, „zuletzt gesehen“
* Zeiterfassung → Chips: unbekannte Chips (Filter) mit „zuletzt gesehen“, Zuordnung zu einem User, Sperren
* Zeiterfassung → Stempel-Protokoll: wer hat wann an welchem Terminal gestochen
* Buchungen: Spalte/Filter „Quelle“ (Cockpit live, Cockpit manuell, Stechuhr)
