<?php
declare(strict_types=1);

namespace Amigo\Service;

use Amigo\Core\App;
use Amigo\Core\Database;
use Amigo\Core\Options;

/**
 * CSV-Import für Sammlung und Durchgespielt-Liste.
 *
 * Ablauf: Datei hochladen → (Zeichensatz/Trennzeichen erkennen, als UTF-8 zwischenspeichern)
 * → Spalten den AMIGO-Feldern zuordnen → Plattform-Werte zuordnen → Vorschau → Import.
 * Alle Werte laufen durch die Parser unten; nichts aus der Datei wird ungeprüft übernommen.
 */
final class CsvImport
{
    public const MAX_BYTES = 5 * 1024 * 1024;
    public const MAX_ROWS = 5000;

    /** Zielfelder je Import-Art: key => [Bezeichnung, Synonyme für die automatische Zuordnung] */
    public const FIELDS = [
        'collection' => [
            'title'           => ['Titel *', ['titel', 'title', 'name', 'spiel', 'game', 'spielname', 'gamename', 'bezeichnung', 'artikel', 'item']],
            'platform'        => ['Plattform', ['plattform', 'platform', 'system', 'konsole', 'console', 'plattformname']],
            'kind'            => ['Art (Spiel/Konsole/Zubehör)', ['art', 'typ', 'type', 'kategorie', 'category', 'kind']],
            'region'          => ['Region', ['region', 'regionalcode', 'regioncode', 'version', 'land', 'country']],
            'completeness'    => ['Vollständigkeit (CIB, lose …)', ['vollstandigkeit', 'completeness', 'umfang', 'cib', 'zustandumfang', 'complete']],
            'cond_overall'    => ['Zustand gesamt', ['zustand', 'condition', 'gesamtzustand', 'overallcondition', 'grade', 'grading']],
            'cond_media'      => ['Zustand Modul/Disc/Gerät', ['zustandmedium', 'zustandmodul', 'zustanddisc', 'mediacondition', 'cartcondition', 'disccondition', 'modul', 'cart', 'disc', 'medium']],
            'cond_box'        => ['Zustand Verpackung', ['zustandverpackung', 'zustandbox', 'boxcondition', 'zustandovp']],
            'cond_manual'     => ['Zustand Anleitung', ['zustandanleitung', 'manualcondition', 'zustandhandbuch']],
            'has_box'         => ['Verpackung vorhanden (ja/nein)', ['box', 'verpackung', 'ovp', 'hasbox', 'boxed', 'karton', 'hulle', 'case']],
            'has_manual'      => ['Anleitung vorhanden (ja/nein)', ['anleitung', 'manual', 'handbuch', 'hasmanual', 'booklet']],
            'condition_notes' => ['Zustandsbeschreibung', ['zustandsnotiz', 'zustandsbeschreibung', 'conditionnotes', 'zustandnotiz']],
            'purchase_date'   => ['Kaufdatum', ['kaufdatum', 'purchasedate', 'gekauftam', 'datum', 'date', 'dateacquired', 'acquired', 'bought']],
            'price'           => ['Kaufpreis', ['preis', 'price', 'kaufpreis', 'preiseur', 'purchaseprice', 'paid', 'bezahlt', 'cost', 'kosten']],
            'purchase_place'  => ['Gekauft bei', ['gekauftbei', 'handler', 'shop', 'store', 'quelle', 'source', 'verkaufer', 'seller', 'purchasedfrom', 'where']],
            'notes'           => ['Notizen', ['notizen', 'notes', 'notiz', 'note', 'bemerkung', 'bemerkungen', 'kommentar', 'comment', 'comments', 'info']],
            'tgdb_id'         => ['TheGamesDB-ID', ['tgdbid', 'thegamesdbid', 'tgdb']],
        ],
        'plays' => [
            'title'        => ['Titel *', ['titel', 'title', 'name', 'spiel', 'game', 'spielname', 'gamename']],
            'platform'     => ['Plattform', ['plattform', 'platform', 'system', 'konsole', 'console']],
            'finished_on'  => ['Durchgespielt am', ['durchgespieltam', 'datum', 'date', 'finished', 'finishedon', 'completed', 'completedon', 'beendet', 'beendetam', 'endedatum', 'enddate']],
            'playtime'     => ['Spielzeit (Stunden, „12:30“, „12h 30m“)', ['spielzeit', 'playtime', 'zeit', 'time', 'stunden', 'hours', 'hoursplayed', 'spielzeitstunden', 'timeplayed']],
            'playtime_min' => ['Spielzeit in Minuten', ['spielzeitmin', 'spielzeitminuten', 'minuten', 'minutes', 'playtimeminutes']],
            'score'        => ['Punkte / Highscore', ['punkte', 'score', 'highscore', 'points', 'punktzahl']],
            'rating'       => ['Wertung (1–5, 1–10, ★)', ['sterne', 'wertung', 'rating', 'stars', 'note', 'bewertungsterne', 'myrating', 'score10']],
            'review'       => ['Bewertungstext', ['bewertung', 'review', 'text', 'kommentar', 'comment', 'notes', 'notizen', 'fazit']],
            'tgdb_id'      => ['TheGamesDB-ID', ['tgdbid', 'thegamesdbid', 'tgdb']],
        ],
    ];

