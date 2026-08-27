# Verbindlicher Aenderungs- und Release-Prozess

## Abschluss jeder Aenderung

- Nach jeder abgeschlossenen Aenderung muss der Benutzer ausdruecklich gefragt
  werden, ob sie nach GitHub uebernommen und ausgerollt werden soll.
- Ohne Zustimmung nicht committen, pushen, mergen oder deployen.
- Nach Zustimmung in `main` uebernehmen und die GitHub Action `Deploy to IONOS`
  erfolgreich abschliessen und pruefen.

## CMS-Versionierung

- Die Version steht in `config/version.php`.
- Funktionale CMS-Aenderungen werden gemaess Semantic Versioning erhoeht.
- Jeder Versionssprung erfordert eine fortlaufend nummerierte SQL-Migration in
  `migrations/`, die dieselbe Version in `changelogs` eintraegt.
- Versionsdatei, Migration und Funktionsaenderung gehoeren zusammen.
- Reine Dokumentations- und Repository-Aenderungen brauchen keinen Sprung.
