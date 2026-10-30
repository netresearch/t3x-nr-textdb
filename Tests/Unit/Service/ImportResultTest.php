<?php

/*
 * This file is part of the package netresearch/nr-textdb.
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
}
