# SimpleKan

Ein schlankes, selbst-gehostetes Kanban-Board ohne Build-Prozess:
PHP + SQLite im Backend, Tailwind (CDN) im Frontend. Ordner auf den
Server laden, einrichten, loslegen.

## Features

- Login mit einem Admin-Account (Passwort-Hashing, Session-Schutz)
- Spalten frei verwaltbar: anlegen, umbenennen, Farbe ändern, löschen,
  per Drag & Drop sortieren
- Karten mit Titel, Beschreibung, Fälligkeitsdatum und Priorität
  (Niedrig/Mittel/Hoch), inkl. farbiger Badges
- Drag & Drop für Karten (zwischen Spalten) und für Spalten (Reihenfolge)
- WIP-Limit pro Spalte (z. B. max. 5 Karten "In Arbeit"), Anzeige wird
  rot, wenn überschritten
- Live-Suche über alle Karten (Titel + Beschreibung)
- Archiv statt sofortigem Löschen: Karten archivieren, später
  wiederherstellen oder endgültig entfernen
- Dark Mode (Umschalter, merkt sich die Wahl, respektiert System-Einstellung)

## Voraussetzungen

- PHP 8.1 oder neuer
- PHP-Extensions `pdo_sqlite` und `session` (bei den meisten Hostern
  standardmäßig aktiviert – prüfen mit `php -m | grep sqlite`)
- Schreibrechte für den Ordner `data/`

## Installation

1. Kompletten Ordner (Inhalt dieses Repos) per FTP/SFTP/`scp` oder `git
   clone` auf den Server laden, z. B. nach `/var/www/html/kanban` oder
   in ein Unterverzeichnis deiner Domain.

2. **Dateiberechtigungen setzen.** Der Webserver-Nutzer (häufig
   `www-data`, je nach Setup auch `nginx`, `apache` oder dein eigener
   User) muss in `data/` schreiben dürfen, damit die SQLite-Datenbank
   angelegt werden kann:
   ```bash
   cd kanban
   chmod 755 .
   chmod 775 data
   chown -R www-data:www-data data      # Nutzer/Gruppe an dein Setup anpassen
   ```
   Falls du unsicher bist, welcher Nutzer dein PHP ausführt, hilft
   `ps aux | grep php-fpm` oder ein kurzer `<?php echo exec('whoami'); ?>`
   in einer Testdatei (danach wieder löschen).

3. `install.php` im Browser aufrufen (`https://deine-domain.de/kanban/install.php`)
   und Benutzername + Passwort für den einzigen Account festlegen.

4. **`install.php` löschen.** Das ist der wichtigste Sicherheitsschritt
   nach der Einrichtung – sonst könnte theoretisch jemand die Datei
   erneut aufrufen und versuchen, Unfug zu treiben (auch wenn ein
   zweiter Account-Versuch serverseitig blockiert wird, gehört die
   Datei danach schlicht nicht mehr auf einen Produktivserver):
   ```bash
   rm install.php
   ```

5. **`.htaccess` schärfen (optional, empfohlen).** Öffne die
   `.htaccess` im Projektordner und entferne die Raute vor
   `Require all denied` im `<Files "install.php">`-Block:
   ```apache
   <Files "install.php">
       Require all denied
   </Files>
   ```
   Das blockt den Zugriff auf `install.php` zusätzlich auf
   Webserver-Ebene, falls die Datei aus Versehen doch mal wieder
   hochgeladen wird (z. B. bei einem künftigen Deployment aus Git).
   Gilt nur für Apache mit aktiviertem `mod_rewrite`/`AllowOverride All`
   – bei Nginx siehe Hinweis unten.

6. Unter `index.php` einloggen und loslegen.

### Update einer bestehenden Installation

Einfach alle Dateien überschreiben *außer* `data/kanban.sqlite` (die
enthält deine Karten). Das Datenbankschema migriert sich beim nächsten
Laden automatisch – neue Spalten/Felder werden ergänzt, ohne dass
Daten verloren gehen. `install.php` brauchst du dafür nicht erneut
auszuführen (blockt sich bei bestehendem Account ohnehin selbst ab).

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
│   ├── cards.php              # Karten: list/create/update/delete/archive/restore/reorder
│   └── columns.php             # Spalten: list/create/rename/recolor/set_limit/delete/reorder
├── assets/js/board.js            # Frontend-Logik (Vanilla JS, kein Framework)
├── data/kanban.sqlite               # SQLite-Datenbank (entsteht automatisch)
├── .htaccess                          # Zugriffsschutz für Apache
└── .gitignore                          # Schließt die Datenbank vom Repo aus
```

## Hinweis zu Nginx

Nutzt dein Server Nginx statt Apache, greift `.htaccess` nicht. Blocke
den Zugriff auf `.sqlite`-Dateien und `install.php` stattdessen im
Server-Block, z. B.:

```nginx
location ~* \.(sqlite|sqlite3|db)$ {
    deny all;
}
location = /install.php {
    deny all;
}
```
