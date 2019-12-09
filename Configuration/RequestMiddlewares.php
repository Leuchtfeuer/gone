<?php
declare(strict_types = 1);

return [
    'frontend' => [
        'bitmotion/gone' => [
            'target' => \Bitmotion\Gone\Middleware\StatusCodeMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/base-redirect-resolver',
            ],
        ],
    ],
];
