<?php
namespace Netresearch\NrTextdb\Command;

use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\COnfiguration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Localization\Parser\XliffParser;
use TYPO3\CMS\Core\Log\Logger;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Package\Exception\UnknownPackageException;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extensionmanager\Utility\ListUtility;


class ImportCommand extends Command
{
    /**
     * @var ListUtility
     */
    protected $listUtility;

    /**
     * @var string path for the language file within an extension.
     */
    private const LANG_FOLDER = 'Resources/Private/Language/';

    /**
     * @var string extension with the language file
     */
    protected $extension;

    /**
     * @var array
     */
    protected $extensions = [];

    /**
     * @var array
     */
    protected $languageFiles = [];

    /**
     * @var TranslationRepository
     */
    private $translationRepository;

    /**
     * @var ComponentRepository
     */
    private $componentRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var EnvironmentRepository
     */
    private $environmentRepository;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * Configures the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setDescription('Imports textdb records from language files');
        $this->setHelp('If you want to add textdb records to your extension. Create a file languagecode.textdb_import.xlf');
        $this->addArgument('extensionKey', InputArgument::OPTIONAL, 'Extension with language file');
        $this->addOption('override', 'o');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @throws UnknownPackageException
     * @throws IllegalObjectTypeException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initializeCommand();

        $extensionKey      = $input->getArgument('extensionKey');

        if (empty($extensionKey)) {
            $this->extensions = $this->listUtility->getAvailableAndInstalledExtensions(
                $this->listUtility->getAvailableExtensions()
            );
        } else {
            $this->extensions = [$this->listUtility->getExtension($extensionKey)];
        }

