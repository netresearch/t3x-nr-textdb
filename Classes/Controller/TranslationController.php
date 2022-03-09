<?php
namespace Netresearch\NrTextdb\Controller;

use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use Netresearch\NrTextdb\Service\TranslationService;
use TYPO3\CMS\Backend\View\BackendTemplateView;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Mvc\View\ViewInterface;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

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
class TranslationController extends ActionController
{
    /**
     * @var EnvironmentRepository
     */
    private $environmentRepository;

    /**
     * @var TranslationRepository
     */
    private $translationRepository = null;

    /**
     * @var ComponentRepository
     */
    private $componentRepository = null;

    /**
     * @var TypeRepository
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
     * @var LanguageService
     */
    private $languageService;

    /**
     * BackendTemplateContainer
     *
     * @var BackendTemplateView
     */
    protected $view;

    /**
     * Backend Template Container
     *
     * @var string
     */
    protected $defaultViewObjectName = BackendTemplateView::class;

    /**
     * TranslationController constructor.
     *
     * @param EnvironmentRepository $environmentRepository
     * @param TranslationRepository $translationRepository
     * @param TranslationService    $translationService
     * @param PersistenceManager    $persistenceManager
     * @param ComponentRepository   $componentRepository
     * @param TypeRepository        $typeRepository
     * @param LanguageService       $languageService
     */
    public function __construct(
        EnvironmentRepository $environmentRepository,
        TranslationRepository $translationRepository,
        TranslationService $translationService,
        PersistenceManager $persistenceManager,
        ComponentRepository $componentRepository,
        TypeRepository $typeRepository,
        LanguageService $languageService
    ) {
        $this->environmentRepository = $environmentRepository;
        $this->translationRepository = $translationRepository;
        $this->translationService    = $translationService;
        $this->persistenceManager    = $persistenceManager;
        $this->componentRepository   = $componentRepository;
        $this->typeRepository        = $typeRepository;
        $this->languageService       = $languageService;

        $this->environmentRepository->setCreateIfMissing(true);
        $this->typeRepository->setCreateIfMissing(true);
        $this->componentRepository->setCreateIfMissing(true);
        $this->translationRepository->setCreateIfMissing(true);
    }

    /**
     * Shows the textDB entires
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
        $this->view->assign('action', 'list');
    }

    /**
     * Create an export of the current filtered textDB entries to import it safely into a other system.
     *
     * @return void
     * @throws StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException
     */
    public function exportAction()
    {
        $exportKey = md5(uniqid().time()) . '-textdb-export';
        $exportDir = "/tmp/" . $exportKey;
        $archivePath = $exportDir . "/export.zip";

        if (! is_dir($exportDir)) {
            mkdir($exportDir, 0700, true);
        }

        [
            "component"   => $component,
            "type"        => $type,
            "placeholder" => $placeholder,
            "value"       => $value,
        ] = $this->getConfigFromBeUserData();

        if (empty($component) && empty($type)) {
            $this->addFlashMessage(
                $this->languageService->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.error.filter'),
                "Export",
                FlashMessage::WARNING
            );
            $this->redirect('list');
            return;
        }

        $languages = $this->translationService->getAllLanguages();

        $originals = [];

        foreach ($languages as $language) {
            $targetFileName = $this->getExportFileNameForLanguage($language);
            $enableTargetMarker = $language->getTwoLetterIsoCode() !== "en";
            if ($language->getLanguageId() === 0) {
                $translations = $this->translationRepository->getAllRecordsByIdentifier(
                    $component,
                    $type,
                    $placeholder,
                    $value
                );
                $originals = $this->writeTranslationExportFile($translations, $exportDir, $targetFileName, $enableTargetMarker);
            } else {
                $translations = $this->translationRepository->getTranslatedRecordsForLanguage(
                    $originals, $language->getLanguageId()
                );
                $this->writeTranslationExportFile($translations, $exportDir, $targetFileName, $enableTargetMarker);
            }

        }

        $archive = GeneralUtility::makeInstance(\ZipArchive::class);
        if ($archive->open($archivePath, \ZipArchive::CREATE) !== true) {
            unlink($archivePath);
            $this->addFlashMessage(
                $this->languageService->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.error.archive'),
                "Export",
                FlashMessage::ERROR
            );
            $this->redirect('list');
            return;
        }

        foreach (glob($exportDir . "/*") as $translationFile) {
            $archive->addFile($translationFile, basename($translationFile));
        }

        $archive->close();

        header('Content-Disposition: attachment; filename="textdb_export.zip";' );
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: ' . filesize($archivePath) );
        header('Content-Type: application/zip; charset=utf-8');
        readfile($archivePath);
        shell_exec("rm -rf " . $exportDir);
        exit;
    }

