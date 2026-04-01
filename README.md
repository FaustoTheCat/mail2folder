# Mail2Folder – Nextcloud App

Empfängt E-Mails über IMAP und speichert Anhänge automatisch in Nextcloud-Benutzerordnern.
Die Zuordnung zum richtigen Benutzer erfolgt über den **Betreff** der E-Mail.

## Funktionsweise

1. Alle Benutzer senden E-Mails mit Anhängen an **eine zentrale E-Mail-Adresse** (z.B. ein 1&1/IONOS-Postfach)
2. Im Betreff wird der Nextcloud-Benutzername angegeben, z.B. `[anna] Rechnung Mai`
3. Ein Nextcloud-Hintergrund-Job pollt das IMAP-Postfach periodisch
4. Die App erkennt den Benutzernamen im Betreff und legt die Anhänge im Ordner des jeweiligen Benutzers ab
5. Verarbeitete E-Mails werden als gelesen markiert (optional gelöscht)

### Beispiel

| Betreff                          | Ergebnis                                      |
|----------------------------------|-----------------------------------------------|
| `[anna] Rechnung Mai`           | Anhänge → Benutzerin `anna`                   |
| `[max] Vertrag unterschrieben`  | Anhänge → Benutzer `max`                      |
| `Rechnung ohne Tag`             | → Fallback-Benutzer oder wird ignoriert       |

## Voraussetzungen

- Nextcloud 28–33
- PHP 8.1+
- PHP IMAP Extension
- Ein IMAP-fähiges E-Mail-Postfach (z.B. 1&1/IONOS, GMX, Gmail, eigener Mailserver)
- Nextcloud Cron (empfohlen: Systemcron, nicht AJAX)

## Installation

### 1. PHP IMAP Extension installieren

Das Nextcloud-Docker-Image (ab Debian Trixie) enthält kein `php-imap`-Paket.
Am einfachsten geht es mit dem Tool `install-php-extensions`:

```bash
docker exec -u root <container> bash -c "\
  curl -sSLf https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
    -o /usr/local/bin/install-php-extensions && \
  chmod +x /usr/local/bin/install-php-extensions && \
  install-php-extensions imap"

docker restart <container>
```

Prüfen ob die Extension aktiv ist:

```bash
docker exec <container> php -m | grep imap
# Erwartete Ausgabe: imap
```

**Wichtig:** Bei einem Container-Update (neues Nextcloud-Image) geht die Extension verloren.
Für eine dauerhafte Lösung ein eigenes Dockerfile verwenden:

```dockerfile
FROM nextcloud:33

RUN curl -sSLf https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions \
      -o /usr/local/bin/install-php-extensions && \
    chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions imap
```

### 2. App installieren

```bash
# App-Verzeichnis in den Nextcloud-Container kopieren:
docker cp mail2folder/ <container>:/var/www/html/custom_apps/mail2folder

# Berechtigungen setzen:
docker exec -u root <container> chown -R www-data:www-data /var/www/html/custom_apps/mail2folder

# App aktivieren (erstellt automatisch die Datenbank-Tabellen):
docker exec -u www-data <container> php occ app:enable mail2folder
```

### 3. App konfigurieren

#### Admin-Einstellungen

Navigiere zu **Verwaltungseinstellungen → Verwaltung → Zusätzliche Einstellungen → Mail2Folder**.

**IMAP-Server** (Beispiel für 1&1/IONOS):

| Einstellung         | Wert                          |
|---------------------|-------------------------------|
| IMAP-Host           | `imap.ionos.de`              |
| Port                | `993`                         |
| Verschlüsselung     | SSL/TLS                       |
| Benutzername        | `upload@meine-domain.de`     |
| Passwort            | Dein E-Mail-Passwort          |
| IMAP-Ordner         | `INBOX`                       |

**Benutzer-Zuordnung:**

| Einstellung         | Wert / Erklärung                                              |
|---------------------|---------------------------------------------------------------|
| Betreff-Muster      | `[benutzername]` (Standard) — erkennt `[anna]` im Betreff    |
| Fallback-Benutzer   | z.B. `admin` — fängt Mails ohne gültigen Benutzertag auf     |

