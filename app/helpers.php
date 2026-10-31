<?php
/**
 * Globale Hilfsfunktionen für Templates und Controller.
 */
declare(strict_types=1);

use Amigo\Core\Csrf;
use Amigo\Core\Options;

/** HTML-Escaping – jede Ausgabe in Templates läuft hierüber. */
function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $route = 'home', array $params = []): string
{
    $params = array_filter($params, static fn ($v) => $v !== null && $v !== '');
    if ($route === 'home' && !$params) {
        return 'index.php';
    }
    return 'index.php?' . str_replace('%2F', '/', http_build_query(['r' => $route] + $params));
}

function asset(string $path): string
{
    $file = AMIGO_ROOT . '/assets/' . $path;
    return 'assets/' . $path . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

function redirect(string $route = 'home', array $params = []): never
{
    header('Location: ' . url($route, $params), true, 303);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'msg' => $message];
}

function flashes(): array
{
    $list = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $list;
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** String-Eingabe: getrimmt, ohne Steuerzeichen, auf Länge begrenzt. */
function input(string $key, int $max = 255, ?array $source = null): string
{
    $v = ($source ?? $_POST)[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
    $v = trim(str_replace("\r\n", "\n", $v));
    return mb_substr($v, 0, $max);
}

function query(string $key, int $max = 255): string
{
    return input($key, $max, $_GET);
}

/** Ganzzahl oder null, wenn leer/ungültig/außerhalb des Bereichs. */
function input_int(string $key, int $min = 0, int $max = PHP_INT_MAX, ?array $source = null): ?int
{
    $v = ($source ?? $_POST)[$key] ?? null;
    if (is_int($v)) {
        $v = (string)$v;
    }
    if (!is_string($v) || !preg_match('/^-?\d{1,18}$/', trim($v))) {
        return null;
    }
    $n = (int)trim($v);
    return ($n < $min || $n > $max) ? null : $n;
}

function query_int(string $key, int $min = 0, int $max = PHP_INT_MAX): ?int
{
    return input_int($key, $min, $max, $_GET);
}

/** Datum im Format YYYY-MM-DD oder null. */
function input_date(string $key): ?string
{
    $v = input($key, 10);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return null;
    }
    [$y, $m, $d] = array_map('intval', explode('-', $v));
    return checkdate($m, $d, $y) ? $v : null;
}

function input_enum(string $key, array $allowed, ?array $source = null): ?string
{
    $v = input($key, 50, $source);
    return array_key_exists($v, $allowed) ? $v : null;
}

/** Geldbetrag "12,50" / "12.50" -> Cent. */
function input_money(string $key): ?int
{
    $v = str_replace([' ', '€'], '', input($key, 20));
    if ($v === '') {
        return null;
    }
    $v = str_contains($v, ',') ? str_replace(['.', ','], ['', '.'], $v) : $v;
    if (!preg_match('/^\d{1,8}(\.\d{1,2})?$/', $v)) {
        return null;
    }
    return (int)round((float)$v * 100);
}

function fmt_money(?int $cents): string
{
    return $cents === null ? '' : number_format($cents / 100, 2, ',', '.') . ' €';
}

function money_input(?int $cents): string
{
    return $cents === null ? '' : number_format($cents / 100, 2, ',', '');
}

function fmt_date(?string $date): string
{
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts ? date('d.m.Y', $ts) : '';
}

function fmt_playtime(?int $minutes): string
{
    if ($minutes === null) {
        return '';
    }
    $h = intdiv($minutes, 60);
    $m = $minutes % 60;
    return $h > 0 ? sprintf('%d Std %02d Min', $h, $m) : $m . ' Min';
}

function fmt_num(int|float|null $n): string
{
    return $n === null ? '' : number_format((float)$n, 0, ',', '.');
}

/** Pixel-Sterne als HTML. */
function stars(?int $rating, bool $showEmpty = true): string
{
    if ($rating === null || $rating < 1) {
        return $showEmpty ? '<span class="stars stars-none">keine Wertung</span>' : '';
    }
    $html = '<span class="stars" role="img" aria-label="' . $rating . ' von 5 Sternen">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="star' . ($i <= $rating ? ' on' : '') . '" aria-hidden="true">★</span>';
    }
    return $html . '</span>';
}

function cond_label(?int $value): string
{
    return $value === null ? 'nicht vorhanden' : (Options::CONDITIONS[$value] ?? '?');
}

function cond_badge(?int $value): string
{
    if ($value === null) {
        return '<span class="cond cond-na">—</span>';
    }
    return '<span class="cond cond-' . $value . '" title="' . e(cond_label($value)) . '">' . e(cond_label($value)) . '</span>';
}

function selected(mixed $a, mixed $b): string
{
    return (string)$a === (string)$b ? ' selected' : '';
}

function checked(bool $on): string
{
    return $on ? ' checked' : '';
}

/** Cover-URL von TheGamesDB → lokale, gecachte Auslieferung über den eigenen Server. */
function tgdb_image(?string $url): ?string
{
    $path = \Amigo\Service\CoverCache::pathFromUrl($url);
    return $path === null ? null : url('cover', ['p' => $path]);
}

function platform_icon(?string $icon): ?string
{
    if (!$icon || !preg_match('/^[a-zA-Z0-9._-]+$/', $icon)) {
        return null;
    }
    return url('cover', ['p' => 'original/consoles/png48/' . $icon]);
}

/**
 * Relativer URL-Pfad zur Probe-Datei, falls das Datenverzeichnis im Webroot liegt
 * (dann muss es per Webserver-Regel gesperrt sein), sonst null.
 */
function probe_path(): ?string
{
    $root = realpath(AMIGO_ROOT) ?: AMIGO_ROOT;
    $data = realpath(\Amigo\Core\App::dataDir()) ?: \Amigo\Core\App::dataDir();
    if (!str_starts_with($data, $root . DIRECTORY_SEPARATOR)) {
        return null;
    }
    $rel = str_replace(DIRECTORY_SEPARATOR, '/', substr($data, strlen($root) + 1));
    return implode('/', array_map('rawurlencode', explode('/', $rel))) . '/probe.txt';
}

/** Avatar eines Nutzers als HTML (Pixel-Badge aus dem SVG-Sprite oder eigenes Bild). */
function avatar(array $user, int $size = 44, string $class = ''): string
{
    $a = \Amigo\Service\Avatar::resolve($user);
    $cls = trim('avatar ' . $class);
    $alt = 'Avatar von ' . ($user['username'] ?? '');
    if ($a['type'] === 'upload') {
        return '<img class="' . e($cls) . '" src="' . e(url('avatar', ['u' => (int)$user['id'], 'v' => substr($a['value'], 0, 8)]))
            . '" width="' . $size . '" height="' . $size . '" alt="' . e($alt) . '" loading="lazy">';
    }
    return '<svg class="' . e($cls) . '" width="' . $size . '" height="' . $size . '" role="img" aria-label="' . e($alt) . '">'
        . '<use href="' . e(asset('img/badges.svg')) . '#' . e($a['value']) . '"></use></svg>';
}