    /**
     * Returns the name of the File fo a given language.
     *
     * @param SiteLanguage $language
     *
     * @return string
     */
    private function getExportFileNameForLanguage(SiteLanguage $language): string
    {
        if ($language->getTwoLetterIsoCode() === 'en') {
            return "textdb_import.xlf";
        }

        return $language->getTwoLetterIsoCode() .  ".textdb_import.xlf";
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
     * @throws StopActionException
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws \Exception
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
        $this->view->assign('action', 'import');

        if (empty($translationFile)) {
            return;
        }

        $fileName = $translationFile['name'];
        $filePath = $translationFile['tmp_name'];

        $matches = [];
        if (false === (bool) preg_match('/^([a-z]{2,2}\.)?(textdb_(.*)\.xlf)$/', $fileName, $matches)) {
            throw new \Exception('File name does not match the expected pattern');
        }

        $languageCode = trim($matches[1],'.');
        $languageCode = (empty($languageCode)) ? 'en' : $languageCode;

        /** @var \SimpleXMLElement $translation */
        $imported = 0;
        $updated  = 0;
        $languages = [];

        foreach ($this->translationService->getAllLanguages() as $language) {
            if ($language->getTwoLetterIsoCode() !== $languageCode) {
                continue;
            }

            $languageId    = $language->getLanguageId();
            $languageTitle = $language->getTitle();
            $languages[]   = $languageTitle;

            $errors = [];

            libxml_use_internal_errors(true);
            $data = simplexml_load_file($filePath);
            $xmlErrors = libxml_get_errors();
            if (!empty($xmlErrors)) {
                foreach ($xmlErrors as $error) {
                    $errors[] = $error->message;
                }

                $this->view->assign('errors', $errors);
                return;
            }

            /** @var PersistenceManager $persistenceManager */
            $persistenceManager = $this->objectManager->get(PersistenceManager::class);
            $this->translationRepository->injectPersistenceManager($persistenceManager);

            foreach ($data->file->body->children() as $translation) {
                $id = reset($translation->attributes()['id']);
                $parts = explode('|', $id);

                $environment = $this->environmentRepository->findByName('default');
                $component   = $this->componentRepository->findByName($parts[0]);
                $type        = $this->typeRepository->findByName($parts[1]);
                $placeholder = $parts[2];
                $value       = (empty($translation->target)) ? (string) $translation->source : (string) $translation->target;

                $translationRecord = $this->translationRepository->find(
                    $environment,
                    $component,
                    $type,
                    $placeholder,
                    $languageId,
                    true
                );

                if ($translationRecord instanceof Translation && $translationRecord->isAutoCreated()) {
                    $update = true;
                }

                /** Skip if translation exists and update is not requested */
                if ($translationRecord instanceof Translation && $update === false) {
                    continue;
                }

                try {
                    if ($update && $translationRecord instanceof Translation) {
                        $updated++;
                        $translationRecord->setValue($value);
                        $this->translationRepository->update($translationRecord);
                        $persistenceManager->persistAll();
                    } else {
                        $imported++;
                        if ($languageId !== 0) {
                            ## If then language id is not 0 first get the default langauge translation.
                            $defaultTranslation = $this->translationRepository->find(
                                $environment,
                                $component,
                                $type,
                                $placeholder,
                                0,
                                false,
                                false
                            );
                        }

                        $translation = GeneralUtility::makeInstance(Translation::class);
                        $translation->setEnvironment($environment);
                        $translation->setComponent($component);
                        $translation->setType($type);
                        $translation->setPlaceholder($placeholder);
                        $translation->setValue($value);
                        $translation->setPid($this->getConfiguredPageId());
                        $translation->setLanguageUid($languageId);
                        if ($defaultTranslation instanceof Translation) {
                            $translation->setL10nParent($defaultTranslation->getUid());
                        }
                        $this->translationRepository->add($translation);
                        $persistenceManager->persistAll();
                    }
                } catch (\Exception $exception) {
                    $errors[] = $exception->getMessage();
                }
            }
        }

        $this->view->assignMultiple(['updated' => $updated, 'imported' => $imported, 'errors' => $errors, 'language' => implode(',', $languages)]);
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
    protected function getConfigFromBeUserData(): array
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

    /**
     * Write the translation file for export and returns the uid of entries written to file
     *
     * @param QueryResultInterface $translations
     * @param string               $exportDir
     * @param string               $fileName
     * @param bool                 $enableTargetMarker
     *
     * @return array
     */
    protected function writeTranslationExportFile(QueryResultInterface $translations, string $exportDir, string $fileName, bool $enableTargetMarker = false): array
    {
        if (empty($translations) || $translations->count() === 0) {
            return [];
        }

        $markup                = file_get_contents(ExtensionManagementUtility::extPath('nr_textdb', 'Resources/Private/template.xlf'));
        $entries               = "";
        $writtenTranslationIds = [];

        $maker = ($enableTargetMarker === true) ? "target" : "source";

        /** @var Translation $translation */
        foreach ($translations as $translation) {
            $writtenTranslationIds[] = $translation->getUid();
            $entries     .= sprintf(
                '<trans-unit id="%s|%s|%s">
                    <%s><![CDATA[%s]]></%s>
                </trans-unit>' . PHP_EOL,
                $translation->getComponent()->getName(),
                $translation->getType()->getName(),
                $translation->getPlaceholder(),
                $maker,
                $translation->getValue(),
                $maker
            );
        }

        $fileContent = sprintf($markup, $entries);
        file_put_contents($exportDir . '/' . $fileName, $fileContent);
        return $writtenTranslationIds;
    }

}
