<?php
declare(strict_types=1);
namespace Bitmotion\Gone\Middleware;

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

use Bitmotion\Gone\Utility\ConfigurationUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Error\PageErrorHandler\InvalidPageErrorHandlerException;
use TYPO3\CMS\Core\Error\PageErrorHandler\PageErrorHandlerInterface;
use TYPO3\CMS\Core\Error\PageErrorHandler\PageErrorHandlerNotConfiguredException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class StatusCodeMiddleware implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected $languageId = 0;

    protected $base = '';

    protected $suffix = '';

    protected $centerpiece = '';

    protected $shouldProcess = false;

    protected $isAbsoluteUri = false;

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if ($request instanceof ServerRequest) {
            /** @var Site $site */
            $site = $request->getAttribute('site');
            $path = $request->getUri()->getPath();

            $this->setSuffix($site);

            if ($this->suffix !== $path) {
                $siteLanguage = $request->getAttribute('language');
                if ($siteLanguage instanceof SiteLanguage) {
                    $basePath = $siteLanguage->getBase()->getPath();
                    if (strpos($path, $basePath) === 0) {
                        $path = '/' . substr($path, strlen($basePath));
                    } else {
                        $path = '/' . ltrim($path, $basePath);
                    }
                    $this->languageId = $siteLanguage->getLanguageId();
                    $this->base = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST') . $basePath;
                    $this->shouldProcess = true;
                } else {
                    $this->trimLanguage($path, $site);
                }
            }

            if ($this->shouldProcess === true) {
                $path = rtrim($path, $this->suffix);
                $record = $this->getRecordByAbsoluteUri($path, $site)
                    ?? $this->getRecord($path, $site)
                    ?? $this->getRecordByTail($path, $site, $request)
                    ?? false;

                if ($record !== false) {
                    switch ($record['code']) {
                        case 301:
                            $newPath = $this->isAbsoluteUri ? $record['new'] : $this->buildRedirectUri($record);
                            $this->logger->notice(sprintf('Redirect to: %s', $newPath));

                            return new RedirectResponse($newPath, 301);

                        case 410:
                            $errorHandler = $this->getErrorHandler($site);

                            if ($errorHandler !== null) {
                                return $errorHandler->handlePageError($request, 'Record removed.', []);
                            }
                    }
                }
            }
        }

        return $handler->handle($request);
    }

    protected function setSuffix(Site $site): void
    {
        $routeEnhancers = $site->getConfiguration()['routeEnhancers'] ?? [];

        foreach ($routeEnhancers as $routeEnhancer) {
            if (isset($routeEnhancer['type']) && $routeEnhancer['type'] === 'PageType') {
                foreach ($routeEnhancer['map'] as $value => $type) {
                    if ($type === 0) {
                        $this->suffix = (string)$value;
                    }
                }
            }
        }
    }

    protected function trimLanguage(string &$path, Site $site): void
    {
        $languages = $site->getConfiguration()['languages'] ?? [];

        foreach ($languages as $language) {
            $basePath = $language['base'];

            if ($basePath === $path) {
                continue;
            }

            if (strpos($path, $basePath) === 0) {
                $path = '/' . ltrim($path, $basePath);
                $this->languageId = (int)$language['languageId'];
                $this->base = GeneralUtility::getIndpEnv('TYPO3_REQUEST_HOST') . $language['base'];
                $this->shouldProcess = true;
                break;
            }
        }
    }

    /**
     * Find a matching history record by given (old) path and language ID
     *
     * @param string $path  The called cleaned path of the page
     * @param Site   $site  The site underneath the record is located
     *
     * @return array|null   The matching record
     */
    protected function getRecord(string $path, Site $site): ?array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(ConfigurationUtility::TABLE_NAME);

        $result = $queryBuilder
            ->select('*')
            ->from(ConfigurationUtility::TABLE_NAME, 'history')
            ->where($queryBuilder->expr()->eq('history.old', $queryBuilder->createNamedParameter($path)))
            ->andWhere($queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($this->languageId)))
            ->execute()
            ->fetchAll();

        if (count($result) > 1) {
            return $this->getMatchingRecord($result, $site);
        }

        return is_array($result) && !empty($result) ? array_shift($result) : null;
    }

    /**
     * If more then one history record match, we need to figure out the proper root page
     *
     * @param array $records    Matching records for given path and language
     * @param Site  $site       The Site where the record is located in
     *
     * @return array|null       The matching record
     */
    protected function getMatchingRecord(array $records, Site $site): ?array
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        foreach ($records as $record) {
            try {
                $recordSite = $siteFinder->getSiteByPageId($record['orig_uid']);

                if ($recordSite->getIdentifier() === $site->getIdentifier()) {
                    return $record;
                }
            } catch (SiteNotFoundException $exception) {
                // Site was not found. Skip this record
            }
        }

        return null;
    }

    protected function getRecordByTail(string $path, Site $site, ServerRequestInterface $request): ?array
    {
        $pageUid = $this->getTargetPageUid($path, $site, $request);

        if ($pageUid !== null) {
            $tail = ltrim(strrchr($path, '/'), '/');

            return $this->getRecord($tail, $site);
        }

        return null;
    }

    protected function getRecordByAbsoluteUri(string $path, Site $site): ?array
    {
        $record = $this->getRecord(GeneralUtility::getIndpEnv('TYPO3_REQUEST_URL'), $site);

        if ($record !== null) {
            $this->isAbsoluteUri = true;
        }

        return $record;
    }

    protected function getTargetPageUid(string $tail, Site $site, ServerRequestInterface $request): ?int
    {
        $siteRouteResult = $request->getAttribute('routing', null);

        while ($tail !== '') {
            $siteRouteResult->offsetSet('tail', $tail);
            $result = null;

            try {
                $result = $site->getRouter()->matchRequest($request, $request->getAttribute('routing', null));
            } catch (\Exception $exception) {
                // Do nothing
            }

            if ($result instanceof PageArguments) {
                $this->centerpiece = rtrim($tail . $this->centerpiece, '/');

                return $result->getPageId();
            }

            $tail = rtrim($tail, ltrim(strrchr($tail, '/'), '/'));
            $tail = rtrim($tail, '/');
        }

        return null;
    }

    protected function buildRedirectUri(array $record): string
    {
        $centerpiece = $this->centerpiece;

        if (strpos($centerpiece, $record['old']) !== false) {
            $centerpiece = rtrim($centerpiece, $record['old']);
        }

        if ($centerpiece !== '') {
            $centerpiece = '/' . trim($centerpiece, '/') . '/';
        } else {
            $centerpiece = '/';
        }

        return rtrim($this->base, '/') . $centerpiece . trim($record['new'], '/') . $this->suffix;
    }

    /**
     * Tries to find a matching 410 error handler. If no 410 handler is present, it will try to find a 404 error handler.
     *
     * @param Site $site                        The current site
     *
     * @return PageErrorHandlerInterface|null   The matching error handler
     */
    protected function getErrorHandler(Site $site): ?PageErrorHandlerInterface
    {
        $errorHandler = null;

        try {
            $errorHandler = $site->getErrorHandler(410);
        } catch (PageErrorHandlerNotConfiguredException $exception) {
            try {
                // Fallback to 404 error handler
                $errorHandler = $site->getErrorHandler(404);
            } catch (\Exception $exception) {
                // No error Handler configured
            }
        } catch (InvalidPageErrorHandlerException $exception) {
            // Do nothing
        }

        return $errorHandler;
    }
}
