<?php

/*
 * This file is part of the "Gone" extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * Florian Wessels <f.wessels@Leuchtfeuer.com>, Leuchtfeuer Digital Marketing
 */

return [
    '\Bitmotion\Gone\Controller\BackendController' => \Leuchtfeuer\Gone\Controller\BackendController::class,
    '\Bitmotion\Gone\Domain\Repository\HistoryRepository' => \Leuchtfeuer\Gone\Domain\Repository\HistoryRepository::class,
    '\Bitmotion\Gone\Hook\TCEmainHook' => \Leuchtfeuer\Gone\Hook\TCEmainHook::class,
    '\Bitmotion\Gone\Middleware\StatusCodeMiddleware' => \Leuchtfeuer\Gone\Middleware\StatusCodeMiddleware::class,
    '\Bitmotion\Gone\Utility\ConfigurationUtility' => \Leuchtfeuer\Gone\Utility\ConfigurationUtility::class,
];
