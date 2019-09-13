<?php
namespace Netresearch\NrTextdb\Controller;

use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;

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
     * @var \Netresearch\NrTextdb\Domain\Repository\Translation
     * @inject
     */
    private $translationRepository = null;

    /**
     * action list
     *
     * @return void
     */
    public function listAction()
    {
        $translations = $this->translationRepository->findAll();
        $this->view->assign('translations', $translations);
    }

    /**
     * action show
     *
     * @param \Netresearch\NrTextdb\Domain\Model\Translation $translation
     * @return void
     */
    public function showAction(\Netresearch\NrTextdb\Domain\Model\Translation $translation)
    {
        $this->view->assign('translation', $translation);
    }

    /**
     * action new
     *
     * @return void
     */
    public function newAction()
    {
    }

    /**
     * action create
     *
     * @param \Netresearch\NrTextdb\Domain\Model\Translation $newTranslation
     * @return void
     */
    public function createAction(\Netresearch\NrTextdb\Domain\Model\Translation $newTranslation)
    {
        $this->addFlashMessage('The object was created. Please be aware that this action is publicly accessible unless you implement an access check. See https://docs.typo3.org/typo3cms/extensions/extension_builder/User/Index.html', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::WARNING);
        $this->translationRepository->add($newTranslation);
        $this->redirect('list');
    }

    /**
     * action edit
     *
     * @param \Netresearch\NrTextdb\Domain\Model\Translation $translation
     * @ignorevalidation $translation
     * @return void
     */
    public function editAction(\Netresearch\NrTextdb\Domain\Model\Translation $translation)
    {
        $this->view->assign('translation', $translation);
    }

    /**
     * action update
     *
     * @param \Netresearch\NrTextdb\Domain\Model\Translation $translation
     * @return void
     */
    public function updateAction(\Netresearch\NrTextdb\Domain\Model\Translation $translation)
    {
        $this->addFlashMessage('The object was updated. Please be aware that this action is publicly accessible unless you implement an access check. See https://docs.typo3.org/typo3cms/extensions/extension_builder/User/Index.html', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::WARNING);
        $this->translationRepository->update($translation);
        $this->redirect('list');
    }

    /**
     * action delete
     *
     * @param \Netresearch\NrTextdb\Domain\Model\Translation $translation
     * @return void
     */
    public function deleteAction(\Netresearch\NrTextdb\Domain\Model\Translation $translation)
    {
        $this->addFlashMessage('The object was deleted. Please be aware that this action is publicly accessible unless you implement an access check. See https://docs.typo3.org/typo3cms/extensions/extension_builder/User/Index.html', '', \TYPO3\CMS\Core\Messaging\AbstractMessage::WARNING);
        $this->translationRepository->remove($translation);
        $this->redirect('list');
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
}
