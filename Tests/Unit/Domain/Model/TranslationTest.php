<?php
namespace Netresearch\NrTextdb\Tests\Unit\Domain\Model;

/**
 * Test case.
 *
 * @author Thomas Schöne <thomas.schoene@netresearch.de>
 */
class TranslationTest extends \TYPO3\TestingFramework\Core\Unit\UnitTestCase
{
    /**
     * @var \Netresearch\NrTextdb\Domain\Model\Translation
     */
    protected $subject = null;

    protected function setUp()
    {
        parent::setUp();
        $this->subject = new \Netresearch\NrTextdb\Domain\Model\Translation();
    }

    protected function tearDown()
    {
        parent::tearDown();
    }

    /**
     * @test
     */
    public function getEnvironmentReturnsInitialValueForEnvironment()
    {
        self::assertEquals(
            null,
            $this->subject->getEnvironment()
        );
    }

    /**
     * @test
     */
    public function setEnvironmentForEnvironmentSetsEnvironment()
    {
        $environmentFixture = new \Netresearch\NrTextdb\Domain\Model\Environment();
        $this->subject->setEnvironment($environmentFixture);

        self::assertAttributeEquals(
            $environmentFixture,
            'environment',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getComponentReturnsInitialValueForComponent()
    {
        self::assertEquals(
            null,
            $this->subject->getComponent()
        );
    }

    /**
     * @test
     */
    public function setComponentForComponentSetsComponent()
    {
        $componentFixture = new \Netresearch\NrTextdb\Domain\Model\Component();
        $this->subject->setComponent($componentFixture);

        self::assertAttributeEquals(
            $componentFixture,
            'component',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getTypeReturnsInitialValueForType()
    {
        self::assertEquals(
            null,
            $this->subject->getType()
        );
    }

    /**
     * @test
     */
    public function setTypeForTypeSetsType()
    {
        $typeFixture = new \Netresearch\NrTextdb\Domain\Model\Type();
        $this->subject->setType($typeFixture);

        self::assertAttributeEquals(
            $typeFixture,
            'type',
            $this->subject
        );
    }
}
