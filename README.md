# SimpleKan

Ein schlankes, selbst-gehostetes Kanban-Board für **einen Account und
ein Board** — kein Multi-Tenancy, keine Teams, kein Build-Prozess.
PHP + SQLite im Backend, Tailwind (CDN) im Frontend. Ordner auf den
Server laden, einrichten, loslegen.

## Features

- Login mit einem Admin-Account (Passwort-Hashing, Session-Schutz)
- Spalten frei verwaltbar: anlegen, umbenennen, Farbe ändern, löschen,
  per Drag & Drop sortieren
- Karten mit Titel, Beschreibung, Ort/Projekt-Tag (z. B. "Swipe-Stack",
  "Homepage"), Fälligkeitsdatum und Priorität, inkl. farbiger Badges
- Filter nach Ort/Projekt über ein Dropdown im Header, kombinierbar mit
  der Live-Suche
- Drag & Drop für Karten (zwischen Spalten) und für Spalten (Reihenfolge)
- WIP-Limit pro Spalte, Anzeige wird rot, wenn überschritten
- Archiv statt sofortigem Löschen: Karten archivieren, später
  wiederherstellen oder endgültig entfernen
- Dark Mode (Umschalter, merkt sich die Wahl, respektiert System-Einstellung)

## Bekannte Einschränkung

Drag & Drop nutzt die native HTML5-Drag&Drop-API. Diese funktioniert
zuverlässig auf Desktop-Browsern, hat aber keine native
Touch-Unterstützung — auf Smartphones/Tablets lassen sich Karten
aktuell nicht per Wischen verschieben (Klicken/Bearbeiten funktioniert
aber problemlos).

## Voraussetzungen

- PHP 8.1 oder neuer
- PHP-Extensions `pdo_sqlite` und `session`
- Schreibrechte für den Ordner `data/`

## Installation

1. Kompletten Ordner auf den Server laden.
2. **Dateiberechtigungen setzen:**
   ```bash
   cd simplekan
   chmod 755 .
   chmod 775 data
   chown -R www-data:www-data data      # Nutzer/Gruppe an dein Setup anpassen
   ```
3. `install.php` im Browser aufrufen, Benutzername + Passwort festlegen.
4. **`install.php` löschen** (wichtigster Sicherheitsschritt):
   ```bash
   rm install.php
   ```
5. **`.htaccess` schärfen (optional, empfohlen):** Raute vor
   `Require all denied` im `<Files "install.php">`-Block entfernen.
6. Unter `index.php` einloggen und loslegen.

### Update einer bestehenden Installation

Alle Dateien überschreiben *außer* `data/kanban.sqlite`. Das
Datenbankschema migriert sich beim nächsten Laden automatisch.

## Sicherheits-Features

- Passwort-Hashing mit `password_hash()` (bcrypt)
- PDO mit Prepared Statements überall
- CSRF-Token-Prüfung bei jeder verändernden Anfrage
- `HttpOnly` + `SameSite=Strict` Session-Cookies, Session-Regeneration nach Login
- Brute-Force-Schutz (Sperre nach 5 Fehlversuchen)
- `htmlspecialchars()` auf allen Ausgaben
- `.htaccess` blockiert Zugriff auf `.sqlite`-Dateien
- Serverseitige Validierung aller Eingaben

## Struktur

```
simplekan/
├── install.php
├── login.php
├── logout.php
├── index.php
├── config.php
├── api/
│   ├── cards.php
│   └── columns.php
├── assets/js/board.js
├── data/kanban.sqlite
├── .htaccess
├── .gitignore
└── LICENSE
```

## Hinweis zu Nginx

```nginx
location ~* \.(sqlite|sqlite3|db)$ { deny all; }
location = /install.php { deny all; }
```

## Lizenz

MIT – siehe `LICENSE`.
