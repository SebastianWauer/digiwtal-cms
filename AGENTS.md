# Verbindlicher Änderungs- und Release-Prozess

Diese Regeln gelten für alle Arbeiten in diesem Repository.

## Abschluss jeder Änderung

- Nach jeder abgeschlossenen Änderung muss der Benutzer ausdrücklich gefragt werden, ob die Änderung nach GitHub übernommen beziehungsweise gemergt werden soll.
- Ohne diese ausdrückliche Zustimmung dürfen Änderungen nicht eigenständig committed, gepusht oder gemergt werden und es darf kein Deployment ausgelöst werden.
- Wird die Übernahme bestätigt, müssen die freigegebenen Änderungen nach GitHub übernommen beziehungsweise in `main` gemergt werden.
- Anschließend muss die GitHub Action **Deploy to IONOS** (`.github/workflows/deploy.yml`) erfolgreich ausgeführt werden. Ein Push auf `main` löst sie automatisch aus; falls das nicht geschieht, ist sie manuell über `workflow_dispatch` zu starten. Der Abschluss ist erst nach Prüfung des Action-Ergebnisses zu melden.

## Versionierung und Changelog für CMS-Änderungen

- Ausgangsversion ist aktuell **2.2.0**. Maßgebliche Versionsdatei ist `CMS/config/version.php`.
- Jede Änderung an von Git verwalteten Dateien, die Verhalten, Oberfläche, API, Datenmodell, Auslieferung oder sonstige Funktionalität des CMS betrifft, muss vor der Übernahme nach GitHub versioniert werden.
- Die Version wird gemäß Semantic Versioning erhöht: Patch für Fehlerbehebungen und kleine Änderungen, Minor für neue abwärtskompatible Funktionen, Major für inkompatible Änderungen. Bei mehreren zusammengehörigen Dateien einer Änderung genügt ein gemeinsamer Versionssprung.
- Zu jedem Versionssprung muss eine neue, fortlaufend nummerierte SQL-Migration unter `CMS/migrations/` angelegt werden. Sie trägt einen verständlichen Eintrag mit derselben Version in die Tabelle `changelogs` ein, damit die Änderung in der Changelog-Ansicht des CMS erscheint.
- Versionsdatei und Changelog-Migration gehören zwingend zur selben Änderung beziehungsweise zum selben Commit wie die CMS-Änderung.
- Reine Repository-, Dokumentations- oder Arbeitsanweisungsänderungen ohne Auswirkung auf das CMS erfordern keinen Versionssprung und keinen CMS-Changelog-Eintrag.

## Freigabefrage

Am Ende jeder Änderung ist sinngemäß zu fragen:

> Sollen diese Änderungen nach GitHub übernommen beziehungsweise gemergt und anschließend über die Action „Deploy to IONOS“ ausgerollt werden?
