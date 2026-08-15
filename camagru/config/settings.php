<?php

declare(strict_types=1);

/** Behaviour only; deployment (URL, database, SMTP) is in .env. Read: Settings::get('session.lifetime'). */
return [
    'app' => [
        'lang' => 'en',
    ],

    'assets' => [
        'version' => 55,                // ?v= on css, js and svg
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

    'gallery' => [
        'per_page' => 6,
    ],

    'comments' => [
        'max_length' => 500,
    ],

    'photobooth' => [
        'width'        => 800,          // px, montage; the scene keeps this ratio
        'height'       => 600,
        'quality'      => 85,           // jpeg
        'max_layers'   => 8,            // per montage
        'min_scale'    => 0.05,         // layer width, as a fraction of the montage
        'max_scale'    => 2.0,
        'max_source'   => 6291456,      // bytes, decoded source image
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/gif'],
        // slug => label; files are public/filtres/<slug>.png, drawn by scripts/filtres.py
        'filters'      => [
            'cat-ears'      => 'Cat ears',
            'kitten-ears'   => 'Kitten ears',
            'pastel-cat'    => 'Pastel cat',
            'dog-ears'      => 'Dog ears',
            'mouse-ears'    => 'Mouse ears',
            'hearts'        => 'Hearts',
            'heart-glasses' => 'Heart glasses',
            'dog-face'      => 'Dog face',
            'rose-crown'    => 'Rose crown',
            'blue-crown'    => 'Cornflower crown',
            'golden-crown'  => 'Golden crown',
            'purple-horns'  => 'Purple horns',
            'ram-horns'     => 'Ram horns',
            'red-horns'     => 'Red horns',
        ],
    ],
];