        $files = $this->importTranslationsFromFiles($output, $input->getOption('override'));
    }

    /**
     * Import the langauge into the database
     *
     * @param array           $files
     * @param OutputInterface $output
     * @param bool            $forceUpdate
     *
     * @throws IllegalObjectTypeException
     */
    private function importLanguageFiles(array $files, OutputInterface $output, bool $forceUpdate = false)
    {
        /** @var PersistenceManager $persistenceManager */
        $persistenceManager = $this->objectManager->get(PersistenceManager::class);
        $this->translationRepository->injectPersistenceManager($persistenceManager);

        $imported = 0;
        $updated  = 0;
        $errors   = 0;

        foreach ($files as $file) {

            $languageKey = $this->getLanguageKeyFromFile($file);
            $languageUid = $this->getLanguageId($languageKey);
            $fileContent = $this->getXliffParser()->getParsedData($file, $languageKey);

            $output->writeln("Import translations from file $file for langauge $languageKey ($languageUid)");

            $entries = $fileContent[$languageKey];

            foreach ($entries as $key => $data) {
                $environment = $this->environmentRepository->setCreateIfMissing(true)
                    ->findByName('default');
                $component = $this->componentRepository->setCreateIfMissing(true)
                    ->findByName($this->getComponentFromKey($key));
                $type = $this->typeRepository->setCreateIfMissing(true)
                    ->findByName($this->getTypeFromKey($key));

                $placeholder = $this->getPlaceholderFromKey($key);
                $value       = reset($data)['target'];

                $translationRecord = $this->translationRepository->find(
                    $environment,
                    $component,
                    $type,
                    $placeholder,
                    $languageUid,
                    true,
                    false
                );

                if ($translationRecord instanceof Translation && $translationRecord->isAutoCreated()) {
                    $forceUpdate = true;
                }

                /** Skip if translation exists and update is not requested */
                if ($translationRecord instanceof Translation && $forceUpdate === false) {
                    continue;
                }

                try {
                    $defaultTranslation = null;
                    if ($forceUpdate && $translationRecord instanceof Translation) {
                        $translationRecord->setValue($value);
                        $this->translationRepository->update($translationRecord);
                        $persistenceManager->persistAll();
                        $updated++;
                    } else {
                        if ($languageUid !== 0) {
                            ## If then language id is not 0 first get the default langauge translation.
                            $defaultTranslation = $this->translationRepository->find(
                                $environment,
                                $component,
                                $type,
                                $placeholder,
                                0,
                                false
                            );
                            $persistenceManager->persistAll();
                        }

                        $translation = GeneralUtility::makeInstance(Translation::class);
                        $translation->setEnvironment($environment);
                        $translation->setComponent($component);
                        $translation->setType($type);
                        $translation->setPlaceholder($placeholder);
                        $translation->setValue($value);
                        $translation->setPid($this->getConfiguredPageId());
                        $translation->setLanguageUid($languageUid);
                        if ($defaultTranslation instanceof Translation) {
                            $translation->setL10nParent($defaultTranslation->getUid());
                        }
                        $this->translationRepository->add($translation);
                        $persistenceManager->persistAll();
                        $imported++;
                    }
                } catch (\Exception $exception) {
                    $output->writeln("<error>{$exception->getMessage()}</error>");
                    $this->getLogger()->error($exception->getMessage(), ['exception' => $exception]);
                    $errors++;
                }
            }

        }

        $output->writeln("Imported: $imported, Updated: $updated, Errors: $errors");
    }

    /**
     * Returns the sys_language_uid for a language code
     *
     * @param string $languageCode Language Code
     *
     * @return int
     */
    private function getLanguageId(string $languageCode): int
    {
        if ($languageCode === 'default') {
            $languageCode = 'en';
        }
        foreach ($this->getAllLanguages() as $localLanguage) {
            if ($languageCode === $localLanguage->getTwoLetterIsoCode()) {
                return $localLanguage->getLanguageId();
            }
        }

        return 0;
    }

    /**
     * Get the component from key
     *
     * @param $key
     *
     * @return mixed
     */
    protected function getComponentFromKey($key)
    {
        $parts = explode('|', $key);
        return $parts[0];
    }

    /**
     * Get the type from key
     *
     * @param $key
     *
     * @return mixed
     */
    protected function getTypeFromKey($key)
    {
        $parts = explode('|', $key);
        return $parts[1];
    }

    /**
     * Get the placeholder from key
     *
     * @param $key
     *
     * @return mixed
     */
    protected function getPlaceholderFromKey($key)
    {
        $parts = explode('|', $key);
        return $parts[2];
    }

    /**
     * Get All languages, configured for mfag.
     *
     * @return array|SiteLanguage[]
     */
    protected function getAllLanguages(): array
    {
        /** @var  SiteFinder $siteFinder */
        $siteFinder = $this->objectManager->get(SiteFinder::class);

        $sites = $siteFinder->getAllSites();

        return reset($sites)->getAllLanguages();
    }

    /**
     * Returns the extension configuration
     *
     * @return mixed
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    protected function getExtensionConfiguration()
    {
        return GeneralUtility::makeInstance(
            ExtensionConfiguration::class
        )->get('nr_textdb');
    }

    /**
     * Returns the page id to store the translations in
     *
     * @return int
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    protected function getConfiguredPageId(): int
    {
        $configuration = $this->getExtensionConfiguration();
        return (int) $configuration['textDbPid'];
    }

    /**
     * Returns the langauge key from the file name.
     *
     * @param string $file
     *
     * @return string
     */
    private function getLanguageKeyFromFile(string $file): string
    {
        $fileParts = explode('.', basename($file));

        if (count($fileParts) < 3) {
            return 'default';
        }
        return $fileParts[0];
    }

    /**
     * Get the xliff parser.
     *
     * @return XliffParser
     */
    private function getXliffParser(): XliffParser
    {
        return $this->objectManager->get(XliffParser::class);
    }


    /**
     * Get the xliff parser.
     *
     * @return TranslationRepository
     */
    private function getTranslationRepository(): TranslationRepository
    {
        return $this->objectManager->get(TranslationRepository::class);
    }

    /**
     * Initialises all needed repository instances
     *
     * @return void
     */
    private function initializeCommand(): void
    {
        Bootstrap::initializeBackendUser();
        $this->objectManager         = GeneralUtility::makeInstance(ObjectManager::class);
        $this->typeRepository        = $this->objectManager->get(TypeRepository::class);
        $this->componentRepository   = $this->objectManager->get(ComponentRepository::class);
        $this->environmentRepository = $this->objectManager->get(EnvironmentRepository::class);
        $this->translationRepository = $this->getTranslationRepository();
        $this->listUtility           = $this->objectManager->get(ListUtility::class);
    }

    /**
     * @param OutputInterface $output
     * @param bool            $forceUpdate
     *
     * @return array<string>
     * @throws IllegalObjectTypeException
     */
    protected function importTranslationsFromFiles(OutputInterface $output, bool $forceUpdate = false): array
    {
        $files = [];

        foreach ($this->extensions as $extKey => $extensionKey) {
            $folderPath = ExtensionManagementUtility::extPath($extKey) . self::LANG_FOLDER;

            if (false === file_exists($folderPath) || false === is_dir($folderPath)) {
                continue;
            }

            $files = glob($folderPath . "textdb*.xlf");
            $files = array_merge(glob($folderPath . "*.textdb*.xlf"), $files);
            if (empty($files)) {
                continue;
            }

            sort($files);
            $this->importLanguageFiles($files, $output, $forceUpdate);
        }

        return $files;
    }

    /**
     * Returns a logger instance
     *
     * @return Logger
     */
    private function getLogger(): Logger
    {
        return GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }
}
