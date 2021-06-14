<?php

declare(strict_types=1);

/*
 * This file is part of the "Gone" extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * Florian Wessels <f.wessels@Leuchtfeuer.com>, Leuchtfeuer Digital Marketing
 */

namespace Leuchtfeuer\Gone\Hook;

use Leuchtfeuer\Gone\Domain\Repository\HistoryRepository;
use Leuchtfeuer\Gone\Utility\ConfigurationUtility;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\QueryGenerator;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;

class TCEmainHook implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected $mapping = [];

    /**
     * @var HistoryRepository
     */
    protected $historyRepository;

    public function __construct()
    {
        $this->mapping = GeneralUtility::makeInstance(ConfigurationUtility::class)->getConfiguration();
        $this->historyRepository = GeneralUtility::makeInstance(HistoryRepository::class);
    }

    public function processDatamap_postProcessFieldArray(string $status, string $table, $id, array $fields, DataHandler &$dataHandler): void
    {
        if (isset($fields[$this->mapping[$table]])) {
            switch ($status) {
                case 'update':
                    $this->updateEntry($table, (int)$id, $fields);
                    break;

                case 'new':
                    $this->deleteExistingRecord($table, $fields[$this->mapping[$table]], (int)$fields['sys_language_uid']);
                    break;
            }
        }
    }

    public function processCmdmap_preProcess(string $command, string $table, int $id, string $value, DataHandler &$dataHandler): void
    {
        if (isset($this->mapping[$table])) {
            if ((bool)$dataHandler->deleteTree === true && $command === 'delete' && $table === 'pages') {
                $this->preProcessMultipleRecords($command, $table, $id);
            } else {
                $this->preProcessSingleRecord($command, $table, $id, $value);
            }
        }
    }

    /**
     * Handles multiple records (e.g. when a pages are deleted or undeleted recursively)
     *
     * @param string $command The actual command (e.g. delete or undelete)
     * @param string $table   The table name (e.g. pages)
     * @param int    $id      The ID of the record
     */
    protected function preProcessMultipleRecords(string $command, string $table, int $id): void
    {
        $queryGenerator = GeneralUtility::makeInstance(QueryGenerator::class);
        $pages = GeneralUtility::trimExplode(',', $queryGenerator->getTreeList($id, 999));

        foreach ($pages as $page) {
            $this->preProcessSingleRecord($command, $table, (int)$page);
        }
    }

    /**
     * Creates entries for a single record
     *
     * @param string $command The actual command (delete, undelete or move)
     * @param string $table   The table name (e.g. pages)
     * @param int    $id      The ID of the record
     * @param string $value   The ID of the new parent page (when record is moved)
     */
    protected function preProcessSingleRecord(string $command, string $table, int $id, string $value = ''): void
    {
        switch ($command) {
            case 'delete':
                $this->handleDeleteCommand($table, $id);
                break;

            case 'undelete':
                $record = BackendUtility::getRecord($table, $id, '*', '', false);
                $this->deleteExistingRecord($table, $record[$this->mapping[$table]], (int)$record['sys_language_uid']);
                break;

            case 'move':
                if ($table === 'pages') {
                    $this->handleMoveCommand($id, (int)$value);
                    // TODO: Feature: Update slug and create 301 Record
                }
        }
    }

    /**
     * Creates 410 entries for given record
     *
     * @param string $table The table name
     * @param int    $id    The ID of the record
     */
    protected function handleDeleteCommand(string $table, int $id): void
    {
        $records = $this->getAllTranslationsOfRecord($table, $id) ?? [];

        foreach ($records as $record) {
            $original = $record[$this->mapping[$table]];
            $this->create410Entry($original, $table, $record['uid'], (int)$record['sys_language_uid']);
        }
    }

    /**
     * Creates 301 entries for given page record to anhother Site
     *
     * @param int $id    The ID of the page
     * @param int $value The new parent page ID
     */
    protected function handleMoveCommand(int $id, int $value): void
    {
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        try {
            $oldSite = $siteFinder->getSiteByPageId($id);
            $newSite = $siteFinder->getSiteByPageId($value);

            // Rootpage has been changed
            if ($oldSite->getIdentifier() !== $newSite->getIdentifier()) {
                $this->movePageToAnotherSite($id, $oldSite, $newSite);
            }
        } catch (SiteNotFoundException $exception) {
            // Do nothing.
        }
    }

    /**
     * Updates an existing database entry
     *
     * @param string $table  The table name
     * @param int    $id     The ID of the record to handle
     * @param array  $fields Additional data record fields (e.g. the slug or path_segment)
     */
    protected function updateEntry(string $table, int $id, array $fields): void
    {
        $key = $this->mapping[$table];
        $record = BackendUtility::getRecord($table, $id);

        $original = $record[$key];
        $new = $fields[$key];

        if ($table === 'pages') {
            $this->getAbsoluteUrls($record['uid'], $record['sys_language_uid'], $original, $new);
        }

        if ($original !== $new) {
            $this->create301Entry($original, $new, $table, $id, (int)$record['sys_language_uid']);
        }
    }

    protected function create301Entry(string $original, string $new, string $table, int $id, int $languageId): void
    {
        $this->deleteExistingRecord($table, $new, $languageId);
        $this->createEntry($original, $new, $table, $id, HttpUtility::HTTP_STATUS_301, ConfigurationUtility::TYPE_301, $languageId);
        $this->modifyExistingRedirect($table, $original, $new);
    }

    protected function create410Entry(string $original, string $table, int $id, int $languageId): void
    {
        $this->deleteExistingRecord($table, $original, $languageId);
        $this->createEntry($original, '', $table, $id, HttpUtility::HTTP_STATUS_410, ConfigurationUtility::TYPE_410, $languageId);
    }

    /**
     * Writes the 301 / 410 entry into the database
     *
     * @param string $original   Old known path
     * @param string $new        New path (empty if the record was deleted)
     * @param string $table      The table name
     * @param int    $id         The id of the record
     * @param string $status     The speaking status name
     * @param int    $code       The status code
     * @param int    $languageId The language ID of the record
     */
    protected function createEntry(string $original, string $new, string $table, int $id, string $status, int $code, int $languageId): void
    {
        $this->historyRepository->insert($original, $new, $table, $id, $status, $code, $languageId);
        $this->logger->notice(sprintf('Created new %s-record (from: %s to: %s)', $code, $original, $new));
    }

    /**
     * Remove existing record from the database. This could happen when a new page with an old slug is created
     *
     * @param string $table    The table which should be inspected
     * @param string $path     The old path
     * @param int    $language Language ID
     */
    protected function deleteExistingRecord(string $table, string $path, int $language): void
    {
        $this->historyRepository->deleteByTableAndPathAndLanguage($table, $path, $language);
        $this->logger->notice(sprintf('Removed record for "%s" (Language: %s)', $path, $language));
    }

    /**
     * Update existing redirects to prevent a redirect loop
     *
     * @param string $table   The name of the table which should be inspected
     * @param string $oldPath The old path
     * @param string $newPath The modified path
     */
    protected function modifyExistingRedirect(string $table, string $oldPath, string $newPath): void
    {
        $redirects = $this->historyRepository->findByTablePathAndType($table, $oldPath);

        foreach ($redirects as $redirect) {
            $this->historyRepository->updatePath($newPath, (int)$redirect['uid']);
        }
    }

    /**
     * Transforms slugs of pages into absolute URLs
     *
     * @param int    $id       The ID of the data record
     * @param int    $language The ID of the language
     * @param string $old      Old path
     * @param string $new      New path
     */
    protected function getAbsoluteUrls(int $id, int $language, string &$old, string &$new): void
    {
        try {
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            $site = $siteFinder->getSiteByPageId($id);
            $uri = $site->getRouter()->generateUri($id, ['_language' => $language]);

            $path = $old;
            $old = sprintf('%s://%s%s', $uri->getScheme(), $uri->getHost(), $uri->getPath());
            $new = str_replace($path, $new, $old);
        } catch (\Exception $exception) {
            // Do nothing
        }
    }

    /**
     * Creates an redirect when a page is moved from one Site into another one
     *
     * @param int  $id      The ID of the page
     * @param Site $site    The (old) Site
     * @param Site $newSite The new Site
     */
    protected function movePageToAnotherSite(int $id, Site $site, Site $newSite): void
    {
        try {
            $records = $this->getAllTranslationsOfRecord('pages', $id) ?? [];

            foreach ($records as $record) {
                $uri = $site->getRouter()->generateUri($record['uid'], ['_language' => $record['sys_language_uid']]);
                $oldUri = sprintf('%s://%s%s', $uri->getScheme(), $uri->getHost(), $uri->getPath());

                $uri = $newSite->getRouter()->generateUri($record['uid'], ['_language' => $record['sys_language_uid']]);
                $newUri = sprintf('%s://%s%s', $uri->getScheme(), $uri->getHost(), $uri->getPath());

                $this->create301Entry($oldUri, $newUri, 'pages', (int)$record['uid'], (int)$record['sys_language_uid']);
            }
        } catch (InvalidRouteArgumentsException $exception) {
            // Do nothing
        }
    }

    /**
     * Returns all translations of given page including itself
     *
     * @param string $table The table name
     * @param int    $id    The ID of the record
     *
     * @return array|null   The records
     */
    protected function getAllTranslationsOfRecord(string $table, int $id): ?array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $parentField = $GLOBALS['TCA'][$table]['ctrl']['transOrigPointerField'] ?? 'l10n_parent';

        return $queryBuilder
            ->select($this->mapping[$table], 'uid', 'sys_language_uid')
            ->from($table)
            ->orWhere(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($id, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq($parentField, $queryBuilder->createNamedParameter($id, \PDO::PARAM_INT))
            )->execute()
            ->fetchAll();
    }
}
