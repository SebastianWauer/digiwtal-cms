# DESIGN.md — DIGIWTAL CMS

Designregeln dieses Projekts. Abgeleitet aus `public/assets/css/` und
`app/includes/layout.php`, aus der Vergleichsanalyse gegen die
[DESIGN.md der Verwaltung](../Verwaltung/DESIGN.md) und aus den
Entscheidungen, die seither getroffen wurden.

Diese Datei ist bewusst kürzer als ihr Gegenstück in der Verwaltung. Das CMS
ist getrennt gewachsen: Farben, Radien und Eingabeformen stehen hier
mehrfach im Code statt einmal als Token. Was noch nicht entschieden ist,
steht als **TODO: ungeklärt** — nicht als Platzhalterwert. Wer eine Regel
neu trifft, trägt sie hier ein und ersetzt das TODO.

## Herkunft jeder Regel

Später soll aus beiden Dateien ein gemeinsames System entstehen. Damit das
möglich ist, trägt jede Regel eine Kennzeichnung:

| Marke | Bedeutung |
|---|---|
| **[V]** | Aus der Verwaltung übernommen, gilt in beiden Projekten gleich |
| **[CMS]** | Gilt nur hier — begründet, weil das CMS eine andere Aufgabe hat |
| **TODO** | Nicht entschieden. Nicht raten, nicht füllen. |

Die Guardrails der Verwaltung sind **keine gemeinsamen Regeln**, solange sie
hier nicht ausdrücklich übernommen sind. Drei davon gelten hier
nachweislich nicht (Abschnitt 9).

---

## 1. Charakter **[CMS]**

**Ein Arbeitsplatz, kein Kontrollinstrument.** Das ist der Unterschied zur
Verwaltung, und aus ihm folgt fast alles andere in dieser Datei.

Hier sitzt jemand stundenlang: schreibt Texte, ordnet Inhalte an, verschiebt
Blöcke, sichtet Medien. Die Oberfläche muss über lange Sitzungen ruhig
bleiben. Sie darf nie um die Aufmerksamkeit konkurrieren mit dem, was der
Redakteur gerade schreibt — der Text ist die Arbeit, die Oberfläche ist das
Werkzeug drumherum.

Daraus folgt die Rangordnung: **Zurückhaltung und Ermüdungsfreiheit wiegen
schwerer als Informationsdichte.** Wo die Verwaltung Dichte vor Großzügigkeit
setzt, damit ein Zustand auf einen Blick lesbar ist, gilt hier das Umgekehrte
— eine Ansicht, die alles zeigt, aber nach zwei Stunden anstrengt, ist im CMS
die schlechtere Lösung. Und aus derselben Rangordnung folgt das Hell-Theme
(2.1): Wer stundenlang schreibt, soll die Helligkeit wählen können, die ihn
am wenigsten ermüdet.

Das ist ein Maßstab, keine Stilfrage. Wo eine offene Entscheidung in dieser
Datei zwischen „mehr auf den Schirm" und „ruhiger über die Dauer" steht,
gewinnt das Zweite.

---

## 2. Farben

### 2.1 Zwei Themes — das ist eine Entscheidung dieses Projekts **[CMS]**

Die Verwaltung kennt nur Dunkel und verbietet Theme-Umschaltung
ausdrücklich. **Diese Regel gilt hier nicht.** Das Hell-Theme ist im CMS
kein Versehen, sondern gebaut:

