<?php
declare(strict_types=1);

namespace Amigo\Service;

use Amigo\Core\Database;
use Amigo\Core\Settings;

/**
 * Standardinhalte der öffentlichen Website (beim Update auf Schema v8 einmalig angelegt).
 * Alles ist danach im Adminbereich frei bearbeitbar. Impressum und Datenschutz sind
 * Vorlagen mit Platzhaltern und deshalb zunächst ausgeschaltet.
 */
final class SiteDefaults
{
    public const REPO = 'https://github.com/neocrypton/AmigoTRC';

    public static function install(Database $db): void
    {
        $now = Database::now();
        foreach (self::pages() as $slug => [$title, $body, $enabled]) {
            if (!$db->value('SELECT 1 FROM {site_pages} WHERE slug = ?', [$slug])) {
                $db->insert('site_pages', ['slug' => $slug, 'title' => $title, 'body' => $body, 'enabled' => $enabled, 'updated_at' => $now]);
            }
        }
        if (!(int)$db->value('SELECT COUNT(*) FROM {site_faq}')) {
            foreach (self::faq() as $i => [$q, $a]) {
                $db->insert('site_faq', ['question' => $q, 'answer' => $a, 'sort' => ($i + 1) * 10, 'enabled' => 1]);
            }
        }
        if (!(int)$db->value('SELECT COUNT(*) FROM {site_links}')) {
            $db->insert('site_links', ['name' => 'AMIGO! auf GitHub', 'url' => self::REPO, 'banner' => null, 'sort' => 10, 'enabled' => 1]);
            $db->insert('site_links', ['name' => 'TheGamesDB', 'url' => 'https://thegamesdb.net', 'banner' => null, 'sort' => 20, 'enabled' => 1]);
        }
        foreach (['site_enabled' => '0', 'news_enabled' => '0'] as $k => $v) {
            if (Settings::get($db, $k) === null) {
                Settings::set($db, $k, $v);
            }
        }
    }

