<?php

Kirby::plugin('mirthe/standard-site', [
    'routes' => [
        [
            'pattern' => 'standard-site/export',
            'action'  => function () {
                $kirby = kirby();

                if (($user = $kirby->user()) && $user->role()->id() === 'admin') {
                    return include __DIR__ . '/export.php';
                }

                throw new \Kirby\Exception('Unauthorized', 403);
            }
        ],

        [
            'pattern' => 'standard-site/publish',
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
