<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Command;

use Exception;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Localization\Parser\XliffParser;
use TYPO3\CMS\Core\Package\Exception\UnknownPackageException;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extensionmanager\Utility\ListUtility;

use function count;

/**
 * Class ImportCommand
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class ImportCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Path for the language file within an extension.
     *
     * @var string
     */
    private const LANG_FOLDER = 'Resources/Private/Language/';

    /**
     * @var TranslationRepository
     */
    protected TranslationRepository $translationRepository;

    /**
     * @var ComponentRepository
     */
    protected ComponentRepository $componentRepository;

    /**
     * @var TypeRepository
     */
    protected TypeRepository $typeRepository;

    /**
     * @var EnvironmentRepository
     */
    protected EnvironmentRepository $environmentRepository;

    /**
     * @var ListUtility
     */
    protected ListUtility $listUtility;

    /**
     * @var XliffParser
     */
    protected XliffParser $xliffParser;

    /**
     * @var string extension with the language file
     */
    protected string $extension = '';

    /**
     * @var array
     */
    protected array $extensions = [];

    /**
     * Constructor.
     *
     * @param TranslationRepository $translationRepository
     * @param ComponentRepository   $componentRepository
     * @param TypeRepository        $typeRepository
     * @param EnvironmentRepository $environmentRepository
     * @param ListUtility           $listUtility
     * @param XliffParser           $xliffParser
     */
    public function __construct(
        TranslationRepository $translationRepository,
        ComponentRepository $componentRepository,
        TypeRepository $typeRepository,
        EnvironmentRepository $environmentRepository,
        ListUtility $listUtility,
        XliffParser $xliffParser
    ) {
        parent::__construct();

        $this->translationRepository = $translationRepository;
        $this->componentRepository   = $componentRepository;
        $this->typeRepository        = $typeRepository;
        $this->environmentRepository = $environmentRepository;
        $this->listUtility           = $listUtility;
        $this->xliffParser           = $xliffParser;
    }

    /**
     * Bootstrap.
     */
    protected function bootstrap(): void
    {
        Bootstrap::initializeBackendUser(CommandLineUserAuthentication::class);
        Bootstrap::initializeBackendAuthentication();
    }

    /**
     * Configures the command.
     *
     * @return void
     */
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setDescription('Imports textdb records from language files')
            ->setHelp(
            'If you want to add textdb records to your extension. Create a file languagecode.textdb_import.xlf'
            )
            ->addArgument(
                'extensionKey',
                InputArgument::OPTIONAL,
                'Extension with language file'
            )
            ->addOption(
                'override',
                'o'
            );
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int
     *
     * @throws UnknownPackageException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrap();

        $extensionKey = $input->getArgument('extensionKey');

        if (empty($extensionKey)) {
            $this->extensions = $this->listUtility->getAvailableAndInstalledExtensions(
                $this->listUtility->getAvailableExtensions()
            );
        } else {
            $this->extensions = [
                $this->listUtility->getExtension($extensionKey),
            ];
        }

        $this->importTranslationsFromFiles(
            $output,
            $input->getOption('override')
        );

        return Command::SUCCESS;
    }

    /**
     * Import the langauge into the database
     *
     * @param array           $files
     * @param OutputInterface $output
     * @param bool            $forceUpdate
     */
    protected function importLanguageFiles(array $files, OutputInterface $output, bool $forceUpdate = false): void
    {
        /** @var PersistenceManager $persistenceManager */
        $persistenceManager = GeneralUtility::makeInstance(PersistenceManager::class);

        $this->translationRepository
            ->injectPersistenceManager($persistenceManager);

        $imported = 0;
        $updated  = 0;
        $errors   = 0;

        foreach ($files as $file) {
            $languageKey = $this->getLanguageKeyFromFile($file);
            $languageUid = $this->getLanguageId($languageKey);
            $fileContent = $this->xliffParser->getParsedData($file, $languageKey);

            $output->writeln("Import translations from file $file for langauge $languageKey ($languageUid)");

            $entries = $fileContent[$languageKey];

            foreach ($entries as $key => $data) {
                try {
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

                    $value = $data[0]['target'] ?? null;
                    if ($value === null) {
                        throw new RuntimeException('Missing value in key: ' . $key);
                    }

                    $environmentFound = $this->environmentRepository
                        ->setCreateIfMissing(true)
                        ->findByName('default');

                    $componentFound = $this->componentRepository
                        ->setCreateIfMissing(true)
                        ->findByName($componentName);

                    $typeFound = $this->typeRepository
                        ->setCreateIfMissing(true)
                        ->findByName($typeName);

                    $translationRecord = null;

                    if (
                        ($environmentFound !== null)
                        && ($componentFound !== null)
                        && ($typeFound !== null)
                    ) {
                        $translationRecord = $this->translationRepository
                            ->find(
                                $environmentFound,
                                $componentFound,
                                $typeFound,
                                $placeholder,
                                $languageUid,
                                true,
                                false
                            );
                    }

                    if (
                        ($translationRecord instanceof Translation)
                        && $translationRecord->isAutoCreated()
                    ) {
                        $forceUpdate = true;
                    }

                    // Skip if translation exists and update is not requested
                    if (
                        ($translationRecord instanceof Translation)
                        && ($forceUpdate === false)
                    ) {
                        continue;
                    }

                    $defaultTranslation = null;

                    if (
                        ($translationRecord instanceof Translation)
                        && ($forceUpdate === true)
                    ) {
                        $translationRecord->setValue($value);
                        $this->translationRepository->update($translationRecord);
                        $persistenceManager->persistAll();
                        $updated++;
                    } else {
                        if ($languageUid !== 0) {
                            ## If then language id is not 0 first get the default langauge translation.
                            $defaultTranslation = $this->translationRepository->find(
                                $environmentFound,
                                $componentFound,
                                $typeFound,
                                $placeholder,
                                0,
                                false
                            );
                            $persistenceManager->persistAll();
                        }

                        $translation = GeneralUtility::makeInstance(Translation::class);
                        $translation->setEnvironment($environmentFound);
                        $translation->setComponent($componentFound);
                        $translation->setType($typeFound);
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
                } catch (Exception $exception) {
                    $output->writeln("<error>{$exception->getMessage()}</error>");

                    $this->logger->error(
                        $exception->getMessage(),
                        [
                            'exception' => $exception,
                        ]
                    );

                    ++$errors;
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
    protected function getLanguageId(string $languageCode): int
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
     * @param string $key
     *
     * @return null|string
     */
    protected function getComponentFromKey(string $key): ?string
    {
        $parts = explode('|', $key);
        return $parts[0] ?? null;
    }

    /**
     * Get the type from a key
     *
     * @param string $key
     *
     * @return null|string
     */
    protected function getTypeFromKey(string $key): ?string
    {
        $parts = explode('|', $key);
        return $parts[1] ?? null;
    }

    /**
     * Get the placeholder from key
     *
     * @param string $key
     *
     * @return null|string
     */
    protected function getPlaceholderFromKey(string $key): ?string
    {
        $parts = explode('|', $key);
        return $parts[2] ?? null;
    }

    /**
     * Get All languages, configured.
     *
     * @return SiteLanguage[]
     */
    protected function getAllLanguages(): array
    {
        /** @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);

        $sites = $siteFinder->getAllSites();

        return reset($sites)->getAllLanguages();
    }

    /**
     * Get the extension configuration.
     *
     * @param string $path Path to get the config for
     *
     * @return mixed
     */
    protected function getExtensionConfiguration(string $path): mixed
    {
        try {
            return GeneralUtility::makeInstance(ExtensionConfiguration::class)
                ->get('nr_textdb', $path);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Get the configured page ID, used to store the translation in, from extension configuration.
     *
     * @return int
     */
    protected function getConfiguredPageId(): int
    {
        return (int) ($this->getExtensionConfiguration('textDbPid') ?? 0);
    }

    /**
     * Returns the langauge key from the file name.
     *
     * @param string $file
     *
     * @return string
     */
    protected function getLanguageKeyFromFile(string $file): string
    {
        $fileParts = explode('.', basename($file));

        if (count($fileParts) < 3) {
            return 'default';
        }

        return $fileParts[0];
    }

    /**
     * @param OutputInterface $output
     * @param bool            $forceUpdate
     *
     * @return void
     */
    protected function importTranslationsFromFiles(OutputInterface $output, bool $forceUpdate = false): void
    {
        foreach ($this->extensions as $extKey => $extensionKey) {
            $folderPath = ExtensionManagementUtility::extPath($extKey) . self::LANG_FOLDER;

            if (
                (file_exists($folderPath) === false)
                || (is_dir($folderPath) === false)
            ) {
                continue;
            }

            // Look up translation files
            $files = [
                glob($folderPath . 'textdb*.xlf'),
                glob($folderPath . '*.textdb*.xlf'),
            ];

            $files = array_merge(...$files);

            if (empty($files)) {
                continue;
            }

            sort($files);

            $this->importLanguageFiles($files, $output, $forceUpdate);
        }
    }
}
