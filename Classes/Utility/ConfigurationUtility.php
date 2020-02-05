<?php
declare(strict_types = 1);
namespace Bitmotion\Gone\Utility;

/***
 *
 * This file is part of the "Gone" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019 Florian Wessels <f.wessels@bitmotion.de>, Bitmotion GmbH
 *
 ***/

use TYPO3\CMS\Core\SingletonInterface;

class ConfigurationUtility implements SingletonInterface
{
    public const TABLE_NAME = 'tx_gone_path_history';

    public const TYPE_301 = 301;

    public const TYPE_410 = 410;

    public function getConfiguration(): array
    {
        return $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['gone'];
    }

    public function getConfigurationForModule(): array
    {
        $configuration = [];

        foreach ($this->getConfiguration() as $table => $slug) {
            $configuration[$table] = [
                'table' => $table,
                'title' => $GLOBALS['TCA'][$table]['ctrl']['title'],
                'slug' => $slug,
            ];
        }

        return $configuration;
    }
}
