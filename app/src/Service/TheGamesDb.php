<?php
declare(strict_types=1);

namespace Amigo\Service;

use Amigo\Core\Database;
use Amigo\Core\Settings;

/**
 * Client für die TheGamesDB-API (https://api.thegamesdb.net).
 *
 * Der API-Key bleibt serverseitig. Antworten werden in {api_cache} zwischengespeichert,
 * da das monatliche Kontingent pro Key begrenzt ist.
 */
final class TheGamesDb
{
    private const BASE = 'https://api.thegamesdb.net/v1/';

    public function __construct(private readonly string $apiKey, private readonly Database $db)
    {
    }

    public function enabled(): bool
    {
        return $this->apiKey !== '';
    }

    /** @return array<int, array> normalisierte Treffer */
    public function searchGames(string $name, ?int $platformId = null, int $page = 1): array
    {
        $query = [
            'name'    => $name,
            'fields'  => 'players,publishers,genres,overview',
            'include' => 'boxart',
            'page'    => max(1, $page),
        ];
        if ($platformId) {
            $query['filter[platform]'] = $platformId;
        }
        $res = $this->request('Games/ByGameName', $query, 86400);
        $games = $res['data']['games'] ?? [];
        $boxart = $res['include']['boxart'] ?? [];
        return array_map(fn (array $g) => $this->normalizeGame($g, $boxart), array_values($games));
    }

    public function game(int $id): ?array
    {
        $res = $this->request('Games/ByGameID', [
            'id'      => $id,
            'fields'  => 'players,publishers,genres,overview',
            'include' => 'boxart,platform',
        ], 7 * 86400);
        $games = array_values($res['data']['games'] ?? []);
        if (!$games) {
            return null;
        }
        $game = $this->normalizeGame($games[0], $res['include']['boxart'] ?? []);
        $game['developers'] = $this->names('Developers', 'developers', $games[0]['developers'] ?? []);
        $game['publishers'] = $this->names('Publishers', 'publishers', $games[0]['publishers'] ?? []);
        $game['genres'] = $this->names('Genres', 'genres', $games[0]['genres'] ?? []);
        $platforms = $res['include']['platform'] ?? [];
        $p = $platforms[$game['platform_tgdb_id']] ?? $platforms[(string)$game['platform_tgdb_id']] ?? null;
        $game['platform'] = $p ? $this->normalizePlatform($p) : null;
        return $game;
    }

    /** @return array<int, array> alle Plattformen, sortiert nach Name */
    public function platforms(): array
    {
        $res = $this->request('Platforms', [
            'fields' => 'icon,console,controller,developer,manufacturer,media,cpu,memory,overview',
        ], 7 * 86400);
        $list = array_map([$this, 'normalizePlatform'], array_values($res['data']['platforms'] ?? []));
        usort($list, fn ($a, $b) => strnatcasecmp($a['name'], $b['name']));
        return $list;
    }

    public function platform(int $id): ?array
    {
        foreach ($this->platforms() as $p) {
            if ($p['tgdb_id'] === $id) {
                return $p;
            }
        }
        $res = $this->request('Platforms/ByPlatformID', [
            'id'     => $id,
            'fields' => 'icon,console,controller,developer,manufacturer,media,cpu,memory,overview',
        ], 7 * 86400);
        $list = array_values($res['data']['platforms'] ?? []);
        return $list ? $this->normalizePlatform($list[0]) : null;
    }

    private function normalizeGame(array $g, array $boxart): array
    {
        $id = (int)($g['id'] ?? 0);
        $thumb = $large = null;
        $images = $boxart['data'][$id] ?? $boxart['data'][(string)$id] ?? [];
        foreach ($images as $img) {
            if (($img['side'] ?? '') === 'front' && !empty($img['filename'])) {
                $thumb = $this->imageUrl($boxart['base_url']['thumb'] ?? '', $img['filename']);
                $large = $this->imageUrl($boxart['base_url']['medium'] ?? '', $img['filename']);
                break;
            }
        }
        $date = (string)($g['release_date'] ?? '');
        return [
            'tgdb_id'          => $id,
            'title'            => (string)($g['game_title'] ?? ''),
            'platform_tgdb_id' => (int)($g['platform'] ?? 0),
            'release_date'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null,
            'players'          => isset($g['players']) ? (int)$g['players'] : null,
            'overview'         => isset($g['overview']) ? (string)$g['overview'] : null,
            'boxart_thumb'     => $thumb,
            'boxart_large'     => $large,
        ];
    }

