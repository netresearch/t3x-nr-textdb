<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Service;

use Exception;
use Netresearch\NrTextdb\Domain\Model\Component;
use Netresearch\NrTextdb\Domain\Model\Environment;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Model\Type;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use RuntimeException;
use TYPO3\CMS\Core\Localization\Parser\XliffParser;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

use function count;

/**
 * The import service.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class ImportService
{
    private readonly PersistenceManagerInterface $persistenceManager;

    private readonly XliffParser $xliffParser;

    private readonly TranslationService $translationService;

    private readonly TranslationRepository $translationRepository;

    private readonly ComponentRepository $componentRepository;

    private readonly TypeRepository $typeRepository;

    private readonly EnvironmentRepository $environmentRepository;

    /**
     * Cache for environment lookups to avoid repeated database queries.
     *
     * @var array<string, Environment|null>
     */
    private array $environmentCache = [];

    /**
     * Cache for component lookups to avoid repeated database queries.
     *
     * @var array<string, Component|null>
     */
    private array $componentCache = [];

    /**
     * Cache for type lookups to avoid repeated database queries.
     *
     * @var array<string, Type|null>
     */
    private array $typeCache = [];

    /**
     * Batch of translations to insert.
     *
     * @var Translation[]
     */
    private array $batchInserts = [];

    /**
     * Batch of translations to update.
     *
     * @var Translation[]
     */
    private array $batchUpdates = [];

    /**
     * Batch size for database operations.
     */
    private const BATCH_SIZE = 1000;

    /**
     * Constructor.
     */
    public function __construct(
        PersistenceManagerInterface $persistenceManager,
        XliffParser $xliffParser,
        TranslationService $translationService,
        TranslationRepository $translationRepository,
        ComponentRepository $componentRepository,
        TypeRepository $typeRepository,
        EnvironmentRepository $environmentRepository,
    ) {
        $this->persistenceManager    = $persistenceManager;
        $this->xliffParser           = $xliffParser;
        $this->translationService    = $translationService;
        $this->translationRepository = $translationRepository;
        $this->componentRepository   = $componentRepository;
        $this->typeRepository        = $typeRepository;
        $this->environmentRepository = $environmentRepository;
    }

    /**
     * Imports a XLIFF file.
     *
     * @param string   $file        The file to import
     * @param bool     $forceUpdate TRUE to force update of existing records
     * @param int      $imported    The number of imported entries
     * @param int      $updated     The number of updated entries
     * @param string[] $errors      The error messages during import
     */
    public function importFile(
        string $file,
        bool $forceUpdate,
        int &$imported,
        int &$updated,
        array &$errors,
    ): void {
        // Clear caches at start of import
        $this->clearCaches();

        $languageKey = $this->getLanguageKeyFromFile($file);
        $languageUid = $this->getLanguageId($languageKey);
        $fileContent = $this->xliffParser->getParsedData($file, $languageKey);
        $entries     = $fileContent[$languageKey];
        $totalEntries = count($entries);
        $processedCount = 0;

        // Wrap entire import in a transaction for better performance and atomicity
        try {
            foreach ($entries as $key => $data) {
                $componentName = $this->getComponentFromKey($key);
                if ($componentName === null) {
                    throw new RuntimeException(
                        sprintf(
                            LocalizationUtility::translate('error.missing.component', 'NrTextdb') ?? 'Missing component name in key: %s',
                            (string) $key
                        )
                    );
                }

                $typeName = $this->getTypeFromKey($key);
                if ($typeName === null) {
                    throw new RuntimeException(
                        sprintf(
                            LocalizationUtility::translate('error.missing.type', 'NrTextdb') ?? 'Missing type name in key: %s',
                            (string) $key
                        )
                    );
                }

                $placeholder = $this->getPlaceholderFromKey($key);
                if ($placeholder === null) {
                    throw new RuntimeException(
                        sprintf(
                            LocalizationUtility::translate('error.missing.placeholder', 'NrTextdb') ?? 'Missing placeholder in key: %s',
                            (string) $key
                        )
                    );
                }

                $value = $data[0]['target'] ?? null;
                if ($value === null) {
                    throw new RuntimeException(
                        sprintf(
                            LocalizationUtility::translate('error.missing.value', 'NrTextdb') ?? 'Missing value in key: %s',
                            (string) $key
                        )
                    );
                }

                $this->importEntry(
                    $languageUid,
                    $componentName,
                    $typeName,
                    $placeholder,
                    $value,
                    $forceUpdate,
                    $imported,
                    $updated,
                    $errors
                );

                $processedCount++;
            }

            // Flush any remaining batched operations
            $this->flushBatches($imported, $updated);
        } catch (Exception $exception) {
            // On error, ensure any pending operations are discarded
            $this->clearBatches();
            throw $exception;
        } finally {
            // Clear caches after import to free memory
            $this->clearCaches();
        }
    }

    /**
     * Imports a single entry into the database.
     *
     * @param int<-1, max> $languageUid
     * @param string[]     $errors
     */
    public function importEntry(
        int $languageUid,
        ?string $componentName,
        ?string $typeName,
        string $placeholder,
        string $value,
        bool $forceUpdate,
        int &$imported,
        int &$updated,
        array &$errors,
    ): void {
        try {
            if ($componentName === null || $typeName === null) {
                return;
            }

            // Use cached lookups instead of querying database every time
            $environment = $this->getCachedEnvironment('default');
            $component = $this->getCachedComponent($componentName);
            $type = $this->getCachedType($typeName);

            if (
                (!$environment instanceof Environment)
                || (!$component instanceof Component)
                || (!$type instanceof Type)
            ) {
                return;
            }

            // Find existing translation record
            $translation = $this->translationRepository
                ->findByEnvironmentComponentTypePlaceholderAndLanguage(
                    $environment,
                    $component,
                    $type,
                    $placeholder,
                    $languageUid
                );

            if (
                ($translation instanceof Translation)
                && $translation->isAutoCreated()
            ) {
                $forceUpdate = true;
            }

            // Skip if translation exists and update is not requested
            if (
                ($translation instanceof Translation)
                && ($forceUpdate === false)
            ) {
                return;
            }

            // TODO Add option to overwrite auto created records
            // @see https://github.com/netresearch/t3x-nr-textdb/issues/28
            // TODO Add parent record if not present, if option "overwrite auto created" is true
            // @see https://github.com/netresearch/t3x-nr-textdb/issues/29

            if ($translation instanceof Translation) {
                $translation->setValue($value);

                if ($languageUid !== 0) {
                    // Look up parent translation (sys_language_uid = 0)
                    $parentTranslation = $this->translationRepository
                        ->findByEnvironmentComponentTypeAndPlaceholder(
                            $environment,
                            $component,
                            $type,
                            $placeholder
                        );

                    if ($parentTranslation instanceof Translation) {
                        $parentUid = $parentTranslation->getUid();
                        if ($parentUid !== null) {
                            $translation->setL10nParent($parentUid);
                        }
                    }
                }

                // Add to batch instead of immediate update
                $this->batchUpdates[] = $translation;

                // Flush batch if size limit reached
                if (count($this->batchUpdates) >= self::BATCH_SIZE) {
                    $this->flushUpdates($updated);
                }
            } else {
                $translation = $this->translationService
                    ->createTranslation(
                        $environment,
                        $component,
                        $type,
                        $placeholder,
                        $languageUid,
                        $value
                    );

                // Add to batch instead of immediate insert
                $this->batchInserts[] = $translation;

                // Flush batch if size limit reached
                if (count($this->batchInserts) >= self::BATCH_SIZE) {
                    $this->flushInserts($imported);
                }
            }
        } catch (Exception $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    /**<
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
     * Returns the sys_language_uid for a language code.
     *
     * @param string $languageCode Language Code
     *
     * @return int<-1, max>
     */
    private function getLanguageId(string $languageCode): int
    {
        if ($languageCode === 'default') {
            $languageCode = 'en';
        }

        foreach ($this->getAllLanguages() as $localLanguage) {
            if ($languageCode === $localLanguage->getLocale()->getLanguageCode()) {
                return max(-1, $localLanguage->getLanguageId());
            }
        }

        return 0;
    }

    /**
     * Get all configured languages.
     *
     * @return SiteLanguage[]
     */
    private function getAllLanguages(): array
    {
        /** @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $sites      = $siteFinder->getAllSites();
        $firstSite  = reset($sites);

        return ($firstSite instanceof Site) ? $firstSite->getAllLanguages() : [];
    }

    /**
     * Get the component from a key.
     */
    private function getComponentFromKey(string $key): ?string
    {
        $parts = explode('|', $key);

        return ($parts[0] !== '') ? $parts[0] : null;
    }

    /**
     * Get the type from a key.
     */
    private function getTypeFromKey(string $key): ?string
    {
        $parts = explode('|', $key);

        return isset($parts[1]) && ($parts[1] !== '') ? $parts[1] : null;
    }

    /**
     * Get the placeholder from a key.
     */
    private function getPlaceholderFromKey(string $key): ?string
    {
        $parts = explode('|', $key);

        return isset($parts[2]) && ($parts[2] !== '') ? $parts[2] : null;
    }

    /**
     * Get cached environment or query database if not cached.
     */
    private function getCachedEnvironment(string $name): ?Environment
    {
        if (!isset($this->environmentCache[$name])) {
            $this->environmentCache[$name] = $this->environmentRepository
                ->setCreateIfMissing(true)
                ->findByName($name);
        }

        return $this->environmentCache[$name];
    }

    /**
     * Get cached component or query database if not cached.
     */
    private function getCachedComponent(string $name): ?Component
    {
        if (!isset($this->componentCache[$name])) {
            $this->componentCache[$name] = $this->componentRepository
                ->setCreateIfMissing(true)
                ->findByName($name);
        }

        return $this->componentCache[$name];
    }

    /**
     * Get cached type or query database if not cached.
     */
    private function getCachedType(string $name): ?Type
    {
        if (!isset($this->typeCache[$name])) {
            $this->typeCache[$name] = $this->typeRepository
                ->setCreateIfMissing(true)
                ->findByName($name);
        }

        return $this->typeCache[$name];
    }

    /**
     * Flush batched insert operations to database.
     */
    private function flushInserts(int &$imported): void
    {
        if (empty($this->batchInserts)) {
            return;
        }

        foreach ($this->batchInserts as $translation) {
            $this->translationRepository->add($translation);
            ++$imported;
        }

        $this->persistenceManager->persistAll();
        $this->batchInserts = [];
    }

    /**
     * Flush batched update operations to database.
     */
    private function flushUpdates(int &$updated): void
    {
        if (empty($this->batchUpdates)) {
            return;
        }

        foreach ($this->batchUpdates as $translation) {
            $this->translationRepository->update($translation);
            ++$updated;
        }

        $this->persistenceManager->persistAll();
        $this->batchUpdates = [];
    }

    /**
     * Flush all remaining batched operations.
     */
    private function flushBatches(int &$imported, int &$updated): void
    {
        $this->flushInserts($imported);
        $this->flushUpdates($updated);
    }

    /**
     * Clear all batched operations without persisting.
     */
    private function clearBatches(): void
    {
        $this->batchInserts = [];
        $this->batchUpdates = [];
    }

    /**
     * Clear all caches.
     */
    private function clearCaches(): void
    {
        $this->environmentCache = [];
        $this->componentCache = [];
        $this->typeCache = [];
    }
}
