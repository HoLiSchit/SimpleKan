# SimpleKan

Ein schlankes, selbst-gehostetes Kanban-Board für einen Account und ein
Board. PHP + SQLite im Backend, Tailwind (CDN) im Frontend, kein Build-Prozess.

## Features

- Login mit einem Admin-Account
- Spalten frei verwaltbar (anlegen, umbenennen, Farbe, Reihenfolge, WIP-Limit)
- Karten mit Titel, Beschreibung, Ort/Projekt-Tag, Fälligkeitsdatum, Priorität
- Filter nach Ort + Live-Suche
- Drag & Drop für Karten und Spalten
- Archiv statt sofortigem Löschen
- Backup & Wiederherstellung als JSON
- "Für LLM kopieren" – für das ganze Board oder pro Spalte einzeln
- Dark Mode

## Installation

1. Ordner auf den Server laden.
2. `chmod 775 data` (+ passenden Owner setzen, z. B. `www-data`).
3. `install.php` aufrufen, Account anlegen.
4. `install.php` löschen.
5. Optional `.htaccess` schärfen (Kommentar vor `Require all denied` im `install.php`-Block entfernen).
6. Unter `index.php` einloggen.

Update: alle Dateien außer `data/kanban.sqlite` überschreiben, Schema migriert automatisch.

## Sicherheits-Features

- Passwort-Hashing (bcrypt), PDO Prepared Statements, CSRF-Schutz,
  HttpOnly/SameSite-Cookies, Brute-Force-Sperre, `.htaccess`-Schutz für
  `.sqlite`-Dateien, serverseitige Validierung überall.

## Lizenz

MIT – siehe `LICENSE`.
