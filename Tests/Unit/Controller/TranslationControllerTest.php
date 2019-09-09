<?php
namespace Netresearch\NrTextdb\Tests\Unit\Controller;

/**
 * Test case.
 *
 * @author Thomas Schöne <thomas.schoene@netresearch.de>
 */
class TranslationControllerTest extends \TYPO3\TestingFramework\Core\Unit\UnitTestCase
{
    /**
     * @var \Netresearch\NrTextdb\Controller\TranslationController
     */
    protected $subject = null;

    protected function setUp()
    {
        parent::setUp();
        $this->subject = $this->getMockBuilder(\Netresearch\NrTextdb\Controller\TranslationController::class)
            ->setMethods(['redirect', 'forward', 'addFlashMessage'])
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function tearDown()
    {
        parent::tearDown();
    }

    /**
     * @test
     */
    public function listActionFetchesAllTranslationsFromRepositoryAndAssignsThemToView()
    {

        $allTranslations = $this->getMockBuilder(\TYPO3\CMS\Extbase\Persistence\ObjectStorage::class)
            ->disableOriginalConstructor()
            ->getMock();

        $translationRepository = $this->getMockBuilder(\Netresearch\NrTextdb\Domain\Repository\TranslationRepository::class)
            ->setMethods(['findAll'])
            ->disableOriginalConstructor()
            ->getMock();
        $translationRepository->expects(self::once())->method('findAll')->will(self::returnValue($allTranslations));
        $this->inject($this->subject, 'translationRepository', $translationRepository);

        $view = $this->getMockBuilder(\TYPO3\CMS\Extbase\Mvc\View\ViewInterface::class)->getMock();
        $view->expects(self::once())->method('assign')->with('translations', $allTranslations);
        $this->inject($this->subject, 'view', $view);

        $this->subject->listAction();
    }

    /**
     * @test
     */
    public function showActionAssignsTheGivenTranslationToView()
    {
        $translation = new \Netresearch\NrTextdb\Domain\Model\Translation();

        $view = $this->getMockBuilder(\TYPO3\CMS\Extbase\Mvc\View\ViewInterface::class)->getMock();
        $this->inject($this->subject, 'view', $view);
        $view->expects(self::once())->method('assign')->with('translation', $translation);

        $this->subject->showAction($translation);
    }

    /**
     * @test
     */
    public function createActionAddsTheGivenTranslationToTranslationRepository()
    {
        $translation = new \Netresearch\NrTextdb\Domain\Model\Translation();

        $translationRepository = $this->getMockBuilder(\Netresearch\NrTextdb\Domain\Repository\TranslationRepository::class)
            ->setMethods(['add'])
            ->disableOriginalConstructor()
            ->getMock();

        $translationRepository->expects(self::once())->method('add')->with($translation);
        $this->inject($this->subject, 'translationRepository', $translationRepository);

        $this->subject->createAction($translation);
    }

    /**
     * @test
     */
    public function editActionAssignsTheGivenTranslationToView()
    {
        $translation = new \Netresearch\NrTextdb\Domain\Model\Translation();

        $view = $this->getMockBuilder(\TYPO3\CMS\Extbase\Mvc\View\ViewInterface::class)->getMock();
        $this->inject($this->subject, 'view', $view);
        $view->expects(self::once())->method('assign')->with('translation', $translation);

        $this->subject->editAction($translation);
    }

    /**
     * @test
     */
    public function updateActionUpdatesTheGivenTranslationInTranslationRepository()
    {
        $translation = new \Netresearch\NrTextdb\Domain\Model\Translation();

        $translationRepository = $this->getMockBuilder(\Netresearch\NrTextdb\Domain\Repository\TranslationRepository::class)
            ->setMethods(['update'])
            ->disableOriginalConstructor()
            ->getMock();

        $translationRepository->expects(self::once())->method('update')->with($translation);
        $this->inject($this->subject, 'translationRepository', $translationRepository);

        $this->subject->updateAction($translation);
    }

    /**
     * @test
     */
    public function deleteActionRemovesTheGivenTranslationFromTranslationRepository()
    {
        $translation = new \Netresearch\NrTextdb\Domain\Model\Translation();

        $translationRepository = $this->getMockBuilder(\Netresearch\NrTextdb\Domain\Repository\TranslationRepository::class)
            ->setMethods(['remove'])
            ->disableOriginalConstructor()
            ->getMock();

        $translationRepository->expects(self::once())->method('remove')->with($translation);
        $this->inject($this->subject, 'translationRepository', $translationRepository);

        $this->subject->deleteAction($translation);
    }
}
