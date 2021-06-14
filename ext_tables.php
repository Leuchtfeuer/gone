<?php
defined('TYPO3_MODE') || die('Access denied.');


call_user_func(
    function ($extensionKey) {
        // Backend Module
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            'Leuchtfeuer.Gone',
            'site',
            'gone',
            'bottom',
            [
                'Backend' => 'list, delete'
            ],
            [
                'access' => 'user,group',
                'icon' => 'EXT:gone/Resources/Public/Icons/Extension.svg',
                'labels' => 'LLL:EXT:gone/Resources/Private/Language/locallang_mod.xlf',
            ]
        );
    }, 'gone'
);