Unterstützte Betreff-Muster:

| Muster               | Beispiel-Betreff              |
|----------------------|-------------------------------|
| `[benutzername]`     | `[anna] Rechnung Mai 2025`   |
| `@benutzername`      | `@anna Rechnung Mai 2025`    |
| `user:benutzername`  | `user:anna Rechnung Mai 2025`|

**Verhalten:**

| Einstellung              | Wert / Erklärung                              |
|--------------------------|-----------------------------------------------|
| Abfrage-Intervall        | `300` Sekunden (= 5 Minuten, Minimum: 60)    |
| Unterordner-Struktur     | Nach Datum, Monat, Absender, Betreff oder flach |
| Nach Verarbeitung löschen| Optional — E-Mails nach dem Speichern löschen |
| E-Mail-Text mitspeichern | Optional — speichert den Mail-Body als `.txt`-Datei |

Wenn **„E-Mail-Text mitspeichern"** aktiviert ist, wird der Inhalt jeder E-Mail als Textdatei
im selben Ordner wie die Anhänge abgelegt. Der Dateiname wird aus dem Betreff abgeleitet
(z.B. `Rechnung Mai 2025.txt`). Die Datei enthält einen kurzen Header (Von, Betreff, Datum)
gefolgt vom E-Mail-Text. HTML-Mails werden automatisch in lesbaren Klartext umgewandelt.

Klicke auf **„Verbindung testen"** um die IMAP-Verbindung zu prüfen.

#### Benutzer-Einstellungen

Jeder Benutzer kann unter **Persönliche Einstellungen → Zusätzliche Einstellungen → Mail2Folder**
seinen Zielordner anpassen (Standard: `Mail-Attachments`). Dort sieht jeder Benutzer auch
eine Anleitung mit seiner persönlichen Betreff-Kennung und der E-Mail-Adresse.

### 4. Cron einrichten

Für zuverlässiges Polling muss Nextcloud Cron per System-Cron laufen:

```bash
# Cron-Modus aktivieren (falls noch nicht geschehen):
docker exec -u www-data <container> php occ background:cron

# Crontab auf dem Host bearbeiten:
crontab -e

# Zeile hinzufügen (alle 5 Minuten):
*/5 * * * * docker exec -u www-data <container> php cron.php
```

## Ordnerstruktur der Anhänge

Je nach Admin-Einstellung werden Anhänge in Unterordnern organisiert:

| Modus     | Beispielpfad                                    |
|-----------|------------------------------------------------|
| `date`    | `Mail-Attachments/2025/2025-06-15/bericht.pdf`|
| `month`   | `Mail-Attachments/2025/06/bericht.pdf`         |
| `sender`  | `Mail-Attachments/max.mustermann/bericht.pdf`  |
| `subject` | `Mail-Attachments/Rechnung_Mai/bericht.pdf`    |
| `flat`    | `Mail-Attachments/bericht.pdf`                 |

Bei der Unterordner-Struktur `subject` wird der Benutzertag (z.B. `[anna]`) automatisch
aus dem Betreff entfernt, sodass nur der eigentliche Betrefftext als Ordnername verwendet wird.

## IMAP-Einstellungen für gängige Anbieter

| Anbieter     | IMAP-Host              | Port | Verschlüsselung |
|-------------|------------------------|------|------------------|
| 1&1 / IONOS | `imap.ionos.de`       | 993  | SSL/TLS          |
| GMX          | `imap.gmx.net`        | 993  | SSL/TLS          |
| Gmail        | `imap.gmail.com`      | 993  | SSL/TLS          |
| Outlook.com  | `outlook.office365.com`| 993  | SSL/TLS          |
| Postfix/Dovecot | `mail.example.com` | 993  | SSL/TLS          |

**Hinweis für Gmail:** Es muss ein App-Passwort erstellt werden (nicht das normale Login-Passwort).

## Sicherheitshinweise

