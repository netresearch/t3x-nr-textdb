<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Unit\Domain\Model;

use Netresearch\NrTextdb\Domain\Model\Type;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 *
 * @author Thomas Schöne <thomas.schoene@netresearch.de>
 */
#[CoversClass(Type::class)]
final class TypeTest extends UnitTestCase
{
    /**
     * @var Type
     */
    protected Type $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new Type();
    }

    #[Test]
    public function getNameReturnsInitialValueForString(): void
    {
        self::assertSame(
            '',
            $this->subject->getName()
        );
    }

    #[Test]
    public function setNameForStringSetsName(): void
    {
        $this->subject->setName('Conceived at T3CON10');

        self::assertSame(
            'Conceived at T3CON10',
            $this->subject->getName()
        );
    }
}
