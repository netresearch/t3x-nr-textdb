<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
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
use Symfony\Component\Translation\Exception\InvalidResourceException;
use Throwable;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\Exception\InvalidXmlFileException;
use TYPO3\CMS\Core\Localization\Loader\XliffLoader;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

use function count;

/**
 * The import service.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
final readonly class ImportService
{
    /**
     * Entries written before the persistence session is cleared. Without
     * clearing, every record of a file stays in memory until the end, and a
     * file of 48,500 units exhausted a memory limit of 256 MB.
     */
    private const PERSIST_BATCH_SIZE = 1000;

    /**
     * Suffix XliffLoader appends to the id of a unit marked unused: XLIFF 1.2
     * `x-unused-since`, XLIFF 2.0 segment `subState="deprecated"`.
     */
    private const UNUSED_SUFFIX = '.x-unused';

    /**
     * The key in $GLOBALS['TYPO3_CONF_VARS']['LANG'] that makes XliffLoader
     * leave out the units of a translation file that are not approved.
     */
    public const APPROVAL_SETTING = 'requireApprovedLocalizations';

    private PersistenceManagerInterface $persistenceManager;

    private XliffLoader $xliffLoader;

    private TranslationService $translationService;

    private TranslationRepository $translationRepository;

    private ComponentRepository $componentRepository;

    private TypeRepository $typeRepository;

    private EnvironmentRepository $environmentRepository;

    private ConnectionPool $connectionPool;

    /**
     * Constructor.
     */
    public function __construct(
        PersistenceManagerInterface $persistenceManager,
        XliffLoader $xliffLoader,
        TranslationService $translationService,
        TranslationRepository $translationRepository,
        ComponentRepository $componentRepository,
        TypeRepository $typeRepository,
        EnvironmentRepository $environmentRepository,
        ConnectionPool $connectionPool,
    ) {
        $this->persistenceManager    = $persistenceManager;
        $this->xliffLoader           = $xliffLoader;
        $this->translationService    = $translationService;
        $this->translationRepository = $translationRepository;
        $this->componentRepository   = $componentRepository;
        $this->typeRepository        = $typeRepository;
        $this->environmentRepository = $environmentRepository;
        $this->connectionPool        = $connectionPool;
    }

    /**
     * Imports a XLIFF file.
     *
     * Imported / updated counts and per-entry error messages accumulate on
     * the provided {@see ImportResult}. Callers create the result, pass it
     * through one or more import calls, then read the totals.
     *
     * @param string       $file        The file to import
     * @param bool         $forceUpdate TRUE to force update of existing records
     * @param ImportResult $result      Accumulator for imported/updated counts and error messages
     */
    public function importFile(
        string $file,
        bool $forceUpdate,
        ImportResult $result,
    ): void {
        $languageKey = $this->getLanguageKeyFromFile($file);
        $languageUid = $this->getLanguageId($languageKey);
        $loadedFile  = $this->loadFile($file, $languageKey);

        $this->importEntries($loadedFile->entries, $languageUid, $forceUpdate, $result, $loadedFile->unapprovedKeys);
    }

    /**
     * Reads the messages of an XLIFF file and the keys of the units left out
     * because they are not approved.
     *
     * TYPO3 v14 replaced the localization parsers with Symfony Translation
     * loaders (Deprecation #107436). XliffLoader is the core replacement for
     * XliffParser: unlike Symfony's own XliffFileLoader it keeps TYPO3's
     * XLIFF dialect working — `<xliff version="1.0">` documents, the
     * source-only default-language file, and the `approved` attribute — and
     * returns a Symfony MessageCatalogue instead of the nested array.
     *
     * In a translation file the loader leaves out every unit that is not
     * approved while $GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations']
     * is enabled, its default. To name those units, the file is read a second
     * time with the setting disabled; the keys only that read returns are the
     * unapproved ones. The setting is restored afterwards.
     *
     * @param string $file   Path of the file
     * @param string $locale Language code the loader passes to the catalogue
     *
     * @throws InvalidXmlFileException  when the file is missing or no well-formed XML
     * @throws InvalidResourceException when the path is no file
     */
    public function loadFile(string $file, string $locale): LoadedXliffFile
    {
        $entries = $this->xliffLoader
            ->load($file, $locale)
            ->all('messages');

        if (!$this->isApprovalRequired()) {
            return new LoadedXliffFile($entries, []);
        }

        $languageConfiguration = $this->getLanguageConfiguration();

        try {
            $this->setLanguageConfiguration(
                [...$languageConfiguration ?? [], self::APPROVAL_SETTING => false],
            );

            $allEntries = $this->xliffLoader
                ->load($file, $locale)
                ->all('messages');
        } finally {
            $this->setLanguageConfiguration($languageConfiguration);
        }

        return new LoadedXliffFile(
            $entries,
            array_map(strval(...), array_keys(array_diff_key($allEntries, $entries))),
        );
    }

    /**
     * Imports the messages of a loaded XLIFF file, keyed "component|type|placeholder".
     *
     * A key ending in ".x-unused", the suffix XliffLoader appends to a unit
     * marked unused, imports without that suffix.
     *
     * A unit whose value trims to '' is skipped; its key and the keys in
     * $unapprovedKeys reach $result with the counts, once the file has
     * committed.
     *
     * Every key is checked before the first entry is written, so a file with
     * an unusable key imports nothing instead of a part of it.
     *
     * The entries are written in one database transaction. Extbase writes
     * each new record with an INSERT, an UPDATE for its relations and two
     * reference index updates, and committing every statement on its own
     * made a large file slow (issue #30). When a write fails, the transaction
     * rolls back, the exception propagates and the file imports nothing.
     * The persistence session and the name caches of the repositories are
     * cleared then, because they hold records whose rows were rolled back.
     * The counts of the file reach $result only once the transaction has
     * committed, so a rolled-back file adds nothing to them. Its error
     * messages do reach $result, each once, because one of them can name the
     * cause; on PostgreSQL every statement after a failed one repeats the
     * same error.
     *
     * @param array<array-key, mixed> $entries
     * @param int<-1, max>            $languageUid
     * @param list<string>            $unapprovedKeys Keys of the units the loader left out because they are not approved
     *
     * @throws RuntimeException when a key lacks a component, type or placeholder, or a value is no string
     */
    public function importEntries(
        array $entries,
        int $languageUid,
        bool $forceUpdate,
        ImportResult $result,
        array $unapprovedKeys = [],
    ): void {
        $parsedEntries = [];
        $fileResult    = new ImportResult();

        foreach ($unapprovedKeys as $unapprovedKey) {
            $fileResult->recordSkippedUnapproved($unapprovedKey);
        }

        foreach ($entries as $key => $value) {
            $key = (string) $key;

            // A unit marked unused imports under its own placeholder, as it
            // did before the import read files with XliffLoader.
            if (str_ends_with($key, self::UNUSED_SUFFIX)) {
                $key = substr($key, 0, -strlen(self::UNUSED_SUFFIX));
            }

            $componentName = $this->getComponentFromKey($key);
            if ($componentName === null) {
                throw new RuntimeException(
                    sprintf(
                        LocalizationUtility::translate('error.missing.component', 'NrTextdb') ?? 'Missing component name in key: %s',
                        $key,
                    ),
                );
            }

            $typeName = $this->getTypeFromKey($key);
            if ($typeName === null) {
                throw new RuntimeException(
                    sprintf(
                        LocalizationUtility::translate('error.missing.type', 'NrTextdb') ?? 'Missing type name in key: %s',
                        $key,
                    ),
                );
            }

            $placeholder = $this->getPlaceholderFromKey($key);
            if ($placeholder === null) {
                throw new RuntimeException(
                    sprintf(
                        LocalizationUtility::translate('error.missing.placeholder', 'NrTextdb') ?? 'Missing placeholder in key: %s',
                        $key,
                    ),
                );
            }

            if (!is_string($value)) {
                throw new RuntimeException(
                    sprintf(
                        LocalizationUtility::translate('error.missing.value', 'NrTextdb') ?? 'Missing value in key: %s',
                        $key,
                    ),
                );
            }

            // A unit without text, an untranslated unit of a translation file
            // for example, would overwrite a stored value with nothing.
            if (trim($value) === '') {
                $fileResult->recordSkippedEmpty($key);

                continue;
            }

            $parsedEntries[] = [$componentName, $typeName, $placeholder, $value];
        }

        try {
            $this->connectionPool
                ->getConnectionForTable('tx_nrtextdb_domain_model_translation')
                ->transactional(function () use ($parsedEntries, $languageUid, $forceUpdate, $fileResult): void {
                    foreach ($parsedEntries as $index => [$componentName, $typeName, $placeholder, $value]) {
                        $this->importEntry(
                            $languageUid,
                            $componentName,
                            $typeName,
                            $placeholder,
                            $value,
                            $forceUpdate,
                            $fileResult,
                        );

                        if ((($index + 1) % self::PERSIST_BATCH_SIZE) === 0) {
                            $this->persistenceManager->persistAll();
                            $this->persistenceManager->clearState();
                        }
                    }

                    $this->persistenceManager->persistAll();
                });
        } catch (Throwable $throwable) {
            foreach (array_unique($fileResult->getErrors()) as $error) {
                $result->recordError($error);
            }

            $this->persistenceManager->clearState();
            $this->environmentRepository->clearCache();
            $this->componentRepository->clearCache();
            $this->typeRepository->clearCache();

            throw $throwable;
        }

        $result->add($fileResult);
    }

    /**
     * Imports a single entry into the database.
     *
     * Imported / updated counts and any thrown error message accumulate on
     * the provided {@see ImportResult}.
     *
     * @param int<-1, max> $languageUid
     */
    public function importEntry(
        int $languageUid,
        ?string $componentName,
        ?string $typeName,
        string $placeholder,
        string $value,
        bool $forceUpdate,
        ImportResult $result,
    ): void {
        try {
            if ($componentName === null || $typeName === null) {
                return;
            }

            $environment = $this->environmentRepository
                ->setCreateIfMissing(true)
                ->findByName('default');

            $component = $this->componentRepository
                ->setCreateIfMissing(true)
                ->findByName($componentName);

            $type = $this->typeRepository
                ->setCreateIfMissing(true)
                ->findByName($typeName);

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
                    $languageUid,
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
                            $placeholder,
                        );

                    if ($parentTranslation instanceof Translation) {
                        $parentUid = $parentTranslation->getUid();
                        if ($parentUid !== null) {
                            $translation->setL10nParent($parentUid);
                        }
                    }
                }

                $this->translationRepository->update($translation);

                $result->recordUpdated();
            } else {
                $translation = $this->translationService
                    ->createTranslation(
                        $environment,
                        $component,
                        $type,
                        $placeholder,
                        $languageUid,
                        $value,
                    );

                $this->translationRepository->add($translation);

                $result->recordImported();
            }
        } catch (Exception $exception) {
            $result->recordError($exception->getMessage());
        } finally {
            // Reset createIfMissing to prevent leaking state on shared singleton repositories
            $this->environmentRepository->setCreateIfMissing(false);
            $this->componentRepository->setCreateIfMissing(false);
            $this->typeRepository->setCreateIfMissing(false);
        }
    }

    /**
     * Whether XliffLoader leaves out units that are not approved. It reads the
     * setting the same way, with TRUE when it is not set.
     */
    private function isApprovalRequired(): bool
    {
        return (bool) ($this->getLanguageConfiguration()[self::APPROVAL_SETTING] ?? true);
    }

    /**
     * Returns $GLOBALS['TYPO3_CONF_VARS']['LANG'], or NULL when it is no array.
     *
     * @return array<array-key, mixed>|null
     */
    private function getLanguageConfiguration(): ?array
    {
        $configuration = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        $language      = is_array($configuration) ? ($configuration['LANG'] ?? null) : null;

        return is_array($language) ? $language : null;
    }

    /**
     * Replaces $GLOBALS['TYPO3_CONF_VARS']['LANG'], or removes it for NULL.
     *
     * @param array<array-key, mixed>|null $language
     */
    private function setLanguageConfiguration(?array $language): void
    {
        $configuration = $GLOBALS['TYPO3_CONF_VARS'] ?? [];
        $configuration = is_array($configuration) ? $configuration : [];

        if ($language === null) {
            unset($configuration['LANG']);
        } else {
            $configuration['LANG'] = $language;
        }

        $GLOBALS['TYPO3_CONF_VARS'] = $configuration;
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

        foreach ($this->translationService->getAllLanguages() as $localLanguage) {
            if ($languageCode === $localLanguage->getLocale()->getLanguageCode()) {
                return max(-1, $localLanguage->getLanguageId());
            }
        }

        return 0;
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

        return array_key_exists(1, $parts) && ($parts[1] !== '') ? $parts[1] : null;
    }

    /**
     * Get the placeholder from a key.
     */
    private function getPlaceholderFromKey(string $key): ?string
    {
        $parts = explode('|', $key);

        return array_key_exists(2, $parts) && ($parts[2] !== '') ? $parts[2] : null;
    }
}
