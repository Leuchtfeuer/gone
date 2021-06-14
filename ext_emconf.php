<?php

$EM_CONF['gone'] = [
    'title' => 'Gone',
    'description' => 'Automatically generates redirects (301) when a URL changes and returns a gone status code (410) when a page / record has been deleted.',
    'version' => '1.1.0-dev',
    'category' => 'templates',
    'constraints' => [
        'depends' => [
            'typo3' => '9.5.0-10.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'state' => 'stable',
    'uploadfolder' => false,
    'createDirs' => '',
    'clearCacheOnLoad' => true,
    'author' => 'Florian Wessels',
    'author_email' => 'f.wessels@Leuchtfeuer.com',
    'author_company' => 'Leuchtfeuer Digital Marketing',
    'autoload' => [
        'psr-4' => [
            'Leuchtfeuer\\Gone\\' => 'Classes'
        ],
    ],
];
