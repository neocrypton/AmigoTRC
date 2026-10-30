<?php
declare(strict_types=1);

namespace Amigo\Service;

use Amigo\Core\App;
use Amigo\Core\Crypto;
use Amigo\Core\Settings;

/**
 * E-Mail-Versand über PHP mail() oder SMTP (STARTTLS/SSL, AUTH LOGIN).
 * Nur Klartext-Mails; Adressen werden validiert, Kopfzeilen gegen Injection geschützt.
 */
final class Mailer
{
    public const TRANSPORTS = ['mail' => 'PHP mail() des Servers', 'smtp' => 'SMTP (Postfach beim Hoster)'];
    public const SECURE = ['tls' => 'STARTTLS (Port 587)', 'ssl' => 'SSL/TLS (Port 465)', 'none' => 'unverschlüsselt (nicht empfohlen)'];

    public static function settings(): array
    {
        $db = App::db();
        return [
            'transport' => Settings::get($db, 'mail_transport', 'mail'),
            'from'      => Settings::get($db, 'mail_from', ''),
            'from_name' => Settings::get($db, 'mail_from_name', (string)App::config('site_name', 'AMIGO!')),
            'host'      => Settings::get($db, 'smtp_host', ''),
            'port'      => (int)Settings::get($db, 'smtp_port', '587'),
            'secure'    => Settings::get($db, 'smtp_secure', 'tls'),
            'user'      => Settings::get($db, 'smtp_user', ''),
            'has_pass'  => (string)Settings::get($db, 'smtp_pass', '') !== '',
        ];
    }

    public static function configured(): bool
    {
        $s = self::settings();
        return filter_var($s['from'], FILTER_VALIDATE_EMAIL) !== false
            && ($s['transport'] === 'mail' || $s['host'] !== '');
    }

    /** @return string[] Fehler */
    public static function save(array $in): array
    {
        $errors = [];
        $transport = array_key_exists($in['transport'] ?? '', self::TRANSPORTS) ? $in['transport'] : 'mail';
        $from = trim((string)($in['from'] ?? ''));
        $name = trim(preg_replace('/[\r\n]+/', ' ', (string)($in['from_name'] ?? '')));
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Bitte eine gültige Absender-Adresse angeben.';
        }
        $host = trim((string)($in['host'] ?? ''));
        $port = (int)($in['port'] ?? 0);
        $secure = array_key_exists($in['secure'] ?? '', self::SECURE) ? $in['secure'] : 'tls';
        if ($transport === 'smtp') {
            if (!preg_match('/^[A-Za-z0-9.-]{1,253}$/', $host)) {
                $errors[] = 'Bitte einen gültigen SMTP-Server angeben (z. B. smtp.example.de).';
            }
            if ($port < 1 || $port > 65535) {
                $errors[] = 'Ungültiger SMTP-Port.';
            }
        }
        if ($errors) {
            return $errors;
        }
        $db = App::db();
        Settings::set($db, 'mail_transport', $transport);
        Settings::set($db, 'mail_from', $from);
        Settings::set($db, 'mail_from_name', mb_substr($name, 0, 80));
        Settings::set($db, 'smtp_host', $host);
        Settings::set($db, 'smtp_port', (string)($port ?: 587));
        Settings::set($db, 'smtp_secure', $secure);
        Settings::set($db, 'smtp_user', mb_substr(trim((string)($in['user'] ?? '')), 0, 190));
        $pass = (string)($in['pass'] ?? '');
        if ($pass !== '') {
            Settings::set($db, 'smtp_pass', Crypto::encrypt($pass));
        } elseif (!empty($in['clear_pass'])) {
            Settings::set($db, 'smtp_pass', null);
        }
        return [];
    }

    /** @throws \RuntimeException wenn der Versand scheitert */
    public static function send(string $to, string $subject, string $text): void
    {
        if (!self::configured()) {
            throw new \RuntimeException('E-Mail-Versand ist nicht eingerichtet.');
        }
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('Ungültige Empfängeradresse.');
        }
        $s = self::settings();
        $subject = mb_encode_mimeheader(preg_replace('/[\r\n]+/', ' ', $subject), 'UTF-8', 'B', "\r\n");
        $fromName = $s['from_name'] !== '' ? mb_encode_mimeheader($s['from_name'], 'UTF-8', 'B', "\r\n") . ' ' : '';
        $domain = substr(strrchr($s['from'], '@'), 1);
        $headers = [
            'From: ' . $fromName . '<' . $s['from'] . '>',
            'Reply-To: ' . $s['from'],
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>',
            'X-Mailer: AMIGO-RetroCollector',
        ];
        $body = rtrim(chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $text)), 76, "\r\n"));

        if ($s['transport'] === 'smtp') {
            self::smtp($s, $to, $subject, $headers, $body);
            return;
        }
        if (!@mail($to, $subject, $body, implode("\r\n", $headers))) {
            throw new \RuntimeException('PHP mail() hat den Versand abgelehnt.');
        }
    }

    private static function smtp(array $s, string $to, string $subject, array $headers, string $body): void
    {
        $pass = (string)Crypto::decrypt((string)Settings::get(App::db(), 'smtp_pass', ''));
        $remote = ($s['secure'] === 'ssl' ? 'ssl://' : 'tcp://') . $s['host'] . ':' . $s['port'];
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $s['host']]]);
        $fp = @stream_socket_client($remote, $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new \RuntimeException('SMTP-Server nicht erreichbar: ' . $errstr);
        }
        stream_set_timeout($fp, 15);
        try {
            self::expect($fp, [220]);
            $helo = preg_replace('/[^A-Za-z0-9.-]/', '', (string)($_SERVER['SERVER_NAME'] ?? 'localhost')) ?: 'localhost';
            self::cmd($fp, 'EHLO ' . $helo, [250]);
            if ($s['secure'] === 'tls') {
                self::cmd($fp, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new \RuntimeException('STARTTLS fehlgeschlagen.');
                }
                self::cmd($fp, 'EHLO ' . $helo, [250]);
            }
            if ($s['user'] !== '') {
                self::cmd($fp, 'AUTH LOGIN', [334]);
                self::cmd($fp, base64_encode($s['user']), [334]);
                self::cmd($fp, base64_encode($pass), [235], 'Anmeldung am SMTP-Server fehlgeschlagen.');
            }
            self::cmd($fp, 'MAIL FROM:<' . $s['from'] . '>', [250]);
            self::cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::cmd($fp, 'DATA', [354]);
            $msg = implode("\r\n", [
                'Date: ' . date('r'),
                'To: <' . $to . '>',
                'Subject: ' . $subject,
                ...$headers,
            ]) . "\r\n\r\n" . $body;
            // Dot-Stuffing (RFC 5321) und Abschluss mit einzelnem Punkt
            $msg = preg_replace('/^\./m', '..', $msg);
            self::cmd($fp, $msg . "\r\n.", [250]);
            self::cmd($fp, 'QUIT', [221]);
        } finally {
            fclose($fp);
        }
    }

    private static function cmd($fp, string $line, array $codes, ?string $error = null): void
    {
        fwrite($fp, $line . "\r\n");
        self::expect($fp, $codes, $error);
    }

    private static function expect($fp, array $codes, ?string $error = null): void
    {
        $reply = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $reply .= $line;
            if (strlen($line) < 4 || $line[3] !== '-') {
                break;
            }
        }
        $code = (int)substr($reply, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new \RuntimeException($error ?? ('SMTP-Fehler: ' . mb_substr(trim($reply), 0, 200)));
        }
    }
}
