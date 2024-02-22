<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Unit\Controller;

use Netresearch\NrTextdb\Controller\TranslationController;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3Fluid\Fluid\View\ViewInterface;

/**
 * Test case.
 *
 * @author Thomas Schöne <thomas.schoene@netresearch.de>
 */
class TranslationControllerTest extends UnitTestCase
{
    /**
     * @var TranslationController
     */
    protected TranslationController $subject;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->getMockBuilder(TranslationController::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    /**
     * @test
     */
    public function listActionFetchesAllTranslationsFromRepositoryAndAssignsThemToView(): void
    {
        self::markTestIncomplete('Rework');

        $allTranslations = $this->getMockBuilder(QueryResultInterface::class)
            ->disableOriginalConstructor()
            ->getMock();

        $translationRepository = $this->getMockBuilder(TranslationRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $translationRepository
            ->expects(self::once())
            ->method('getAllRecordsByIdentifier')
            ->willReturn($allTranslations);

        $this->inject($this->subject, 'translationRepository', $translationRepository);

        $view = $this->getMockBuilder(ViewInterface::class)->getMock();

        $view
            ->expects(self::once())
            ->method('assign')
            ->with('translations', $allTranslations);

        $this->inject($this->subject, 'view', $view);

        $this->subject->listAction();
    }
}
