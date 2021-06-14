<?php
declare(strict_types = 1);

return [
    'frontend' => [
        'leuchtfeuer/gone' => [
            'target' => \Leuchtfeuer\Gone\Middleware\StatusCodeMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/base-redirect-resolver',
            ],
        ],
    ],
];
