<?php
declare(strict_types=1);

namespace Amigo\Controller;

use Amigo\Core\Auth;
use Amigo\Core\RateLimiter;
use Amigo\Service\CoverCache;

/** Liefert Cover/Icons aus dem lokalen Cache (lädt sie beim ersten Abruf von TheGamesDB). */
final class CoverController
{
    public function show(): void
    {
        $path = query('p', 255);
        $hit = CoverCache::get($path, static function (): bool {
            // Nur echte Downloads begrenzen, nicht Abrufe aus dem Cache.
            $bucket = 'cover:' . Auth::id();
            if (RateLimiter::tooMany($bucket, 120, 60)) {
                return false;
            }
            RateLimiter::hit($bucket);
            return true;
        });
        if (!$hit) {
            http_response_code(404);
            header('Cache-Control: private, max-age=300');
            exit;
        }
        [$file, $mime] = $hit;
        $etag = '"' . substr(sha1($path), 0, 16) . '"';
        header('Cache-Control: private, max-age=2592000');
        header('ETag: ' . $etag);
        if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
            http_response_code(304);
            exit;
        }
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}
