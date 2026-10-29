<?php
declare(strict_types=1);

namespace Amigo\Core;

/** Minimaler Template-Renderer. Templates escapen Ausgaben mit e(). */
final class View
{
    public static function render(string $template, array $data = [], ?string $layout = 'layout'): void
    {
        $content = self::fetch($template, $data);
        if ($layout === null) {
            echo $content;
            return;
        }
        echo self::fetch($layout, $data + ['content' => $content]);
    }

    public static function fetch(string $template, array $data = []): string
    {
        if (!preg_match('#^[a-z0-9_/]+$#', $template)) {
            throw new \InvalidArgumentException('Ungültiger Template-Name.');
        }
        $file = AMIGO_ROOT . '/app/views/' . $template . '.php';
        ob_start();
        try {
            (static function (string $__file, array $__data): void {
                extract($__data, EXTR_SKIP);
                require $__file;
            })($file, $data);
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }
}
