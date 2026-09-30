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

namespace Netresearch\NrTextdb\Tests\Unit\Domain\Model;

use Netresearch\NrTextdb\Domain\Model\Environment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 *
 * @author Thomas Schöne <thomas.schoene@netresearch.de>
 */
#[CoversClass(Environment::class)]
final class EnvironmentTest extends UnitTestCase
{
    protected Environment $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new Environment();
    }

    #[Test]
    public function getNameReturnsInitialValueForString(): void
    {
        self::assertSame(
            '',
            $this->subject->getName(),
        );
    }

    #[Test]
    public function setNameForStringSetsName(): void
    {
        $this->subject->setName('Conceived at T3CON10');

        self::assertSame(
            'Conceived at T3CON10',
            $this->subject->getName(),
        );
    }
}
