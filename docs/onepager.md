# Kundenspezifischer Onepager

WEBSITE_MODE wird von der Verwaltung pro Installation gesetzt.
Nur der Wert onepager aktiviert Seiten > Onepager und die zugehoerigen
GET-/POST-Routen. Ohne Freigabe bleiben diese ausgeblendet und antworten
mit 404. Lesen verwendet pages.view, Speichern pages.edit und CSRF-Schutz.

Die Auswahl wird unter onepager_pages in site_settings gespeichert,
getrennt von nav_visible und nav_order. Die Startseite bleibt fest zuerst.
Initial werden veroeffentlichte Inhaltsseiten ausgewaehlt, Rechtsseiten
ausgeschlossen und Kontakt zuletzt eingeordnet. Nach dem ersten Speichern
gilt die gespeicherte Auswahl; neue Seiten bleiben zunaechst ausgeschlossen.
Entwuerfe, Weiterleitungen und geloeschte Seiten werden nie oeffentlich
ausgegeben. Die gespeicherte Konfiguration bleibt bei Moduswechseln erhalten.

settings/public liefert website_mode und onepager_page_ids. Diese IDs
enthalten ausschliesslich ausgewaehlte, aktuell veroeffentlichte Inhaltsseiten
in der eingestellten Reihenfolge. Das Frontend entscheidet anhand dieser
Liste ueber Zusammensetzung, Sprunglinks und separate Seiten.

Speichern prueft die vollstaendige Seitenliste und eine Konfigurationsrevision
innerhalb einer Transaktion. Veraltete oder manipulierte Anfragen werden
ohne Aenderung der bestehenden Auswahl abgewiesen.

Es ist keine neue CMS-Tabelle erforderlich. In der Verwaltung ist die Migration
031_customer_website_settings.sql noetig. Reihenfolge beim ersten Release:
Verwaltung samt Migration, Kunden-CMS samt synchronisierter .env, Frontend.

Test ohne produktive Datenbank: php scripts/check-onepager-settings.php
