# SimpleKan

Ein schlankes, selbst-gehostetes Kanban-Board für **einen Account und
ein Board** — kein Multi-Tenancy, keine Teams, kein Build-Prozess.
PHP + SQLite im Backend, Tailwind (CDN) im Frontend. Ordner auf den
Server laden, einrichten, loslegen.

## Features

- Login mit einem Admin-Account (Passwort-Hashing, Session-Schutz)
- Spalten frei verwaltbar: anlegen, umbenennen, Farbe ändern, löschen,
  per Drag & Drop sortieren
- Karten mit Titel, Beschreibung, **Ort/Projekt-Tag** (z. B.
  "Swipe-Stack", "Homepage"), Fälligkeitsdatum und Priorität
  (Niedrig/Mittel/Hoch), inkl. farbiger Badges
- **Filter nach Ort/Projekt** über ein Dropdown im Header, kombinierbar
  mit der Live-Suche
- Drag & Drop für Karten (zwischen Spalten) und für Spalten (Reihenfolge)
- WIP-Limit pro Spalte, Anzeige wird rot, wenn überschritten
- Live-Suche über alle Karten (Titel + Beschreibung)
- Archiv statt sofortigem Löschen: Karten archivieren, später
  wiederherstellen oder endgültig entfernen
- Dark Mode (Umschalter, merkt sich die Wahl, respektiert System-Einstellung)

## Bekannte Einschränkung

Drag & Drop nutzt die native HTML5-Drag&Drop-API. Diese funktioniert
zuverlässig auf Desktop-Browsern, hat aber **keine native
Touch-Unterstützung** — auf Smartphones/Tablets lassen sich Karten
aktuell nicht per Wischen verschieben (Klicken/Bearbeiten funktioniert
aber problemlos).

## Voraussetzungen

- PHP 8.1 oder neuer
- PHP-Extensions `pdo_sqlite` und `session` (bei den meisten Hostern
  standardmäßig aktiviert – prüfen mit `php -m | grep sqlite`)
- Schreibrechte für den Ordner `data/`

## Installation

1. Kompletten Ordner (Inhalt dieses Repos) per FTP/SFTP/`scp` oder `git
   clone` auf den Server laden, z. B. nach `/var/www/html/simplekan`
   oder in ein Unterverzeichnis deiner Domain.

2. **Dateiberechtigungen setzen.** Der Webserver-Nutzer (häufig
   `www-data`, je nach Setup auch `nginx`, `apache` oder dein eigener
   User) muss in `data/` schreiben dürfen, damit die SQLite-Datenbank
   angelegt werden kann:
   ```bash
   cd simplekan
   chmod 755 .
   chmod 775 data
   chown -R www-data:www-data data      # Nutzer/Gruppe an dein Setup anpassen
   ```
   Unsicher, welcher Nutzer dein PHP ausführt? `ps aux | grep php-fpm`
   oder kurz `<?php echo exec('whoami'); ?>` in einer Testdatei
   aufrufen (danach wieder löschen).

3. `install.php` im Browser aufrufen (`https://deine-domain.de/simplekan/install.php`)
   und Benutzername + Passwort für den einzigen Account festlegen.

4. **`install.php` löschen.** Wichtigster Sicherheitsschritt nach der
   Einrichtung:
   ```bash
   rm install.php
   ```

5. **`.htaccess` schärfen (optional, empfohlen).** In der `.htaccess`
   im Projektordner die Raute vor `Require all denied` im
   `<Files "install.php">`-Block entfernen:
   ```apache
   <Files "install.php">
       Require all denied
   </Files>
   ```
   Blockt den Zugriff auf `install.php` zusätzlich auf
   Webserver-Ebene, falls die Datei z. B. bei einem künftigen
   Git-Deployment aus Versehen wieder mit hochgeladen wird. Gilt nur
   für Apache mit `AllowOverride All` – bei Nginx siehe Hinweis unten.

6. Unter `index.php` einloggen und loslegen.

### Update einer bestehenden Installation

Alle Dateien überschreiben *außer* `data/kanban.sqlite` (enthält deine
Karten). Das Datenbankschema migriert sich beim nächsten Laden
automatisch – neue Spalten/Felder werden ergänzt, ohne Datenverlust.
`install.php` musst du dafür nicht erneut ausführen.

## Sicherheits-Features

- Passwort-Hashing mit `password_hash()` (bcrypt), nie Klartext.
- PDO mit Prepared Statements überall – Schutz vor SQL-Injection.
- CSRF-Token-Prüfung bei jeder verändernden Anfrage (Login + komplette API).
- `HttpOnly` + `SameSite=Strict` Session-Cookies, Session-ID-Regeneration
  nach Login (Schutz vor Session-Fixation).
- Brute-Force-Schutz: Sperre für 5 Minuten nach 5 Fehlversuchen,
  generische Fehlermeldung (kein Username-Enumeration).
- `htmlspecialchars()` auf allen Ausgaben (Schutz vor XSS).
- `.htaccess` blockiert direkten Zugriff auf `.sqlite`-Dateien von außen.
- Serverseitige Validierung aller Eingaben (Längen, Datumsformat,
  Farben-/Prioritäts-Whitelist).

## Struktur

```
simplekan/
├── install.php        # Einmalige Einrichtung (danach löschen!)
├── login.php           # Login
├── logout.php           # Logout
├── index.php             # Das Board
├── config.php              # DB-Verbindung, Schema-Migration, Session, CSRF
├── api/
│   ├── cards.php              # Karten: list/tags/create/update/delete/archive/restore/reorder
│   └── columns.php             # Spalten: list/create/rename/recolor/set_limit/delete/reorder
├── assets/js/board.js            # Frontend-Logik (Vanilla JS, kein Framework)
├── data/kanban.sqlite               # SQLite-Datenbank (entsteht automatisch)
├── .htaccess                          # Zugriffsschutz für Apache
├── .gitignore                          # Schließt die Datenbank vom Repo aus
└── LICENSE                              # MIT
```

## Hinweis zu Nginx

`.htaccess` greift nur bei Apache. Bei Nginx stattdessen im
Server-Block:

```nginx
location ~* \.(sqlite|sqlite3|db)$ {
    deny all;
}
location = /install.php {
    deny all;
}
```

## Lizenz

MIT – siehe `LICENSE`. Quelloffen, Attribution (Copyright-Hinweis in
Kopien) erforderlich.
