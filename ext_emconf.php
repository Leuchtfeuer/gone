<?php

$EM_CONF['gone'] = [
    'title' => 'Gone',
    'description' => 'Automatically generates redirects (301) when a URL changes and returns a gone status code (410) when a page / record has been deleted.',
    'version' => '1.0.0',
    'category' => 'templates',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-9.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'state' => 'stable',
    'uploadfolder' => false,
    'createDirs' => '',
    'clearCacheOnLoad' => true,
    'author' => 'Florian Wessels',
    'author_email' => 'typo3-ext@bitmotion.de',
    'author_company' => 'Bitmotion GmbH',
    'autoload' => [
        'psr-4' => [
            'Bitmotion\\Gone\\' => 'Classes'
        ],
    ],
];
