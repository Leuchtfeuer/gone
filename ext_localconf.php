<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function ($extensionKey) {
        isset($GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extensionKey]) ?: $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['gone'] = [];

        // Redirects for pages are supported for TYPO3 v9 only
        if (version_compare(TYPO3_version, '10.0.0', '<')) {
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF'][$extensionKey]['pages'] = 'slug';
        }

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][$extensionKey] = \Leuchtfeuer\Gone\Hook\TCEmainHook::class;
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][$extensionKey] = \Leuchtfeuer\Gone\Hook\TCEmainHook::class;
    }, 'gone'
);