    private function normalizePlatform(array $p): array
    {
        $s = static fn ($v) => ($v === null || $v === '') ? null : mb_substr(trim((string)$v), 0, 255);
        return [
            'tgdb_id'      => (int)($p['id'] ?? 0),
            'name'         => (string)($p['name'] ?? ''),
            'alias'        => $s($p['alias'] ?? null),
            'manufacturer' => $s($p['manufacturer'] ?? null),
            'developer'    => $s($p['developer'] ?? null),
            'media'        => $s($p['media'] ?? null),
            'cpu'          => $s($p['cpu'] ?? null),
            'memory'       => $s($p['memory'] ?? null),
            'overview'     => isset($p['overview']) ? (string)$p['overview'] : null,
            'icon'         => $s($p['icon'] ?? null),
        ];
    }

    private function imageUrl(string $base, string $file): ?string
    {
        $url = $base . ltrim($file, '/');
        return str_starts_with($url, 'https://cdn.thegamesdb.net/') ? $url : null;
    }

    /** Löst ID-Listen (Entwickler, Publisher, Genres) in Namen auf. */
    private function names(string $endpoint, string $key, array $ids): ?string
    {
        if (!$ids) {
            return null;
        }
        try {
            $all = $this->request($endpoint, [], 30 * 86400)['data'][$key] ?? [];
        } catch (\Throwable) {
            return null;
        }
        $names = [];
        foreach ($ids as $id) {
            $entry = $all[$id] ?? $all[(string)$id] ?? null;
            if ($entry && !empty($entry['name'])) {
                $names[] = $entry['name'];
            }
        }
        return $names ? mb_substr(implode(', ', $names), 0, 255) : null;
    }

    private function request(string $path, array $query, int $ttl): array
    {
        if (!$this->enabled()) {
            throw new \RuntimeException('Kein TheGamesDB-API-Key konfiguriert.');
        }
        $url = self::BASE . $path . '?' . http_build_query(['apikey' => $this->apiKey] + $query);
        $cacheKey = hash('sha256', $path . '?' . http_build_query($query));

        $cached = $this->db->one('SELECT payload, expires_at FROM {api_cache} WHERE ckey = ?', [$cacheKey]);
        if ($cached && (int)$cached['expires_at'] > time()) {
            return json_decode($cached['payload'], true) ?: [];
        }

        [$status, $body] = $this->httpGet($url);
        $data = json_decode((string)$body, true);
        if ($status !== 200 || !is_array($data)) {
            $msg = is_array($data) && isset($data['status']) ? (string)$data['status'] : 'HTTP ' . $status;
            throw new \RuntimeException('TheGamesDB-Anfrage fehlgeschlagen: ' . mb_substr($msg, 0, 200));
        }

        if (isset($data['remaining_monthly_allowance'])) {
            Settings::set($this->db, 'tgdb_allowance', (string)((int)$data['remaining_monthly_allowance'] + (int)($data['extra_allowance'] ?? 0)));
        }

        $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
        $this->db->transaction(function (Database $db) use ($cacheKey, $payload, $ttl) {
            $db->delete('api_cache', ['ckey' => $cacheKey]);
            $db->insert('api_cache', ['ckey' => $cacheKey, 'payload' => $payload, 'expires_at' => time() + $ttl]);
        });
        if (random_int(1, 50) === 1) {
            $this->db->run('DELETE FROM {api_cache} WHERE expires_at < ?', [time()]);
        }
        return $data;
    }

    /** @return array{0:int,1:string} */
    private function httpGet(string $url): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
                CURLOPT_ENCODING       => '',
                CURLOPT_USERAGENT      => 'AMIGO-RetroCollector/' . AMIGO_VERSION,
                CURLOPT_HTTPHEADER     => ['Accept: application/json'],
            ]);
            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($body === false) {
                throw new \RuntimeException('TheGamesDB nicht erreichbar: ' . $error);
            }
            return [$status, (string)$body];
        }

        $ctx = stream_context_create([
            'http' => ['timeout' => 15, 'ignore_errors' => true, 'follow_location' => 0,
                'header' => "Accept: application/json\r\nUser-Agent: AMIGO-RetroCollector/" . AMIGO_VERSION],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            throw new \RuntimeException('TheGamesDB nicht erreichbar.');
        }
        $status = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) {
                $status = (int)$m[1];
            }
        }
        return [$status, $body];
    }
}
