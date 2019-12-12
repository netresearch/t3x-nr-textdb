<?php
namespace Netresearch\NrTextdb\Controller;

use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
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
     * @var TranslationRepository
     */
    private $componentRepository = null;

    /**
     * @var TranslationRepository
     */
    private $typeRepository = null;

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
     * @param ComponentRepository $componentRepository
     * @param TranslationRepository $translationRepository
     */
    public function __construct(
        TranslationRepository $translationRepository,
        TranslationService $translationService,
        PersistenceManager $persistenceManager,
        ComponentRepository $componentRepository,
        TypeRepository $typeRepository
    ) {
        $this->translationRepository = $translationRepository;
        $this->translationService    = $translationService;
        $this->persistenceManager    = $persistenceManager;
        $this->componentRepository   = $componentRepository;
        $this->typeRepository        = $typeRepository;
    }

    /**
     * action list
     *
     * @return void
     */
    public function listAction()
    {
        $defaultComponent = '';
        $defaultType = '';
        $defaultPlaceholder = '';
        $defaultValue = '';

        $config = $this->getConfigFromBeUserData();

        if ($this->request->hasArgument('component')) {
            $componentId = (int) $this->request->getArgument('component');
            $defaultComponent = $this->componentRepository->findByUid($componentId);
        }
        if (empty($componentId) && !$this->request->hasArgument('component')) {
            $componentId = $config['component'];
            $defaultComponent = $this->componentRepository->findByUid($componentId);
        }
        if ($this->request->hasArgument('type')) {
            $typeId = (int) $this->request->getArgument('type');
            $defaultType = $this->typeRepository->findByUid($typeId);
        }
        if (empty($typeId) && !$this->request->hasArgument('type')) {
            $typeId = $config['type'];
            $defaultType = $this->typeRepository->findByUid($typeId);
        }

        if ($this->request->hasArgument('placeholder')) {
            $placeholder = (string) trim($this->request->getArgument('placeholder'));
            $defaultPlaceholder = $placeholder;
        }
        if (empty($placeholder) && !$this->request->hasArgument('placeholder')) {
            $placeholder = $config['placeholder'];
            $defaultPlaceholder = $placeholder;
        }
        if ($this->request->hasArgument('value')) {
            $value = (string) trim($this->request->getArgument('value'));
            $defaultValue = $value;
        }
        if (empty($value) && !$this->request->hasArgument('value')) {
            $value = $config['value'];
            $defaultValue = $value;
        }

        $translations = $this->translationRepository->getAllRecordsByIdentifier(
            $componentId,
            $typeId,
            $placeholder,
            $value
        );

        $config['component'] = $componentId;
        $config['type'] = $typeId;
        $config['placeholder'] = $placeholder;
        $config['value'] = $value;

        $this->persistConfigInBeUserData($config);

        $this->view->assign('defaultComponent', $defaultComponent);
        $this->view->assign('defaultType', $defaultType);
        $this->view->assign('defaultPlaceholder', $defaultPlaceholder);
        $this->view->assign('defaultValue', $defaultValue);
        $this->view->assign('components', $this->componentRepository->findAll()->toArray());
        $this->view->assign('types', $this->typeRepository->findAll()->toArray());
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
                $this->translationRepository->findRecordByUid($uid),
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
                $originalTranslation->getComponent()->getName(),
                $originalTranslation->getEnvironment()->getName(),
                $originalTranslation->getType()->getName(),
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
     * Import translations from file
     *
     * @param array $translationFile File to import
     * @param bool  $update          check if entries should be updated
     *
     * @return void
     */
    public function importAction(array $translationFile = null, bool $update = false)
    {
        if (empty($translationFile)) {
            return;
        }

        $fileName = $translationFile['name'];
        $filePath = $translationFile['tmp_name'];

        $matches = [];
        if (false === (bool) preg_match('/^([a-z]{2,2}\.)?(textdb_import\.xlf)$/', $fileName, $matches)) {
            throw new \Exception('File name does not match the expected pattern');
        }

        $languageCode = trim($matches[1],'.');
        $languageCode = (empty($languageCode)) ? 'en' : $languageCode;
        $languageId   = 0;
        foreach ($this->translationService->getAllLanguages() as $language) {
            if ($language->getTwoLetterIsoCode() !== $languageCode) {
                continue;
            }

            $languageId    = $language->getLanguageId();
            $languageTitle = $language->getTitle();
        }

        $data = simplexml_load_file($filePath);
        /** @var \SimpleXMLElement $translation */
        $imported = 0;
        $updated  = 0;
        $errors = [];

        foreach ($data->file->body->children() as $translation) {
            $id = reset($translation->attributes()['id']);
            $parts = explode('|', $id);

            $component   = $parts[0];
            $type        = $parts[1];
            $placeholder = $parts[2];
            $value       = (empty($translation->target)) ? reset($translation->source) : reset($translation->target);

            try {
                if ($update) {
                    $updated++;
                    $persistenceManager = $this->objectManager->get(PersistenceManager::class);
                    $this->translationRepository->injectPersistenceManager($persistenceManager);
                    $translationRecord = $this->translationRepository->findEntry(
                        $component,
                        'default',
                        $type,
                        $placeholder,
                        $languageId
                    );
                    $translationRecord->setValue($value);
                    $this->translationRepository->update($translationRecord);
                    $persistenceManager->persistAll();
                } else {
                    $imported++;
                    $this->translationRepository->createTranslation(
                        $component,
                        'default',
                        $type,
                        $placeholder,
                        $languageId,
                        $value
                    );
                }
            } catch (\Exception $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        $this->view->assignMultiple(['updated' => $updated, 'imported' => $imported, 'errors' => $errors, 'language' => $languageTitle]);
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
    /**
     * Get module config from user data
     *
     * @return array
     */
    protected function getConfigFromBeUserData()
    {
        $serializedConfig = $GLOBALS['BE_USER']->getModuleData(static::class);
        $config = array();
        if (is_string($serializedConfig) && !empty($serializedConfig)) {
            $config = @unserialize($serializedConfig);
        }
        return $config;
    }

    /**
     * Save current config in be user settings
     *
     * @param array $config
     */
    protected function persistConfigInBeUserData(array $config)
    {
        $GLOBALS['BE_USER']->pushModuleData(static::class, serialize($config));
    }

}
