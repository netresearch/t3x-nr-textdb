<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Controller;

use Exception;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use Netresearch\NrTextdb\Service\TranslationService;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use SimpleXMLElement;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Mvc\Exception\NoSuchArgumentException;
use TYPO3\CMS\Extbase\Mvc\Exception\StopActionException;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use ZipArchive;

use function is_string;

/**
 * TranslationController
 *
 * @author  Thomas Schöne <thomas.schoene@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class TranslationController extends ActionController
{
    /**
     * @var ModuleTemplateFactory
     */
    protected readonly ModuleTemplateFactory $moduleTemplateFactory;

    /**
     * @var PageRenderer
     */
    protected readonly PageRenderer $pageRenderer;

    /**
     * @var IconFactory
     */
    protected readonly IconFactory $iconFactory;

    /**
     * @var EnvironmentRepository
     */
    protected readonly EnvironmentRepository $environmentRepository;

    /**
     * @var TranslationRepository
     */
    protected readonly TranslationRepository $translationRepository;

    /**
     * @var ComponentRepository
     */
    protected readonly ComponentRepository $componentRepository;

    /**
     * @var TypeRepository
     */
    protected readonly TypeRepository $typeRepository;

    /**
     * @var TranslationService
     */
    protected readonly TranslationService $translationService;

    /**
     * @var PersistenceManager
     */
    protected readonly PersistenceManager $persistenceManager;

    /**
     * @var int
     */
    protected int $pid = 0;

    /**
     * TranslationController constructor.
     *
     * @param ModuleTemplateFactory $moduleTemplateFactory
     * @param PageRenderer          $pageRenderer
     * @param IconFactory           $iconFactory
     * @param EnvironmentRepository $environmentRepository
     * @param TranslationRepository $translationRepository
     * @param TranslationService    $translationService
     * @param PersistenceManager    $persistenceManager
     * @param ComponentRepository   $componentRepository
     * @param TypeRepository        $typeRepository
     */
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        PageRenderer $pageRenderer,
        IconFactory $iconFactory,
        EnvironmentRepository $environmentRepository,
        TranslationRepository $translationRepository,
        TranslationService $translationService,
        PersistenceManager $persistenceManager,
        ComponentRepository $componentRepository,
        TypeRepository $typeRepository
    ) {
        $this->environmentRepository = $environmentRepository;
        $this->translationRepository = $translationRepository;
        $this->translationService = $translationService;
        $this->persistenceManager = $persistenceManager;
        $this->componentRepository = $componentRepository;
        $this->typeRepository = $typeRepository;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->iconFactory = $iconFactory;

        $this->pageRenderer = $pageRenderer;
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/Modal');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/NrTextdb/TextDbModule');

        $this->environmentRepository->setCreateIfMissing(true);
        $this->typeRepository->setCreateIfMissing(true);
        $this->componentRepository->setCreateIfMissing(true);
        $this->translationRepository->setCreateIfMissing(true);
    }

    /**
     * Initialize Action
     *
     * @return void
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    protected function initializeAction(): void
    {
        parent::initializeAction();

        $this->pid = (int) ($this->getExtensionConfiguration()['textDbPid'] ?? 0);
    }

    /**
     * @return ResponseInterface
     */
    private function moduleResponse(): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);
        $moduleTemplate->setContent($this->view->render());

        $this->registerDocHeaderButtons($moduleTemplate);

        return $this->htmlResponse($moduleTemplate->renderContent());
    }

    /**
     * Shows the textDB entires
     *
     * @return ResponseInterface
     *
     * @throws InvalidQueryException
     * @throws NoSuchArgumentException
     */
    public function listAction(): ResponseInterface
    {
        $config = $this->getConfigFromBeUserData();

        if ($this->request->hasArgument('component')) {
            $componentId = (int) $this->request->getArgument('component');
        } else {
            $componentId = $config['component'] ?? 0;
        }

        if ($this->request->hasArgument('type')) {
            $typeId = (int) $this->request->getArgument('type');
        } else {
            $typeId = $config['type'] ?? 0;
        }

        if ($this->request->hasArgument('placeholder')) {
            $placeholder = trim((string) $this->request->getArgument('placeholder'));
        } else {
            $placeholder = $config['placeholder'] ?? null;
        }

        if ($this->request->hasArgument('value')) {
            $value = trim((string) $this->request->getArgument('value'));
        } else {
            $value = $config['value'] ?? null;
        }

        $defaultComponent   = $this->componentRepository->findByUid($componentId);
        $defaultType        = $this->typeRepository->findByUid($typeId);
        $defaultPlaceholder = $placeholder;
        $defaultValue       = $value;

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

        $this->view->assignMultiple([
            'defaultComponent'   => $defaultComponent,
            'defaultType'        => $defaultType,
            'defaultPlaceholder' => $defaultPlaceholder,
            'defaultValue'       => $defaultValue,
            'components'         => $this->componentRepository->findAll()->toArray(),
            'types'              => $this->typeRepository->findAll()->toArray(),
            'translations'       => $translations,
            'textDbPid'          => $this->pid,
            'action'             => 'list',
            'pagination'         => $this->getPagination(
                $translations,
                $this->settings['pagination'] ?? []
            ),
        ]);

        return $this->moduleResponse();
    }

    /**
     * Create an export of the current filtered textDB entries to import it safely into another system.
     *
     * @return ResponseInterface
     *
     * @throws RuntimeException
     * @throws InvalidQueryException
     * @throws StopActionException
     */
    public function exportAction(): ResponseInterface
    {
        $exportKey   = md5(uniqid('', true) . time()) . '-textdb-export';
        $exportDir   = '/tmp/' . $exportKey;
        $archivePath = $exportDir . '/export.zip';

        if (
            !is_dir($exportDir)
            && !mkdir($exportDir, 0700, true)
            && !is_dir($exportDir)
        ) {
            throw new RuntimeException(
                sprintf(
                    'Directory "%s" was not created',
                    $exportDir
                )
            );
        }

        [
            'component'   => $component,
            'type'        => $type,
            'placeholder' => $placeholder,
            'value'       => $value,
        ] = $this->getConfigFromBeUserData();

        if (empty($component) && empty($type)) {
            $this->addFlashMessageToQueue(
                'Export',
                $this->getLanguageService()->sL(
                    'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.error.filter'
                )
            );

            return $this->redirectToUri(
                $this->uriBuilder->reset()->uriFor('list')
            );
        }

        $languages = $this->translationService->getAllLanguages();

        $originals = [];

        foreach ($languages as $language) {
            $targetFileName     = $this->getExportFileNameForLanguage($language);
            $enableTargetMarker = $language->getTwoLetterIsoCode() !== 'en';

            if ($language->getLanguageId() === 0) {
                $translations = $this->translationRepository
                    ->getAllRecordsByIdentifier(
                        (int) $component,
                        (int) $type,
                        $placeholder,
                        $value
                    );

                $originals = $this->writeTranslationExportFile(
                    $translations,
                    $exportDir,
                    $targetFileName,
                    $enableTargetMarker
                );
            } else {
                $translations = $this->translationRepository
                    ->getTranslatedRecordsForLanguage(
                        $originals,
                        $language->getLanguageId()
                    );

                $this->writeTranslationExportFile(
                    $translations,
                    $exportDir,
                    $targetFileName,
                    $enableTargetMarker
                );
            }
        }

        $archive = GeneralUtility::makeInstance(ZipArchive::class);

        if ($archive->open($archivePath, ZipArchive::CREATE) !== true) {
            unlink($archivePath);
            $this->addFlashMessageToQueue(
                'Export',
                $this->getLanguageService()->sL(
                    'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.error.archive'
                )
            );

            return $this->redirectToUri(
                $this->uriBuilder->reset()->uriFor('list')
            );
        }

        foreach (glob($exportDir . '/*') as $translationFile) {
            $archive->addFile($translationFile, basename((string) $translationFile));
        }

        $archive->close();

        $response = $this->createStreamResponseFromFile($archivePath);

        shell_exec('rm -rf ' . $exportDir);

        return $response;
    }

    /**
     * Creates a stream response.
     *
     * @param string $file
     *
     * @return ResponseInterface
     */
    private function createStreamResponseFromFile(string $file): ResponseInterface
    {
        return $this->responseFactory
            ->createResponse()
            ->withAddedHeader(
                'Content-Type',
                'application/zip; charset=utf-8'
            )
            ->withAddedHeader(
                'Content-Transfer-Encoding',
                'binary'
            )
            ->withAddedHeader(
                'Content-Length',
                (string) (filesize($file) ?: '')
            )
            ->withAddedHeader(
                'Content-Disposition',
                'attachment; filename="textdb_export.zip";'
            )
            ->withBody($this->streamFactory->createStreamFromFile($file));
    }

    /**
     * Returns the name of the file for a given language.
     *
     * @param SiteLanguage $language
     *
     * @return string
     */
    private function getExportFileNameForLanguage(SiteLanguage $language): string
    {
        if ($language->getTwoLetterIsoCode() === 'en') {
            return 'textdb_import.xlf';
        }

        return $language->getTwoLetterIsoCode() . '.textdb_import.xlf';
    }

    /**
     * @param int $uid
     *
     * @return ResponseInterface
     */
    public function translatedAction(int $uid): ResponseInterface
    {
        $translated = array_merge(
            [
                $this->translationRepository->findRecordByUid($uid),
            ],
            $this->translationRepository->getTranslatedRecords($uid)
        );

        $languages    = $this->translationService->getAllLanguages();
        $untranslated = $languages;

        /** @var Translation $translation */
        foreach ($translated as $translation) {
            unset($untranslated[$translation->getLanguageUid()]);
        }

        $this->view->assign('originalUid', $uid);
        $this->view->assign('translated', $translated);
        $this->view->assign('untranslated', $untranslated);
        $this->view->assign('languages', $languages);

        return $this->moduleResponse();
    }

    /**
     * @param int   $parent
     * @param array $new
     * @param array $update
     *
     * @return ResponseInterface
     *
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws Exception
     */
    public function translateRecordAction(int $parent, array $new = [], array $update = []): ResponseInterface
    {
        $this->translationRepository->injectPersistenceManager($this->persistenceManager);

        /** @var Translation $originalTranslation */
        $originalTranslation = $this->translationRepository->findByUid($parent);

        if (
            ($originalTranslation->getComponent() !== null)
            && ($originalTranslation->getEnvironment() !== null)
            && ($originalTranslation->getType() !== null)
        ) {
            foreach ($new as $language => $value) {
                $this->translationRepository
                    ->createTranslation(
                        $originalTranslation->getComponent()->getName(),
                        $originalTranslation->getEnvironment()->getName(),
                        $originalTranslation->getType()->getName(),
                        $originalTranslation->getPlaceholder(),
                        $language,
                        $value
                    );
            }
        }

        foreach ($update as $translationUid => $value) {
            $translation = $this->translationRepository->findRecordByUid($translationUid);

            if ($translation !== null) {
                $translation->setValue($value);
                $this->translationRepository->update($translation);
            }
        }

        $this->persistenceManager->persistAll();

        return (new ForwardResponse('translated'))
            ->withControllerName('Translation')
            ->withExtensionName('NrTextdb')
            ->withArguments(['uid' => $parent]);
    }

    /**
     * Import translations from file
     *
     * @param null|array $translationFile File to import
     * @param bool       $update          check if entries should be updated
     *
     * @return ResponseInterface
     *
     * @throws IllegalObjectTypeException
     * @throws Exception
     */
    public function importAction(array $translationFile = null, bool $update = false): ResponseInterface
    {
        $this->view->assign('action', 'import');

        if (empty($translationFile) || empty($translationFile['name'])) {
            return $this->moduleResponse();
        }

        $fileName = $translationFile['name'];
        $filePath = $translationFile['tmp_name'];

        $matches     = [];
        $matchResult = (bool) preg_match('/^([a-z]{2}\.)?(textdb_(.*)\.xlf)$/', (string) $fileName, $matches);

        if ($matchResult === false) {
            $this->addFlashMessageToQueue(
                'Import',
                $this->getLanguageService()->sL(
                    'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.error.import'
                )
            );

            return $this->redirectToUri(
                $this->uriBuilder->reset()->uriFor('import')
            );
        }

        $languageCode = trim($matches[1], '.');
        $languageCode = empty($languageCode) ? 'en' : $languageCode;

        $imported = 0;
        $updated  = 0;
        $languages = [];
        $errors = [];

        foreach ($this->translationService->getAllLanguages() as $language) {
            if ($language->getTwoLetterIsoCode() !== $languageCode) {
                continue;
            }

            $languageId    = $language->getLanguageId();
            $languageTitle = $language->getTitle();
            $languages[]   = $languageTitle;

            libxml_use_internal_errors(true);

            $data      = simplexml_load_string(file_get_contents($filePath));
            $xmlErrors = libxml_get_errors();

            if (!empty($xmlErrors)) {
                foreach ($xmlErrors as $error) {
                    $errors[] = $error->message;
                }

                $this->view->assign('errors', $errors);
                return $this->moduleResponse();
            }

            /** @var PersistenceManager $persistenceManager */
            $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);
            $this->translationRepository->injectPersistenceManager($persistenceManager);

            /** @var SimpleXMLElement $translation */
            foreach ($data->file->body->children() as $translation) {
                $id = reset($translation->attributes()['id']);
                $parts = explode('|', (string) $id);

                $environmentFound = $this->environmentRepository->findByName('default');
                $componentFound   = $this->componentRepository->findByName($parts[0]);
                $typeFound        = $this->typeRepository->findByName($parts[1]);
                $placeholder      = $parts[2];

                $value = empty($translation->target)
                    ? (string) $translation->source
                    : (string) $translation->target;

                if (
                    ($environmentFound === null)
                    || ($componentFound === null)
                    || ($typeFound === null)
                ) {
                    continue;
                }

                $translationRecord = $this->translationRepository->find(
                    $environmentFound,
                    $componentFound,
                    $typeFound,
                    $placeholder,
                    $languageId,
                    true
                );

                if (
                    ($translationRecord instanceof Translation)
                    && $translationRecord->isAutoCreated()
                ) {
                    $update = true;
                }

                // Skip if translation exists and update is not requested
                if (
                    ($translationRecord instanceof Translation)
                    && ($update === false)
                ) {
                    continue;
                }

                try {
                    if ($update && $translationRecord instanceof Translation) {
                        $updated++;
                        $translationRecord->setValue($value);
                        $this->translationRepository->update($translationRecord);
                    } else {
                        $imported++;

                        if ($languageId !== 0) {
                            ## If then language id is not 0 first get the default langauge translation.
                            $defaultTranslation = $this->translationRepository->find(
                                $environmentFound,
                                $componentFound,
                                $typeFound,
                                $placeholder,
                                0,
                                false,
                                false
                            );
                        }

                        $translation = GeneralUtility::makeInstance(Translation::class);
                        $translation->setEnvironment($environmentFound);
                        $translation->setComponent($componentFound);
                        $translation->setType($typeFound);
                        $translation->setPlaceholder($placeholder);
                        $translation->setValue($value);
                        $translation->setPid($this->pid);
                        $translation->setLanguageUid($languageId);

                        if (isset($defaultTranslation) && $defaultTranslation instanceof Translation) {
                            $translation->setL10nParent($defaultTranslation->getUid());
                        }

                        $this->translationRepository->add($translation);
                    }

                    $persistenceManager->persistAll();
                } catch (Exception $exception) {
                    $errors[] = $exception->getMessage();
                }
            }
        }

        $this->view->assignMultiple([
            'updated'  => $updated,
            'imported' => $imported,
            'errors'   => $errors,
            'language' => implode(
                ',',
                $languages
            ),
        ]);

        return $this->moduleResponse();
    }

    /**
     * Get the extension configuration.
     *
     * @return mixed
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    protected function getExtensionConfiguration(): mixed
    {
        return GeneralUtility::makeInstance(ExtensionConfiguration::class)
            ->get('nr_textdb');
    }

    /**
     * Get module config from user data
     *
     * @return array
     */
    protected function getConfigFromBeUserData(): array
    {
        $serializedConfig = $this->getBackendUser()->getModuleData(static::class);
        $config           = [];

        if (is_string($serializedConfig) && !empty($serializedConfig)) {
            $config = unserialize(
                $serializedConfig,
                [
                    'allowed_classes' => true,
                ]
            );
        }

        return $config;
    }

    /**
     * Save current config in backend user settings
     *
     * @param array $config
     */
    protected function persistConfigInBeUserData(array $config): void
    {
        $this->getBackendUser()->pushModuleData(static::class, serialize($config));
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
    protected function writeTranslationExportFile(
        QueryResultInterface $translations,
        string $exportDir,
        string $fileName,
        bool $enableTargetMarker = false
    ): array {
        if ($translations->count() === 0) {
            return [];
        }

        $markup = file_get_contents(
            ExtensionManagementUtility::extPath(
                'nr_textdb',
                'Resources/Private/template.xlf'
            )
        );

        $entries               = '';
        $writtenTranslationIds = [];

        $maker = $enableTargetMarker === true ? 'target' : 'source';

        /** @var Translation $translation */
        foreach ($translations as $translation) {
            if (
                ($translation->getComponent() === null)
                || ($translation->getType() === null)
            ) {
                continue;
            }

            $writtenTranslationIds[] = $translation->getUid();

            $entries .= sprintf(
                <<<XML
            <trans-unit id="%s|%s|%s">
                <%s>
                    <![CDATA[%s]]>
                </%s>
            </trans-unit>
    XML . PHP_EOL,
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

    /**
     * Generates and registers buttons for the doc header.
     *
     * @param ModuleTemplate $moduleTemplate
     *
     * @return void
     */
    protected function registerDocHeaderButtons(ModuleTemplate $moduleTemplate): void
    {
        // Instantiate required classes
        $buttonBar = $moduleTemplate->getDocHeaderComponent()->getButtonBar();

        // Prepare an array for the button definitions
        $buttons = [
            [
                'label'     => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:button.label.list',
                'action'    => 'list',
                'icon'      => 'actions-list-alternative',
                'group'     => 1,
            ],
            [
                'label'     => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:button.label.export',
                'action'    => 'export',
                'icon'      => 'actions-database-export',
                'group'     => 1,
            ],
            [
                'label'     => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:import',
                'action'    => 'import',
                'icon'      => 'actions-database-import',
                'group'     => 1,
            ],
        ];

        // Add buttons from the definition to the doc header
        foreach ($buttons as $tableConfiguration) {
            $title = LocalizationUtility::translate(
                $tableConfiguration['label'],
                'nr_textdb'
            );

            $link = $this->uriBuilder
                ->reset()
                ->uriFor(
                    $tableConfiguration['action'],
                    [],
                    'Translation'
                );

            $icon = $this->iconFactory->getIcon(
                $tableConfiguration['icon'],
                Icon::SIZE_SMALL
            );

            $viewButton = $buttonBar
                ->makeLinkButton()
                ->setHref($link)
                ->setDataAttributes([
                    'toggle'    => 'tooltip',
                    'placement' => 'bottom',
                    'title'     => $title,
                ])
                ->setTitle($title)
                ->setIcon($icon);

            $buttonBar->addButton(
                $viewButton,
                ButtonBar::BUTTON_POSITION_LEFT,
                $tableConfiguration['group']
            );
        }
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * Returns an array with variables for the pagination. An array with pagination settings should be passed.
     * Applies default values if settings are not available:
     *
     * - pagination disabled
     * - itemsPerPage = 10
     *
     * @param QueryResultInterface $items
     * @param array                $settings
     *
     * @return array
     *
     * @throws NoSuchArgumentException
     */
    protected function getPagination(QueryResultInterface $items, array $settings): array
    {
        $currentPage = $this->request->hasArgument('currentPage')
            ? (int) $this->request->getArgument('currentPage') : 1;

        if (
            ($settings['enablePagination'] ?? false)
            && ((int) $settings['itemsPerPage'] > 0)
        ) {
            $paginator = new QueryResultPaginator(
                $items,
                $currentPage,
                (int) ($settings['itemsPerPage'] ?? 15)
            );

            return [
                'paginator'  => $paginator,
                'pagination' => new SimplePagination($paginator),
            ];
        }

        return [];
    }

    /**
     * Adds a flash message to the queue.
     *
     * @param string $messageTitle
     * @param string $messageText
     * @param int    $severity
     *
     * @return void
     */
    protected function addFlashMessageToQueue(
        string $messageTitle,
        string $messageText,
        int $severity = AbstractMessage::ERROR
    ): void {
        /** @var FlashMessage $message */
        $message = GeneralUtility::makeInstance(
            FlashMessage::class,
            $messageText,
            $messageTitle,
            $severity,
            true
        );

        GeneralUtility::makeInstance(FlashMessageService::class)
            ->getMessageQueueByIdentifier()
            ->addMessage($message);
    }

    /**
     * Shorthand functionality for fetching the language service
     *
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
