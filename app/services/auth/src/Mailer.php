<?php
declare(strict_types=1);

class Mailer
{
    public static function sendReset(string $to, string $token): void
    {
        $link = 'https://localhost:8443/#/reset?token=' . rawurlencode($token);
        $body = "Bonjour,\r\n\r\n"
              . "Vous avez demandé la réinitialisation de votre mot de passe.\r\n\r\n"
              . "Cliquez sur ce lien (valable 5 minutes) :\r\n"
              . $link . "\r\n\r\n"
              . "Si vous n'êtes pas à l'origine de cette demande, ignorez ce mail.\r\n";

        self::smtp($to, 'Réinitialisation de votre mot de passe – Camagru', $body);
    }

    public static function sendVerification(string $to, string $token): void
    {
        $link = 'https://localhost:8443/#/verify?token=' . rawurlencode($token);
        $body = "Bonjour,\r\n\r\n"
              . "Bienvenue sur Camagru ! Confirmez votre adresse email en cliquant sur ce lien (valable 24h) :\r\n"
              . $link . "\r\n\r\n"
              . "Si vous n'avez pas créé de compte, ignorez ce mail.\r\n";

        self::smtp($to, 'Confirmez votre adresse email – Camagru', $body);
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
