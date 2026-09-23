# API

# Anbindung Zeiterfassungsterminals

Das Terminal meldet sich per BASIC-Authentication am Server an

http://cockpit.z-lab-bruchsal.de/api/book/[ID_CHIP]
http://cockpit.z-lab-bruchsal.de/api/bookinginfo/[ID_CHIP]

Antwort: JSON mit 3 Feldern:
status: {ok, warning, error}
subject: Text
text: text

# Todo Cockpit:
* Terminals als Objekte
* der nächste Chip gehört user X
* Liste aller unbekannter Chips mit Zeitstempel "last seen"
* Wer hat wann an welchem Terminal gestochen?
* Unterschied Buchungen (cockpit live, cockpit manuell, Stechuhr)
* Antwort der Buchungen mit Saldo heute, Länge aktuelle Buchung



