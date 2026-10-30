# AMIGO! The Retro Collector 2.0

Schlanke Sammlungs-Datenbank für Retro-Spiele, Konsolen und Zubehör – mit
TheGamesDB-Anbindung, Fotos, Zustandsbewertung, „Durchgespielt“-Liste und
Zwei-Faktor-Anmeldung. Läuft auf jedem normalen PHP-Webspace, im Browser,
auf dem Tablet und auf dem Smartphone.

## Funktionen

**Sammlung**
- Spiele, Konsolen/Computer, Zubehör und Sonstiges erfassen
- Spielsuche mit Live-Vorschlägen aus TheGamesDB: Titel, Plattform, Cover,
  Entwickler, Publisher, Genre und Erscheinungsdatum werden übernommen
- Region (PAL, NTSC-U, NTSC-J …), Umfang (versiegelt, CIB, lose …)
- Zustand je Teil (Gesamt, Modul/Disc/Gerät, Verpackung, Anleitung) auf
  einer Skala von „Neuwertig“ bis „Defekt“ + freie Zustandsbeschreibung
- Beliebig viele Fotos pro Eintrag (bis 30), **jedes mit eigener Beschreibung**
  und Reihenfolge; Galerie mit Vollbildansicht
- Kaufdatum, Preis, Händler, Notizen
- Filter, Suche, Sortierung, Kachel- oder datensparende Listenansicht
- CSV-Export (Excel-kompatibel)

**Durchgespielt**
- Spiel (aus TheGamesDB oder frei), Plattform, Datum, Spielzeit,
  Punkte/Highscore, 1–5 Sterne und Bewertungstext – alles außer dem Titel optional
- Statistik (Anzahl, Gesamtspielzeit, Ø-Wertung), Filter nach Jahr/Plattform/Sternen
- CSV-Export

**Plattformen**
- Import aus TheGamesDB (einzeln, gefiltert oder alle auf einmal)
- Plattformen werden außerdem automatisch angelegt, wenn ein Spiel gewählt wird
- Eigene Plattformen für Exoten, die TheGamesDB nicht kennt

**Benutzer**
- Mehrere Benutzer, jeder sieht nur seine eigene Sammlung
- Zwei-Faktor-Anmeldung (TOTP, z. B. Aegis, 2FAS, Bitwarden, Google/Microsoft
  Authenticator) mit QR-Code und 10 Einmal-Recovery-Codes
- Admin: Benutzer anlegen/sperren/löschen, 2FA zurücksetzen, Registrierung
  öffnen/schließen, 2FA für alle verpflichtend machen, Systemcheck

**Design**
- Retro-Look mit Pixel-Schrift, CRT-Scanlines, Modul-Kacheln, Pixel-Sternen
- Drei Farbschemata: *CRT Neon*, *Game Boy* (DMG-Grün) und *C64*
- Mobil: App-artige Navigationsleiste unten, große Touch-Ziele,
  „Zum Startbildschirm hinzufügen“ (Web-App-Manifest)

## Schlank für langsame Verbindungen (EDGE/3G)

- Kein Framework, kein jQuery/Bootstrap: eine CSS-Datei (~7 KB gzip),
  ein Script (~4 KB gzip), eine Schrift (12 KB) – alles lokal, keine externen CDNs
- HTML wird gzip-komprimiert ausgeliefert, statische Dateien werden ein Jahr gecacht
- Fotos werden **im Browser vor dem Hochladen verkleinert** (aus 5 MB werden
  ~300 KB) und vom Server zusätzlich als progressive JPEGs mit Vorschaubildern
  gespeichert; Vollbilder laden erst beim Öffnen
- Listenansicht ganz ohne Bilder
- Alles funktioniert auch ohne JavaScript (dann ohne Live-Suche)

## Voraussetzungen

- PHP **8.1** oder neuer mit `pdo_sqlite` **oder** `pdo_mysql`, `gd`,
  `fileinfo`, `mbstring`, `sodium` (oder `openssl`), `curl` (empfohlen)
