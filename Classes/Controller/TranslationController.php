<?php
namespace Netresearch\NrTextdb\Controller;

use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Service\TranslationService;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;

/***
 *
 * This file is part of the "Netresearch TextDB" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019 Thomas Schöne <thomas.schoene@netresearch.de>, Netresearch
 *
 ***/
/**
 * TranslationController
 */
class TranslationController extends \TYPO3\CMS\Extbase\Mvc\Controller\ActionController
{

    /**
     * @var TranslationRepository
     */
    private $translationRepository = null;

    /**
     * @var TranslationService
     */
    private $translationService;

    /**
     * @var PersistenceManager
     */
    private $persistenceManager;

    /**
     * TranslationController constructor.
     *
     * @param TranslationRepository $translationRepository
     * @param TranslationService    $translationService
     * @param PersistenceManager    $persistenceManager
     */
    public function __construct(
        TranslationRepository $translationRepository,
        TranslationService $translationService,
        PersistenceManager $persistenceManager
    ) {
        $this->translationRepository = $translationRepository;
        $this->translationService    = $translationService;
        $this->persistenceManager    = $persistenceManager;
    }

    /**
     * action list
     *
     * @return void
     */
    public function listAction()
    {
        $translations = $this->translationRepository->findAllWithHidden();

        $this->view->assign('translations', $translations);
        $this->view->assign('textDbPid', $this->getConfiguredPageId());
    }

    /**
     * @param int $uid
     */
    public function translatedAction(int $uid)
    {
        $translated = array_merge(
            [
                $this->translationRepository->findRecordByUid($uid)
            ],
            $this->translationRepository->getTranslatedRecords($uid)
        );

        $languages  = $this->translationService->getAllLanguages();
        $untranslated = $languages;
        /** @var Translation $translation */
        foreach ($translated as $translation) {
            unset($untranslated[$translation->getLanguageUid()]);
        }
        $this->view->assign('originalUid', $uid);
        $this->view->assign('translated', $translated);
        $this->view->assign('untranslated', $untranslated);
        $this->view->assign('languages', $languages);

        echo $this->view->render();
        exit;
    }

    /**
     * @param int   $parent
     * @param array $new
     * @param array $update
     *
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException
     */
    public function translateRecordAction(int $parent, array $new = [], array $update = [])
    {
        $this->translationRepository->injectPersistenceManager($this->persistenceManager);

        /** @var Translation $originalTranslation */
        $originalTranslation = $this->translationRepository->findByUid($parent);

        foreach ($new as $language => $value) {
            $this->translationRepository->createTranslation(
                $originalTranslation->getComponent(),
                $originalTranslation->getEnvironment(),
                $originalTranslation->getType(),
                $originalTranslation->getPlaceholder(),
                $language,
                $value
            );
        }

        foreach ($update as $translationUid => $value) {
            /** @var Translation $translation */
            $translation = $this->translationRepository->findRecordByUid($translationUid);
            $translation->setValue($value);
            $this->translationRepository->update($translation);
            $this->persistenceManager->persistAll();
        }

        $this->forward('translated', 'Translation', 'NrTextdb', ['uid' => $parent]);
    }

    /**
     * Set up the doc header properly here
     *
     * @param ViewInterface $view
     * @return void
     */
    protected function initializeView(ViewInterface $view)
    {
        if ($view instanceof BackendTemplateView) {
            $view->getModuleTemplate()->getPageRenderer()->loadRequireJsModule('TYPO3/CMS/Backend/Modal');
        }
    }

    /**
     * Get the extension configuration.
     *
     * @return mixed
     */
    protected function getExtensionConfiguration()
    {
        return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\COnfiguration\ExtensionConfiguration::class
        )->get('nr_textdb');
    }

    /**
     * Get the configured pid from extension configuration.
     *
     * @return mixed
     */
    protected function getConfiguredPageId()
    {
        $configuration = $this->getExtensionConfiguration();
        return $configuration['textDbPid'];
    }
}
