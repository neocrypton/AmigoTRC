<?php
declare(strict_types=1);

namespace Amigo\Service;

use Amigo\Core\App;
use Amigo\Core\Database;
use Amigo\Core\Settings;

/**
 * Nachträgliche Zuordnung von Sammlungs- und Durchgespielt-Einträgen zu TheGamesDB
 * (Cover, Entwickler, Genre …). Eindeutige Treffer werden automatisch verknüpft,
 * unklare Fälle als 'review' markiert, ohne Treffer als 'none'.
 */
final class Scraper
{
    public const TARGETS = ['collection' => 'items', 'plays' => 'plays'];
    /** Unter diesem Restkontingent stoppt die Stapelverarbeitung. */
    public const MIN_ALLOWANCE = 25;

    /** WHERE-Bedingung für Einträge ohne Verknüpfung (Sammlung: nur Spiele). */
    public static function openWhere(string $target, string $alias = ''): string
    {
        $a = $alias !== '' ? $alias . '.' : '';
        return "{$a}user_id = ? AND {$a}game_id IS NULL" . ($target === 'collection' ? " AND {$a}kind = 'game'" : '');
    }

    /** Anzahl der Einträge ohne Verknüpfung (für Hinweise in den Listen). */
    public static function openCount(int $userId, string $target): int
    {
        $table = self::TARGETS[$target];
        return (int)App::db()->value("SELECT COUNT(*) FROM {{$table}} WHERE " . self::openWhere($target), [$userId]);
    }

    public static function counts(int $userId, string $target): array
    {
        $db = App::db();
        $table = self::TARGETS[$target];
        $where = self::openWhere($target);
        return [
            'open'   => (int)$db->value("SELECT COUNT(*) FROM {{$table}} WHERE $where", [$userId]),
            'todo'   => (int)$db->value("SELECT COUNT(*) FROM {{$table}} WHERE $where AND scrape_status IS NULL", [$userId]),
            'review' => (int)$db->value("SELECT COUNT(*) FROM {{$table}} WHERE $where AND scrape_status = 'review'", [$userId]),
            'none'   => (int)$db->value("SELECT COUNT(*) FROM {{$table}} WHERE $where AND scrape_status = 'none'", [$userId]),
        ];
    }

    public static function normalize(string $title): string
    {
        $t = mb_strtolower(trim($title));
        $t = strtr($t, ['ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss', 'é' => 'e', 'è' => 'e', 'á' => 'a', 'ó' => 'o', '&' => 'and']);
        $t = preg_replace('/^(the|der|die|das)\s+/', '', $t);
        return preg_replace('/[^a-z0-9]/', '', $t) ?? '';
    }

    /** TGDB-Plattform-ID zur lokalen Plattform (falls von TGDB). */
    public static function platformTgdb(?int $platformId): ?int
    {
        if (!$platformId) {
            return null;
        }
        $v = App::db()->value('SELECT tgdb_id FROM {platforms} WHERE id = ?', [$platformId]);
        return $v ? (int)$v : null;
    }

    /**
     * Suchtreffer mit lokalen Plattformnamen.
     * @return array<int, array> tgdb_id, title, platform, year, thumb
     */
    public static function candidates(string $query, ?int $platformTgdb): array
    {
        $names = array_column(App::db()->all('SELECT tgdb_id, name FROM {platforms} WHERE tgdb_id IS NOT NULL'), 'name', 'tgdb_id');
        $out = [];
        foreach (Catalog::tgdb()->searchGames($query, $platformTgdb) as $g) {
            $out[] = [
                'tgdb_id'          => $g['tgdb_id'],
                'title'            => $g['title'],
                'platform_tgdb_id' => $g['platform_tgdb_id'],
                'platform'         => $names[$g['platform_tgdb_id']] ?? ('TGDB-Plattform #' . $g['platform_tgdb_id']),
                'year'             => $g['release_date'] ? substr($g['release_date'], 0, 4) : null,
                'date'             => $g['release_date'],
                'thumb'            => $g['boxart_thumb'],
            ];
        }
        return $out;
    }

