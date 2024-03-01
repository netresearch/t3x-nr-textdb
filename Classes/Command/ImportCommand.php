<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Command;

use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Service\ImportService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Authentication\CommandLineUserAuthentication;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Package\Exception\UnknownPackageException;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extensionmanager\Utility\ListUtility;

use function count;

/**
 * Class ImportCommand.
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
     * @var PersistenceManagerInterface
     */
    private PersistenceManagerInterface $persistenceManager;

    /**
     * @var TranslationRepository
     */
    protected TranslationRepository $translationRepository;

    /**
     * @var ListUtility
     */
    protected ListUtility $listUtility;

    /**
     * @var string extension with the language file
     */
    protected string $extension = '';

    /**
     * @var array<array-key, mixed>
     */
    protected array $extensions = [];

    /**
     * @var ImportService
     */
    private ImportService $importService;

    /**
     * Constructor.
     *
     * @param PersistenceManagerInterface $persistenceManager
     * @param TranslationRepository       $translationRepository
     * @param ListUtility                 $listUtility
     * @param ImportService               $importService
     */
    public function __construct(
        PersistenceManagerInterface $persistenceManager,
        TranslationRepository $translationRepository,
        ListUtility $listUtility,
        ImportService $importService
    ) {
        parent::__construct();

        $this->persistenceManager    = $persistenceManager;
        $this->translationRepository = $translationRepository;
        $this->listUtility           = $listUtility;
        $this->importService         = $importService;
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
     * @throws IllegalObjectTypeException
     * @throws UnknownPackageException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->bootstrap();

        $extensionKey = $input->getArgument('extensionKey');

        if (($extensionKey === null) || ($extensionKey === '')) {
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
     * Returns the sys_language_uid for a language code.
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
            if ($languageCode === $localLanguage->getLocale()->getLanguageCode()) {
                return $localLanguage->getLanguageId();
            }
        }

        return 0;
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
     *
     * @throws IllegalObjectTypeException
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

            if (count($files) === 0) {
                continue;
            }

            sort($files);

            $this->importLanguageFiles($files, $output, $forceUpdate);
        }
    }

    /**
     * Import the language files into the database.
     *
     * @param string[]        $files
     * @param OutputInterface $output
     * @param bool            $forceUpdate
     */
    protected function importLanguageFiles(array $files, OutputInterface $output, bool $forceUpdate = false): void
    {
        $this->translationRepository
            ->injectPersistenceManager($this->persistenceManager);

        $imported = 0;
        $updated  = 0;

        foreach ($files as $file) {
            $errors = [];

            $this->importFile(
                $output,
                $file,
                $forceUpdate,
                $imported,
                $updated,
                $errors
            );

            foreach ($errors as $error) {
                $output->writeln("<error>{$error}</error>");
            }
        }

        $output->writeln("Imported: $imported, Updated: $updated");
    }

    /**
     * @param OutputInterface $output
     * @param string          $file
     * @param bool            $forceUpdate
     * @param int             $imported
     * @param int             $updated
     * @param string[]        $errors
     *
     * @return void
     */
    protected function importFile(
        OutputInterface $output,
        string $file,
        bool $forceUpdate,
        int &$imported,
        int &$updated,
        array &$errors
    ): void {
        $languageKey = $this->getLanguageKeyFromFile($file);
        $languageUid = $this->getLanguageId($languageKey);

        $output->writeln("Import translations from file $file for langauge $languageKey ($languageUid)");

        $this->importService
            ->importFile(
                $file,
                $forceUpdate,
                $imported,
                $updated,
                $errors
            );
    }
}