- `ThemeController.php` mit eigener Route `Paths::THEME`
- Persistenz als Benutzereinstellung, gelesen in
  [layout.php](app/includes/layout.php#L50)
- Umschalter in [sidebar.php](app/includes/sidebar.php#L275)
- **getrennte Logo-Dateien je Theme** —
  `$logoNow = ($theme === 'light') ? $logoLight : $logoDark`
- 39 `[data-theme="light"]`-Regeln über 7 CSS-Dateien, dazu 17
  `[data-theme="dark"]`-Overrides

Zwei hochgeladene Logos sind ein Beleg, den niemand nebenbei produziert.

**Verbindlich daraus:**

- Theme-Overrides stehen auf `html[data-theme="…"]`, **nie auf `body`**.
  `layout.php` setzt das Attribut auf `<html>`; ein `body`-Selektor greift
  nicht. Diese Verwechslung hatte die System-Health-Seite hell auf dunklem
  Panel gerendert.
- Jede neue Farbregel wird in **beiden** Themes geprüft. Ein Wert, der nur
  im Dunkeln funktioniert, ist keine Lösung.

**Warum es das Hell-Theme gibt:** weil hier stundenlang geschrieben wird und
Ermüdungsfreiheit vor Informationsdichte geht (Abschnitt 1). Wer den ganzen
Tag in dieser Oberfläche arbeitet, soll die Helligkeit wählen können, die ihn
am wenigsten ermüdet. Das ist keine Vorliebe, die man später wegoptimiert,
sondern folgt direkt aus dem Charakter des Projekts.

Damit ist auch beantwortet, warum die Verwaltung **nicht** nachziehen muss:
sie wird zum Nachsehen geöffnet, nicht zum Arbeiten. Ihre Begründung für den
einen dunklen Modus bleibt gültig, ohne dass sie hier gälte.

### 2.2 Grundflächen und Text **[V]**

Diese Tokens sind im CMS und in der Verwaltung wertgleich. Sie stehen auf
`:root` in [admin-layout.css](public/assets/css/admin-layout.css#L1) und
sind die erste Ebene, die ins gemeinsame System gehört.

| Variable | Dunkel | Hell | Rolle |
|---|---|---|---|
| `--bg` | `#0f1012` | `#f5f7fb` | Seitengrund |
| `--text` | `#f2f2f3` | `#0b1020` | Primärtext |
| `--muted` | `#b0b3b8` | `#5e6575` | Sekundärtext, Labels |
| `--panel` | `#131416` | `#ffffff` | Tragendes Panel |
| `--panel-border` | `#202226` | `#e7ebf1` | Rahmen des Panels |
| `--sidebar-bg` | `#0b0c0e` | `#ffffff` | Seitenleiste |
| `--sidebar-border` | `#1a1c20` | `#e7ebf1` | Trennkante |
| `--card` | `rgba(255,255,255,.03)` | `#ffffff` | Fläche auf dem Panel |
| `--card-border` | `rgba(255,255,255,.08)` | `#e7ebf1` | Rahmen dieser Flächen |
| `--btn-bg` | `rgba(255,255,255,.05)` | `#ffffff` | Sekundäre Buttons |
| `--btn-border` | `rgba(255,255,255,.10)` | `#e7ebf1` | Rahmen |
| `--btn-hover` | `rgba(255,255,255,.08)` | `#f1f3f6` | Hover-Fläche |
| `--active` | `rgba(255,255,255,.10)` | `#f1f3f6` | Aktiver Nav-Eintrag |

Die Hell-Spalte ist **[CMS]** — die Verwaltung hat sie nicht.

### 2.3 Der Fokusring hat ein eigenes Token **[V]**

```css
:root                    { --focus: #ffffff; }
html[data-theme="light"] { --focus: #0b1020; }
```

**Warum ein eigenes Token und nicht `--text`, `--primary` oder eine
Zustandsfarbe:** Fokus zeigt *Bedienung* an, sonst nichts. Er ist kein
Zustand, keine Bedeutung und keine Marke. Hinge er an `--primary`, wäre die
Kontur an „primäre Aktion" gekoppelt — im Hell-Theme ist `--primary: #111111`
genau die Füllung des Primärbuttons, ein Ring in Buttonfarbe. Hinge er an
einer Zustandsfarbe, läse sich Fokus als Meldung.

Im Dunkeln ist `#ffffff` deshalb bewusst *heller* als `--text: #f2f2f3`: der
höchste verfügbare Kontrast auf `#0f1012`, und ein Wert, der keiner anderen
Rolle gehört.

Im Hellen wäre Weiß unsichtbar. Gewählt ist der **dunkelste Ton der
Hell-Ebene** — dieselbe Konstruktion, nur gespiegelt: dort der hellste Ton
auf dunklem Grund, hier der dunkelste auf hellem. Rund 19:1 gegen die
Panelfläche.

> **Entschiedene Ausnahme von der Ein-Ton-Regel.** `#0b1020` steht damit
> zweimal im Token-Block: einmal als `--text`, einmal als `--focus`. Das
> verstößt gegen 2.5 und ist trotzdem richtig — ein Alias `var(--text)`
> würde `--focus` an `--text` koppeln und genau die Entkopplung aufheben,
> die dieses Token begründet. Die Werte sind heute gleich, ihre Rollen
> nicht: wer `--text` ändert, ändert die Textfarbe, nicht den Fokusring.
>
> Die Entkopplung ist damit in beiden Themes echt.

### 2.4 Es gibt eine Akzentfarbe, und sie ist nicht definiert

Die Verwaltung hat keine Akzentfarbe und begründet das. Das CMS benutzt an
vier Stellen `var(--accent, #c41e3a)` — in
[admin-pages-edit.css](public/assets/css/admin-pages-edit.css#L1490) und
[admin-pages-list.css](public/assets/css/admin-pages-list.css#L112).

`--accent` ist **nirgends im Projekt definiert**; der Fallback greift immer.
Über `accent-color` färbt er jede Checkbox im Blockeditor. Er ist rot und
damit nicht von der Gefahr-Bedeutung zu unterscheiden.

**TODO: ungeklärt** — ob das CMS eine Akzentfarbe haben soll. Wenn ja, mit
welchem Wert und welcher Rolle; wenn nein, wodurch die vier Stellen ersetzt
werden. Nicht raten: das Muster sieht nach einer begonnenen und
abgebrochenen Tokenisierung aus, aber der Code sagt es nicht.

### 2.5 Token-Konvention **[V]**

Verbindlich für **neuen** Code:

- **Ein Farbton steht genau einmal.** Kein Hex- oder `rgba()`-Wert direkt in
  einer Regel, wenn er eine Rolle trägt.
- **Tönungen entstehen per `color-mix()`**, abgeleitet aus dem einen Token:
  ```css
  color-mix(in srgb, var(--token) 8%, transparent)
  ```
  nicht als eigener Hex- oder `rgba()`-Wert.

`color-mix()` setzt einen Browser ab 2023 voraus. Das ist hier gesetzt und
keine neue Annahme: `admin-pages-edit.css` benutzt es bereits 60-mal. Auf
derselben Grundlage ist auch `:has()` zulässig (Abschnitt 6.4).

**Der Bestand wird nicht konvertiert.** Heute stehen im Admin-CSS 172
`rgba()`-Literale und rund 60 verschiedene Hex-Werte. Eine Umstellung würde
jede Ansicht anfassen. Die Regel gilt ab jetzt, nicht rückwirkend.

### 2.6 Zustandsfarben **[V]** — Töne entschieden, Migration läuft

Vier Semantiken, je **ein Token mit zwei Werten**: einer für Dunkel, einer
für Hell. Die Leiter darüber ist themeunabhängig, weil sie aus dem jeweils
aktiven Grundton abgeleitet wird.

| Semantik | Dunkel | Hell | Kontrast dunkel / hell (auf `--panel`) |
|---|---|---|---|
| `--success` | `#32d583` | `#166534` | 9,64 / 7,13 |
| `--warning` | `#ffba49` | `#92400e` | 10,86 / 7,09 |
| `--danger` | `#ff6b6b` | `#b42318` | 6,64 / 6,57 |
| `--info` | `#6aa9ff` | `#1d4ed8` | 7,66 / 6,70 |

**Warum zwei Werte je Semantik:** Die Töne der Verwaltung sind für dunklen
Grund gebaut. Als Schrift auf `#ffffff` erreichen sie 1,70:1 (Gelb), 1,91:1
(Grün), 2,40:1 (Blau) und 2,78:1 (Rot) — allesamt unlesbar. Auch `#1d9f6f`,
der beste vorgefundene Grünton, kommt dort nur auf 3,36:1. Ein einziger Ton
je Semantik kann in diesem Projekt nicht funktionieren, solange es zwei
Themes gibt.

Die Dunkel-Spalte ist **wertgleich mit der Verwaltung**. Die Hell-Spalte ist
**[CMS]** — dieselbe Konstruktion wie bei den Grundflächen in 2.2.

#### Was eine Zustandsfarbe ist — und was nicht

Die vier Tokens tragen **Bedeutung**, nicht Aussehen. Wer eine Farbe braucht,
weil etwas rot *aussehen* soll, greift zum falschen Werkzeug.

| Semantik | Bedeutet | Beispiele |
|---|---|---|
| `--success` | Etwas ist gültig, aktiv, gelungen | Plakette *live*, Startseiten-Haken, Speichern, Status *fertig* |
| `--warning` | Etwas verlangt Aufmerksamkeit oder ist umkehrbar folgenreich | Plakette *Entwurf*, Abbrechen, Wiederherstellen, Status *in Arbeit* |
| `--danger` | Etwas zerstört oder ist fehlgeschlagen | Löschen, Plakette *abgelaufen*, Fehlermeldung |
| `--info` | Neutraler Hinweis ohne Wertung | Navigationsbereich einer Seite |

**Die Abgrenzung zwischen `--warning` und `--danger` ist ein Schweregrad,
keine Farbwahl.** `.pages-edit-iconbtn--cancel` verwirft eine Bearbeitung,
`--delete` zerstört eine Seite. Nur das Zweite ist eine Gefahr. Deshalb liegt
Abbrechen auf `--warning` und Löschen auf `--danger` — die beiden sind damit
über den Farbton unverwechselbar, ohne dass die Leiter eine fünfte Stufe
bräuchte.

**Nicht zu den Zustandsfarben gehört, was auf fremdem Material liegt.** Der
Fokuspunkt der Bildbearbeitung (`.media-edit__focus-dot`) ist ein Marker auf
beliebigen Benutzerfotos, keine Zustandsmeldung. Er hat ein eigenes,
**themeunabhängiges** Token:

```css
--marker: #e6007e;
```

Seine Anforderung ist eine andere als die einer Zustandsfarbe: Er muss auf
hellen wie dunklen Bildern sitzen, und er darf sich mit dem Theme nicht
ändern — das Bild darunter ändert sich ja auch nicht. Der Punkt trägt einen
weißen Innenrand und einen dunklen Außenring; die Füllung muss sich deshalb
von **beiden** absetzen. Magenta erreicht 4,50 gegen Weiß und 4,67 gegen
Schwarz und liegt damit fast auf dem bestmöglichen Gleichgewicht (Optimum
4,68 bei mittlerer Leuchtdichte). Es kommt außerdem in Fotos selten dominant
vor. Der vorherige Ton `#e74c3c` lag bei 3,82 / 5,50, also deutlich
unausgewogener.

**Ebenfalls keine Zustandsfarben,** obwohl farbig: `--accent` (2.4), das
Violett der Footer-Plakette, die Graublautöne des Katalogstatus.

#### Die Tönungsleiter

| Stufe | Anteil | Wofür |
|---|---|---|
| `-surface` | 8 % | Plaketten, Meldungen, Hinweiskarten |
| `-surface-strong` | 14 % | Getönte Buttons |
| `-border` | 20 % | Rahmen der Meldungsflächen |
| `-border-strong` | 28 % | Rahmen getönter Buttons und Plaketten |

**Gemischt wird gegen `transparent`, nicht gegen `var(--panel)`.** Getönte
Flächen sitzen im CMS nicht immer auf dem Panel: `.pages-badge` steht in
Tabellenzeilen innerhalb `.pages-card`, das selbst `--card` über `--panel`
ist. Gegen `var(--panel)` gemischt wird die Panelfarbe in ein Element
eingebacken, das auf einer Karte liegt — gemessen verliert die Tönung dort
im Dunkeln ein Drittel ihrer Wirkung (Delta 1,107 gegen 1,032) und bekommt
eine sichtbare Kante. Alpha-Komposition passt sich dem tatsächlichen
Untergrund an.

Dass dieselbe Prozentzahl auf hellem und dunklem Grund unterschiedlich
wirkt, löst sich durch die zwei Grundtöne von selbst: heller Ton auf dunklem
Grund und dunkler Ton auf hellem ergeben bei 8 % nahezu gleiche
Tönungsstärke (1,10–1,15 dunkel gegen 1,11–1,14 hell).

#### Es gibt hier kein `-text`-Token — und das ist Absicht

Die Verwaltung führt `--info-text: color-mix(in srgb, var(--info) 50%,
white)`, weil ihr einziger Ton auf dunklem Grund als Schrift aufgehellt
werden muss. **Das CMS braucht das nicht:** weil der Grundton je Theme
gewählt ist, trägt er selbst als Schrift. Auf der eigenen 8-%-Tönung
gemessen 4,68:1 im schlechtesten Fall, auf der 20-%-Tönung 4,68–7,02:1 —
durchgehend über der Schwelle.

Ein `-text`-Token wäre hier ein fünftes, das nichts löst. Beim späteren
Extrahieren des gemeinsamen Systems ist dieser Unterschied deshalb **ein
bewusster, kein Versehen**: die Verwaltung braucht das Token, weil sie
einfarbig dunkel ist; das CMS löst dasselbe Problem über die zweite
Themespalte.

#### Die Rahmenstufen bleiben bewusst unter 3:1

`-border` und `-border-strong` erreichen gegen ihren Untergrund 1,31 bis
1,92:1. Das ist **kein Versäumnis**: Es sind dekorative Begrenzungen
getönter Flächen, keine Grenzen interaktiver Bedienelemente. Für die gilt
der Fokusring aus 6.4, der als deckende Kontur mit `--focus` den
höchstmöglichen Kontrast trägt.

Wer hier 3:1 verlangte, müsste die Rahmen auf rund 55 % heben — das macht
jede getönte Fläche deutlich lauter und widerspricht Abschnitt 1.

#### Stand der Migration

Betroffen sind 77 Deklarationen in 8 Dateien. Migriert wird **eine Semantik
je Durchgang**, damit ein Fehler am Server einer Farbe zuzuordnen ist.

| Durchgang | Semantik | migriert | Overrides entfernt | ausgenommen | Stand |
|---|---|---|---|---|---|
| 1 | Blau | 2 | 0 | 4 | **erledigt** |
| 2 | Grün | 14 | 0 | 4 | **erledigt** |
| 3 | Gelb | 17 | 6 | 0 | **erledigt** |
| 4 | Rot | 16 | 6 | 8 | **erledigt** |
| | **Summe** | **49** | **12** | **16** | von 77 |

Die Durchgänge sind nach der **Herkunftsfarbe** gezählt, nicht nach dem
Zieltoken. Vier Deklarationen aus der Rot-Familie sind bewusst anderswo
gelandet: die drei von `.pages-edit-iconbtn--cancel` auf `--warning` und die
eine des Bildmarkers auf `--marker`.

**Die Migration ist abgeschlossen.** Jeder der vier Grundtöne steht danach
genau einmal im Projekt, nämlich als Token. Die zwölf Hell-Overrides bei Gelb
und Rot sind entfallen: Das Hell-Theme hängt jetzt allein an der zweiten
Themespalte der Tokens.

**Werte, die die Leiter nicht kennt, werden der nächstgelegenen Stufe
zugeordnet — die Leiter wird nicht erweitert.** Der Bestand benutzte
Deckkräfte zwischen 8 % und 50 %, oft für dieselbe Rolle. Die Angleichung
macht Rahmen sichtbar schwächer, wo sie vorher über 28 % lagen; das ist der
Preis dafür, dass vier Stufen genügen. Bisher zugeordnet: 35 %, 40 %, 45 %
und 50 % auf `-border-strong` (28 %), 20 % als Fläche auf `-surface-strong`
(14 %). Vollständig aufgetreten sind 10 %, 12 %, 14 %, 20 %, 35 %, 40 %,
45 %, 50 % und 55 %.

Drei Konstruktionen sind dabei ganz entfallen, weil die zweite Themespalte
sie überflüssig macht:

- Der Weißanteil, mit dem die Statusschalter ihren Ton aufhellten
  (`color-mix(… 78%, #ffffff 22%)` bei Grün, `… 70%, #ffffff 30%` bei Gelb).
- Die deckenden blassen Flächen des Katalogstatus (`#dcfce7` und `#fef3c7`),
  die im Dunkel-Theme hell leuchtende Inseln waren.
- Die aufgehellten Schriftfarben der getönten Buttons (`#ffd691` bei
  `.btn--warn`, `#ffaaaa` bei `.btn--danger`). Sie waren das handgemachte
  Gegenstück zum `-text`-Token der Verwaltung. Mit einem Grundton je Theme
  braucht es sie nicht — genau die Begründung aus dem Abschnitt oben.

Wo die Angleichung zwei Elemente ununterscheidbar gemacht hätte, ist **die
Semantik geschärft worden statt die Leiter erweitert**:
`.pages-edit-iconbtn--cancel` (vormals 35 % Rahmen) und `--delete` (45 %)
lägen beide auf `-border-strong`. Statt einer fünften Stufe trägt Abbrechen
jetzt `--warning` und Löschen `--danger` — sie sind über den Farbton
unterschieden, und der Farbton sagt zusätzlich etwas Wahres über den
Schweregrad aus (siehe die Abgrenzung oben).

**TODO: ungeklärt** — fünf Fundstellen bleiben bewusst außerhalb dieser
Migration und sind je eine eigene Entscheidung: `--accent` / `#c41e3a`
(siehe 2.4), das Violett `#a78bfa` als unbenannte fünfte Semantik
(„Footer"), die Graublautöne `#475569` und `#64748b` (gehören vermutlich zu
`--muted`), die vier `--flash-*`-Tokens (hängen an 2.7 zusammen mit dem fest
verdrahteten `color: #fff`) und die `.hc-badge`-Farben `#10b981` / `#ef4444`
in der System-Health-Datei (siehe Abschnitt 10).

### 2.7 Meldungsflächen

Vier Tokens, in beiden Themes definiert, für Flash-Meldungen:
`--flash-ok-bg`, `--flash-ok-border`, `--flash-err-bg`, `--flash-err-border`.

Entschieden **[CMS]**: `.flash` setzt `border: 1px solid transparent` als
Grundform, damit `border-color` überhaupt wirkt, und `.flash--error` schaltet
auf die Fehler-Tokens um. Beides fehlte; Fehlermeldungen erschienen in Grün.
`flash_render()` in `app/includes/components.php` erzeugt genau die zwei
Typen `ok` und `error`.

**TODO: ungeklärt** — `.flash` setzt `color: #fff` fest verdrahtet. Im
Hell-Theme steht damit weiße Schrift auf blassrotem oder blassgrünem Grund.

### 2.8 `--outline` ist kein Fokus-Token

`--outline` (`rgba(255,255,255,.15)` / `#bbbbbb`) wird an 14 Stellen als
**Hover**-Rahmenfarbe benutzt. Es ist ausdrücklich **nicht** der Fokusring —
diese Doppelnutzung war der Grund, warum Fokus und Hover im CMS
ununterscheidbar waren.

**Verbindlich:** `--outline` gehört dem Hover. Fokus benutzt `--focus`.

### 2.9 Farben außerhalb der Tokens

Im Code, aber nicht tokenisiert — beim nächsten Anfassen aufnehmen oder
ersetzen: die gesamte Zustandsfamilie aus 2.6, dazu `#1d9f6f` (Schalter),
`#b4232e` (Icon-Buttons), `#c41e3a` (`--accent`-Fallback), `#0f131a`
(Editor-Flächen), `#e6e6e6` (`--primary`, siehe 6.1).

---

## 3. Typografie

### Entschieden

**Kein Webfont, System-Stack** **[V]** — nichts nachzuladen heißt kein
Layoutsprung und keine Abhängigkeit vom Netz.

**Mono** gilt für `code`, `pre`, Tokens, Pfade, IDs **[V]**:
```
ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas,
"Liberation Mono", "Courier New", monospace
```

**Versalien nur für Kleinlabels** **[V]** — im CMS an drei Stellen, alle bei
10–11px. Nie für Fließtext.

### Offen

**TODO: ungeklärt — der Fließtext-Stack weicht ab.**

```
Verwaltung  system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif
CMS         system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif
```

`BlinkMacSystemFont` fehlt, `Roboto` und `Arial` kommen dazu. Gültiges CSS,
aber ein anderer Stack. Welcher gilt?

**TODO: ungeklärt — Größenskala.** Im Bestand: 30, 28, 26, 25, 24, 22, 20,
18, 16, 15, 14, 13, 12.5, 12, 11, 10 px sowie 11 rem-Werte und zwei
`clamp()`. Die Verwaltung führt 11–30 px in acht Stufen. Welche Skala gilt
für neuen Code — und was ist `12.5px` (6 Vorkommen)?

**TODO: ungeklärt — Gewichte.** Verwaltung 500–800. CMS benutzt 600, 650,
700, 750, **760**, 800, **850**, **900**. Drei Stufen mehr, `760` genau
einmal.

**TODO: ungeklärt — `letter-spacing`.** Die Verwaltung nennt `.04em`–`.08em`
für Kleinlabels. Das CMS benutzt an zwei Stellen `.12em`, beide in
Hero-Vorschauen. Frontend-Typografie (siehe 6.6) oder Ausreißer?

**TODO: ungeklärt — Zeilenhöhe.** `body` hat keine `line-height`; der
Fließtext läuft mit dem Browserwert. Dieselbe Lücke wie in der Verwaltung.

**TODO: ungeklärt — Zeilenlänge.** Keine Begrenzung in `ch`. `--max: 1200px`
ist definiert, wird aber **nirgends benutzt**; `.panel` setzt stattdessen
`max-width: clamp(980px, 92vw, 1560px)`. Soll `--max` weg oder auf den
echten Wert gesetzt werden?

---

## 4. Abstände

**TODO: ungeklärt.**

Die Verwaltung hat eine Zielskala für neuen Code (4 · 8 · 12 · 16 · 24 · 32)
und lässt den Bestand stehen. Ob dieselbe Skala hier gilt, ist nicht
entschieden.

Der Bestand, nach Häufigkeit: 10 (95×), 12 (83×), 8 (54×), 6 (39×), 14
(38×), 16 (23×), 4 (16×), 2 (12×), 18 (11×), dazu 7, 22, 20, 9, 5, 3, 36,
15, 11 px und rund 20 rem-Werte zwischen `.15rem` und `1.25rem`.

Auffällig gegenüber der Verwaltung: `10px` ist hier der häufigste Wert und
in der Zielskala der Verwaltung nicht enthalten. Eine Übernahme der Skala
würde also nicht nur „für neuen Code" gelten, sondern den verbreitetsten
Abstand des Projekts zum Sonderfall erklären. Das ist zu entscheiden, nicht
zu übernehmen.

---

## 5. Radien, Rahmen, Schatten

### Radien **[V]** — vollständig tokenisiert

Vier Tokens auf `:root` in
[admin-layout.css](public/assets/css/admin-layout.css#L18). Radien sind
themeunabhängig und stehen deshalb **nicht** im Hell-Block.

| Token | Wert | Wofür |
|---|---|---|
| `--radius-lg` | `18px` | Panel, Modal-Dialoge |
| `--radius-md` | `14px` | Karten, Container, Kacheln, Meldungen, Medienflächen |
| `--radius-sm` | `10px` | Buttons, Eingaben, Nav-Einträge, kleine Bedienelemente, Thumbnails |
| `--radius-pill` | `999px` | Plaketten, Pillen, Schalter, Tab-Leisten |

**18/14/10 ist die Skala der Verwaltung.** Das ist der Grund für die Wahl,
nicht ein optischer: Das CMS zieht auf die Stufung, die später das gemeinsame
Designsystem trägt. Wäre hier eine eigene Skala entstanden, hätte die
Zusammenführung sie doppelt kosten müssen.

`--radius-pill` steht bewusst **neben** der Skala, nicht darin. `999px` ist
keine Größenstufe, sondern eine Form — der Wert ist absichtlich größer als
jede erreichbare Elementhöhe. In die Skala gepresst würde er bei jeder
Änderung von 18/14/10 mitwandern, obwohl er davon unabhängig ist.

**Die 32 Stellen mit vormals `12px` wurden gespalten**, weil `12px` im CMS
zwei Aufgaben erfüllte:

- **6 Eingabefelder** → `--radius-sm`. Das ist eine Konsistenz-Entscheidung,
  keine Größenfrage: `.btn`, `.nav__item` und `.pages-edit-iconbtn` lagen
  bereits auf 10px. In jedem Formular steht ein Feld neben seinem Button —
  die 2px am Feld sieht niemand, den Formunterschied zum Nachbarn schon.
- **24 Container, Karten, Meldungen und Medienflächen** → `--radius-md`.
  Karten lagen vorher auf 12, 14 **und** 16px; auf 14 zusammengezogen tragen
  sie ein Token statt drei Werte.

Damit benutzen **106 von 118** Deklarationen ein Token. Vorher waren es zwei.

#### Dokumentierte Ausnahmen

Zwölf Deklarationen stehen nicht auf der Skala. Jede aus einem Grund, keine
aus Nachlässigkeit:

| Was | Anzahl | Warum |
|---|---|---|
| `50%` | 4 | Echte Kreise (`.add-folder-btn`, Fokuspunkt, Schalterknopf, Modal-Schließer) — eine Form, keine Stufe |
| `0` | 1 | `.pages-edit-richtext__html`: bündige Textarea im Editorrahmen, Absicht |
| Frontend-Vorschau | 4 | `.pages-edit-hero-preview__*` und `.pages-edit-dual-hero-preview__*` bilden die Website ab, nicht den Admin — siehe 6.6 |
| `var(--hc-*)` | 3 | Eigene Token-Ebene der System-Health-Seite, siehe Abschnitt 10. Betrifft nur Flächen — die Plaketten derselben Datei liegen auf `--radius-pill`, weil ein Radius keine Farbfrage ist |

#### Die Panel-Leiter hat zwei Stufen

`.panel` stand auf 18px → 14px (≤820px) → 12px (≤640px). Auf der Skala wären
die letzten beiden Stufen zusammengefallen. **Entschieden:** die dritte Stufe
entfällt, das Panel läuft 18 → 14. Ein Schritt leistet, wozu die Leiter da
ist — das Panel soll auf kleinen Schirmen nicht zu weich wirken.

### Rahmen

Durchgehend `1px solid` **[V]**. Rahmenfarbe folgt der Fläche
(`--panel-border`, `--card-border`, `--btn-border`).

**TODO: ungeklärt** — das CMS hat kein `--input-border`; die sechs
Eingabeformen benutzen drei verschiedene Rahmenfarben (Abschnitt 6.3).

### Schatten

**TODO: ungeklärt.**

Entschieden ist nur das Token: `--shadow: 0 10px 30px rgba(0,0,0,.35)`
(hell: `rgba(16,24,40,.10)`) **[V]**, benutzt auf `.panel`. Die Auth-Karte
setzt bewusst `box-shadow: none` **[V]**.

Daneben stehen **12 weitere, eindeutige `box-shadow`-Werte**, darunter drei
verschiedene Modal-Schatten (`0 10px 26px`, `0 22px 52px`, `0 24px 80px`).
Die Verwaltung erlaubt außer `--shadow` nur einen `inset`-Statusring. Ob
diese Regel hier gilt, ist nicht entschieden — sie würde 12 Stellen
betreffen.

---

## 6. Komponenten

### 6.1 Buttons

**TODO: ungeklärt — die Grundform weicht ab.**

| | Verwaltung | CMS |
|---|---|---|
| Polsterung | `8px 14px` | `7px 12px` |
| Radius | `10px` (`--radius-sm`) | `10px` roh |
| Mindesthöhe | `38px` | keine (nur unter 820px: 40px) |
| Schriftgewicht | `700` | nicht gesetzt |
| Primärfläche | `#f2f2f3` / Text `#101114` | `var(--primary)` = `#e6e6e6` / Text `var(--bg)` = `#0f1012` |

`#e6e6e6` gegen `#f2f2f3` und `#0f1012` gegen `#101114` sind zwei Töne für
dieselbe Rolle, die sich nicht unterscheiden lassen. Welcher gilt?

**Entschieden [CMS]:** Die Varianten sind `--ghost` (Grundform für alles
Nebensächliche), `--warn`, `--danger`, dazu die Größen `--sm`, `--xs`,
`--badge`. Eine `--secondary`/`--info`/`--success`-Reihe wie in der
Verwaltung gibt es hier nicht.

**Beobachtung, keine Regel:** Die Verwaltungsregel „primäre Aktion höchstens
einmal pro Screen" wird hier faktisch eingehalten — 20 von 22 Ansichten
haben genau einen Button ohne `--ghost`. Ausnahmen: `media_list.php` (6),
`pages_list.php` (2). Ob die Regel gelten *soll*, ist nicht entschieden.

### 6.2 Karten

`.stat`, `.pages-edit-card` und die Medienkarten folgen dem Muster
`--card` auf `--card-border`.

**TODO: ungeklärt** — es gibt keine gemeinsame `.surface`-Klasse wie in der
Verwaltung, und keine Regel zur Schachtelungstiefe.

### 6.3 Eingaben **[V]** — eine Form

Alle Eingaben teilen sich einen Regelblock in
[admin-components.css](public/assets/css/admin-components.css#L330):

```css
width: 100%;
min-height: 38px;
padding: 10px 12px;
border: 1px solid var(--input-border);
border-radius: var(--radius-sm);
background: var(--input-bg);
color: var(--text);
font: inherit;
font-size: 14px;
```

Vorher standen dort sechs Klassen mit **vier Flächen, drei Rahmenfarben und
drei Polsterungen** für dieselbe Sache.

#### Die zwei Tokens

| Token | Dunkel | Hell |
|---|---|---|
| `--input-bg` | `rgba(0,0,0,0.20)` | `rgba(17,17,20,0.03)` |
| `--input-border` | `rgba(255,255,255,0.12)` | `var(--card-border)` |

Beide stammen aus dem Bestand, keiner ist erfunden. `rgba(0,0,0,0.20)` war
die Fläche der Medien-Felder, `rgba(255,255,255,0.12)` deren Rahmen — und
letzterer ist zugleich wertgleich mit `--input-border` der Verwaltung.
`rgba(17,17,20,0.03)` war die Hell-Fläche des Anmeldefelds.

**Felder sind im CMS dunkler als ihr Grund, nicht heller.** Das ist der eine
Punkt, an dem die Verwaltung nicht als Vorlage taugt: Sie hellt mit
`rgba(255,255,255,0.02)` auf. Fünf der sechs CMS-Klassen setzten das Feld ab,
indem sie es vertieften. Die Verwaltungswerte zu übernehmen hätte jedes Feld
der Anwendung umgedreht.

**`--input-bg` ist bewusst ein Alpha-Wert, kein deckender.** Felder liegen im
CMS in Modals, Hero-Tabflächen und Karten mit jeweils anderem Grund. Ein
Alpha-Wert bleibt dort überall gleich stark abgesetzt, ein deckender nicht —
dieselbe Begründung wie bei den Zustandstönungen in 2.6. Er reproduziert die
beiden früher handverlesenen Sonderfälle bis auf ein bis zwei Stufen
(`#121317` gegen `#101216` im Modal, `#0c0f15` gegen `#0b0f15` in der
Hero-Fläche); die beiden Dunkel-Overrides sind dadurch entfallen.

Kontrast der Eingabeschrift gegen `--input-bg`: **17,02:1** dunkel,
**17,83:1** hell.

Im Hell-Theme teilt sich der Rahmen den einen vorhandenen Rahmenton
(`#e7ebf1`, zugleich `--card-border`, `--btn-border` und `--panel-border`) —
als Alias geschrieben, damit der Wert nicht ein viertes Mal dasteht. Im
Dunkeln steht er eigenständig, weil das Feld dort einen kräftigeren Rahmen
braucht als ein Button: seine Fläche allein trägt die Form nicht.

**`font: inherit` ist Teil der Form, keine Zutat.** Formularelemente erben
die Schrift des Dokuments nicht. Fünf der sechs Klassen setzten sie nicht und
rendeten deshalb in der Browser-Standardschrift statt im System-Stack.

**Der Fokus kommt aus der zentralen Regel** (6.4), nicht aus diesem Block. Er
setzt darum bewusst weder `:focus` noch `outline`.

#### Zweckgebundene Abweichungen

Sechs Regeln bleiben, weil sie eine Aufgabe erfüllen statt gewachsen zu sein:

| Regel | Was | Warum |
|---|---|---|
| `.login-input` | `font-size: 15px`, `line-height: 1.25` | Die Auth-Karte führt eine eigene, größere Textstufe (28px Titel) |
| `.textarea` | `min-height: 110px`, `resize: vertical` | Mehrzeilig |
| `.pages-edit-textarea` | Mono, 13px, `min-height: 260px` | Feld für HTML-Quelltext |
| `.events-edit-form .pages-edit-textarea` | `min-height: 190px` | Kürzeres Feld im Veranstaltungsformular |
| `.pages-edit-card--compact .pages-edit-input` | `padding: 8px 10px` | Kompakte Kartenvariante |
| `.migrate-input` | `min-width: 240px` | Steht inline in einer Aktionszeile statt im Formularraster |

**TODO: ungeklärt** — `.migrate-input` wird von keinem Markup benutzt. Die
Klasse ist tot und steht nur noch in der Form. Löschen oder Verwendung
nachtragen.

### 6.4 Zustände — Fokus und Deaktivierung **[V]**

Das ist der am gründlichsten entschiedene Teil dieser Datei.

**Grundsatz:** *Fokus ist eine Kontur, Hover eine Fläche. Die beiden dürfen
nie gleich aussehen.* Vorher wechselten im CMS beide nur die Rahmenfarbe auf
`--outline` — mit der Tastatur war nicht erkennbar, wo man stand.

**Eine zentrale Fokusregel**, in `admin-components.css`, nicht in einer
Seitendatei:

```css
.btn:focus-visible,
a:focus-visible,
button:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible,
summary:focus-visible,
.nav__item:focus-visible,
[contenteditable="true"]:focus-visible,
[role="button"]:focus-visible{
  outline: 2px solid var(--focus);
  outline-offset: 2px;
}
```

- **`:focus-visible`, nie `:focus`** — der Ring gehört der Tastatur und darf
  beim Mausklick nicht erscheinen.
- **`outline`, nicht `box-shadow` oder `border-color`** — die Kontur liegt
  außerhalb des Elements und ist auch dort sichtbar, wo die Fläche selbst
  hell ist.
- Die Selektorliste geht über die der Verwaltung hinaus: `button` (deckt
  Tab-Buttons, Icon-Buttons, Rich-Text-Leiste ab — echte `<button>` ohne
  `.btn`), `a` (jeder Link, nicht nur `a.btn`), `summary`,
  `[contenteditable="true"]` (Rich-Text-Fläche) und `.nav__item` (die
  Verwaltung führt ihre Seitenleiste als offenes TODO).
- **`outline: none` ist nur zulässig, wo eine gleichwertige Kontur an seine
  Stelle tritt** — nicht, um den Ring loszuwerden. Im ganzen Admin-CSS gibt
  es genau eine solche Stelle (6.5).

**Eine zentrale Disabled-Regel:**

```css
:disabled,
[aria-disabled="true"],
.is-disabled{
  opacity: .5;
  cursor: not-allowed;
}
```

`[aria-disabled="true"]` und `.is-disabled` sind nicht dekorativ: gesperrte
Links (`<a>`) nehmen kein `:disabled` an. Die Paginierung in
`media_list.php` benutzt genau diesen Fall.

**Hover-Reaktionen sind im Disabled-Zustand aus** — Fläche, Farbe, Transform
und Cursor. Ein deaktivierter Button darf sich unter dem Zeiger nicht
lebendig anfühlen.

**Wie**, verbindlich für neuen Code: über einen `:not()`-Wächter an der
Hover-Regel selbst, nicht über eine zweite Regel mit höherer Spezifität und
**nie** über `!important`:

```css
.btn:not(:disabled,[aria-disabled="true"],.is-disabled):hover{ … }
```

Das ist das Idiom, das `admin-pages-list.css` schon benutzte. Es vermeidet
einen Spezifitätswettlauf — die stärkste konkurrierende Hover-Regel ist
`html[data-theme="light"] .btn.btn--ghost.btn--warn:hover` mit (0,5,1) — und
der gleichmäßige Zuwachs von (0,1,0) erhält die bestehende Kaskadenordnung.

**Wenn eine Seitendatei die zentrale Regel schlägt, wird die Deklaration
herausgezogen, nicht überschrieben.** Zwei Stellen setzten `cursor: pointer`
in später geladenen Dateien und gewannen damit gegen `:disabled` —
`.pages-order-controls button` (0,1,1) in
[admin-pages-list.css](public/assets/css/admin-pages-list.css#L186) und
`.pages-edit-checkbox` (0,1,0) in
[admin-pages-edit.css](public/assets/css/admin-pages-edit.css#L1546).

Beide Regeln setzen mehr als den Cursor (Kastenmaße, Rahmen,
`accent-color`); ein `:not()` am ganzen Selektor hätte deaktivierten
Elementen auch das genommen. Gelöst ist es deshalb, indem allein die
`cursor`-Zeile in eine eigene, gewächterte Regel wandert:

```css
.pages-edit-checkbox:not(:disabled){ cursor: pointer; }
```

Damit entfällt der Konflikt ganz, statt ihn zu gewinnen: das deaktivierte
Element bekommt nie `pointer`, und die zentrale Regel greift unbehelligt.

#### Offen: zwei gelbe Buttons nebeneinander

Die Aktionsleiste des Seiteneditors zeigt `--delete` nur bei nicht
gelöschten und `--restore` nur bei gelöschten Seiten — die beiden schließen
sich aus. Abbrechen steht immer da.

| Ansicht | Buttons | Unterscheidung |
|---|---|---|
| Normale Seite | Speichern (grün), Abbrechen (gelb), Löschen (rot) | drei Töne, eindeutig |
| Gelöschte Seite | Speichern (grün), **Abbrechen (gelb)**, **Wiederherstellen (gelb)** | beide gelb, nur über die Fläche (8 % gegen 14 %) |

**TODO: ungeklärt.** Bewusst so belassen, nicht vergessen. Der Fall ist
selten, und die Symbole unterscheiden sich (✕ gegen ↺). Naheliegende Lösung
wäre `--success` für Wiederherstellen — eine Wiederherstellung ist inhaltlich
eher eine Erholung als eine Warnung —, aber das ist eine Bedeutungsfrage und
keine Farbfrage, also nicht nebenbei zu entscheiden.

### 6.5 Die eine zulässige Ausnahme **[CMS]**

`.pages-edit-switch` — der Ein/Aus-Schalter im Seiteneditor. Sein
`<input type="checkbox">` ist visuell verborgen
(`position:absolute; opacity:0; width:0; height:0`), der sichtbare Teil ist
ein `<span>` daneben.

Die zentrale Regel kann ihn nicht bedienen: ein Ring auf einem 0×0-Element
wäre ein Punkt im Leeren, und `opacity: .5` auf einem unsichtbaren Input
bewirkt nichts — ein deaktivierter Schalter sah voll bedienbar aus.

Deshalb trägt der Slider Fokus und Zustand **stellvertretend**:

```css
.pages-edit-switch input:focus-visible{ outline: none; }

.pages-edit-switch input:focus-visible + .pages-edit-switch__slider{
  outline: 2px solid var(--focus);
  outline-offset: 2px;
}

.pages-edit-switch:has(input:disabled){
  opacity: .5;
  cursor: not-allowed;
}
```

**Warum das zulässig ist:** Das `outline: none` steht nicht allein — die
Zeile darunter setzt exakt dieselbe Kontur auf das Element, das der Benutzer
tatsächlich sieht. Genau diesen Fall meint die Regel in 6.4 mit
„gleichwertige Kontur an seiner Stelle". Der Ring benutzt `--focus`, nicht
die grüne Zustandsfarbe, die vorher dort stand: Fokus ist kein Zustand.

**Der Bauplan für künftige Ausnahmen:** Wenn ein Bedienelement visuell
verborgen ist, trägt sein sichtbarer Stellvertreter Fokus und
Disabled-Zustand — mit derselben Kontur und derselben Deckkraft wie die
zentralen Regeln. Eine Ausnahme ohne sichtbaren Ersatz ist keine Ausnahme,
sondern ein Fehler.

### 6.6 Vorschauflächen bilden die Website ab, nicht den Admin **[CMS]**

`.pages-edit-hero-preview__*` und `.pages-edit-dual-hero-preview__*` zeigen
Inhalt der ausgelieferten Website. Ihre Typografie und ihre Flächen folgen
der Frontend-Gestaltung, nicht dieser Datei — dieselbe Logik, nach der
`.code-block` in der Verwaltung eigene Farben haben darf.

**TODO: ungeklärt** — wie weit diese Ausnahme reicht. Die vier
Farbverläufe in diesen Blöcken sind Leerflächen einer Bildvorschau; ob dafür
ein Verlauf nötig ist statt einer Volltonfläche, ist nicht entschieden.

### 6.7 Icons **[CMS]** — hier ist das CMS weiter

Inline-SVG, `currentColor`, 18×18, in `.nav__icon`. 36 Icons in der
Seitenleiste.

Die Verwaltung benutzt heute noch geometrische Unicode-Zeichen und nennt
Inline-SVG als *Zielzustand*. **Diese Regel geht in die andere Richtung: der
CMS-Iconsatz ist der Kandidat für das gemeinsame System.**

**Verbindlich für neuen Code:** Icons nur als Inline-SVG mit `currentColor`.

**TODO: ungeklärt — Emoji.** Die Verwaltung verbietet Emoji als Icons
ausnahmslos. Im CMS stehen sechs: 🌙/☀️ (Theme-Umschalter, `sidebar.php`),
💾 und 🗑 (`pages_edit.php`), 🔗/🚫🔗 (Editor-Werkzeugleiste), 📁
(`media_list.php`), ⚠️ (Meldungstext in `setup_step2.php`). Ob die Regel hier
gilt, ist zu entscheiden — sie ist mit dem vorhandenen SVG-Satz billig
umzusetzen.

Geometrische Zeichen und Pfeile (`☰ ✓ ↑ ↓ ← → ↺ ⎋`, rund 20 Stellen) sind
derselbe Fall wie die Glyphen der Verwaltung: abzulösender Bestand, kein
Verstoß.

---

## 7. Bewegung

**TODO: ungeklärt.**

Die Verwaltung erlaubt genau eine Formel (`120ms ease` auf `opacity`,
`background`, `border-color`), auf genau zwei Elementen, und verbietet
Transforms. Das CMS hat:

- **10 verschiedene Transition-Deklarationen** mit sechs Dauern: `.08s`,
  `.12s`, `.15s`, `.2s`, `160ms`, `0.5s`
- **`transform` wird animiert**, unter anderem hebt
  `.pages-edit-iconbtn:hover` um `translateY(-1px)` an
- umgekehrt hat `.nav__item` **keine** Transition, obwohl es eines der zwei
  Elemente ist, die die Verwaltung animiert

Zu entscheiden: eine Formel oder mehrere, und ob Transforms zulässig sind.
Die Transform-Frage ist nicht rein ästhetisch — der Editor arbeitet mit
Drag-and-Drop, wo Bewegung Funktion trägt.

**TODO: ungeklärt** — `prefers-reduced-motion` wird nirgends abgefragt.
Dieselbe Lücke wie in der Verwaltung, hier aber mit mehr Bewegung.

---

## 8. Breakpoints

**TODO: ungeklärt.**

| | Verwaltung | CMS |
|---|---|---|
| Anzahl | 3 (980, 860, 640) | **13 Deklarationen**, 10 verschiedene Grenzen |
| Grenzen | — | 640, 680, 720, 820, 859, 900, 980, 1100, 1180, 1400 |
| Richtung | nur `max-width` | 10 × `max-width`, **3 × `min-width`** |
| Shell-Umbruch | 980px | **820px** |

**Entschieden [CMS]:** Der Shell-Umbruch liegt bei 820px, weil dort die
mobile Navigation als Overlay mit Backdrop einsetzt — ein Bauteil, das die
Verwaltung nicht hat. Die Seitenleiste ist außerdem einklappbar (250px → 64px),
persistiert als `ui.sidebar_collapsed`. Beides folgt daraus, dass der
Seiteneditor die Breite braucht.

Offen ist alles andere: ob die übrigen neun Grenzen zusammengefasst werden
und ob `min-width` zulässig bleibt.

**TODO: ungeklärt** — `admin-system-health.css` enthält eine
`@media (prefers-color-scheme: dark)`-Abfrage. Sie ist heute wirkungslos
(der Selektor greift nur ohne `data-theme`, und `layout.php` setzt es
immer), aber sie steht im Widerspruch zur Theme-Wahl aus 2.1.

---

## 9. Guardrails

Die Verwaltung führt zehn ausnahmslose Regeln. **Sie gelten hier nicht
automatisch.** Stand:

| # | Regel der Verwaltung | Status im CMS |
|---|---|---|
| 1 | Keine Emoji als Icons | **TODO** — 6 Verstöße, siehe 6.7 |
| 2 | Keine Farbverläufe als Flächen | **TODO** — 5 Verläufe; 4 in Vorschauflächen (6.6), 1 in einer Kartenüberschrift der System-Health-Seite |
| 3 | Keine zentrierten Textblöcke über zwei Zeilen | **Gilt [V]** — geprüft: 15 × `text-align:center`, alle auf Tabellenspalten, Plaketten und der Auth-Karte |
| 4 | Keine Schatten außerhalb Abschnitt 5 | **TODO** — 12 weitere Werte, siehe 5 |
| 5 | Primäre Aktion höchstens einmal pro Screen | **TODO** — faktisch eingehalten, aber nicht beschlossen, siehe 6.1 |
| 6 | Keine neuen Farben ohne Token | **Gilt [V]** ab jetzt, siehe 2.5 |
| 7 | Keine Abstände außerhalb der Skala | **TODO** — keine Skala entschieden, siehe 4 |
| 8 | Keine zweite Schriftfamilie | **Gilt [V]** — System-Stack und Mono, sonst nichts |
| 9 | Kein helles Theme, keine Theme-Umschaltung | **Gilt hier ausdrücklich NICHT.** Siehe 2.1 |
| 10 | Versalien nur für Kleinlabels | **Gilt [V]** — geprüft |

### Was hier ausnahmslos gilt **[CMS]**

Diese drei sind in dieser Session entschieden und nicht verhandelbar:

1. **Fokus ist eine Kontur, Hover eine Fläche.** Kein Bedienelement bekommt
   einen Fokusstil, der mit seinem Hover identisch ist. `outline: none` nur
   mit sichtbarem Ersatz (6.4, 6.5).
2. **Deaktivierte Bedienelemente reagieren auf nichts.** Keine
   Hover-Fläche, kein Farbwechsel, kein Transform, kein Zeigefinger (6.4).
3. **Kein `!important`, um Spezifität zu erschlagen.** Wer auf eine
   Kollision stößt, meldet die Stelle oder löst sie mit `:not()` an der
   konkurrierenden Regel (6.4).

### Theme-Regel **[CMS]**

4. **Theme-Overrides stehen auf `html[data-theme="…"]`, nie auf `body`.**
   Jede neue Farbregel wird in beiden Themes geprüft (2.1).

---

## 10. Bekannte Altlasten außerhalb dieser Regeln

Kein eigener Abschnitt der Verwaltungs-DESIGN.md, hier aber nötig, weil es
Fundstellen gibt, die weder Regel noch TODO sind, sondern schlicht offen:

- **`theme.css`** (572 Zeilen) wird von nichts geladen — weder in
  `app/Views`, `app/includes`, `app/Frontend`, `app/PageBuilder` noch in
  `plugins/`. Sie enthält ein **drittes** Farbsystem (`--color-primary:
  #2563eb`, `--color-accent: #f59e0b`, rem-Radien, `transform: scale(1.03)`).
  **TODO: ungeklärt** — Frontend-Theme der ausgelieferten Website (dann
  gehört es nicht in diesen Ordner) oder toter Code?
- **`admin-system-health.css`** führt eine eigene, vollständige Token-Ebene
  (`--hc-*`) ohne Bezug zur Basis. **TODO: ungeklärt** — auflösen oder als
  bewusster Sonderfall dokumentieren?
- **Die neueren Teile von `admin-pages-edit.css`** (ab etwa Zeile 970) sind
  hell-zuerst geschrieben, mit 14 `[data-theme="dark"]`-Blöcken als
  Korrektur — die Umkehrung der Basisebene. **TODO: ungeklärt** — Absicht
  oder Nebeneffekt der Entwicklungsumgebung?
- **`.nav__item`** dämpft inaktive Einträge über `opacity: 0.5` statt über
  `--muted`, was das SVG-Icon mitdämpft. **TODO: ungeklärt.**
- **Kein Stil für `:invalid`** und keiner für Ladezustände. Dieselben zwei
  Lücken wie in der Verwaltung.

---

## Diese Datei gehört nicht ins Deployment

`DESIGN.md`, `CLAUDE.md` und `AGENTS.md` sind aus der rsync-Ausschlussliste
in [.github/workflows/deploy.yml](.github/workflows/deploy.yml#L179)
ausgenommen und erreichen keinen Kunden-Webspace.