- Apache mit `.htaccess` (oder nginx, siehe unten)
- HTTPS (dringend empfohlen, z. B. Let's Encrypt beim Hoster)

## Installation

1. Dateien auf den Webspace laden.
2. `https://deine-domain.tld/pfad/install.php` aufrufen.
3. Der Installer legt `config/INSTALL_TOKEN.txt` an. Öffne die Datei per
   FTP/Dateimanager und gib den Inhalt im Installer ein. So kann niemand
   anderes deine frische Installation übernehmen.
4. Datenbank wählen, Datenverzeichnis angeben, Admin-Konto anlegen – fertig.
5. Nach dem Login: **Konto › 2FA einrichten**.

Der Installer sperrt sich selbst, sobald `config/config.php` existiert.
`install.php` darf danach zusätzlich gelöscht werden.

### Welche Datenbank?

| | **SQLite** (empfohlen) | **MySQL / MariaDB** |
|---|---|---|
| Datenbank-Server nötig | nein | ja |
| Verbraucht ein MySQL-Kontingent | nein | ja (oder per Präfix eine vorhandene Datenbank mitnutzen) |
| Speicherort | eine Datei im Datenverzeichnis | DB-Server |
| Backup | Datenverzeichnis kopieren | SQL-Dump + Datenverzeichnis |
| Geeignet für | Privatsammlung bis viele 10 000 Einträge, wenige gleichzeitige Nutzer | große/viele parallele Nutzer |

**Warum SQLite sicher ist:** Die Datenbank ist eine einzelne Datei. Liegt das
Datenverzeichnis *außerhalb* des öffentlichen Webordners (der Installer schlägt
`../amigo-data` vor), ist sie über das Web grundsätzlich nicht erreichbar – es
gibt keinen Datenbank-Port, kein Datenbank-Passwort, das abgegriffen werden
könnte, und nichts, was ein Angreifer von außen ansprechen kann. Zusätzlich:
`secure_delete` (gelöschte Daten werden überschrieben), Fremdschlüssel aktiv,
WAL-Journal für Absturzsicherheit. Wenn dein Hoster keine Ordner außerhalb des
Webroots erlaubt, bleibt `data/` im Projekt und wird per `.htaccess` gesperrt;
**Admin › System** prüft automatisch, ob die Sperre greift.

Bei MySQL kann über das **Tabellen-Präfix** (Standard `amigo_`) eine bereits
vorhandene Datenbank mitgenutzt werden – praktisch, wenn dein Paket nur wenige
Datenbanken enthält.

### TheGamesDB-API-Key

Ohne Key funktioniert alles, nur ohne Vorschläge und Plattform-Import.
Einen kostenlosen Key bekommst du über das
[TheGamesDB-Forum](https://forums.thegamesdb.net/). Eintragen im Installer oder
jederzeit unter **Admin › System** (dort auch „Verbindung testen“ und
„Entfernen“). Der Key wird verschlüsselt in der Datenbank gespeichert und bleibt
auf dem Server; alternativ kann er als Rückfallwert in `config/config.php` unter
`tgdb_api_key` stehen (ein im Adminbereich gesetzter Key hat Vorrang). alle Antworten werden zwischengespeichert (Suche 1 Tag, Spiele und
Plattformen 7 Tage, Listen 30 Tage), um das Monatskontingent zu schonen. Das
Restkontingent steht unter **Admin › System**.

### nginx

`.htaccess` wird von nginx ignoriert. Sperre die internen Ordner selbst:

```nginx
location ~ ^/(app|config|data)(/|$) { deny all; return 404; }
location ~ /\.(?!well-known) { deny all; }
location ~ \.(md|sqlite|log|lock)$ { deny all; }
```

## Sicherheit

- **SQL-Injection:** ausschließlich vorbereitete Statements (PDO, echte
  Prepared Statements), Sortierungen/Filter nur über Whitelists
- **XSS:** jede Ausgabe wird escaped; strenge Content-Security-Policy
  (keine Inline-Scripts, nur eigene Dateien + Cover-Bilder von TheGamesDB)
- **CSRF:** Token in jedem Formular + Prüfung des Origin-Headers
- **Passwörter:** Argon2id (Fallback bcrypt), automatisches Rehashing,
  Mindestlänge 10; Passwortwechsel meldet andere Sitzungen ab
- **Brute-Force:** Rate-Limits für Login (je IP und je Konto), 2FA,
  Passwortprüfungen, Registrierung und API-Suche; IPs nur gehasht gespeichert
- **2FA:** TOTP mit Schutz gegen Wiederverwendung eines Codes; Geheimnis
  verschlüsselt (libsodium) in der Datenbank, Schlüssel nur in `config.php`;
  Recovery-Codes nur als Hash gespeichert
- **Sitzungen:** HttpOnly/SameSite/Secure-Cookies (`__Host-`-Präfix unter HTTPS),
  neue Session-ID beim Login, Auto-Logout bei Inaktivität, eigenes
  Session-Verzeichnis statt des geteilten Server-Temp-Ordners
- **Uploads:** Prüfung von Größe, echtem Dateityp und Bildmaßen; jedes Bild
  wird neu berechnet (entfernt EXIF/GPS-Standortdaten und eingeschmuggelten
  Code), zufällige Dateinamen, Ablage außerhalb des Webroots, Auslieferung nur
  an den Besitzer
- **Zugriffsrechte:** jede Abfrage ist auf den angemeldeten Benutzer beschränkt
- **Header:** HSTS, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy
- **CSV-Export** mit Schutz gegen Formel-Injection
- Fehlerdetails nur im Log (`data/logs`), nie im Browser

## Backup

- `config/config.php` (enthält den Verschlüsselungsschlüssel – ohne ihn sind
  eingerichtete 2FA-Zugänge nicht mehr nutzbar)
- das komplette Datenverzeichnis (Fotos, bei SQLite auch die Datenbank)
- bei MySQL zusätzlich ein Datenbank-Dump

## Aufbau

```
index.php            Front-Controller (alle Seiten: index.php?r=…)
install.php          Installer
app/src/Core         Datenbank, Schema, Auth, CSRF, TOTP, Krypto, Rate-Limit
app/src/Service      TheGamesDB-Client, Katalog, Bild-Upload
app/src/Controller   Seitenlogik
app/views            Templates
assets/              CSS, JS, Schrift, Icons
config/              config.php (wird erzeugt, nicht ins Git!)
data/                Fotos, SQLite, Sessions, Logs (falls nicht ausgelagert)
```

## Hinweise zum Umstieg von Version 1

Version 2 ist eine Neuentwicklung mit neuem Datenbankschema; die alten
Tabellen (`collecting_games`, `categories`, `users`) werden nicht automatisch
übernommen. **Wichtig:** In der Git-Historie der alten Version stehen die
damaligen MySQL-Zugangsdaten (`support/con_db.php`). Falls diese noch irgendwo
gültig sind, das Passwort bitte ändern.

## Lizenzen

- Schrift „Press Start 2P“ – SIL Open Font License (`assets/fonts/OFL.txt`)
- QR-Code-Generator von Kazuhiko Arase – MIT-Lizenz
- Spieldaten und Cover: [TheGamesDB](https://thegamesdb.net)
