<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Command;

use function count;

use Netresearch\NrTextdb\Service\ImportResult;
use Netresearch\NrTextdb\Service\ImportService;

use function sprintf;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;
use TYPO3\CMS\Core\Package\Exception\UnknownPackageException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extensionmanager\Utility\ListUtility;

/**
 * Class ImportCommand.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
final class ImportCommand extends Command
{
    /**
     * Path for the language file within an extension.
     *
     * @var string
     */
    private const LANG_FOLDER = 'Resources/Private/Language/';

    private readonly ListUtility $listUtility;

    /**
     * @var array<array-key, mixed>
     */
    private array $extensions = [];

    private readonly ImportService $importService;

    private readonly SiteFinder $siteFinder;

    /**
     * Constructor.
     */
    public function __construct(
        ListUtility $listUtility,
        ImportService $importService,
        SiteFinder $siteFinder,
    ) {
        parent::__construct();

        $this->listUtility   = $listUtility;
        $this->importService = $importService;
        $this->siteFinder    = $siteFinder;
    }

    /**
     * Configures the command.
     */
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setDescription('Imports textdb records from language files')
            ->setHelp(
                'If you want to add textdb records to your extension. Create a file <LanguageCode>.textdb_import.xlf',
            )
            ->addArgument(
                'extensionKey',
                InputArgument::OPTIONAL,
                'Extension with language file',
            )
            ->addOption(
                'override',
                'o',
            );
    }

    /**
     * @throws UnknownPackageException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $extensionKey = $input->getArgument('extensionKey');

        if (!is_string($extensionKey) || $extensionKey === '') {
            $this->extensions = $this->listUtility->getAvailableAndInstalledExtensions(
                $this->listUtility->getAvailableExtensions(),
            );
        } else {
            $this->extensions = [
                $this->listUtility->getExtension($extensionKey),
            ];
        }

        $this->importTranslationsFromFiles(
            $output,
            (bool) $input->getOption('override'),
        );

        return Command::SUCCESS;
    }

    /**
     * Returns the sys_language_uid for a language code.
     *
     * @param string $languageCode Language Code
     */
    private function getLanguageId(string $languageCode): int
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
    private function getAllLanguages(): array
    {
        $sites     = $this->siteFinder->getAllSites();
        $firstSite = reset($sites);

        return ($firstSite instanceof Site) ? $firstSite->getAllLanguages() : [];
    }

    /**
     * Returns the language key from the file name.
     */
    private function getLanguageKeyFromFile(string $file): string
    {
        $fileParts = explode('.', basename($file));

        if (count($fileParts) < 3) {
            return 'default';
        }

        return $fileParts[0];
    }

    private function importTranslationsFromFiles(OutputInterface $output, bool $forceUpdate = false): void
    {
        foreach (array_keys($this->extensions) as $extKey) {
            $folderPath = ExtensionManagementUtility::extPath($extKey) . self::LANG_FOLDER;

            if (file_exists($folderPath) === false) {
                continue;
            }

            if (is_dir($folderPath) === false) {
                continue;
            }

            // Look up translation files
            $files = [
                glob($folderPath . 'textdb*.xlf'),
                glob($folderPath . '*.textdb*.xlf'),
            ];

            // Filter all possible "false" values
            $files = array_filter(
                $files,
                static fn ($file): bool => $file !== false,
            );

            $files = array_merge(...$files);

            if ($files === []) {
                continue;
            }

            sort($files);

            $this->importLanguageFiles($files, $output, $forceUpdate);
        }
    }

    /**
     * Import the language files into the database.
     *
     * Per-file failures are recorded on the {@see ImportResult} accumulator
     * and surfaced via the command output, but do not abort the loop. A
     * single malformed file therefore no longer prevents the remaining
     * files from being processed.
     *
     * @param string[] $files
     */
    private function importLanguageFiles(array $files, OutputInterface $output, bool $forceUpdate = false): void
    {
        $result = new ImportResult();

        foreach ($files as $file) {
            $errorOffset = count($result->getErrors());

            try {
                $this->importFile(
                    $output,
                    $file,
                    $forceUpdate,
                    $result,
                );
            } catch (Throwable $exception) {
                $result->recordError(
                    sprintf('Failed to import file %s: %s', $file, $exception->getMessage()),
                );
            }

            // Print only errors collected during this file's import.
            $errorsForFile = array_slice($result->getErrors(), $errorOffset);
            foreach ($errorsForFile as $error) {
                $output->writeln(sprintf('<error>%s</error>', $error));
            }
        }

        $output->writeln(sprintf('Imported: %s, Updated: %s', $result->getImported(), $result->getUpdated()));
    }

    private function importFile(
        OutputInterface $output,
        string $file,
        bool $forceUpdate,
        ImportResult $result,
    ): void {
        $languageKey = $this->getLanguageKeyFromFile($file);
        $languageUid = $this->getLanguageId($languageKey);

        $output->writeln(
            sprintf(
                'Import translations from file %s for language %s (%d)',
                $file,
                $languageKey,
                $languageUid,
            ),
        );

        $this->importService
            ->importFile(
                $file,
                $forceUpdate,
                $result,
            );
    }
}
