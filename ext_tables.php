<?php
defined('TYPO3_MODE') || die('Access denied.');


call_user_func(
    function ($extensionKey) {
        $extensionName = 'Gone';
        $controllerName = \Leuchtfeuer\Gone\Controller\BackendController::class;

        // Use deprecated names for TYPO3 v9
        if (version_compare(TYPO3_version, '10.0.0', '<')) {
            $extensionName = 'Leuchtfeuer.Gone';
            $controllerName = 'Login';
        }

        // Backend Module
        \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
            $extensionName,
            'site',
            'gone',
            'bottom',
            [
                $controllerName => 'list, delete'
            ],
            [
                'access' => 'user,group',
                'icon' => sprintf('EXT:%s/Resources/Public/Icons/Extension.svg', $extensionKey),
                'labels' => sprintf('LLL:EXT:%s/Resources/Private/Language/locallang_mod.xlf', $extensionKey)
            ]
        );
    }, 'gone'
);
