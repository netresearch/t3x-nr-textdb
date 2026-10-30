<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Platform\PlatformInformation;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\ReferenceIndex;

use function array_chunk;
use function array_key_exists;
use function array_keys;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function is_numeric;
use function is_string;
use function max;

/**
 * Reads and writes rows of the translation table for the import, without
 * Extbase.
 *
 * Extbase writes a new record with an INSERT, an UPDATE for its relations and
 * two reference index updates, and looks every record up on its own. For a
 * file of 48,500 units that made an import take minutes (issue #30). This
 * class looks the records of a file up in chunks, inserts new records in bulk
 * and updates the reference index of each written record once.
 *
 * The rows it writes carry the values Extbase wrote: crdate and tstamp from
 * $GLOBALS['EXEC_TIME'], an empty l10n_diffsource, sorting 0.
 *
 * Not final, so that unit tests of the import can double it.
 *
 * @phpstan-type TranslationKey array{int, int, int, string}
 * @phpstan-type StoredTranslation array{uid: int, value: string, l10n_parent: int}
 */
readonly class TranslationTableWriter
{
    public const TABLE = 'tx_nrtextdb_domain_model_translation';

    /**
     * Parameters the queries need besides the placeholders of a chunk.
     */
    private const RESERVED_PARAMETERS = 10;

    /**
     * Records read back at once to update their reference index.
     */
    private const READ_BACK_CHUNK_SIZE = 1000;

    public function __construct(
        private ConnectionPool $connectionPool,
        private ReferenceIndex $referenceIndex,
    ) {}

    /**
     * Returns the stored translations of the keys, by the index of the key.
     *
     * Finds what TranslationRepository::findByEnvironmentComponentTypePlaceholderAndLanguage()
     * finds: a record on the page, in the language, not deleted, hidden or
     * not. The placeholder is compared by the database: MariaDB and MySQL
     * compare case-insensitively and ignore trailing spaces, so "Key" finds
     * the record "key" there, as it did through Extbase. A key whose record
     * the chunk query returned under such a different spelling is looked up
     * on its own.
     *
     * @param array<int, TranslationKey> $keys Environment, component, type and placeholder of each key
     *
     * @return array<int, StoredTranslation>
     */
    public function findTranslations(int $pageId, int $languageUid, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $connection = $this->getConnection();
        $chunkSize  = max(1, PlatformInformation::getMaxBindParameters($connection->getDatabasePlatform()) - self::RESERVED_PARAMETERS);
        $found      = [];

        foreach (array_chunk($keys, $chunkSize, true) as $chunk) {
            $placeholders = array_values(array_unique(array_map(static fn (array $key): string => $key[3], $chunk)));

            $queryBuilder = $this->createLookupQuery($pageId, $languageUid);
            $rows         = $queryBuilder
                ->andWhere(
                    $queryBuilder->expr()->in(
                        'placeholder',
                        $queryBuilder->createNamedParameter($placeholders, Connection::PARAM_STR_ARRAY),
                    ),
                )
                ->executeQuery()
                ->fetchAllAssociative();

            $rowsByKey = [];
            foreach ($rows as $row) {
                $rowsByKey[$this->rowKey($row)] = $row;
            }

            $claimed   = [];
            $unmatched = [];
            foreach ($chunk as $index => $key) {
                $keyString = implode('|', $key);

                if (array_key_exists($keyString, $rowsByKey)) {
                    $found[$index]       = $this->storedTranslation($rowsByKey[$keyString]);
                    $claimed[$keyString] = true;
                } else {
                    $unmatched[$index] = $key;
                }
            }

            // A row no key matched exactly was returned for a placeholder
            // the database considers equal. Only the keys of its environment,
            // component and type can be the one it belongs to.
            $looseMatches = [];
            foreach ($rowsByKey as $keyString => $row) {
                if (!array_key_exists($keyString, $claimed)) {
                    $looseMatches[$this->referenceKey($row)] = true;
                }
            }

            foreach ($unmatched as $index => [$environment, $component, $type, $placeholder]) {
                if (!array_key_exists($environment . '|' . $component . '|' . $type, $looseMatches)) {
                    continue;
                }

                $queryBuilder = $this->createLookupQuery($pageId, $languageUid);
                $row          = $queryBuilder
                    ->andWhere(
                        $queryBuilder->expr()->eq('environment', $queryBuilder->createNamedParameter($environment, Connection::PARAM_INT)),
                        $queryBuilder->expr()->eq('component', $queryBuilder->createNamedParameter($component, Connection::PARAM_INT)),
                        $queryBuilder->expr()->eq('type', $queryBuilder->createNamedParameter($type, Connection::PARAM_INT)),
                        $queryBuilder->expr()->eq('placeholder', $queryBuilder->createNamedParameter($placeholder)),
                    )
                    ->setMaxResults(1)
                    ->executeQuery()
                    ->fetchAssociative();

                if ($row !== false) {
                    $found[$index] = $this->storedTranslation($row);
                }
            }
        }

        return $found;
    }

    /**
     * Inserts new records, in the order given, and updates their reference
     * index.
     *
     * @param list<array<string, int|string>> $rows Rows with the same columns each
     */
    public function insert(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $connection = $this->getConnection();
        $lastUid    = $this->getHighestUid();

        $connection->bulkInsert(self::TABLE, $rows, array_keys($rows[0]));

        // The records just inserted are the ones above the highest uid
        // before. Another process inserting at the same time adds records
        // there too; updating their reference index as well changes nothing.
        do {
            $queryBuilder = $connection->createQueryBuilder();
            $queryBuilder->getRestrictions()->removeAll();

            $inserted = $queryBuilder
                ->select('*')
                ->from(self::TABLE)
                ->where($queryBuilder->expr()->gt('uid', $queryBuilder->createNamedParameter($lastUid, Connection::PARAM_INT)))
                ->orderBy('uid')
                ->setMaxResults(self::READ_BACK_CHUNK_SIZE)
                ->executeQuery()
                ->fetchAllAssociative();

            $this->updateReferenceIndex($inserted);

            $lastRow = end($inserted);
            if ($lastRow !== false) {
                $lastUid = $this->toInt($lastRow['uid']);
            }
        } while (count($inserted) === self::READ_BACK_CHUNK_SIZE);
    }

    /**
     * Updates the columns of records, then their reference index.
     *
     * @param array<int, array<string, int|string>> $columnsByUid
     */
    public function update(array $columnsByUid): void
    {
        if ($columnsByUid === []) {
            return;
        }

        $connection = $this->getConnection();

        foreach ($columnsByUid as $uid => $columns) {
            $connection->update(self::TABLE, $columns, ['uid' => $uid]);
        }

        foreach (array_chunk(array_keys($columnsByUid), self::READ_BACK_CHUNK_SIZE) as $uids) {
            $queryBuilder = $connection->createQueryBuilder();
            $queryBuilder->getRestrictions()->removeAll();

            $this->updateReferenceIndex(
                $queryBuilder
                    ->select('*')
                    ->from(self::TABLE)
                    ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($uids, Connection::PARAM_INT_ARRAY)))
                    ->orderBy('uid')
                    ->executeQuery()
                    ->fetchAllAssociative(),
            );
        }
    }

    /**
     * Updates the reference index of records from their full rows, as
     * Extbase does after it wrote a record, without reading each row again.
     *
     * ReferenceIndex::updateRefIndexTable() is marked internal; Extbase
     * calls it the same way.
     *
     * @param list<array<string, mixed>> $rows
     */
    private function updateReferenceIndex(array $rows): void
    {
        foreach ($rows as $row) {
            $this->referenceIndex->updateRefIndexTable(self::TABLE, $this->toInt($row['uid']), false, 0, $row);
        }
    }

    private function getConnection(): Connection
    {
        return $this->connectionPool->getConnectionForTable(self::TABLE);
    }

    private function getHighestUid(): int
    {
        $queryBuilder = $this->getConnection()->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();

        return $this->toInt(
            $queryBuilder
                ->selectLiteral('MAX(' . $queryBuilder->quoteIdentifier('uid') . ')')
                ->from(self::TABLE)
                ->executeQuery()
                ->fetchOne(),
        );
    }

    /**
     * Selects the records of a page and language, hidden or not, that are not
     * deleted.
     */
    private function createLookupQuery(int $pageId, int $languageUid): QueryBuilder
    {
        $queryBuilder = $this->getConnection()->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();

        return $queryBuilder
            ->select('uid', 'environment', 'component', 'type', 'placeholder', 'value', 'l10n_parent')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($pageId, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
            );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function rowKey(array $row): string
    {
        return $this->referenceKey($row) . '|' . $this->toString($row['placeholder']);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function referenceKey(array $row): string
    {
        return $this->toInt($row['environment']) . '|' . $this->toInt($row['component']) . '|' . $this->toInt($row['type']);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return StoredTranslation
     */
    private function storedTranslation(array $row): array
    {
        return [
            'uid'         => $this->toInt($row['uid']),
            'value'       => $this->toString($row['value']),
            'l10n_parent' => $this->toInt($row['l10n_parent']),
        ];
    }

    private function toInt(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function toString(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
