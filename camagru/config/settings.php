<?php

declare(strict_types=1);

/** Behaviour only; deployment (URL, database, SMTP) is in .env. Read: Settings::get('session.lifetime'). */
return [
    'app' => [
        'lang' => 'en',
    ],

    'assets' => [
        'version' => 33,                // ?v= on css, js and svg
    ],

    'session' => [
        'name'            => 'camagru_session',
        'lifetime'        => 7200,      // s, inactivity before drop
        'cookie_lifetime' => 0,         // 0 = cleared on browser close
        'samesite'        => 'Lax',
        'regenerate'      => 900,       // s between id rotations
    ],

    'auth' => [
        'password_min_length' => 8,
        'token_bytes'         => 32,    // bytes, secret of the mail links
        'verification_ttl'    => 86400, // s, sign-up link validity
        'password_reset_ttl'  => 86400, // s, reset link validity
    ],

    'avatars' => [
        'default'      => 'generique.png',
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    ],
];
