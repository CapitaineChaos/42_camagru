<?php
declare(strict_types=1);

class Mailer
{
    public static function sendReset(string $to, string $token): void
    {
        $link = 'https://localhost:8443/#/reset?token=' . rawurlencode($token);
        $body = "Hello,\r\n\r\n"
              . "You requested a password reset.\r\n\r\n"
              . "Click this link (valid for 5 minutes):\r\n"
              . $link . "\r\n\r\n"
              . "If you did not request this, ignore this email.\r\n";

        self::smtp($to, 'Reset your password – Camagru', $body);
    }

    public static function sendVerification(string $to, string $token): void
    {
        $link = 'https://localhost:8443/#/verify?token=' . rawurlencode($token);
        $body = "Hello,\r\n\r\n"
              . "Welcome to Camagru! Confirm your email address by clicking this link (valid for 24h):\r\n"
              . $link . "\r\n\r\n"
              . "If you did not create an account, ignore this email.\r\n";

        self::smtp($to, 'Confirm your email address – Camagru', $body);
    }

    public static function sendEmailChange(string $to, string $token): void
    {
        $link = 'https://localhost:8443/#/verify?token=' . rawurlencode($token);
        $body = "Hello,\r\n\r\n"
              . "You requested an email address change on Camagru.\r\n\r\n"
              . "Confirm your new address by clicking this link (valid for 24h):\r\n"
              . $link . "\r\n\r\n"
              . "Until you confirm this link, you can continue to log in with your old address.\r\n\r\n"
              . "If you did not request this change, ignore this email — your account is not affected.\r\n";

        self::smtp($to, 'Confirm your new email address – Camagru', $body);
    }

    private static function smtp(string $to, string $subject, string $body): void
    {
        $host = getenv('SMTP_HOST') ?: 'mailhog';
        $port = (int)(getenv('SMTP_PORT') ?: 1025);
        $from = 'no-reply@camagru.local';

        $fp = @fsockopen($host, $port, $errno, $errstr, 5);
        if (!$fp) {
            throw new RuntimeException("SMTP inaccessible ($host:$port): $errstr");
        }

        $r = fn() => fgets($fp, 515);
        $w = fn(string $cmd) => fwrite($fp, $cmd . "\r\n");

        $r(); // 220 greeting
        $w("EHLO camagru.local");
        while (($line = $r()) && str_starts_with(substr($line, 3, 1), '-')) {} // drain EHLO

        $w("MAIL FROM:<$from>");   $r();
        $w("RCPT TO:<$to>");       $r();
        $w("DATA");                $r(); // 354

        fwrite($fp,
            "From: Camagru <$from>\r\n"
          . "To: $to\r\n"
          . "Subject: $subject\r\n"
          . "Content-Type: text/plain; charset=utf-8\r\n"
          . "\r\n"
          . $body
          . "\r\n.\r\n"
        );
        $r(); // 250 OK

        $w("QUIT");
        fclose($fp);
    }
}
