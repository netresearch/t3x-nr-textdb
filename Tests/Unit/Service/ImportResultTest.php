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

namespace Netresearch\NrTextdb\Tests\Unit\Service;

use Netresearch\NrTextdb\Service\ImportResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(ImportResult::class)]
final class ImportResultTest extends UnitTestCase
{
    #[Test]
    public function addSumsTheCountsAndAppendsTheErrors(): void
    {
        $total = new ImportResult();
        $total->recordImported();
        $total->recordError('first');

        $file = new ImportResult();
        $file->recordImported();
        $file->recordImported();
        $file->recordUpdated();
        $file->recordError('second');

        $total->add($file);

        self::assertSame(3, $total->getImported());
        self::assertSame(1, $total->getUpdated());
        self::assertSame(['first', 'second'], $total->getErrors());
    }

    #[Test]
    public function addMergesTheSkippedKeysOfBothReasons(): void
    {
        $total = new ImportResult();
        $total->recordSkippedUnapproved('c|t|first');
        $total->recordSkippedEmpty('c|t|empty');
        $total->recordPartiallyApproved('c|t|plural');

        $file = new ImportResult();
        $file->recordSkippedUnapproved('c|t|second');
        $file->recordSkippedUnapproved('c|t|first');
        $file->recordSkippedEmpty('c|t|blank');
        $file->recordPartiallyApproved('c|t|other');
        $file->recordPartiallyApproved('c|t|plural');

        $total->add($file);

        self::assertSame(['c|t|first', 'c|t|second'], $total->getSkippedUnapproved());
        self::assertSame(['c|t|empty', 'c|t|blank'], $total->getSkippedEmpty());
        self::assertSame(['c|t|plural', 'c|t|other'], $total->getPartiallyApproved());
    }

    #[Test]
    public function aSkippedKeyCountsOnceAndStaysAString(): void
    {
        // The import of one file for two site languages with the same code
        // reports the same key twice. A key of digits only becomes an integer
        // as an array key.
        $result = new ImportResult();
        $result->recordSkippedUnapproved('123');
        $result->recordSkippedUnapproved('123');
        $result->recordSkippedEmpty('42');
        $result->recordSkippedEmpty('42');
        $result->recordPartiallyApproved('7');
        $result->recordPartiallyApproved('7');

        self::assertSame(['123'], $result->getSkippedUnapproved());
        self::assertSame(['42'], $result->getSkippedEmpty());
        self::assertSame(['7'], $result->getPartiallyApproved());
    }
}
