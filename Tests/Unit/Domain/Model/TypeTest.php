<?php
namespace Netresearch\NrTextdb\Tests\Unit\Domain\Model;

/**
 * Test case.
 *
 * @author Thomas Schöne <thomas.schoene@netresearch.de>
 */
class TypeTest extends \TYPO3\TestingFramework\Core\Unit\UnitTestCase
{
    /**
     * @var \Netresearch\NrTextdb\Domain\Model\Type
     */
    protected $subject = null;

    protected function setUp()
    {
        parent::setUp();
        $this->subject = new \Netresearch\NrTextdb\Domain\Model\Type();
    }

    protected function tearDown()
    {
        parent::tearDown();
    }

    /**
     * @test
     */
    public function getNameReturnsInitialValueForString()
    {
        self::assertSame(
            '',
            $this->subject->getName()
        );
    }

    /**
     * @test
     */
    public function setNameForStringSetsName()
    {
        $this->subject->setName('Conceived at T3CON10');

        self::assertAttributeEquals(
            'Conceived at T3CON10',
            'name',
            $this->subject
        );
    }
}