- Das IMAP-Passwort wird in der Nextcloud-Datenbank gespeichert (`oc_appconfig`)
- Nur E-Mails mit gültigem Nextcloud-Benutzernamen im Betreff werden verarbeitet
- Unbekannte Benutzernamen werden ignoriert (Mail bleibt ungelesen im Postfach)
- Dateinamen werden sanitisiert (kein Path Traversal möglich)
- Duplikate werden anhand der Message-ID erkannt und übersprungen
- Der Betreff-Abgleich ist case-insensitive (`[Anna]` findet Benutzer `anna`)

## Fehlerbehebung

```bash
# Logs prüfen:
docker exec -u www-data <container> php occ log:tail --level=debug | grep Mail2Folder

# Hintergrund-Jobs anzeigen:
docker exec -u www-data <container> php occ background-job:list | grep Mail2Folder

# Job manuell ausführen:
docker exec -u www-data <container> php occ background-job:execute \
  "OCA\Mail2Folder\BackgroundJob\FetchMailJob"

# IMAP Extension prüfen:
docker exec <container> php -m | grep imap

# App-Status prüfen:
docker exec -u www-data <container> php occ app:list | grep mail2folder
```

### Häufige Probleme

**„Mail2Folder ist noch nicht konfiguriert"**
→ Das ist die persönliche Einstellungsseite. Die Admin-Konfiguration befindet sich unter
**Verwaltungseinstellungen** (nicht persönliche Einstellungen) → Zusätzliche Einstellungen.

**IMAP-Verbindung schlägt fehl**
→ Prüfe Host, Port und Zugangsdaten. Bei 1&1/IONOS: Benutzername ist die vollständige
E-Mail-Adresse. Teste die Verbindung über den Button in den Admin-Einstellungen.

**Keine Anhänge werden gespeichert**
→ Prüfe ob der Benutzername im Betreff exakt einem Nextcloud-Benutzer entspricht.
Der Abgleich ist case-insensitive, aber Tippfehler werden nicht erkannt.

**E-Mails werden nicht abgeholt**
→ Stelle sicher, dass Nextcloud Cron per System-Cron läuft (`php occ background:cron`).
Im AJAX-Modus werden Hintergrund-Jobs nur bei Seitenaufrufen ausgeführt.

## App aktualisieren

```bash
docker exec -u root <container> rm -rf /var/www/html/custom_apps/mail2folder
docker cp mail2folder/ <container>:/var/www/html/custom_apps/mail2folder
docker exec -u root <container> chown -R www-data:www-data /var/www/html/custom_apps/mail2folder
```

Die App muss nicht erneut aktiviert werden — die Einstellungen bleiben in der Datenbank erhalten.

## Dateiübersicht

```
mail2folder/
├── appinfo/
│   ├── info.xml              # App-Manifest
│   └── routes.php            # API-Routen
├── lib/
│   ├── AppInfo/Application.php
│   ├── BackgroundJob/FetchMailJob.php      # Cron-Job (IMAP-Polling)
│   ├── Controller/
│   │   ├── AdminSettingsController.php     # Admin API (Speichern/Testen)
│   │   └── PersonalSettingsController.php  # User API (Zielordner)
│   ├── Db/
│   │   ├── ProcessedMail.php               # Entity
│   │   └── ProcessedMailMapper.php         # DB-Queries
│   ├── Migration/
│   │   └── Version1000Date...Install.php   # DB-Schema
│   ├── Service/
│   │   ├── ImapService.php                 # IMAP-Verbindung & Attachment-Extraktion
│   │   ├── AttachmentService.php           # Datei-Speicherung in Nextcloud
│   │   └── MailProcessorService.php        # Orchestrierung & Betreff-Routing
│   └── Settings/
│       ├── AdminSettings.php               # Admin-Panel Registration
│       └── PersonalSettings.php            # User-Panel Registration
├── templates/
│   ├── admin.php             # Admin-UI (IMAP-Konfiguration)
│   └── personal.php          # User-UI (Anleitung & Zielordner)
├── js/
│   ├── admin.js              # Admin-Frontend
│   └── personal.js           # User-Frontend
├── css/
│   └── style.css             # Styles
├── img/
│   └── app.svg               # App-Icon
└── README.md
```

## Lizenz

AGPL-3.0 – wie Nextcloud selbst.