    /** Häufige Kürzel → mögliche Plattform-Namen (normalisiert). */
    private const PLATFORM_SYNONYMS = [
        'nes' => ['nintendoentertainmentsystemnes', 'nintendoentertainmentsystem', 'nes'],
        'famicom' => ['famicom', 'nintendoentertainmentsystemnes'],
        'snes' => ['supernintendosnes', 'supernintendo', 'snes'],
        'supernes' => ['supernintendosnes', 'supernintendo'],
        'superfamicom' => ['superfamicom', 'supernintendosnes'],
        'n64' => ['nintendo64'], 'gb' => ['nintendogameboy', 'gameboy'], 'gameboy' => ['nintendogameboy'],
        'gbc' => ['nintendogameboycolor', 'gameboycolor'], 'gameboycolor' => ['nintendogameboycolor'],
        'gba' => ['nintendogameboyadvance', 'gameboyadvance'], 'gameboyadvance' => ['nintendogameboyadvance'],
        'nds' => ['nintendods'], 'ds' => ['nintendods'], '3ds' => ['nintendo3ds'],
        'gc' => ['nintendogamecube', 'gamecube'], 'ngc' => ['nintendogamecube'], 'gamecube' => ['nintendogamecube'],
        'wii' => ['nintendowii'], 'wiiu' => ['nintendowiiu'], 'switch' => ['nintendoswitch'], 'ns' => ['nintendoswitch'],
        'virtualboy' => ['nintendovirtualboy'],
        'ps1' => ['sonyplaystation'], 'psx' => ['sonyplaystation'], 'psone' => ['sonyplaystation'], 'playstation' => ['sonyplaystation'],
        'ps' => ['sonyplaystation'], 'ps2' => ['sonyplaystation2'], 'ps3' => ['sonyplaystation3'], 'ps4' => ['sonyplaystation4'], 'ps5' => ['sonyplaystation5'],
        'psp' => ['sonyplaystationportable', 'sonypsp'], 'vita' => ['sonyplaystationvita'], 'psvita' => ['sonyplaystationvita'],
        'megadrive' => ['segamegadrive', 'segagenesis'], 'genesis' => ['segagenesis', 'segamegadrive'], 'md' => ['segamegadrive', 'segagenesis'],
        'mastersystem' => ['segamastersystem'], 'sms' => ['segamastersystem'], 'gamegear' => ['segagamegear'], 'gg' => ['segagamegear'],
        'saturn' => ['segasaturn'], 'dreamcast' => ['segadreamcast'], 'dc' => ['segadreamcast'], 'megacd' => ['segacd', 'segamegacd'],
        'segacd' => ['segacd'], '32x' => ['sega32x'],
        'xbox' => ['microsoftxbox'], 'xbox360' => ['microsoftxbox360'], '360' => ['microsoftxbox360'], 'xboxone' => ['microsoftxboxone'],
        'pcengine' => ['necpcengine', 'necturbografx16', 'turbografx16'], 'turbografx' => ['necturbografx16', 'turbografx16'], 'tg16' => ['necturbografx16'],
        'neogeo' => ['neogeo', 'snkneogeoaes', 'neogeoaes'], 'ngpc' => ['neogeopocketcolor'],
        'atari2600' => ['atari2600'], '2600' => ['atari2600'], 'vcs' => ['atari2600'], 'jaguar' => ['atarijaguar'], 'lynx' => ['atarilynx'],
        'c64' => ['commodore64'], 'amiga' => ['amiga', 'commodoreamiga'], 'cpc' => ['amstradcpc'], 'zxspectrum' => ['sinclairzxspectrum'],
        'pc' => ['pc'], 'windows' => ['pc'], 'dos' => ['pc', 'dos'],
    ];

