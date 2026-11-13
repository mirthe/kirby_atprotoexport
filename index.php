<?php

Kirby::plugin('mirthe/atprotoexport', [
    'options' => [
        'repo'        => 'did:plc:' . option('atprotoexport.repo'),
        'publication' => option('atprotoexport.publication'),
        'pds'         => option('atprotoexport.pds'),
        'identifier'  => option('atprotoexport.identifier'),
        'password'    => option('atprotoexport.password'),
    ],
    'routes' => [
        [
            'pattern' => 'atprotoexport/export',
            'action'  => function () {
                $kirby = kirby();

                if (($user = $kirby->user()) && $user->role()->id() === 'admin') {
                    return include __DIR__ . '/export.php';
                }

                throw new \Kirby\Exception('Unauthorized', 403);
            }
        ],

        [
            'pattern' => 'atprotoexport/publish',
            'method'  => 'GET|POST',
            'action'  => function () {
                $kirby = kirby();

                if (
                    ($user = $kirby->user()) &&
                    $user->role()->id() === 'admin'
                ) {
                    return include __DIR__ . '/publish.php';
                }

                throw new \Kirby\Exception('Unauthorized', 403);
            }
        ]

    ]
]);
