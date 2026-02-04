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
use Netresearch\NrTextdb\Domain\Model\Type;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use RuntimeException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
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

    private readonly ComponentRepository $componentRepository;

    private readonly TypeRepository $typeRepository;

    private readonly EnvironmentRepository $environmentRepository;

    /**
     * Constructor.
     */
    public function __construct(
        PersistenceManagerInterface $persistenceManager,
        XliffParser $xliffParser,
        ComponentRepository $componentRepository,
        TypeRepository $typeRepository,
        EnvironmentRepository $environmentRepository,
    ) {
        $this->persistenceManager    = $persistenceManager;
        $this->xliffParser           = $xliffParser;
        $this->componentRepository   = $componentRepository;
        $this->typeRepository        = $typeRepository;
        $this->environmentRepository = $environmentRepository;
    }

    /**
     * Imports a XLIFF file using bulk DBAL operations for performance.
     *
     * Optimized implementation that processes translations in batches:
     * 1. Pre-process: Extract unique components/types, find/create reference records
     * 2. Bulk lookup: Query all existing translations in single query
     * 3. Prepare: Build INSERT/UPDATE arrays based on existence
     * 4. Execute: DBAL bulk insert/update operations
     * 5. Persist: Single persistAll() at the end (not per-entry)
     *
     * This eliminates the 400K+ individual persistAll() calls that caused >99.9% of execution time.
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
        $languageKey = $this->getLanguageKeyFromFile($file);
        $languageUid = $this->getLanguageId($languageKey);
        $fileContent = $this->xliffParser->getParsedData($file, $languageKey);
        $entries     = $fileContent[$languageKey];

        // Phase 1: Extract unique component/type names and validate entries
        $componentNames   = [];
        $typeNames        = [];
        $validatedEntries = [];

        foreach ($entries as $key => $data) {
            try {
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

                $componentNames[$componentName] = true;
                $typeNames[$typeName]           = true;

                $validatedEntries[] = [
                    'component'   => $componentName,
                    'type'        => $typeName,
                    'placeholder' => $placeholder,
                    'value'       => $value,
                ];
            } catch (Exception $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if ($validatedEntries === []) {
            return; // No valid entries to process
        }

        // Phase 2: Find/create reference records (environment, components, types)
        try {
            $environment = $this->environmentRepository
                ->setCreateIfMissing(true)
                ->findByName('default');

            if (!$environment instanceof Environment) {
                throw new RuntimeException('Failed to find or create environment');
            }

            $environmentUid = $environment->getUid();
            if ($environmentUid === null) {
                throw new RuntimeException('Environment UID is null');
            }

            // Find/create all unique components
            $componentMap = []; // name => uid
            foreach (array_keys($componentNames) as $componentName) {
                $component = $this->componentRepository
                    ->setCreateIfMissing(true)
                    ->findByName($componentName);

                if ($component instanceof Component) {
                    $componentUid = $component->getUid();
                    if ($componentUid !== null) {
                        $componentMap[$componentName] = $componentUid;
                    }
                }
            }

            // Find/create all unique types
            $typeMap = []; // name => uid
            foreach (array_keys($typeNames) as $typeName) {
                $type = $this->typeRepository
                    ->setCreateIfMissing(true)
                    ->findByName($typeName);

                if ($type instanceof Type) {
                    $typeUid = $type->getUid();
                    if ($typeUid !== null) {
                        $typeMap[$typeName] = $typeUid;
                    }
                }
            }

            // Persist reference records once
            $this->persistenceManager->persistAll();
        } catch (Exception $exception) {
            $errors[] = 'Failed to initialize reference data: ' . $exception->getMessage();

            return;
        }

        // Phase 3: Bulk lookup existing translations
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation');

        $queryBuilder         = $connection->createQueryBuilder();
        $existingTranslations = $queryBuilder
            ->select('uid', 'environment', 'component', 'type', 'placeholder', 'sys_language_uid', 'l10n_parent', 'auto_created')
            ->from('tx_nrtextdb_domain_model_translation')
            ->where(
                $queryBuilder->expr()->eq('environment', $queryBuilder->createNamedParameter($environmentUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchAllAssociative();

        // Build lookup map: "{component_uid}_{type_uid}_{placeholder}" => row
        $translationMap = [];
        foreach ($existingTranslations as $row) {
            $key                  = sprintf('%s_%s_%s', (string) ($row['component'] ?? ''), (string) ($row['type'] ?? ''), (string) ($row['placeholder'] ?? ''));
            $translationMap[$key] = $row;
        }

        // Phase 4: Prepare bulk INSERT and UPDATE arrays
        $inserts   = [];
        $updates   = [];
        $timestamp = time();
        $pid       = 0; // Default PID for Extbase records

        foreach ($validatedEntries as $entry) {
            $componentUid = $componentMap[$entry['component']] ?? null;
            $typeUid      = $typeMap[$entry['type']] ?? null;

            if ($componentUid === null || $typeUid === null) {
                $errors[] = sprintf('Missing component or type UID for: %s|%s', $entry['component'], $entry['type']);
                continue;
            }

            $key      = sprintf('%d_%d_%s', $componentUid, $typeUid, $entry['placeholder']);
            $existing = $translationMap[$key] ?? null;

            // Determine if we should update
            $shouldUpdate = $forceUpdate;
            if ($existing !== null && isset($existing['auto_created']) && (int) $existing['auto_created'] === 1) {
                $shouldUpdate = true; // Always update auto-created records
            }

            if ($existing !== null) {
                // Record exists
                if ($shouldUpdate) {
                    $updates[] = [
                        'uid'    => (int) (is_numeric($existing['uid'] ?? 0) ? $existing['uid'] : 0),
                        'value'  => $entry['value'],
                        'tstamp' => $timestamp,
                    ];
                    ++$updated;
                }

            // else skip (exists and no force update)
            } else {
                // New record - need to insert
                $inserts[] = [
                    'pid'              => $pid,
                    'tstamp'           => $timestamp,
                    'crdate'           => $timestamp,
                    'sys_language_uid' => $languageUid,
                    'l10n_parent'      => 0, // Will be set later if needed
                    'deleted'          => 0,
                    'hidden'           => 0,
                    'sorting'          => 0,
                    'environment'      => $environmentUid,
                    'component'        => $componentUid,
                    'type'             => $typeUid,
                    'placeholder'      => $entry['placeholder'],
                    'value'            => $entry['value'],
                ];
                ++$imported;
            }
        }

        // Phase 5: Execute bulk operations using DBAL with transaction safety
        try {
            // Begin transaction for atomic bulk operations
            $connection->beginTransaction();

            // Bulk INSERT - batch by 1000 records
            if ($inserts !== []) {
                $batchSize = 1000;
                $batches   = array_chunk($inserts, $batchSize);

                foreach ($batches as $batch) {
                    $connection->bulkInsert(
                        'tx_nrtextdb_domain_model_translation',
                        $batch,
                        ['pid', 'tstamp', 'crdate', 'sys_language_uid', 'l10n_parent', 'deleted', 'hidden', 'sorting', 'environment', 'component', 'type', 'placeholder', 'value']
                    );
                }
            }

            // Bulk UPDATE - batch updates using CASE expression for performance
            if ($updates !== []) {
                $batchSize = 1000;
                $batches   = array_chunk($updates, $batchSize);

                foreach ($batches as $batch) {
                    $uids        = [];
                    $valueCases  = [];
                    $tstampCases = [];

                    foreach ($batch as $update) {
                        $uids[]        = $update['uid'];
                        $valueCases[]  = sprintf('WHEN %d THEN ?', $update['uid']);
                        $tstampCases[] = sprintf('WHEN %d THEN ?', $update['uid']);
                    }

                    $valueParams  = array_column($batch, 'value');
                    $tstampParams = array_column($batch, 'tstamp');
                    $params       = array_merge($valueParams, $tstampParams, $uids);

                    $sql = sprintf(
                        'UPDATE tx_nrtextdb_domain_model_translation 
                         SET value = (CASE uid %s END), 
                             tstamp = (CASE uid %s END) 
                         WHERE uid IN (%s)',
                        implode(' ', $valueCases),
                        implode(' ', $tstampCases),
                        implode(',', array_fill(0, count($uids), '?'))
                    );

                    $connection->executeStatement($sql, $params);
                }
            }

            // Commit transaction on success
            $connection->commit();
        } catch (Exception $exception) {
            // Rollback transaction on failure to prevent partial imports
            $connection->rollBack();
            $errors[] = 'Bulk operation failed: ' . $exception->getMessage();
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
}