    public static function dir(): string
    {
        return App::dataDir() . '/imports';
    }

    /**
     * Prüft die hochgeladene Datei, wandelt sie in UTF-8 und legt sie ab.
     * @return array{file: string, delimiter: string, encoding: string}
     */
    public static function store(array $upload): array
    {
        if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)$upload['tmp_name'])) {
            throw new \RuntimeException('Bitte eine CSV-Datei auswählen.');
        }
        if (filesize($upload['tmp_name']) > self::MAX_BYTES) {
            throw new \RuntimeException('Die Datei ist zu groß (max. 5 MB).');
        }
        $raw = (string)file_get_contents($upload['tmp_name']);
        if ($raw === '' || str_contains(substr($raw, 0, 4096), "\0")) {
            throw new \RuntimeException('Das sieht nicht nach einer CSV-Textdatei aus.');
        }
        $encoding = 'UTF-8';
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        } elseif (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
            $encoding = 'UTF-16';
        } elseif (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
            $encoding = 'Windows-1252';
        }
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        $dir = self::dir();
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('Import-Verzeichnis kann nicht angelegt werden.');
        }
        self::cleanup();
        $name = bin2hex(random_bytes(16));
        file_put_contents($dir . '/' . $name . '.csv', $raw, LOCK_EX);
        @chmod($dir . '/' . $name . '.csv', 0640);
        return ['file' => $name, 'delimiter' => self::detectDelimiter($raw), 'encoding' => $encoding];
    }

    public static function path(string $name): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $name)) {
            return null;
        }
        $p = self::dir() . '/' . $name . '.csv';
        return is_file($p) ? $p : null;
    }

    public static function remove(string $name): void
    {
        if ($p = self::path($name)) {
            @unlink($p);
        }
    }

    /** Liegengebliebene Zwischendateien nach einem Tag löschen. */
    private static function cleanup(): void
    {
        foreach (glob(self::dir() . '/*.csv') ?: [] as $f) {
            if (time() - (int)filemtime($f) > 86400) {
                @unlink($f);
            }
        }
    }

    private static function detectDelimiter(string $raw): string
    {
        // Die ersten Datensätze echt parsen (Anführungszeichen mit Zeilenumbrüchen korrekt behandeln).
        $sample = substr($raw, 0, 64 * 1024);
        $best = ';';
        $bestScore = -1;
        foreach ([';', ',', "\t", '|'] as $d) {
            $fh = fopen('php://memory', 'r+');
            fwrite($fh, $sample);
            rewind($fh);
            $counts = [];
            while (count($counts) < 6 && ($row = fgetcsv($fh, 0, $d, '"', '')) !== false) {
                if ($row !== [null]) {
                    $counts[] = count($row);
                }
            }
            fclose($fh);
            if (count($counts) > 1 && strlen($sample) === 64 * 1024) {
                array_pop($counts); // letzter Datensatz evtl. abgeschnitten
            }
            if (!$counts || $counts[0] < 2) {
                continue;
            }
            // Viele Spalten, die in allen Zeilen gleich bleiben, gewinnen.
            $score = $counts[0] * 10 - (max($counts) - min($counts)) * 5;
            if ($score > $bestScore) {
                $best = $d;
                $bestScore = $score;
            }
        }
        return $best;
    }

    /**
     * Liest die Datei. Gibt [Kopfzeile, Datenzeilen] zurück; ohne Kopfzeile heißen die Spalten „Spalte 1…n“.
     * @return array{0: string[], 1: array<int, string[]>}
     */
    public static function read(string $name, string $delimiter, bool $hasHeader, int $limit = self::MAX_ROWS + 1): array
    {
        $path = self::path($name);
        if (!$path) {
            throw new \RuntimeException('Die Import-Datei ist abgelaufen. Bitte erneut hochladen.');
        }
        $fh = fopen($path, 'r');
        $rows = [];
        while (($row = fgetcsv($fh, 0, $delimiter, '"', '')) !== false) {
            if ($row === [null] || implode('', array_map('trim', array_map('strval', $row))) === '') {
                continue; // Leerzeilen
            }
            // Schutz-Apostroph aus dem AMIGO-Export vor „=“, „+“, „-“, „@“ wieder entfernen.
            $rows[] = array_map(static fn ($v) => preg_replace("/^'(?=[=+\\-@])/", '', trim((string)$v)), $row);
            if (count($rows) > $limit) {
                break;
            }
        }
        fclose($fh);
        $width = $rows ? max(array_map('count', $rows)) : 0;
        $header = $hasHeader && $rows ? array_shift($rows) : [];
        $cols = [];
        for ($i = 0; $i < $width; $i++) {
            $label = trim((string)($header[$i] ?? ''));
            $cols[] = $label !== '' ? mb_substr($label, 0, 60) : 'Spalte ' . ($i + 1);
        }
        return [$cols, $rows];
    }

    /** Automatische Zuordnung Spalte → Feld anhand der Spaltennamen. */
    public static function suggest(string $target, array $columns): array
    {
        $map = [];
        $used = [];
        // Exakte Treffer zuerst, damit z. B. „Zustand Verpackung“ nicht als „Zustand“ endet.
        foreach ([true, false] as $exact) {
            foreach ($columns as $i => $col) {
                if (isset($map[$i])) {
                    continue;
                }
                $n = self::norm($col);
                foreach (self::FIELDS[$target] as $field => [$label, $syn]) {
                    if (isset($used[$field])) {
                        continue;
                    }
                    $hit = $exact
                        ? in_array($n, $syn, true) || $n === self::norm($label)
                        : $n !== '' && array_filter($syn, fn ($s) => strlen($s) >= 4 && str_contains($n, $s));
                    if ($hit) {
                        $map[$i] = $field;
                        $used[$field] = true;
                        break;
                    }
                }
            }
        }
        // Unser eigener Export: „Spielzeit (Min)“ → Minuten
        foreach ($columns as $i => $col) {
            if ($target === 'plays' && self::norm($col) === 'spielzeitmin') {
                $map[$i] = 'playtime_min';
            }
        }
        return $map;
    }

    public static function norm(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'é' => 'e', 'è' => 'e']);
        return preg_replace('/[^a-z0-9]/', '', $s) ?? '';
    }

    // ------------------------------------------------------------------ Plattformen

    /** Normalisierte Schlüssel → Plattform-ID für alle vorhandenen Plattformen. */
    public static function platformIndex(): array
    {
        $index = [];
        $strip = '/^(nintendo|sony|sega|microsoft|atari|nec|snk|commodore|sinclair|amstrad)/';
        foreach (App::db()->all('SELECT id, name, alias FROM {platforms}') as $p) {
            $keys = [self::norm($p['name']), self::norm((string)$p['alias'])];
            if (preg_match('/\(([^)]+)\)/', $p['name'], $m)) {
                $keys[] = self::norm($m[1]);
                $keys[] = self::norm(preg_replace('/\s*\([^)]*\)/', '', $p['name']));
            }
            foreach ($keys as $k) {
                if ($k !== '') {
                    $index[$k] ??= (int)$p['id'];
                    $short = preg_replace($strip, '', $k);
                    if ($short !== '' && $short !== $k) {
                        $index[$short] ??= (int)$p['id'];
                    }
                }
            }
        }
        return $index;
    }

    public static function matchPlatform(string $value, array $index): ?int
    {
        $n = self::norm($value);
        if ($n === '') {
            return null;
        }
        if (isset($index[$n])) {
            return $index[$n];
        }
        foreach (self::PLATFORM_SYNONYMS[$n] ?? [] as $candidate) {
            if (isset($index[$candidate])) {
                return $index[$candidate];
            }
        }
        return null;
    }

    /** Plattform-Werte der Datei, die nicht automatisch erkannt werden: [Wert => Anzahl] */
    public static function unknownPlatforms(array $rows, ?int $col, array $index): array
    {
        if ($col === null) {
            return [];
        }
        $unknown = [];
        foreach ($rows as $row) {
            $v = trim((string)($row[$col] ?? ''));
            if ($v !== '' && self::matchPlatform($v, $index) === null) {
                $key = mb_substr($v, 0, 100);
                $unknown[$key] = ($unknown[$key] ?? 0) + 1;
            }
        }
        arsort($unknown);
        return $unknown;
    }

    // ------------------------------------------------------------------ Zeilen umwandeln

    /**
     * Baut aus einer CSV-Zeile einen Datensatz.
     * @return array{data: array, warnings: string[], error: ?string}
     */
    public static function buildRecord(string $target, array $row, array $mapping, array $platformIndex, array $platformMap): array
    {
        $get = static function (string $field) use ($row, $mapping): ?string {
            $col = array_search($field, $mapping, true);
            if ($col === false) {
                return null;
            }
            $v = trim((string)($row[$col] ?? ''));
            return $v === '' ? null : preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
        };
        $warnings = [];
        $warn = static function (string $field, string $value, string $msg) use (&$warnings) {
            $warnings[] = $msg . ' („' . mb_strimwidth($value, 0, 30, '…') . '“)';
        };

        $title = $get('title');
        if ($title === null) {
            return ['data' => [], 'warnings' => [], 'error' => 'kein Titel'];
        }
        $data = ['title' => mb_substr($title, 0, 255)];
        $notes = [];

        // Plattform
        $platformId = null;
        if (($pv = $get('platform')) !== null) {
            $platformId = self::matchPlatform($pv, $platformIndex);
            if ($platformId === null) {
                $choice = $platformMap[mb_substr($pv, 0, 100)] ?? 'note';
                if (is_int($choice) || ctype_digit((string)$choice)) {
                    $platformId = (int)$choice;
                } else {
                    $notes[] = 'Plattform (Import): ' . $pv;
                }
            }
        }
        $data['platform_id'] = $platformId;

        // Spiel im lokalen Katalog verknüpfen (TGDB-ID oder Titel + Plattform)
        $data['game_id'] = self::findGame($get('tgdb_id'), $title, $platformId);

        if ($target === 'collection') {
            $kind = $get('kind');
            $data['kind'] = $kind !== null ? (self::parseKind($kind) ?? 'game') : 'game';
            if ($kind !== null && self::parseKind($kind) === null) {
                $warn('kind', $kind, 'Art unbekannt, als Spiel übernommen');
            }
            foreach (['region' => [self::class, 'parseRegion'], 'completeness' => [self::class, 'parseCompleteness']] as $f => $parser) {
                $v = $get($f);
                $data[$f] = $v === null ? null : $parser($v);
                if ($v !== null && $data[$f] === null) {
                    $warn($f, $v, ($f === 'region' ? 'Region' : 'Vollständigkeit') . ' nicht erkannt');
                }
            }
            foreach (['cond_overall', 'cond_media', 'cond_box', 'cond_manual'] as $f) {
                $v = $get($f);
                $data[$f] = $v === null ? null : self::parseCondition($v);
                if ($v !== null && $data[$f] === null && !self::isNo($v)) {
                    $warn($f, $v, 'Zustand nicht erkannt');
                }
            }
            // Verpackung/Anleitung als ja/nein → Vollständigkeit ableiten, falls nicht angegeben
            $hasBox = ($v = $get('has_box')) !== null ? self::parseBool($v) : null;
            $hasManual = ($v = $get('has_manual')) !== null ? self::parseBool($v) : null;
            if ($data['completeness'] === null && ($hasBox !== null || $hasManual !== null)) {
                $data['completeness'] = match (true) {
                    $hasBox && $hasManual => 'cib',
                    (bool)$hasBox         => 'boxed',
                    (bool)$hasManual      => 'manual',
                    default               => 'loose',
                };
            }
            if ($hasBox === false) {
                $data['cond_box'] = null;
            }
            if ($hasManual === false) {
                $data['cond_manual'] = null;
            }
            $data['condition_notes'] = ($v = $get('condition_notes')) !== null ? mb_substr($v, 0, 5000) : null;
            if (($v = $get('purchase_date')) !== null) {
                $data['purchase_date'] = self::parseDate($v);
                if ($data['purchase_date'] === null) {
                    $warn('purchase_date', $v, 'Kaufdatum nicht erkannt');
                }
            } else {
                $data['purchase_date'] = null;
            }
            if (($v = $get('price')) !== null) {
                $data['price_cents'] = self::parsePrice($v);
                if ($data['price_cents'] === null) {
                    $warn('price', $v, 'Preis nicht erkannt');
                }
            } else {
                $data['price_cents'] = null;
            }
            $data['purchase_place'] = ($v = $get('purchase_place')) !== null ? mb_substr($v, 0, 150) : null;
            $userNotes = $get('notes');
            $data['notes'] = trim(implode("\n", array_filter([$userNotes, ...$notes]))) ?: null;
            if ($data['notes'] !== null) {
                $data['notes'] = mb_substr($data['notes'], 0, 10000);
            }
        } else {
            if (($v = $get('finished_on')) !== null) {
                $data['finished_on'] = self::parseDate($v);
                if ($data['finished_on'] === null) {
                    $warn('finished_on', $v, 'Datum nicht erkannt');
                }
            } else {
                $data['finished_on'] = null;
            }
            $minutes = null;
            if (($v = $get('playtime_min')) !== null) {
                $minutes = preg_match('/^\d{1,7}$/', str_replace(['.', ' '], '', $v)) ? (int)str_replace(['.', ' '], '', $v) : null;
                if ($minutes === null) {
                    $warn('playtime_min', $v, 'Minuten nicht erkannt');
                }
            } elseif (($v = $get('playtime')) !== null) {
                $minutes = self::parsePlaytime($v);
                if ($minutes === null) {
                    $warn('playtime', $v, 'Spielzeit nicht erkannt');
                }
            }
            $data['playtime_minutes'] = $minutes;
            if (($v = $get('score')) !== null) {
                $digits = preg_replace('/[\s.,\']/', '', $v);
                $data['score'] = preg_match('/^\d{1,15}$/', $digits) ? (int)$digits : null;
                if ($data['score'] === null) {
                    $warn('score', $v, 'Punkte nicht erkannt');
                }
            } else {
                $data['score'] = null;
            }
            if (($v = $get('rating')) !== null) {
                $data['rating'] = self::parseRating($v);
                if ($data['rating'] === null) {
                    $warn('rating', $v, 'Wertung nicht erkannt');
                }
            } else {
                $data['rating'] = null;
            }
            $review = trim(implode("\n", array_filter([$get('review'), ...$notes])));
            $data['review'] = $review !== '' ? mb_substr($review, 0, 10000) : null;
        }
        return ['data' => $data, 'warnings' => $warnings, 'error' => null];
    }

    private static function findGame(?string $tgdbId, string $title, ?int $platformId): ?int
    {
        $db = App::db();
        if ($tgdbId !== null && ctype_digit($tgdbId)) {
            $id = $db->value('SELECT id FROM {games} WHERE tgdb_id = ?', [(int)$tgdbId]);
            if ($id) {
                return (int)$id;
            }
        }
        if ($platformId !== null) {
            $id = $db->value('SELECT id FROM {games} WHERE LOWER(title) = LOWER(?) AND platform_id = ?', [$title, $platformId]);
            return $id ? (int)$id : null;
        }
        return null;
    }

    // ------------------------------------------------------------------ Parser

    public static function parseCondition(string $v): ?int
    {
        $n = self::norm($v);
        if (preg_match('/^[0-5]$/', $n)) {
            return (int)$n;
        }
        foreach (Options::CONDITIONS as $k => $label) {
            if ($n === self::norm($label)) {
                return $k;
            }
        }
        $map = [
            5 => ['neu', 'new', 'mint', 'neuwertig', 'wieneu', 'likenew', 'sealed', 'versiegelt', 'nm', 'perfekt', 'perfect', 'a'],
            4 => ['sehrgut', 'verygood', 'nearmint', 'vg', 'excellent', 'exzellent', 'b'],
            3 => ['gut', 'good', 'ok', 'okay', 'c'],
            2 => ['gebraucht', 'used', 'fair', 'akzeptabel', 'acceptable', 'befriedigend', 'mittel', 'd'],
            1 => ['mangelhaft', 'poor', 'schlecht', 'bad', 'beschadigt', 'damaged', 'e'],
            0 => ['defekt', 'broken', 'kaputt', 'fürbastler', 'furbastler', 'forparts', 'f'],
        ];
        foreach ($map as $k => $words) {
            if (in_array($n, $words, true)) {
                return $k;
            }
        }
        // Skala 1–10
        if (preg_match('/^(\d{1,2})(10)?$/', $n, $m) && (int)$m[1] <= 10 && isset($m[2])) {
            return (int)round((int)$m[1] / 2);
        }
        return null;
    }

    public static function parseRegion(string $v): ?string
    {
        $n = self::norm($v);
        return match (true) {
            in_array($n, ['paldeu', 'palde', 'de', 'deu', 'deutsch', 'deutschland', 'germany', 'ger', 'usk'], true) => 'PAL-DE',
            in_array($n, ['pal', 'eu', 'eur', 'europe', 'europa', 'paleu', 'uk', 'pal-uk', 'paluk', 'fr', 'ita', 'esp', 'aus'], true) => 'PAL',
            in_array($n, ['ntscu', 'ntsc', 'us', 'usa', 'na', 'ntscus', 'northamerica', 'amerika', 'america', 'ntscusa', 'can'], true) => 'NTSC-U',
            in_array($n, ['ntscj', 'jp', 'jap', 'jpn', 'japan', 'ntscjp', 'ntscjap'], true) => 'NTSC-J',
            in_array($n, ['ntsck', 'kr', 'kor', 'korea'], true) => 'NTSC-K',
            in_array($n, ['free', 'regionfree', 'regionfrei', 'all', 'world', 'worldwide'], true) => 'FREE',
            in_array($n, ['other', 'andere', 'sonstige'], true) => 'OTHER',
            default => null,
        };
    }

    public static function parseCompleteness(string $v): ?string
    {
        $n = self::norm($v);
        foreach (Options::COMPLETENESS as $k => $label) {
            if ($n === self::norm($label) || $n === $k) {
                return $k;
            }
        }
        return match (true) {
            in_array($n, ['sealed', 'versiegelt', 'neu', 'new', 'nib', 'neuovp', 'ovpversiegelt'], true) => 'sealed',
            in_array($n, ['cib', 'komplett', 'complete', 'completeinbox', 'vollstandig', 'ovp', 'boxmanual', 'mitovpundanleitung'], true) => 'cib',
            in_array($n, ['boxed', 'box', 'boxonly', 'nurovp', 'mitovp', 'ohneanleitung', 'gameandbox'], true) => 'boxed',
            in_array($n, ['manual', 'mitanleitung', 'ohneovp', 'gameandmanual'], true) => 'manual',
            in_array($n, ['loose', 'lose', 'modul', 'nurmodul', 'cartonly', 'disconly', 'nurdisc', 'nurspiel'], true) => 'loose',
            default => null,
        };
    }

    public static function parseKind(string $v): ?string
    {
        $n = self::norm($v);
        return match (true) {
            in_array($n, ['spiel', 'game', 'games', 'spiele', 'software', 'sw', 'videospiel', 'videogame'], true) => 'game',
            in_array($n, ['konsole', 'console', 'konsolen', 'hardware', 'hw', 'computer', 'system', 'konsolecomputer', 'handheld'], true) => 'console',
            in_array($n, ['zubehor', 'accessory', 'accessories', 'controller', 'zubehorcontroller', 'peripherie', 'peripheral'], true) => 'accessory',
            in_array($n, ['sonstiges', 'other', 'misc', 'andere'], true) => 'other',
            default => null,
        };
    }

    public static function parseBool(string $v): ?bool
    {
        $n = self::norm($v);
        if (in_array($n, ['ja', 'yes', 'y', 'j', 'x', '1', 'true', 'wahr', 'vorhanden', 'mit', 'with'], true) || in_array(trim($v), ['✓', '✔', '☑'], true)) {
            return true;
        }
        return self::isNo($v) ? false : null;
    }

    private static function isNo(string $v): bool
    {
        return in_array(self::norm($v), ['nein', 'no', 'n', '0', 'false', 'falsch', 'ohne', 'without', 'fehlt', 'missing', 'keine', 'kein'], true)
            || in_array(trim($v), ['-', '–', '✗', '✘'], true);
    }

    public static function parseDate(string $v): ?string
    {
        $v = trim($v);
        // ISO mit Uhrzeit abschneiden
        if (preg_match('/^(\d{4}-\d{2}-\d{2})[ T]/', $v, $m)) {
            $v = $m[1];
        }
        foreach (['!Y-m-d', '!d.m.Y', '!j.n.Y', '!d.m.y', '!j.n.y', '!Y/m/d', '!d/m/Y', '!j/n/Y', '!d-m-Y'] as $fmt) {
            $d = \DateTimeImmutable::createFromFormat($fmt, $v);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($d && (!$errors || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                $y = (int)$d->format('Y');
                if ($y >= 1950 && $y <= 2100) {
                    return $d->format('Y-m-d');
                }
            }
        }
        // Nur Jahr
        if (preg_match('/^(19[5-9]\d|20\d\d)$/', $v)) {
            return $v . '-01-01';
        }
        return null;
    }

    public static function parsePrice(string $v): ?int
    {
        $v = preg_replace('/[^\d,.\-]/', '', $v);
        if ($v === '' || str_starts_with($v, '-')) {
            return null;
        }
        $lastComma = strrpos($v, ',');
        $lastDot = strrpos($v, '.');
        if ($lastComma !== false && $lastDot !== false) {
            // Das zuletzt stehende Zeichen ist das Dezimaltrennzeichen.
            $v = $lastComma > $lastDot ? str_replace(['.', ','], ['', '.'], $v) : str_replace(',', '', $v);
        } elseif ($lastComma !== false) {
            $v = (strlen($v) - $lastComma - 1 === 3 && substr_count($v, ',') >= 1 && !preg_match('/,\d{1,2}$/', $v))
                ? str_replace(',', '', $v) : str_replace(',', '.', $v);
        } elseif ($lastDot !== false && (substr_count($v, '.') > 1 || preg_match('/^\d{1,3}\.\d{3}$/', $v))) {
            $v = str_replace('.', '', $v); // Tausenderpunkte („1.234“)
        }
        if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $v)) {
            return null;
        }
        return (int)round((float)$v * 100);
    }

    public static function parsePlaytime(string $v): ?int
    {
        $s = mb_strtolower(trim($v));
        if (preg_match('/^(\d{1,6}):([0-5]\d)(:[0-5]\d)?$/', $s, $m)) {
            return (int)$m[1] * 60 + (int)$m[2];
        }
        if (preg_match('/^(?:(\d{1,6})\s*(?:h|std|stunden?|hours?|hrs?)\.?)?\s*(?:(\d{1,4})\s*(?:m|min|minuten?|minutes?|mins?)\.?)?$/u', $s, $m)
            && (($m[1] ?? '') !== '' || ($m[2] ?? '') !== '')) {
            return (int)($m[1] ?? 0) * 60 + (int)($m[2] ?? 0);
        }
        $num = str_replace(',', '.', preg_replace('/\s*(h|std|stunden?|hours?|hrs?)\.?$/u', '', $s));
        if (preg_match('/^\d{1,6}(\.\d+)?$/', $num)) {
            return (int)round((float)$num * 60);
        }
        return null;
    }

    public static function parseRating(string $v): ?int
    {
        $s = trim($v);
        $stars = mb_substr_count($s, '★') + mb_substr_count($s, '⭐');
        if ($stars > 0) {
            return min(5, $stars);
        }
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*\/\s*(\d+)$/', $s, $m)) {
            $max = (int)$m[2];
            $val = (float)str_replace(',', '.', $m[1]);
            return ($max > 0 && $val <= $max) ? max(1, min(5, (int)round($val / $max * 5))) : null;
        }
        $s = preg_replace('/\s*(sterne?|stars?|punkte|points)$/iu', '', $s);
        if (preg_match('/^\d+(?:[.,]\d+)?$/', $s)) {
            $val = (float)str_replace(',', '.', $s);
            if ($val >= 1 && $val <= 5) {
                return (int)round($val);
            }
            if ($val > 5 && $val <= 10) {
                return max(1, (int)round($val / 2));
            }
            if ($val > 10 && $val <= 100) {
                return max(1, (int)round($val / 20));
            }
        }
        return null;
    }

    // ------------------------------------------------------------------ Import

    /**
     * Führt den Import aus.
     * @return array{imported: int, skipped: int, failed: array<int, string>}
     */
    public static function run(int $userId, string $target, array $rows, array $mapping, array $platformMap, bool $skipDuplicates): array
    {
        $db = App::db();
        $table = $target === 'collection' ? 'items' : 'plays';
        $index = self::platformIndex();
        $seen = [];
        if ($skipDuplicates) {
            foreach ($db->all("SELECT title, platform_id FROM {{$table}} WHERE user_id = ?", [$userId]) as $r) {
                $seen[self::dupKey($r['title'], $r['platform_id'] !== null ? (int)$r['platform_id'] : null)] = true;
            }
        }
        $result = ['imported' => 0, 'skipped' => 0, 'failed' => []];
        $db->transaction(function (Database $db) use ($rows, $target, $mapping, $index, $platformMap, $skipDuplicates, $userId, $table, &$seen, &$result) {
            $now = Database::now();
            foreach ($rows as $i => $row) {
                $rec = self::buildRecord($target, $row, $mapping, $index, $platformMap);
                if ($rec['error']) {
                    $result['failed'][$i] = $rec['error'];
                    continue;
                }
                $key = self::dupKey($rec['data']['title'], $rec['data']['platform_id']);
                if ($skipDuplicates && isset($seen[$key])) {
                    $result['skipped']++;
                    continue;
                }
                $seen[$key] = true;
                $db->insert($table, $rec['data'] + ['user_id' => $userId, 'created_at' => $now, 'updated_at' => $now]);
                $result['imported']++;
            }
        });
        return $result;
    }

    private static function dupKey(string $title, ?int $platformId): string
    {
        return mb_strtolower(trim($title)) . '|' . ($platformId ?? 0);
    }
}
