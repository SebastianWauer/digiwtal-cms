<?php
declare(strict_types=1);

return [
    [
        'slug'  => '/',
        'title' => 'Startseite',
        'content' => [
            'blocks' => [
                [
                    'type' => 'html',
                    'data' => [
                        'html' => '<h2>Willkommen</h2><p>Diese Seite kannst du im CMS bearbeiten.</p>',
                    ],
                ],
            ],
        ],
    ],
    [
        'slug'  => '/kontakt',
        'title' => 'Kontakt',
        'content' => [
            'blocks' => [
                [
                    'type' => 'html',
                    'data' => [
                        'html' => '<h2>Kontakt</h2><p>Kontaktinfos hier eintragen.</p>',
                    ],
                ],
            ],
        ],
    ],
    [
        'slug'  => '/impressum',
        'title' => 'Impressum',
        'content' => [
            'blocks' => [
                [
                    'type' => 'html',
                    'data' => [
                        'html' => '<h2>Impressum</h2><p>Impressumstext hier eintragen.</p>',
                    ],
                ],
            ],
        ],
    ],
    [
        'slug'  => '/datenschutz',
        'title' => 'Datenschutz',
        'content' => [
            'blocks' => [
                [
                    'type' => 'html',
                    'data' => [
                        'html' => '<h2>Datenschutz</h2><p>Datenschutzerklärung hier eintragen.</p>',
                    ],
                ],
            ],
        ],
    ],
];
