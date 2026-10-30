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

namespace Netresearch\NrTextdb\Tests\Functional\Service;

use ArrayObject;
use Netresearch\NrTextdb\Service\TranslationTableWriter;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\ReferenceIndex;

/**
 * Records the component of every row a lookup of the import reads.
 */
final readonly class RowRecordingTranslationTableWriter extends TranslationTableWriter
{
    /**
     * @param ArrayObject<int, int> $components
     */
    public function __construct(
        ConnectionPool $connectionPool,
        ReferenceIndex $referenceIndex,
        public ArrayObject $components,
    ) {
        parent::__construct($connectionPool, $referenceIndex);
    }

    protected function fetchRows(QueryBuilder $queryBuilder): array
    {
        $rows = parent::fetchRows($queryBuilder);

        foreach ($rows as $row) {
            $this->components[] = (int) $row['component'];
        }

        return $rows;
    }
}
