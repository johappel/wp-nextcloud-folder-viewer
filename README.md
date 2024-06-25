# Nextcloud Folder Viewer

## Beschreibung

Das Nextcloud Folder Viewer Plugin ist eine WordPress-Erweiterung, die es ermöglicht, Ordnerstrukturen und Dateien von Nextcloud-Freigaben direkt in WordPress-Seiten oder -Beiträgen anzuzeigen. Es bietet eine nahtlose Integration von Nextcloud-Inhalten in Ihre WordPress-Website.

Hauptfunktionen:
- Anzeige von Nextcloud-Ordnerstrukturen
- Einbettung von Nextcloud-Dateien (Bilder, PDFs, Videos, Audio, Text)
- Sicherer Zugriff auf Nextcloud-Inhalte über Freigabe-Links
- Anpassbare Darstellung von Datei-Vorschauen

## Installation

1. Laden Sie das Plugin-Verzeichnis `nextcloud-folder-viewer` in das `/wp-content/plugins/`-Verzeichnis Ihrer WordPress-Installation hoch.
2. Führen sie auf der Console im Ordner `wp-content/plugins/nextcloud-folder-viewer` folgenden Befehl aus: 'composer install'
3. Aktivieren Sie das Plugin über das 'Plugins'-Menü in WordPress.
4. Stellen Sie sicher, dass Sie über gültige Nextcloud-Freigabe-Links verfügen, die Sie einbetten möchten.

## Nutzung

### Shortcode

Verwenden Sie den folgenden Shortcode, um einen Nextcloud-Ordner oder eine Datei in Ihren Beiträgen oder Seiten einzubetten:

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

- Stellen Sie sicher, dass Ihre Nextcloud-Freigabe-Links öffentlich zugänglich sind.
- Die Darstellung von eingebetteten Dateien hängt vom Dateityp und den Browsereinstellungen der Benutzer ab.
- Für eine optimale Leistung wird empfohlen, die Anzahl der eingebetteten Dateien und Ordner pro Seite zu begrenzen.

## Support

Bei Fragen oder Problemen eröffnen Sie bitte ein Issue auf der GitHub-Projektseite oder kontaktieren Sie den Plugin-Autor.

## Lizenz

Dieses Plugin ist unter der GPL v2 oder später lizenziert.
