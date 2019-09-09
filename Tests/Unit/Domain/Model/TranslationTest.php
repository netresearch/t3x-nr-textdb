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
    public function getValueReturnsInitialValueForString()
    {
        self::assertSame(
            '',
            $this->subject->getValue()
        );
    }

    /**
     * @test
     */
    public function setValueForStringSetsValue()
    {
        $this->subject->setValue('Conceived at T3CON10');

        self::assertAttributeEquals(
            'Conceived at T3CON10',
            'value',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function getEnvironmentReturnsInitialValueForEnvironment()
    {
        $newObjectStorage = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
        self::assertEquals(
            $newObjectStorage,
            $this->subject->getEnvironment()
        );
    }

    /**
     * @test
     */
    public function setEnvironmentForObjectStorageContainingEnvironmentSetsEnvironment()
    {
        $environment = new \Netresearch\NrTextdb\Domain\Model\Environment();
        $objectStorageHoldingExactlyOneEnvironment = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
        $objectStorageHoldingExactlyOneEnvironment->attach($environment);
        $this->subject->setEnvironment($objectStorageHoldingExactlyOneEnvironment);

        self::assertAttributeEquals(
            $objectStorageHoldingExactlyOneEnvironment,
            'environment',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function addEnvironmentToObjectStorageHoldingEnvironment()
    {
        $environment = new \Netresearch\NrTextdb\Domain\Model\Environment();
        $environmentObjectStorageMock = $this->getMockBuilder(\TYPO3\CMS\Extbase\Persistence\ObjectStorage::class)
            ->setMethods(['attach'])
            ->disableOriginalConstructor()
            ->getMock();

        $environmentObjectStorageMock->expects(self::once())->method('attach')->with(self::equalTo($environment));
        $this->inject($this->subject, 'environment', $environmentObjectStorageMock);

        $this->subject->addEnvironment($environment);
    }

    /**
     * @test
     */
    public function removeEnvironmentFromObjectStorageHoldingEnvironment()
    {
        $environment = new \Netresearch\NrTextdb\Domain\Model\Environment();
        $environmentObjectStorageMock = $this->getMockBuilder(\TYPO3\CMS\Extbase\Persistence\ObjectStorage::class)
            ->setMethods(['detach'])
            ->disableOriginalConstructor()
            ->getMock();

        $environmentObjectStorageMock->expects(self::once())->method('detach')->with(self::equalTo($environment));
        $this->inject($this->subject, 'environment', $environmentObjectStorageMock);

        $this->subject->removeEnvironment($environment);
    }

    /**
     * @test
     */
    public function getComponentReturnsInitialValueForComponent()
    {
        $newObjectStorage = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
        self::assertEquals(
            $newObjectStorage,
            $this->subject->getComponent()
        );
    }

    /**
     * @test
     */
    public function setComponentForObjectStorageContainingComponentSetsComponent()
    {
        $component = new \Netresearch\NrTextdb\Domain\Model\Component();
        $objectStorageHoldingExactlyOneComponent = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
        $objectStorageHoldingExactlyOneComponent->attach($component);
        $this->subject->setComponent($objectStorageHoldingExactlyOneComponent);

        self::assertAttributeEquals(
            $objectStorageHoldingExactlyOneComponent,
            'component',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function addComponentToObjectStorageHoldingComponent()
    {
        $component = new \Netresearch\NrTextdb\Domain\Model\Component();
        $componentObjectStorageMock = $this->getMockBuilder(\TYPO3\CMS\Extbase\Persistence\ObjectStorage::class)
            ->setMethods(['attach'])
            ->disableOriginalConstructor()
            ->getMock();

        $componentObjectStorageMock->expects(self::once())->method('attach')->with(self::equalTo($component));
        $this->inject($this->subject, 'component', $componentObjectStorageMock);

        $this->subject->addComponent($component);
    }

    /**
     * @test
     */
    public function removeComponentFromObjectStorageHoldingComponent()
    {
        $component = new \Netresearch\NrTextdb\Domain\Model\Component();
        $componentObjectStorageMock = $this->getMockBuilder(\TYPO3\CMS\Extbase\Persistence\ObjectStorage::class)
            ->setMethods(['detach'])
            ->disableOriginalConstructor()
            ->getMock();

        $componentObjectStorageMock->expects(self::once())->method('detach')->with(self::equalTo($component));
        $this->inject($this->subject, 'component', $componentObjectStorageMock);

        $this->subject->removeComponent($component);
    }

    /**
     * @test
     */
    public function getTypeReturnsInitialValueForType()
    {
        $newObjectStorage = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
        self::assertEquals(
            $newObjectStorage,
            $this->subject->getType()
        );
    }

    /**
     * @test
     */
    public function setTypeForObjectStorageContainingTypeSetsType()
    {
        $type = new \Netresearch\NrTextdb\Domain\Model\Type();
        $objectStorageHoldingExactlyOneType = new \TYPO3\CMS\Extbase\Persistence\ObjectStorage();
        $objectStorageHoldingExactlyOneType->attach($type);
        $this->subject->setType($objectStorageHoldingExactlyOneType);

        self::assertAttributeEquals(
            $objectStorageHoldingExactlyOneType,
            'type',
            $this->subject
        );
    }

    /**
     * @test
     */
    public function addTypeToObjectStorageHoldingType()
    {
        $type = new \Netresearch\NrTextdb\Domain\Model\Type();
        $typeObjectStorageMock = $this->getMockBuilder(\TYPO3\CMS\Extbase\Persistence\ObjectStorage::class)
            ->setMethods(['attach'])
            ->disableOriginalConstructor()
            ->getMock();

        $typeObjectStorageMock->expects(self::once())->method('attach')->with(self::equalTo($type));
        $this->inject($this->subject, 'type', $typeObjectStorageMock);

        $this->subject->addType($type);
    }

    /**
     * @test
     */
    public function removeTypeFromObjectStorageHoldingType()
    {
        $type = new \Netresearch\NrTextdb\Domain\Model\Type();
        $typeObjectStorageMock = $this->getMockBuilder(\TYPO3\CMS\Extbase\Persistence\ObjectStorage::class)
            ->setMethods(['detach'])
            ->disableOriginalConstructor()
            ->getMock();

        $typeObjectStorageMock->expects(self::once())->method('detach')->with(self::equalTo($type));
        $this->inject($this->subject, 'type', $typeObjectStorageMock);

        $this->subject->removeType($type);
    }
}
