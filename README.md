# Nextcloud Shares Viewer

## Beschreibung

Das Nextcloud Folder Viewer Plugin ist eine WordPress-Erweiterung, 
die es ermöglicht, Nextcloud-Freigaben direkt in WordPress-Seiten oder -Beiträgen einzubetten. 
Es bietet eine nahtlose Integration von Nextcloud-Inhalten in Ihre WordPress-Website.

Hauptfunktionen:
- Anzeige von Nextcloud-Ordnerstrukturen
- Einbettung von Nextcloud-Dateien (Bilder, PDFs, Videos, Audio, Text)
- Sicherer Zugriff auf Nextcloud-Inhalte über Freigabe-Links
- Anpassbare Darstellung von Datei-Vorschauen

## Installation

1. Lade das Plugin-Verzeichnis `nextcloud-folder-viewer` in das `/wp-content/plugins/`-Verzeichnis Ihrer WordPress-Installation hoch.
2. Aktiviere das Plugin über das 'Plugins'-Menü in WordPress.

Falls du einen Meldung erhältst , dass nicht ale Komponenten installiert sind, dann bitte folgendes 
auf der Console im Ordner `wp-content/plugins/nextcloud-folder-viewer` ausführen: 'composer install'

## Nutzung

**Kopiere den Freigabe-Link einfach in einen Beitrag. Inhalt oder Ordnerstruktur werden wenn möglich automatisch dargestellt.**

### Shortcode

Verwende den folgenden Shortcode, um einen Nextcloud-Ordner oder eine Datei in Ihren Beiträgen oder Seiten einzubetten:
```
[nextcloud_folder url="https://ihre-nextcloud-instanz.de/s/IhrFreigabeLink"]
```

Parameter:
- `url` (erforderlich): Der vollständige Nextcloud-Freigabe-Link.
- `title` (optional): Ein benutzerdefinierter Titel für die Anzeige.
- `show` (optional): Setzt, ob der Inhalt direkt angezeigt (`true`) oder nur als Download-Link (`false`) dargestellt werden soll.

### Beispiele

Einbetten eines Ordners:
```
[nextcloud_folder url="https://nextcloud.beispiel.de/s/abcdefghijk"]
```

Einbetten einer Datei mit benutzerdefiniertem Titel:
```
[nextcloud_folder url="https://nextcloud.beispiel.de/s/datei123" title="Mein Dokument" show="true"]
```

## Hinweise

- Stelle sicher, dass die Nextcloud-Freigabe-Links öffentlich zugänglich sind.
- Die Darstellung von eingebetteten Dateien hängt vom Dateityp und den Browsereinstellungen der Benutzer ab.
- Für eine optimale Leistung wird empfohlen, die Anzahl der eingebetteten Dateien und Ordner pro Seite zu begrenzen.

## Support

Bei Fragen oder Problemen eröffne bitte ein Issue auf der GitHub-Projektseite öffenen.

## Lizenz

Dieses Plugin ist unter der GPL v2 oder später lizenziert.
