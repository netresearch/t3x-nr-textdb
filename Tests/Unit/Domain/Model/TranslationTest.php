<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Unit\Domain\Model;

use Netresearch\NrTextdb\Domain\Model\Component;
use Netresearch\NrTextdb\Domain\Model\Environment;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Model\Type;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case.
 *
 * @author Thomas Schöne <thomas.schoene@netresearch.de>
 */
#[CoversClass(Translation::class)]
#[UsesClass(Component::class)]
#[UsesClass(Environment::class)]
#[UsesClass(Type::class)]
class TranslationTest extends UnitTestCase
{
    /**
     * @var Translation
     */
    protected Translation $subject;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new Translation();
    }

    /**
     * @test
     */
    public function getValueReturnsInitialValueForString(): void
    {
        self::assertSame(
            '',
            $this->subject->getValue()
        );
    }

    /**
     * @test
     */
    public function setValueForStringSetsValue(): void
    {
        $this->subject->setValue('Conceived at T3CON10');

        self::assertSame(
            'Conceived at T3CON10',
            $this->subject->getValue()
        );
    }

    /**
     * @test
     */
    public function getEnvironmentReturnsInitialValueForEnvironment(): void
    {
        self::assertEquals(
            null,
            $this->subject->getEnvironment()
        );
    }

    /**
     * @test
     */
    public function setEnvironmentForEnvironmentSetsEnvironment(): void
    {
        $environmentFixture = new Environment();
        $this->subject->setEnvironment($environmentFixture);

        self::assertSame(
            $environmentFixture,
            $this->subject->getEnvironment()
        );
    }

    /**
     * @test
     */
    public function getComponentReturnsInitialValueForComponent(): void
    {
        self::assertEquals(
            null,
            $this->subject->getComponent()
        );
    }

    /**
     * @test
     */
    public function setComponentForComponentSetsComponent(): void
    {
        $componentFixture = new Component();
        $this->subject->setComponent($componentFixture);

        self::assertSame(
            $componentFixture,
            $this->subject->getComponent()
        );
    }

    /**
     * @test
     */
    public function getTypeReturnsInitialValueForType(): void
    {
        self::assertEquals(
            null,
            $this->subject->getType()
        );
    }

    /**
     * @test
     */
    public function setTypeForTypeSetsType(): void
    {
        $typeFixture = new Type();
        $this->subject->setType($typeFixture);

        self::assertSame(
            $typeFixture,
            $this->subject->getType()
        );
    }
}