    /** @return array<string, array{0:string,1:string,2:int}> slug => [Titel, HTML, aktiv] */
    public static function pages(): array
    {
        $repo = self::REPO;
        return [
            'home' => ['Start', <<<HTML
<h2 class="retro-pixel">Deine Retro-Sammlung. Endlich im Griff.</h2>
<p><strong>AMIGO! The Retro Collector</strong> ist eine kostenlose Web-App für alle, die alte Spiele, Konsolen und Zubehör sammeln. Du installierst sie auf deinem eigenen Webspace – deine Daten bleiben bei dir, ohne Werbung, ohne Tracking, ohne Abo.</p>
<p class="retro-box">Vom Modul im Schuhkarton bis zur CIB-Rarität im Regal: Mit AMIGO weißt du immer, was du hast, in welchem Zustand es ist und was du schon durchgespielt hast.</p>
<h3>Was AMIGO dir bietet</h3>
<ul>
<li><strong>Sammlung erfassen:</strong> Spiele, Konsolen, Computer und Zubehör mit Region, Vollständigkeit (versiegelt, CIB, lose …), Zustand je Teil, Kaufdaten und Notizen.</li>
<li><strong>Fotos:</strong> bis zu 30 Bilder pro Eintrag, jedes mit eigener Beschreibung – direkt mit dem Handy aufgenommen und schon im Browser verkleinert.</li>
<li><strong>TheGamesDB-Anbindung:</strong> Titel eintippen, Spiel auswählen – Cover, Plattform, Entwickler, Publisher, Genre und Erscheinungsdatum kommen automatisch dazu.</li>
<li><strong>Durchgespielt-Liste:</strong> Datum, Spielzeit, Highscore, Sterne und eine eigene Bewertung für jedes beendete Spiel.</li>
<li><strong>Community:</strong> mehrere Nutzer, öffentliche Profile, Sammlungen gegenseitig ansehen (Preise und Notizen bleiben immer privat) und private Nachrichten mit Pixel-Smilies.</li>
<li><strong>Umziehen leicht gemacht:</strong> CSV-Import aus Excel, Google Sheets oder anderen Sammler-Apps – und jederzeit CSV-Export.</li>
<li><strong>Sicher:</strong> Zwei-Faktor-Anmeldung, verschlüsselte Geheimnisse, strenge Sicherheits-Header und Schutz vor Brute-Force.</li>
<li><strong>Schlank:</strong> kein Framework, keine fremden Server im Browser – läuft sogar über eine langsame EDGE-Verbindung, auf Smartphone, Tablet und PC.</li>
<li><strong>Retro-Look:</strong> Pixel-Schrift, CRT-Scanlines und drei Farbschemata: CRT Neon, Game Boy und C64.</li>
</ul>
<h3>Für wen ist AMIGO?</h3>
<p><strong>Für Einzelsammler</strong>, die ihre Sammlung privat auf dem eigenen Webspace verwalten wollen – und <strong>für Communities</strong>: Vereine, Foren, Freundeskreise oder Streamer, die ihren Leuten eine gemeinsame Sammler-Plattform bieten möchten.</p>
<h3>Open Source</h3>
<p>AMIGO ist freie Software unter der <strong>GPL-3.0</strong>. Der Quellcode liegt auf <a href="{$repo}" target="_blank">GitHub</a> – Ideen, Fehlerberichte und Mithilfe sind willkommen.</p>
<p><a href="index.php?r=page&amp;p=install">▶ So installierst du AMIGO</a></p>
HTML, 1],

            'install' => ['Installation', <<<HTML
<h2>AMIGO installieren</h2>
<p>AMIGO läuft auf praktisch jedem normalen PHP-Webspace. Du brauchst keine Kommandozeile – Dateien hochladen und den Installer im Browser aufrufen reicht.</p>
<h3>Voraussetzungen</h3>
<ul>
<li>PHP <strong>8.1</strong> oder neuer mit den Erweiterungen <code>pdo_sqlite</code> oder <code>pdo_mysql</code>, <code>gd</code>, <code>fileinfo</code>, <code>mbstring</code>, <code>sodium</code> (oder <code>openssl</code>) und möglichst <code>curl</code></li>
<li>Apache mit <code>.htaccess</code> (oder nginx – die nötigen Regeln stehen in der README)</li>
<li>HTTPS (z. B. kostenloses Let's-Encrypt-Zertifikat deines Hosters)</li>
</ul>
<h3>Schritt für Schritt</h3>
<ol>
<li><strong>Herunterladen:</strong> die aktuelle Version von <a href="{$repo}" target="_blank">GitHub</a> laden (grüner Button „Code“ → „Download ZIP“) und entpacken.</li>
<li><strong>Hochladen:</strong> alle Dateien per FTP oder Dateimanager auf deinen Webspace kopieren, z. B. in den Ordner <code>/amigo</code>.</li>
<li><strong>Installer starten:</strong> <code>https://deine-domain.de/amigo/install.php</code> aufrufen.</li>
<li><strong>Installations-Token:</strong> Der Installer legt die Datei <code>config/INSTALL_TOKEN.txt</code> an. Öffne sie per FTP und gib den Inhalt im Installer ein – so kann niemand sonst deine frische Installation übernehmen.</li>
<li><strong>Datenbank wählen:</strong> <strong>SQLite</strong> (empfohlen, keine Datenbank nötig) oder <strong>MySQL/MariaDB</strong> (auch mit Tabellen-Präfix in einer vorhandenen Datenbank).</li>
<li><strong>Datenverzeichnis:</strong> am besten außerhalb des öffentlichen Webordners (der Installer schlägt <code>../amigo-data</code> vor).</li>
<li><strong>Admin-Konto anlegen</strong> – fertig! Danach unter <em>Mein Konto</em> gleich die Zwei-Faktor-Anmeldung einrichten.</li>
</ol>
<h3>Variante 1: Einzelsammler (Single)</h3>
<p>Du willst nur deine eigene Sammlung verwalten? Dann bist du nach der Installation schon fast fertig:</p>
<ul>
<li>Die öffentliche Registrierung bleibt <strong>geschlossen</strong> (Standard). Weitere Konten, z. B. für die Familie, legst du bei Bedarf unter <em>Admin › Benutzer</em> selbst an.</li>
<li>Einen kostenlosen <a href="https://thegamesdb.net/register.php" target="_blank">TheGamesDB-API-Key</a> unter <em>Admin › System</em> eintragen – dann gibt es Spielvorschläge, Cover und Plattform-Import.</li>
<li>Die öffentliche Website kannst du ausgeschaltet lassen: Besucher sehen dann nur die Anmeldeseite.</li>
</ul>
<h3>Variante 2: Community</h3>
<p>Du möchtest AMIGO für eine Gruppe, einen Verein oder deine Community öffnen? Dann zusätzlich unter <em>Admin › System</em>:</p>
<ol>
<li><strong>E-Mail-Versand einrichten</strong> (PHP <code>mail()</code> oder SMTP deines Hosters) und mit der Testmail prüfen.</li>
<li><strong>Google reCAPTCHA v2</strong> („Ich bin kein Roboter“) anlegen und die Schlüssel eintragen – ohne Captcha lässt sich die Registrierung nicht öffnen.</li>
<li><strong>Aktivierung neuer Konten</strong> wählen: per E-Mail-Bestätigung, durch dich als Admin oder beides.</li>
<li><strong>Öffentliche Registrierung erlauben.</strong> Damit werden auch die privaten Nachrichten aktiv.</li>
<li><strong>TheGamesDB:</strong> Jeder Nutzer kann einen eigenen Key hinterlegen. Alternativ erlaubst du, dass alle deinen System-Key mitbenutzen – das teilt aber dein Kontingent.</li>
<li><strong>Website einschalten</strong> (<em>Admin › Website</em>), Impressum und Datenschutzerklärung ausfüllen und aktivieren.</li>
</ol>
<h3>Updates</h3>
<p>Neue Version herunterladen und die Dateien einfach überschreiben. <code>config/config.php</code> und dein Datenverzeichnis bleiben erhalten; die Datenbank wird beim ersten Aufruf automatisch aktualisiert.</p>
<h3>Backup</h3>
<p>Sichere regelmäßig <code>config/config.php</code> (enthält den Verschlüsselungsschlüssel!) und das komplette Datenverzeichnis – bei MySQL zusätzlich einen Datenbank-Dump.</p>
HTML, 1],

            'faq' => ['FAQ', <<<HTML
<h2>Häufig gestellte Fragen</h2>
<p>Deine Frage ist nicht dabei? Schau in die README auf <a href="{$repo}" target="_blank">GitHub</a> oder melde dich beim Betreiber dieser Seite.</p>
HTML, 1],

            'imprint' => ['Impressum', <<<HTML
<p class="retro-box"><strong>Vorlage:</strong> Bitte ersetze alle Angaben in [eckigen Klammern] durch deine eigenen Daten und lösche danach diesen Kasten. Diese Vorlage ist keine Rechtsberatung.</p>
<h2>Impressum</h2>
<h3>Angaben gemäß § 5 DDG</h3>
<p>[Vorname Nachname]<br>[Straße Hausnummer]<br>[PLZ Ort]<br>[Land]</p>
<h3>Kontakt</h3>
<p>E-Mail: [deine@e-mail.de]<br>Telefon: [optional]</p>
<h3>Verantwortlich für den Inhalt nach § 18 Abs. 2 MStV</h3>
<p>[Vorname Nachname], Anschrift wie oben</p>
<h3>Haftung für Links</h3>
<p>Diese Website enthält Links zu externen Websites Dritter, auf deren Inhalte wir keinen Einfluss haben. Für die Inhalte der verlinkten Seiten ist stets der jeweilige Anbieter verantwortlich. Bei Bekanntwerden von Rechtsverletzungen werden wir derartige Links umgehend entfernen.</p>
<h3>Spieldaten und Cover</h3>
<p>Spieldaten und Coverbilder stammen von <a href="https://thegamesdb.net" target="_blank">TheGamesDB</a>. Marken und Logos gehören ihren jeweiligen Inhabern.</p>
HTML, 0],

            'privacy' => ['Datenschutz', <<<HTML
<p class="retro-box"><strong>Vorlage:</strong> Diese Datenschutzerklärung beschreibt, was AMIGO technisch tut. Ersetze die Angaben in [eckigen Klammern], entferne Abschnitte zu Funktionen, die du nicht nutzt (z. B. Registrierung/reCAPTCHA), und ergänze Angaben zu deinem Hoster. Lösche danach diesen Kasten. Diese Vorlage ist keine Rechtsberatung.</p>
<h2>Datenschutzerklärung</h2>
<h3>1. Verantwortlicher</h3>
<p>[Vorname Nachname]<br>[Straße Hausnummer], [PLZ Ort]<br>E-Mail: [deine@e-mail.de]</p>
<h3>2. Überblick</h3>
<p>Wir verarbeiten personenbezogene Daten nur, soweit dies für den Betrieb dieser Website und der Sammlungsverwaltung „AMIGO! The Retro Collector“ nötig ist. Wir setzen <strong>keine Analyse- oder Tracking-Werkzeuge</strong> und keine Werbung ein. Schriftarten, Skripte und Bilder werden vom eigenen Server geladen.</p>
<h3>3. Hosting und Server-Logfiles</h3>
<p>Diese Website wird bei [Name und Anschrift des Hosters] betrieben. Beim Aufruf speichert der Webserver automatisch Informationen in Logfiles (IP-Adresse, Datum und Uhrzeit, aufgerufene Seite, Browsertyp, Referrer). Das ist zur sicheren Bereitstellung der Website erforderlich (Art. 6 Abs. 1 lit. f DSGVO). Die Logfiles werden nach [Anzahl] Tagen gelöscht.</p>
<h3>4. Cookies</h3>
<p>Wir verwenden ausschließlich technisch notwendige Cookies (§ 25 Abs. 2 Nr. 2 TDDDG):</p>
<ul>
<li><strong>Sitzungs-Cookie</strong> (<code>amigo</code> bzw. <code>__Host-amigo</code>): hält dich angemeldet und schützt Formulare vor Missbrauch (CSRF-Schutz). Es wird beim Abmelden bzw. Schließen des Browsers ungültig.</li>
<li><strong>Farbschema</strong> (<code>amigo_theme</code>): merkt sich das gewählte Design (CRT, Game Boy, C64) für bis zu ein Jahr. Es enthält keine personenbezogenen Daten.</li>
</ul>
<h3>5. Benutzerkonto und Sammlungsdaten</h3>
<p>Für ein Konto speichern wir Benutzername, Passwort (nur als sicherer Hash), optional deine E-Mail-Adresse und Profileinstellungen sowie die Inhalte, die du selbst anlegst: Sammlungseinträge, Fotos (ohne EXIF-/Standortdaten), Durchgespielt-Liste, Avatar und private Nachrichten. Ein eigener TheGamesDB-API-Key und das Geheimnis der Zwei-Faktor-Anmeldung werden verschlüsselt gespeichert. Rechtsgrundlage ist die Bereitstellung des Dienstes (Art. 6 Abs. 1 lit. b DSGVO).</p>
<p>Sammlung und Durchgespielt-Liste sind standardmäßig privat. Nur wenn du sie freigibst, können andere angemeldete Nutzer sie ansehen; Kaufpreis, Kaufdatum, Händler und Notizen bleiben immer privat.</p>
<p>Zum Schutz vor Angriffen (z. B. Passwort-Raten) speichern wir fehlgeschlagene Anmeldeversuche kurzzeitig mit einer <strong>gehashten</strong> IP-Adresse (Art. 6 Abs. 1 lit. f DSGVO).</p>
<h3>6. Registrierung und Google reCAPTCHA</h3>
<p>Zum Schutz vor automatisierten Anmeldungen nutzen wir auf der Registrierungsseite Google reCAPTCHA v2 der Google Ireland Limited, Gordon House, Barrow Street, Dublin 4, Irland. Dabei werden u. a. IP-Adresse und Informationen über deinen Browser an Google übertragen; eine Übermittlung in die USA ist möglich (Grundlage: EU-US Data Privacy Framework). reCAPTCHA wird nur auf der Registrierungsseite geladen. Rechtsgrundlage ist unser berechtigtes Interesse am Schutz vor Missbrauch (Art. 6 Abs. 1 lit. f DSGVO). Weitere Informationen: <a href="https://policies.google.com/privacy" target="_blank">Datenschutzerklärung von Google</a>.</p>
<h3>7. E-Mails</h3>
<p>Wenn du eine E-Mail-Adresse angibst, nutzen wir sie für die Bestätigung deines Kontos, Informationen zur Freischaltung und – sofern nicht abgeschaltet – für Hinweise auf neue private Nachrichten (ohne Nachrichtentext). Die Benachrichtigungen kannst du jederzeit im Konto oder über den Link in jeder Mail abbestellen.</p>
<h3>8. TheGamesDB</h3>
<p>Spieldaten und Cover stammen von TheGamesDB (thegamesdb.net). Die Abfragen stellt unser Server; Cover werden auf unserem Server zwischengespeichert. Dein Browser nimmt dabei keinen Kontakt zu TheGamesDB auf.</p>
<h3>9. Links zu anderen Websites</h3>
<p>Links zu externen Seiten (auch zu Partnern) übertragen erst dann Daten an den jeweiligen Anbieter, wenn du sie anklickst. Eingebettete Videos zeigen wir nur als Link an.</p>
<h3>10. Speicherdauer</h3>
<p>Kontodaten speichern wir, bis du dein Konto löschen lässt. Nicht bestätigte Registrierungen werden nach 7 Tagen automatisch entfernt.</p>
<h3>11. Deine Rechte</h3>
<p>Du hast das Recht auf Auskunft (Art. 15 DSGVO), Berichtigung (Art. 16), Löschung (Art. 17), Einschränkung der Verarbeitung (Art. 18), Datenübertragbarkeit (Art. 20) und Widerspruch (Art. 21). Wende dich dazu an die oben genannte Adresse. Außerdem kannst du dich bei einer Datenschutz-Aufsichtsbehörde beschweren.</p>
<p>Stand: [Monat Jahr]</p>
HTML, 0],
        ];
    }

    /** @return list<array{0:string,1:string}> [Frage, Antwort-HTML] */
    public static function faq(): array
    {
        $repo = self::REPO;
        return [
            ['Was kostet AMIGO?', '<p>Nichts. AMIGO ist freie Software unter der GPL-3.0. Du brauchst nur einen Webspace mit PHP – den hast du oft schon.</p>'],
            ['Brauche ich einen eigenen Server?', '<p>Nein. Ein ganz normales Webhosting-Paket mit PHP 8.1 reicht. Eine Datenbank ist nicht einmal nötig, wenn du SQLite wählst.</p>'],
            ['SQLite oder MySQL – was soll ich nehmen?', '<p>Für private Sammlungen und kleine Communities ist <strong>SQLite</strong> ideal: eine einzige Datei, kein Datenbank-Server, Backup durch einfaches Kopieren. <strong>MySQL/MariaDB</strong> lohnt sich bei vielen gleichzeitigen Nutzern oder wenn dein Hoster SQLite nicht anbietet.</p>'],
            ['Wozu brauche ich einen TheGamesDB-API-Key?', '<p>Mit dem kostenlosen Key schlägt AMIGO beim Tippen passende Spiele vor und übernimmt Cover, Plattform und weitere Daten. Ohne Key funktioniert alles andere trotzdem – du trägst die Daten dann selbst ein. <a href="https://thegamesdb.net/register.php" target="_blank">Hier registrieren</a> und den Key unter <em>Mein Konto</em> eintragen.</p>'],
            ['Wer kann meine Sammlung sehen?', '<p>Standardmäßig niemand außer dir. Du kannst Sammlung und Durchgespielt-Liste getrennt für andere angemeldete Nutzer freigeben. Kaufpreis, Kaufdatum, Händler und Notizen bleiben immer privat – sie werden gar nicht erst ausgeliefert.</p>'],
            ['Funktioniert AMIGO auf dem Smartphone?', '<p>Ja. AMIGO ist für Smartphone, Tablet und PC gebaut, mit Navigationsleiste unten wie in einer App. Über „Zum Home-Bildschirm hinzufügen“ startet es wie eine eigene App. Fotos nimmst du direkt mit der Kamera auf.</p>'],
            ['Kann ich meine bisherige Liste übernehmen?', '<p>Ja, per <strong>CSV-Import</strong> (z. B. aus Excel, LibreOffice, Google Sheets oder anderen Sammler-Apps). Du ordnest die Spalten zu, siehst eine Vorschau und kannst unbekannte Plattformen von Hand zuordnen. Danach lassen sich Cover nachträglich automatisch ergänzen.</p>'],
            ['Was ist der Unterschied zwischen Single- und Community-Betrieb?', '<p>Im <strong>Single-Betrieb</strong> ist die Registrierung geschlossen; nur du (und Konten, die du selbst anlegst) nutzen AMIGO. Im <strong>Community-Betrieb</strong> können sich Besucher selbst registrieren (geschützt durch reCAPTCHA und Freischaltung), sich gegenseitig die Sammlungen ansehen und private Nachrichten schreiben. Die Anleitung zu beiden Varianten findest du unter <a href="index.php?r=page&amp;p=install">Installation</a>.</p>'],
            ['Wie sicher ist AMIGO?', '<p>AMIGO setzt auf Zwei-Faktor-Anmeldung, Argon2id-Passwort-Hashes, verschlüsselte Geheimnisse, CSRF-Schutz, strenge Content-Security-Policy, Rate-Limits gegen Passwort-Raten und neu berechnete Foto-Uploads ohne EXIF-Daten. Details stehen in der README auf <a href="' . $repo . '" target="_blank">GitHub</a>.</p>'],
            ['Ich habe mein Handy mit der 2FA-App verloren – was nun?', '<p>Melde dich mit einem deiner <strong>Recovery-Codes</strong> an, die du beim Einrichten erhalten hast. Hast du keine mehr, kann der Admin die Zwei-Faktor-Anmeldung für dein Konto zurücksetzen.</p>'],
            ['Wie mache ich ein Update?', '<p>Neue Version herunterladen und die Dateien auf dem Webspace überschreiben. Konfiguration und Daten bleiben erhalten, die Datenbank wird beim nächsten Aufruf automatisch angepasst.</p>'],
            ['Wie sichere ich meine Daten?', '<p>Sichere <code>config/config.php</code> und das Datenverzeichnis (bei SQLite steckt dort auch die Datenbank). Bei MySQL kommt ein Datenbank-Dump dazu. Zusätzlich kann jeder Nutzer seine Sammlung als CSV exportieren.</p>'],
        ];
    }
}
