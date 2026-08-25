<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/** SMTP client without auth or TLS. */
final class Mailer
{
    /** Sends and swallows: a mail that fails must not undo the action it follows. */
    public static function sendOrLog(string $to, string $subject, string $htmlBody): bool
    {
        try {
            self::send($to, $subject, $htmlBody);
            return true;
        } catch (Throwable $e) {
            error_log('Mail "' . $subject . '" not sent: ' . $e->getMessage());
            return false;
        }
    }

    /** @throws RuntimeException */
    public static function send(string $to, string $subject, string $htmlBody): void
    {
        $socket = @fsockopen(MAIL_HOST, MAIL_PORT, $errno, $errstr, 10);
        if ($socket === false) {
            throw new RuntimeException("SMTP indisponible: {$errstr} ({$errno})");
        }

        self::expect($socket, '220');
        self::cmd($socket, 'EHLO camagru', '250');
        self::cmd($socket, 'MAIL FROM:<' . MAIL_FROM . '>', '250');
        self::cmd($socket, 'RCPT TO:<' . $to . '>', '250');
        self::cmd($socket, 'DATA', '354');

        $headers = implode("\r\n", [
            'From: Camagru <' . MAIL_FROM . '>',
            'To: <' . $to . '>',
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
        ]);

        // RFC 5321 dot-stuffing: a leading "." is doubled
        $body = str_replace("\r\n.", "\r\n..", "\r\n" . $htmlBody);

        fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
        self::expect($socket, '250');
        self::cmd($socket, 'QUIT', '221');
        fclose($socket);
    }

    /** @param resource $socket */
    private static function cmd($socket, string $command, string $expected): void
    {
        fwrite($socket, $command . "\r\n");
        self::expect($socket, $expected);
    }

    /** @param resource $socket */
    private static function expect($socket, string $code): void
    {
        $line = fgets($socket, 512);
        // Multi-line replies: "250-..." continues, "250 ..." ends
        while ($line !== false && isset($line[3]) && $line[3] === '-') {
            $line = fgets($socket, 512);
        }
        if ($line === false || !str_starts_with($line, $code)) {
            throw new RuntimeException(
                'SMTP: unexpected reply "' . trim((string) $line) . '" (expected ' . $code . ')'
            );
        }
    }
}
