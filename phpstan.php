<?php

declare(strict_types=1);

return [
    'includes' => [
        __DIR__ . '/vendor/phpstan/phpstan-phpunit/extension.neon',
        __DIR__ . '/vendor/phpstan/phpstan-doctrine/extension.neon',
    ],
    'parameters' => [
        'level' => 8,
        'paths' => ['src', 'tests'],
        'excludePaths' => [
            'analyseAndScan' => [
                'vendor/*',
            ],
        ],
    ],
];
