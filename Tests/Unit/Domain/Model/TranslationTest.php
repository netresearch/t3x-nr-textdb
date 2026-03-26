<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Unit\Domain\Model;

use DateTime;
use Netresearch\NrTextdb\Domain\Model\Component;
use Netresearch\NrTextdb\Domain\Model\Environment;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Model\Type;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(Translation::class)]
#[UsesClass(Component::class)]
#[UsesClass(Environment::class)]
#[UsesClass(Type::class)]
final class TranslationTest extends UnitTestCase
{
    private Translation $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new Translation();
    }

    #[Test]
    public function getValueReturnsEmptyStringByDefault(): void
    {
        self::assertSame('', $this->subject->getValue());
    }

    #[Test]
    public function setValueSetsAndReturnsValue(): void
    {
        $result = $this->subject->setValue('Test translation');

        self::assertSame('Test translation', $this->subject->getValue());
        self::assertSame($this->subject, $result, 'Fluent interface returns self');
    }

    #[Test]
    public function getEnvironmentReturnsNullByDefault(): void
    {
        self::assertNull($this->subject->getEnvironment());
    }

    #[Test]
    public function setEnvironmentSetsEnvironment(): void
    {
        $environment = new Environment();
        $result      = $this->subject->setEnvironment($environment);

        self::assertSame($environment, $this->subject->getEnvironment());
        self::assertSame($this->subject, $result);
    }

    #[Test]
    public function getComponentReturnsNullByDefault(): void
    {
        self::assertNull($this->subject->getComponent());
    }

    #[Test]
    public function setComponentSetsComponent(): void
    {
        $component = new Component();
        $result    = $this->subject->setComponent($component);

        self::assertSame($component, $this->subject->getComponent());
        self::assertSame($this->subject, $result);
    }

    #[Test]
    public function getTypeReturnsNullByDefault(): void
    {
        self::assertNull($this->subject->getType());
    }

    #[Test]
    public function setTypeSetsType(): void
    {
        $type   = new Type();
        $result = $this->subject->setType($type);

        self::assertSame($type, $this->subject->getType());
        self::assertSame($this->subject, $result);
    }

    #[Test]
    public function getPlaceholderReturnsEmptyStringByDefault(): void
    {
        self::assertSame('', $this->subject->getPlaceholder());
    }

    #[Test]
    public function setPlaceholderSetsPlaceholder(): void
    {
        $result = $this->subject->setPlaceholder('test.key');

        self::assertSame('test.key', $this->subject->getPlaceholder());
        self::assertSame($this->subject, $result);
    }

    #[Test]
    public function isAutoCreatedReturnsTrueForAutoCreateIdentifier(): void
    {
        $this->subject->setValue(Translation::AUTO_CREATE_IDENTIFIER);

        self::assertTrue($this->subject->isAutoCreated());
    }

    #[Test]
    public function isAutoCreatedReturnsFalseForRegularValue(): void
    {
        $this->subject->setValue('Regular translation');

        self::assertFalse($this->subject->isAutoCreated());
    }

    #[Test]
    public function getValueReturnsPlaceholderWhenAutoCreated(): void
    {
        $this->subject->setValue(Translation::AUTO_CREATE_IDENTIFIER);
        $this->subject->setPlaceholder('my.placeholder');

        self::assertSame('my.placeholder', $this->subject->getValue());
    }

    #[Test]
    public function getValueReturnsActualValueWhenNotAutoCreated(): void
    {
        $this->subject->setValue('Actual translation');
        $this->subject->setPlaceholder('my.placeholder');

        self::assertSame('Actual translation', $this->subject->getValue());
    }

    #[Test]
    public function getCrdateReturnsNullByDefault(): void
    {
        self::assertNull($this->subject->getCrdate());
    }

    #[Test]
    public function setCrdateSetsCrdate(): void
    {
        $date   = new DateTime('2024-01-01');
        $result = $this->subject->setCrdate($date);

        self::assertSame($date, $this->subject->getCrdate());
        self::assertSame($this->subject, $result);
    }

    #[Test]
    public function getTstampReturnsNullByDefault(): void
    {
        self::assertNull($this->subject->getTstamp());
    }

    #[Test]
    public function setTstampSetsTstamp(): void
    {
        $date   = new DateTime('2024-06-15');
        $result = $this->subject->setTstamp($date);

        self::assertSame($date, $this->subject->getTstamp());
        self::assertSame($this->subject, $result);
    }

    #[Test]
    public function getL10nParentReturnsZeroByDefault(): void
    {
        self::assertSame(0, $this->subject->getL10nParent());
    }

    #[Test]
    public function setL10nParentSetsL10nParent(): void
    {
        $result = $this->subject->setL10nParent(42);

        self::assertSame(42, $this->subject->getL10nParent());
        self::assertSame($this->subject, $result);
    }

    #[Test]
    public function isHiddenReturnsFalseByDefault(): void
    {
        self::assertFalse($this->subject->isHidden());
    }

    #[Test]
    public function setHiddenSetsHidden(): void
    {
        $result = $this->subject->setHidden(true);

        self::assertTrue($this->subject->isHidden());
        self::assertSame($this->subject, $result);
    }

    #[Test]
    public function isDeletedReturnsFalseByDefault(): void
    {
        self::assertFalse($this->subject->isDeleted());
    }

    #[Test]
    public function setDeletedSetsDeleted(): void
    {
        $result = $this->subject->setDeleted(true);

        self::assertTrue($this->subject->isDeleted());
        self::assertSame($this->subject, $result);
    }

    #[Test]
    public function getSortingReturnsZeroByDefault(): void
    {
        self::assertSame(0, $this->subject->getSorting());
    }

    #[Test]
    public function setSortingSetsSorting(): void
    {
        $result = $this->subject->setSorting(100);

        self::assertSame(100, $this->subject->getSorting());
        self::assertSame($this->subject, $result);
    }

    #[Test]
    public function setEnvironmentToNullClearsEnvironment(): void
    {
        $this->subject->setEnvironment(new Environment());
        $this->subject->setEnvironment(null);

        self::assertNull($this->subject->getEnvironment());
    }

    #[Test]
    public function isAutoCreatedReturnsFalseForEmptyString(): void
    {
        $this->subject->setValue('');
        self::assertFalse($this->subject->isAutoCreated());
    }
}
