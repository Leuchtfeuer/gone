<?php
declare(strict_types=1);
namespace Bitmotion\Gone\Domain\Repository;

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
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class HistoryRepository implements SingletonInterface
{
    public function findAll(int $limit = 0): array
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder->select('*')->from(ConfigurationUtility::TABLE_NAME);

        if ($limit > 0) {
            $queryBuilder->setMaxResults($limit);
        }

        $entries = $queryBuilder->execute()->fetchAll();
        $this->enrichData($entries);

        return $entries;
    }

    public function findByTablePathAndType(string $table, string $path, int $type = ConfigurationUtility::TYPE_301): array
    {
        $queryBuilder = $this->getQueryBuilder();

        return $queryBuilder
            ->select('uid')
            ->from(ConfigurationUtility::TABLE_NAME)
            ->where($queryBuilder->expr()->eq('table', $queryBuilder->createNamedParameter($table)))
            ->andWhere(
                $queryBuilder->expr()->eq('new', $queryBuilder->createNamedParameter($path)),
                $queryBuilder->expr()->eq(
                    'code',
                    $queryBuilder->createNamedParameter(ConfigurationUtility::TYPE_301, \PDO::PARAM_INT)
                )
            )->execute()
            ->fetchAll();
    }

    public function delete(int $id): void
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->delete(ConfigurationUtility::TABLE_NAME)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($id, \PDO::PARAM_INT)))
            ->execute();
    }

    public function deleteByTableAndPathAndLanguage(string $table, string $path, int $language): void
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->delete(ConfigurationUtility::TABLE_NAME)
            ->where($queryBuilder->expr()->eq('old', $queryBuilder->createNamedParameter($path)))
            ->andWhere($queryBuilder->expr()->eq('table', $queryBuilder->createNamedParameter($table)))
            ->andWhere($queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($language)))
            ->execute();
    }

    public function insert(string $original, string $new, string $table, int $id, string $status, int $code, int $languageId): void
    {
        $this->getQueryBuilder()->insert(ConfigurationUtility::TABLE_NAME)->values([
            'old' => $original,
            'new' => $new,
            'table' => $table,
            'orig_uid' => $id,
            'status' => $status,
            'code' => $code,
            'sys_language_uid' => $languageId,
            'crdate' => time(),
        ])->execute();
    }

    public function updatePath(string $path, int $uid)
    {
        $queryBuilder = $this->getQueryBuilder();
        $queryBuilder
            ->update(ConfigurationUtility::TABLE_NAME)
            ->set('new', $path)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid)))
            ->execute();
    }

    protected function getQueryBuilder(): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(ConfigurationUtility::TABLE_NAME);
    }

    protected function enrichData(array &$data): void
    {
        foreach ($data as $key => $item) {
            $data[$key]['record'] = BackendUtility::getRecord($item['table'], (int)$item['orig_uid'], '*', '', false);
            $data[$key]['title'] = BackendUtility::getRecordTitle($item['table'], $data[$key]['record']);
        }
    }
}