    /**
     * Sucht automatisch einen eindeutigen Treffer.
     * @return array{status: string, tgdb_id: ?int}
     */
    public static function autoMatch(array $record): array
    {
        $platformTgdb = self::platformTgdb($record['platform_id'] ? (int)$record['platform_id'] : null);
        $results = self::candidates((string)$record['title'], $platformTgdb);
        if (!$results) {
            return ['status' => 'none', 'tgdb_id' => null];
        }
        $want = self::normalize((string)$record['title']);
        $exact = array_values(array_filter($results, fn ($r) => self::normalize($r['title']) === $want));
        if (count($exact) === 1) {
            return ['status' => 'linked', 'tgdb_id' => $exact[0]['tgdb_id']];
        }
        if (count($exact) > 1) {
            // Gleicher Titel auf mehreren Plattformen (nur möglich ohne Plattform-Filter) → prüfen lassen
            return ['status' => 'review', 'tgdb_id' => null];
        }
        // Nur ein Treffer mit sehr ähnlichem Titel (z. B. Satzzeichen, Untertitel-Schreibweise)
        if (count($results) === 1) {
            similar_text($want, self::normalize($results[0]['title']), $pct);
            if ($pct >= 90) {
                return ['status' => 'linked', 'tgdb_id' => $results[0]['tgdb_id']];
            }
        }
        return ['status' => 'review', 'tgdb_id' => null];
    }

    /** Verknüpft einen Eintrag mit einem TGDB-Spiel (lädt es bei Bedarf in den Katalog). */
    public static function link(string $target, array $record, int $tgdbId, bool $adoptTitle): ?array
    {
        $game = Catalog::importGame($tgdbId);
        if (!$game) {
            return null;
        }
        $update = ['game_id' => (int)$game['id'], 'scrape_status' => null, 'updated_at' => Database::now()];
        if (empty($record['platform_id']) && $game['platform_id']) {
            $update['platform_id'] = (int)$game['platform_id'];
        }
        if ($adoptTitle) {
            $update['title'] = $game['title'];
        }
        App::db()->update(self::TARGETS[$target], $update, ['id' => $record['id'], 'user_id' => $record['user_id']]);
        return $game;
    }

    /**
     * Arbeitet einen Stapel offener Einträge ab, bis Anzahl oder Zeitbudget erreicht ist.
     * @return array{processed: int, linked: int, review: int, none: int, remaining: int, stopped: ?string}
     */
    public static function runBatch(int $userId, string $target, int $max, float $seconds): array
    {
        $db = App::db();
        $table = self::TARGETS[$target];
        $start = microtime(true);
        $stats = ['processed' => 0, 'linked' => 0, 'review' => 0, 'none' => 0, 'remaining' => 0, 'stopped' => null];
        $rows = $db->all(
            "SELECT * FROM {{$table}} WHERE " . self::openWhere($target) . ' AND scrape_status IS NULL ORDER BY id LIMIT ?',
            [$userId, $max]
        );
        foreach ($rows as $row) {
            if (microtime(true) - $start > $seconds) {
                break;
            }
            $left = self::allowance($userId);
            if ($left !== null && $left < self::MIN_ALLOWANCE) {
                $stats['stopped'] = 'Das TheGamesDB-Kontingent ist fast aufgebraucht (' . $left . ' Anfragen übrig) – Rest bitte nächsten Monat.';
                break;
            }
            try {
                $m = self::autoMatch($row);
                if ($m['status'] === 'linked' && self::link($target, $row, (int)$m['tgdb_id'], false)) {
                    $stats['linked']++;
                } else {
                    $status = $m['status'] === 'linked' ? 'review' : $m['status'];
                    $db->update($table, ['scrape_status' => $status], ['id' => $row['id'], 'user_id' => $userId]);
                    $stats[$status]++;
                }
                $stats['processed']++;
            } catch (\Throwable $e) {
                $stats['stopped'] = 'TheGamesDB ist gerade nicht erreichbar oder hat die Anfrage abgelehnt.';
                break;
            }
        }
        $stats['remaining'] = self::counts($userId, $target)['todo'];
        return $stats;
    }

    /** Restkontingent des Keys, den der Nutzer gerade verwendet (falls bekannt). */
    public static function allowance(int $userId): ?int
    {
        $user = App::db()->one('SELECT * FROM {users} WHERE id = ?', [$userId]);
        if ($user && Catalog::keySourceFor($user) === 'user') {
            return $user['tgdb_allowance'] !== null ? (int)$user['tgdb_allowance'] : null;
        }
        $v = Settings::get(App::db(), 'tgdb_allowance');
        return $v !== null ? (int)$v : null;
    }

    /** Status „nicht gefunden“ / „prüfen“ zurücksetzen, damit der Stapel es erneut versucht. */
    public static function resetStatus(int $userId, string $target): void
    {
        App::db()->run('UPDATE {' . self::TARGETS[$target] . "} SET scrape_status = NULL WHERE user_id = ? AND game_id IS NULL AND scrape_status IN ('none', 'review')", [$userId]);
    }
}
