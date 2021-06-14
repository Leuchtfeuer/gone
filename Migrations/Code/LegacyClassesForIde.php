<?php

/*
 * This file is part of the "Gone" extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * Florian Wessels <f.wessels@Leuchtfeuer.com>, Leuchtfeuer Digital Marketing
 */

namespace {
    die('Access denied');
}

namespace Bitmotion\Gone\Controller {

    /** @deprecated  */
    abstract class BackendController extends \Leuchtfeuer\Gone\Controller\BackendController
    {
    }
}

namespace Bitmotion\Gone\Domain\Repository {

    /** @deprecated  */
    class HistoryRepository extends \Leuchtfeuer\Gone\Domain\Repository\HistoryRepository
    {
    }
}

namespace Bitmotion\Gone\Hook {

    /** @deprecated  */
    class TCEmainHook extends \Leuchtfeuer\Gone\Hook\TCEmainHook
    {
    }
}

namespace Bitmotion\Gone\Middleware {

    /** @deprecated  */
    class StatusCodeMiddleware extends \Leuchtfeuer\Gone\Middleware\StatusCodeMiddleware
    {
    }
}

namespace Bitmotion\Gone\Utility {

    /** @deprecated  */
    class ConfigurationUtility extends \Leuchtfeuer\Gone\Utility\ConfigurationUtility
    {
    }
}
