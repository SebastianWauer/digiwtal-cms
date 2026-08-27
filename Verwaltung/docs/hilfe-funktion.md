# Hilfe-Funktion

Ein Kunde meldet ein Problem in seinem eigenen CMS. Die Meldung landet in der
Verwaltung, in einem Posteingang ueber alle Instanzen hinweg. Die Antwort geht
denselben Weg zurueck und erscheint im CMS des Kunden - an genau der Stelle, an
der er sie geschrieben hat.

Kein E-Mail-Verkehr, keine Frage nach der Version, kein Suchen im Postfach.

## Der Weg

```
Kunden-CMS  /hilfe
   |  POST /api/support/tickets      Header: X-Support-Token
   v
Verwaltung  support_tickets          -> /admin/support
   |  Antwort + Status
   v
Kunden-CMS  /hilfe                   GET /api/support/tickets
```

## Das Token

Jede Instanz weist sich mit einem eigenen Token aus. Es haengt am Serverzugang
des Kunden (`server_access.support_token_*`) und wird **automatisch erzeugt**,
sobald die Pipeline die Zugangsdaten abholt. Niemand muss es eintragen - und
genau deshalb kann es auch niemand vergessen. Die Fehlversuche beim ersten
Rollout gingen samt und sonders auf Felder zurueck, die leer geblieben waren.

Gespeichert wird es zweifach: als SHA-256-Hash zum Nachschlagen in einer
einzigen Abfrage, und verschluesselt im Vault, damit es bei einem spaeteren
Rollout unveraendert wieder in die `.env` geschrieben werden kann.

Das Token bestimmt zugleich, wer meldet. Es gibt keinen Weg, im Namen eines
anderen Kunden zu schreiben oder dessen Meldungen zu lesen.

## In der `.env` der Instanz

```
SUPPORT_URL=https://verwaltung.example.de
SUPPORT_TOKEN=<64 Hex-Zeichen>
```

Beides schreibt der Rollout. Bei bestehenden Instanzen ergaenzt der Schritt
*Hilfe-Zugang nachtragen* die fehlenden Zeilen, ohne die `.env` sonst
anzufassen. Fehlen die Werte, ist die Hilfe-Seite trotzdem sichtbar, das
Absenden aber deaktiviert - mit einem Hinweis statt einer Fehlermeldung.

## Rechte

Im CMS: keine. Wer sich anmelden kann, darf um Hilfe bitten. Ein Recht, das
erst jemand vergeben muss, waere genau die Huerde, an der so eine Funktion
stirbt.

In der Verwaltung: jeder angemeldete Admin sieht den Posteingang.

## Grenzen

- 20 Meldungen pro Kunde und Stunde. Danach `429 rate_limited`.
- Betreff 190 Zeichen, Text 20.000, Anfrage insgesamt 200 KB.
- Umgebungsangaben: hoechstens 20 Schluessel, je 300 Zeichen, nur Skalares.
  Was von einer fremden Installation kommt, wird gekuerzt und gefiltert.

## Status

`neu` -> `in_arbeit` -> `beantwortet` -> `erledigt`. Eine Antwort setzt den
Status automatisch auf `beantwortet`; ein ausdruecklich gewaehlter Status
gewinnt dagegen, damit sich "erledigt" zusammen mit der Antwort speichern
laesst. Jede Antwort und jede Statusaenderung steht im Audit-Log
(`support.answered`, `support.status`, `support.ticket_received`).
