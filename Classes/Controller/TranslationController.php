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
use Netresearch\NrTextdb\Service\ImportService;
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
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Exception\UnknownObjectException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use ZipArchive;

use function is_string;
use function sprintf;

/**
 * TranslationController.
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
     * @var ModuleTemplate
     */
    protected ModuleTemplate $moduleTemplate;

    /**
     * @var ExtensionConfiguration
     */
    protected ExtensionConfiguration $extensionConfiguration;

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
     * @var ImportService
     */
    private readonly ImportService $importService;

    /**
     * @var int
     */
    protected int $pid = 0;

    /**
     * TranslationController constructor.
     *
     * @param ModuleTemplateFactory  $moduleTemplateFactory
     * @param ExtensionConfiguration $extensionConfiguration
     * @param IconFactory            $iconFactory
     * @param EnvironmentRepository  $environmentRepository
     * @param TranslationRepository  $translationRepository
     * @param TranslationService     $translationService
     * @param PersistenceManager     $persistenceManager
     * @param ComponentRepository    $componentRepository
     * @param TypeRepository         $typeRepository
     * @param ImportService          $importService
     */
    public function __construct(
        ModuleTemplateFactory $moduleTemplateFactory,
        ExtensionConfiguration $extensionConfiguration,
        IconFactory $iconFactory,
        EnvironmentRepository $environmentRepository,
        TranslationRepository $translationRepository,
        TranslationService $translationService,
        PersistenceManager $persistenceManager,
        ComponentRepository $componentRepository,
        TypeRepository $typeRepository,
        ImportService $importService,
    ) {
        $this->extensionConfiguration = $extensionConfiguration;
        $this->environmentRepository  = $environmentRepository;
        $this->translationRepository  = $translationRepository;
        $this->translationService     = $translationService;
        $this->persistenceManager     = $persistenceManager;
        $this->componentRepository    = $componentRepository;
        $this->typeRepository         = $typeRepository;
        $this->moduleTemplateFactory  = $moduleTemplateFactory;
        $this->iconFactory            = $iconFactory;

        $this->environmentRepository->setCreateIfMissing(true);
        $this->typeRepository->setCreateIfMissing(true);
        $this->componentRepository->setCreateIfMissing(true);
        $this->translationRepository->setCreateIfMissing(true);

        $this->importService = $importService;
    }

    /**
     * Initialize Action.
     *
     * @return void
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    protected function initializeAction(): void
    {
        parent::initializeAction();

        $this->moduleTemplate = $this->getModuleTemplate();
        $this->pid            = (int) ($this->getExtensionConfiguration()['textDbPid'] ?? 0);

        $this->registerDocHeaderButtons();
    }

    /**
     * Returns the module template instance.
     *
     * @return ModuleTemplate
     */
    private function getModuleTemplate(): ModuleTemplate
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($this->request);

        $moduleTemplate
            ->setModuleId('typo3-module-textdb-translation')
            ->setModuleClass('typo3-module-textdb-translation');

        $moduleTemplate->assign('settings', $this->settings);

        return $moduleTemplate;
    }

    /**
     * Shows the textDB entries.
     *
     * @return ResponseInterface
     *
     * @throws InvalidQueryException
     */
    public function listAction(): ResponseInterface
    {
        if ($this->pid === 0) {
            $this->moduleTemplate->addFlashMessage(
                'Please configure a valid storage page ID in the extension configuration.',
                'TextDb',
                ContextualFeedbackSeverity::ERROR
            );
        }

        $config      = $this->getConfigFromBeUserData();
        $componentId = (int) ($config['component'] ?? 0);
        $typeId      = (int) ($config['type'] ?? 0);
        $placeholder = is_string($config['placeholder']) ? $config['placeholder'] : null;
        $value       = is_string($config['value']) ? $config['value'] : null;

        if ($this->request->hasArgument('component')) {
            $componentId = (int) $this->request->getArgument('component');
        }

        if ($this->request->hasArgument('type')) {
            $typeId = (int) $this->request->getArgument('type');
        }

        if ($this->request->hasArgument('placeholder')) {
            $placeholder = trim((string) $this->request->getArgument('placeholder'));
        }

        if ($this->request->hasArgument('value')) {
            $value = trim((string) $this->request->getArgument('value'));
        }

        $defaultComponent   = $this->componentRepository->findByUid($componentId);
        $defaultType        = $this->typeRepository->findByUid($typeId);
        $defaultPlaceholder = $placeholder;
        $defaultValue       = $value;

        $translations = $this->translationRepository
            ->findAllByComponentTypePlaceholderValueAndLanguage(
                $componentId,
                $typeId,
                $placeholder,
                $value
            );

        $config['component']   = $componentId;
        $config['type']        = $typeId;
        $config['placeholder'] = $placeholder;
        $config['value']       = $value;

        $this->persistConfigInBeUserData($config);

        $this->moduleTemplate->assignMultiple([
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

        return $this->moduleTemplate->renderResponse('Translation/List');
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
                $this->translationRepository->findByUid($uid),
            ],
            $this->translationRepository->findByPidAndLanguage($uid)
        );

        $languages    = $this->translationService->getAllLanguages();
        $untranslated = $languages;

        /** @var Translation $translation */
        foreach ($translated as $translation) {
            unset($untranslated[$translation->getSysLanguageUid()]);
        }

        $this->moduleTemplate->assign('originalUid', $uid);
        $this->moduleTemplate->assign('translated', $translated);
        $this->moduleTemplate->assign('untranslated', $untranslated);
        $this->moduleTemplate->assign('languages', $languages);

        return $this->moduleTemplate->renderResponse('Translation/Translated');
    }

    /**
     * @param int                         $parent
     * @param array<int<-1, max>, string> $new
     * @param array<int, string>          $update
     *
     * @return ResponseInterface
     *
     * @throws IllegalObjectTypeException
     * @throws UnknownObjectException
     * @throws Exception
     */
    public function translateRecordAction(int $parent, array $new = [], array $update = []): ResponseInterface
    {
        $parentTranslation = $this->translationRepository->findByUid($parent);

        if ($parentTranslation instanceof Translation) {
            foreach ($new as $language => $value) {
                $translation = $this->translationService
                    ->createTranslationFromParent(
                        $parentTranslation,
                        $language,
                        $value
                    );

                if ($translation instanceof Translation) {
                    $this->translationRepository->add($translation);
                }
            }
        }

        foreach ($update as $translationUid => $value) {
            $translation = $this->translationRepository->findByUid($translationUid);

            if ($translation instanceof Translation) {
                $translation->setValue($value);

                $this->translationRepository->update($translation);
            }
        }

        $this->persistenceManager->persistAll();

        return (new ForwardResponse('translated'))
            ->withControllerName('Translation')
            ->withExtensionName('NrTextdb')
            ->withArguments([
                'uid' => $parent,
            ]);
    }

    /**
     * Create an export of the current filtered textDB entries to import it safely into another system.
     *
     * @return ResponseInterface
     *
     * @throws RuntimeException
     * @throws InvalidQueryException
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

        if (($component === 0) && ($type === 0)) {
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
            $enableTargetMarker = $language->getLocale()->getLanguageCode() !== 'en';

            if ($language->getLanguageId() === 0) {
                $translations = $this->translationRepository
                    ->findAllByComponentTypePlaceholderValueAndLanguage(
                        (int) $component,
                        (int) $type,
                        is_string($placeholder) ? $placeholder : null,
                        is_string($value) ? $value : null
                    );

                $originals = $this->writeTranslationExportFile(
                    $language,
                    $translations,
                    $exportDir,
                    $targetFileName,
                    $enableTargetMarker
                );
            } else {
                $translations = $this->translationRepository
                    ->findByTranslationsAndLanguage(
                        $originals,
                        $language->getLanguageId()
                    );

                $this->writeTranslationExportFile(
                    $language,
                    $translations,
                    $exportDir,
                    $targetFileName,
                    $enableTargetMarker
                );
            }
        }

        /** @var ZipArchive $archive */
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

        $translationFiles = glob($exportDir . '/*');

        if ($translationFiles !== false) {
            /** @var string $translationFile */
            foreach ($translationFiles as $translationFile) {
                $archive->addFile(
                    $translationFile,
                    basename($translationFile)
                );
            }
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
        $filesize = filesize($file);

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
                (string) ($filesize !== false ? $filesize : '')
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
        if ($language->getLocale()->getLanguageCode() === 'en') {
            return 'textdb_import.xlf';
        }

        return $language->getLocale()->getLanguageCode() . '.textdb_import.xlf';
    }

    /**
     * Import translations from a file.
     *
     * @param bool $update Check if entries should be updated
     *
     * @return ResponseInterface
     */
    public function importAction(bool $update = false): ResponseInterface
    {
        $this->moduleTemplate->assign('action', 'import');

        /** @var UploadedFile|null $translationFile */
        $translationFile = $this->request->getUploadedFiles()['translationFile'] ?? null;

        if (
            ($translationFile === null)
            || ($translationFile->getClientFilename() === null)
            || ($translationFile->getClientFilename() === '')
        ) {
            return $this->moduleTemplate->renderResponse('Translation/Import');
        }

        $filename     = $translationFile->getClientFilename();
        $uploadedFile = $translationFile->getTemporaryFileName();

        $matches     = [];
        $matchResult = (bool) preg_match('/^([a-z]{2}\.)?(textdb_(.*)\.xlf)$/', $filename, $matches);

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
        $languageCode = $languageCode === '' ? 'en' : $languageCode;

        $imported    = 0;
        $updated     = 0;
        $languages   = [];
        $errors      = [];
        $forceUpdate = $update;

        foreach ($this->translationService->getAllLanguages() as $language) {
            if ($language->getLocale()->getLanguageCode() !== $languageCode) {
                continue;
            }

            $languageUid   = max(-1, $language->getLanguageId());
            $languageTitle = $language->getTitle();
            $languages[]   = $languageTitle;

            $uploadedFileContent = file_get_contents($uploadedFile);

            if ($uploadedFileContent === false) {
                continue;
            }

            libxml_use_internal_errors(true);

            // We can't use the XliffParser here, due it's limitations regarding filenames
            $data      = simplexml_load_string($uploadedFileContent);
            $xmlErrors = libxml_get_errors();

            if ($data === false) {
                continue;
            }

            if ($xmlErrors !== []) {
                foreach ($xmlErrors as $error) {
                    $errors[] = $error->message;
                }

                $this->moduleTemplate->assign('errors', $errors);

                return $this->moduleTemplate->renderResponse('Translation/Import');
            }

            /** @var SimpleXMLElement $translation */
            foreach ($data->file->body->children() as $translation) {
                $key = (string) $translation->attributes()['id'];

                $componentName = $this->getComponentFromKey($key);
                if ($componentName === null) {
                    throw new RuntimeException('Missing component name in key: ' . $key);
                }

                $typeName = $this->getTypeFromKey($key);
                if ($typeName === null) {
                    throw new RuntimeException('Missing type name in key: ' . $key);
                }

                $placeholder = $this->getPlaceholderFromKey($key);
                if ($placeholder === null) {
                    throw new RuntimeException('Missing placeholder in key: ' . $key);
                }

                $value = $translation->target->getName() === ''
                    ? (string) $translation->source
                    : (string) $translation->target;

                $this->importService
                    ->importEntry(
                        $languageUid,
                        $componentName,
                        $typeName,
                        $placeholder,
                        trim($value),
                        $forceUpdate,
                        $imported,
                        $updated,
                        $errors
                    );
            }
        }

        $this->moduleTemplate->assignMultiple([
            'updated'  => $updated,
            'imported' => $imported,
            'errors'   => $errors,
            'language' => implode(
                ',',
                $languages
            ),
        ]);

        return $this->moduleTemplate->renderResponse('Translation/Import');
    }

    /**
     * Get the component from a key.
     *
     * @param string $key
     *
     * @return string|null
     */
    private function getComponentFromKey(string $key): ?string
    {
        $parts = explode('|', $key);

        return ($parts[0] !== '') ? $parts[0] : null;
    }

    /**
     * Get the type from a key.
     *
     * @param string $key
     *
     * @return string|null
     */
    private function getTypeFromKey(string $key): ?string
    {
        $parts = explode('|', $key);

        return isset($parts[1]) && ($parts[1] !== '') ? $parts[1] : null;
    }

    /**
     * Get the placeholder from key.
     *
     * @param string $key
     *
     * @return string|null
     */
    private function getPlaceholderFromKey(string $key): ?string
    {
        $parts = explode('|', $key);

        return isset($parts[2]) && ($parts[2] !== '') ? $parts[2] : null;
    }

    /**
     * Get the extension configuration.
     *
     * @return mixed
     *
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    private function getExtensionConfiguration(): mixed
    {
        return $this->extensionConfiguration->get('nr_textdb');
    }

    /**
     * Get module config from user data.
     *
     * @return array<array-key, int|string>
     */
    private function getConfigFromBeUserData(): array
    {
        $serializedConfig = $this->getBackendUser()
            ->getModuleData(static::class);

        if (
            is_string($serializedConfig)
            && ($serializedConfig !== '')
        ) {
            return unserialize(
                $serializedConfig,
                [
                    'allowed_classes' => true,
                ]
            );
        }

        return [];
    }

    /**
     * Save current config in backend user settings.
     *
     * @param array<array-key, int|string> $config
     */
    private function persistConfigInBeUserData(array $config): void
    {
        $this->getBackendUser()->pushModuleData(static::class, serialize($config));
    }

    /**
     * Write the translation file for export and returns the uid of entries written to file.
     *
     * @param SiteLanguage                           $language
     * @param QueryResultInterface<int, Translation> $translations
     * @param string                                 $exportDir
     * @param string                                 $filename
     * @param bool                                   $enableTargetMarker
     *
     * @return int[]
     */
    private function writeTranslationExportFile(
        SiteLanguage $language,
        QueryResultInterface $translations,
        string $exportDir,
        string $filename,
        bool $enableTargetMarker = false,
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

        if ($markup === false) {
            return [];
        }

        $entries               = '';
        $writtenTranslationIds = [];

        $marker = $enableTargetMarker ? 'target' : 'source';

        /** @var Translation $translation */
        foreach ($translations as $translation) {
            if ($translation->getComponent() === null) {
                continue;
            }

            if ($translation->getType() === null) {
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
                $marker,
                $translation->getValue(),
                $marker
            );
        }

        $fileContent = sprintf(
            $markup,
            $language->getLocale()->getLanguageCode(),
            $entries
        );

        file_put_contents($exportDir . '/' . $filename, $fileContent);

        return $writtenTranslationIds;
    }

    /**
     * Generates and registers buttons for the doc header.
     *
     * @return void
     */
    private function registerDocHeaderButtons(): void
    {
        // Instantiate required classes
        $buttonBar = $this->moduleTemplate
            ->getDocHeaderComponent()
            ->getButtonBar();

        // Prepare an array for the button definitions
        $buttons = [
            [
                'label'  => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:button.label.list',
                'action' => 'list',
                'icon'   => 'actions-list-alternative',
                'group'  => 1,
            ],
            [
                'label'  => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:button.label.export',
                'action' => 'export',
                'icon'   => 'actions-database-export',
                'group'  => 1,
            ],
            [
                'label'  => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:import',
                'action' => 'import',
                'icon'   => 'actions-database-import',
                'group'  => 1,
            ],
        ];

        // Add buttons from the definition to the doc header
        foreach ($buttons as $tableConfiguration) {
            $title = $this->translate($tableConfiguration['label']) ?? '';

            $link = $this->uriBuilder
                ->reset()
                ->uriFor(
                    $tableConfiguration['action'],
                    [],
                    'Translation'
                );

            $icon = $this->iconFactory->getIcon(
                $tableConfiguration['icon'],
                IconSize::SMALL
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
    private function getBackendUser(): BackendUserAuthentication
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
     * @param QueryResultInterface<int, Translation> $items
     * @param array<string, int|bool>                $settings
     *
     * @return array<string, mixed>
     */
    private function getPagination(QueryResultInterface $items, array $settings): array
    {
        $currentPage = $this->request->hasArgument('currentPage')
            ? (int) $this->request->getArgument('currentPage') : 1;

        if (
            isset($settings['enablePagination'])
            && ((bool) $settings['enablePagination'])
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
     * @param string                     $messageTitle
     * @param string                     $messageText
     * @param ContextualFeedbackSeverity $severity
     *
     * @return void
     */
    private function addFlashMessageToQueue(
        string $messageTitle,
        string $messageText,
        ContextualFeedbackSeverity $severity = ContextualFeedbackSeverity::ERROR,
    ): void {
        /** @var FlashMessage $message */
        $message = GeneralUtility::makeInstance(
            FlashMessage::class,
            $messageText,
            $messageTitle,
            $severity,
            true
        );

        /** @var FlashMessageService $flashMessageService */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);

        $flashMessageService
            ->getMessageQueueByIdentifier()
            ->addMessage($message);
    }

    /**
     * Shorthand functionality for fetching the language service.
     *
     * @return LanguageService
     */
    private function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * @param string       $key
     * @param array<mixed> $arguments
     *
     * @return string|null
     */
    private function translate(string $key, array $arguments = []): ?string
    {
        return LocalizationUtility::translate($key, 'NrTextdb', $arguments);
    }
}
