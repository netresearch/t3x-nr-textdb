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

namespace Netresearch\NrTextdb\Tests\Functional\Service;

use ArrayObject;
use Doctrine\DBAL\Exception as DBALException;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Exception;
use Netresearch\NrTextdb\Domain\Model\Component;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use Netresearch\NrTextdb\Service\ImportResult;
use Netresearch\NrTextdb\Service\ImportService;
use Netresearch\NrTextdb\Service\TranslationService;
use Netresearch\NrTextdb\Service\TranslationTableWriter;
use Netresearch\NrTextdb\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;
use Throwable;
use TYPO3\CMS\Core\Configuration\FlexForm\FlexFormTools;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Platform\SQLitePlatform;
use TYPO3\CMS\Core\Database\ReferenceIndex;
use TYPO3\CMS\Core\DataHandling\SoftReference\SoftReferenceParserFactory;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Core\Localization\Loader\XliffLoader;
use TYPO3\CMS\Core\Registry;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Extbase\Event\Persistence\EntityAddedToPersistenceEvent;
use TYPO3\CMS\Extbase\Persistence\Generic\Storage\Exception\SqlErrorException;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Functional tests for ImportService.
 *
 * The storage page lies in no site, so ImportService gets the languages of
 * the first site. The test instance has none, so XLIFF files named without a
 * language prefix ("default.*") map to language uid 0.
 *
 * Fixtures use pid=1 as storage page. Extension configuration mock returns
 * pid=1 and createIfMissing=1 so the service can auto-create missing records.
 *
 * Fixture pre-existing translations:
 *   uid 1: env=default / component=mycomponent / type=button / placeholder=existing_key / value="Existing Value" / lang=0
 *   uid 2: env=default / component=mycomponent / type=button / placeholder=auto_created_key / value="auto-created-by-repository" / lang=0
 */
#[CoversClass(ImportService::class)]
final class ImportServiceTest extends AbstractFunctionalTestCase
{
    /**
     * Records the entries of the rollback test reference, by table.
     */
    private const REFERENCE_RECORDS = [
        'tx_nrtextdb_domain_model_environment' => 'default',
        'tx_nrtextdb_domain_model_component'   => 'rollbackcomponent',
        'tx_nrtextdb_domain_model_type'        => 'label',
    ];

    private ImportService $importService;

    private TranslationRepository $translationRepository;

    /**
     * $GLOBALS['TYPO3_CONF_VARS']['LANG'] as it was before the test.
     */
    private mixed $languageConfigurationBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->languageConfigurationBackup = $GLOBALS['TYPO3_CONF_VARS']['LANG'] ?? null;

        $this->setExtensionConfiguration(textDbPid: '1', createIfMissing: '1');

        $this->importService         = $this->get(ImportService::class);
        $this->translationRepository = $this->get(TranslationRepository::class);

        $this->importCSVDataSet(
            __DIR__ . '/../Fixtures/ImportService/Environments.csv',
        );
        $this->importCSVDataSet(
            __DIR__ . '/../Fixtures/ImportService/Components.csv',
        );
        $this->importCSVDataSet(
            __DIR__ . '/../Fixtures/ImportService/Types.csv',
        );
        $this->importCSVDataSet(
            __DIR__ . '/../Fixtures/ImportService/Translations.csv',
        );
    }

    protected function tearDown(): void
    {
        if ($this->languageConfigurationBackup === null) {
            unset($GLOBALS['TYPO3_CONF_VARS']['LANG']);
        } else {
            $GLOBALS['TYPO3_CONF_VARS']['LANG'] = $this->languageConfigurationBackup;
        }

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // importFile
    // -------------------------------------------------------------------------

    #[Test]
    public function importFileWithValidXliffCreatesNewTranslationRecord(): void
    {
        $result = new ImportResult();

        $this->importService->importFile(
            __DIR__ . '/../Fixtures/ImportService/import_valid.xlf',
            false,
            $result,
        );

        self::assertSame([], $result->getErrors(), 'Import should produce no errors.');
        self::assertSame(1, $result->getImported(), 'Exactly one record should have been imported.');
        self::assertSame(0, $result->getUpdated(), 'No records should have been updated.');
    }

    #[Test]
    public function importFileCreatedRecordIsPersisted(): void
    {
        $result = new ImportResult();

        $this->importService->importFile(
            __DIR__ . '/../Fixtures/ImportService/import_valid.xlf',
            false,
            $result,
        );

        self::assertSame([], $result->getErrors());

        // Query the database directly via the repository to verify persistence
        $result = $this->translationRepository
            ->findAllByComponentTypePlaceholderValueAndLanguage(
                placeholder: 'new_key',
                languageId: 0,
            );

        self::assertGreaterThanOrEqual(1, $result->count());

        /** @var Translation $translation */
        $translation = $result->getFirst();
        self::assertSame('new_key', $translation->getPlaceholder());
        self::assertSame('New Button Label', $translation->getValue());
    }

    #[Test]
    public function importFileSkipsExistingRecordWhenForceUpdateIsFalse(): void
    {
        $result = new ImportResult();

        // import_update.xlf contains placeholder=existing_key which already
        // exists in the fixtures with value "Existing Value"
        $this->importService->importFile(
            __DIR__ . '/../Fixtures/ImportService/import_update.xlf',
            false,
            $result,
        );

        self::assertSame([], $result->getErrors());
        self::assertSame(0, $result->getImported());
        self::assertSame(0, $result->getUpdated(), 'Existing record should be skipped when forceUpdate=false.');
    }

    #[Test]
    public function importFileUpdatesExistingRecordWhenForceUpdateIsTrue(): void
    {
        $result = new ImportResult();

        // import_update.xlf: existing_key → "Updated Value"
        $this->importService->importFile(
            __DIR__ . '/../Fixtures/ImportService/import_update.xlf',
            true,
            $result,
        );

        self::assertSame([], $result->getErrors());
        self::assertSame(0, $result->getImported());
        self::assertSame(1, $result->getUpdated(), 'The existing record should be updated when forceUpdate=true.');
    }

    #[Test]
    public function importFileForceUpdateChangesStoredValue(): void
    {
        $result = new ImportResult();

        $this->importService->importFile(
            __DIR__ . '/../Fixtures/ImportService/import_update.xlf',
            true,
            $result,
        );

        self::assertSame([], $result->getErrors());

        $result = $this->translationRepository
            ->findAllByComponentTypePlaceholderValueAndLanguage(
                placeholder: 'existing_key',
                languageId: 0,
            );

        self::assertGreaterThanOrEqual(1, $result->count());

        /** @var Translation $translation */
        $translation = $result->getFirst();
        self::assertSame('Updated Value', $translation->getValue());
    }

    #[Test]
    public function importFileReadsSourceOfADefaultLanguageExport(): void
    {
        // The extension's own default-language export (Resources/Private/template.xlf)
        // carries the texts in <source> and declares no target-language. TYPO3 v14
        // parses XLIFF through Symfony message catalogues now (Deprecation #107436),
        // and that stack decides between <source> and <target> by the presence of
        // target-language — so this file shape has to keep round-tripping.
        $result = new ImportResult();

        $this->importService->importFile(
            __DIR__ . '/../Fixtures/ImportService/textdb_import.xlf',
            false,
            $result,
        );

        self::assertSame([], $result->getErrors());
        self::assertSame(1, $result->getImported());

        $translations = $this->translationRepository
            ->findAllByComponentTypePlaceholderValueAndLanguage(
                placeholder: 'source_only_key',
                languageId: 0,
            );

        /** @var Translation|null $translation */
        $translation = $translations->getFirst();

        self::assertInstanceOf(Translation::class, $translation);
        self::assertSame('Source Only Label', $translation->getValue());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unusedUnitFileProvider(): array
    {
        return [
            'XLIFF 1.2, x-unused-since'      => ['import_unused.xlf'],
            'XLIFF 2.0, subState=deprecated' => ['import_unused_xliff2.xlf'],
        ];
    }

    #[Test]
    #[DataProvider('unusedUnitFileProvider')]
    public function importFileImportsAnUnusedUnitUnderItsOwnPlaceholder(string $fixture): void
    {
        // XliffLoader appends ".x-unused" to the id of a unit marked unused.
        // The placeholder is the part of the id after the second "|", so the
        // suffix would end up in it.
        $result = new ImportResult();

        $this->importService->importFile(
            __DIR__ . '/../Fixtures/ImportService/' . $fixture,
            false,
            $result,
        );

        self::assertSame([], $result->getErrors());
        self::assertSame(1, $result->getImported());

        $placeholders = $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation')
            ->select(['placeholder'], 'tx_nrtextdb_domain_model_translation', ['value' => 'Unused Label'])
            ->fetchFirstColumn();

        self::assertSame(['unused_key'], $placeholders);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function translationFileWithSkippedUnitsProvider(): array
    {
        return [
            'XLIFF 1.2' => ['de.import_skipped.xlf'],
            'XLIFF 2.0' => ['de.import_skipped_xliff2.xlf'],
        ];
    }

    #[Test]
    #[DataProvider('translationFileWithSkippedUnitsProvider')]
    public function importFileReportsTheUnitsItSkipped(string $fixture): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations'] = true;

        $result = new ImportResult();

        $this->importService->importFile(
            __DIR__ . '/../Fixtures/ImportService/' . $fixture,
            false,
            $result,
        );

        self::assertSame([], $result->getErrors());
        self::assertSame(2, $result->getImported());
        self::assertSame(['skipcomponent|label|draft'], $result->getSkippedUnapproved());
        // XLIFF 2.0: the unit without <target> is not in the catalogue at
        // all, with or without approval, so it is not reported.
        self::assertSame(['skipcomponent|label|untranslated'], $result->getSkippedEmpty());
        // The plural unit is imported with its approved form only.
        self::assertSame(['skipcomponent|label|plural'], $result->getPartiallyApproved());
        self::assertSame(['approved', 'plural'], $this->importedPlaceholders('skipcomponent'));
        self::assertTrue($GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations']);
    }

    #[Test]
    #[DataProvider('translationFileWithSkippedUnitsProvider')]
    public function importFileImportsAndReportsNoUnapprovedUnitWhenApprovalIsNotRequired(string $fixture): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations'] = false;

        $result = new ImportResult();

        $this->importService->importFile(
            __DIR__ . '/../Fixtures/ImportService/' . $fixture,
            false,
            $result,
        );

        self::assertSame([], $result->getSkippedUnapproved());
        self::assertSame([], $result->getPartiallyApproved());
        self::assertSame(['skipcomponent|label|untranslated'], $result->getSkippedEmpty());
        self::assertSame(['approved', 'draft', 'plural'], $this->importedPlaceholders('skipcomponent'));
        self::assertFalse($GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations']);
    }

    // -------------------------------------------------------------------------
    // importEntries
    // -------------------------------------------------------------------------

    #[Test]
    public function importEntriesImportsNothingWhenAWriteFailsAndCanBeRepeated(): void
    {
        $entries = [
            'rollbackcomponent|label|first'  => 'First',
            'rollbackcomponent|label|second' => 'Second',
            'rollbackcomponent|label|third'  => 'Third',
            'rollbackcomponent|label|empty'  => '',
        ];

        // Fails the reference index update of the second new translation,
        // after the translations of the file and the new component have
        // reached the database.
        $referenceIndex = $this->createFailingReferenceIndex(2);
        $importService  = $this->createImportService($referenceIndex);

        // Without the fixture's environment, the import creates the
        // environment, the component and the type, all three inside the
        // transaction that is rolled back.
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_environment')
            ->delete('tx_nrtextdb_domain_model_environment', ['name' => 'default']);

        $translationsBefore = $this->countRows('tx_nrtextdb_domain_model_translation');
        $failedResult       = new ImportResult();

        try {
            $importService->importEntries(
                $entries,
                0,
                false,
                $failedResult,
                ['rollbackcomponent|label|draft'],
                ['rollbackcomponent|label|first'],
            );
            self::fail('The simulated write failure did not reach the caller.');
        } catch (RuntimeException $exception) {
            self::assertSame(1793253600, $exception->getCode());
        }

        self::assertSame(
            $translationsBefore + 3,
            $referenceIndex->translationsWhenFailing,
            'The translations of the file were not written before the failure.',
        );
        self::assertSame($translationsBefore, $this->countRows('tx_nrtextdb_domain_model_translation'));
        self::assertSame(0, $failedResult->getImported(), 'A rolled-back file counts no entry.');
        self::assertSame([], $failedResult->getSkippedUnapproved(), 'A rolled-back file reports no unapproved unit.');
        self::assertSame([], $failedResult->getSkippedEmpty(), 'A rolled-back file reports no unit without text.');
        self::assertSame([], $failedResult->getPartiallyApproved(), 'A rolled-back file reports no partly approved unit.');

        foreach (self::REFERENCE_RECORDS as $table => $name) {
            self::assertSame(0, $this->countRows($table, $name), $table . ' keeps a rolled-back row.');
        }

        // The same process imports the file again, as the CLI command does
        // with its next file. Each record it references must be a row again.
        $referenceIndex->enabled = false;
        $result                  = new ImportResult();
        $importService->importEntries($entries, 0, false, $result);

        self::assertSame(3, $result->getImported());

        $translation = $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation')
            ->select(['environment', 'component', 'type'], 'tx_nrtextdb_domain_model_translation', ['placeholder' => 'second'])
            ->fetchAssociative();
        self::assertIsArray($translation);

        foreach (self::REFERENCE_RECORDS as $table => $name) {
            $uids = $this->get(ConnectionPool::class)
                ->getConnectionForTable($table)
                ->select(['uid'], $table, ['name' => $name])
                ->fetchFirstColumn();
            $column = substr($table, strlen('tx_nrtextdb_domain_model_'));

            self::assertSame([$translation[$column]], $uids, 'The translation references a missing ' . $column . '.');
        }
    }

    #[Test]
    public function importEntriesImportsNothingWhenAStatementFailsInsideAnEntryAndCanBeRepeated(): void
    {
        $entries = [
            'atomiccomponent|label|first'  => 'First',
            'brokencomponent|label|second' => 'Second',
            'atomiccomponent|label|third'  => 'Third',
            'atomiccomponent|label|fourth' => 'Fourth',
        ];

        // Runs a statement that fails once the new component "brokencomponent"
        // is inserted, inside importEntry(). The component itself is written,
        // so no later persistAll() repeats the failure.
        $connection = $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation');
        $listener = new class ($connection) {
            public bool $enabled = true;

            public function __construct(private readonly Connection $connection) {}

            public function __invoke(EntityAddedToPersistenceEvent $event): void
            {
                $object = $event->getObject();

                if ($this->enabled && $object instanceof Component && $object->getName() === 'brokencomponent') {
                    $this->connection->executeQuery('SELECT uid FROM tx_nrtextdb_missing_table');
                }
            }
        };
        $this->getContainer()->set('nr_textdb.test.failing_statement', $listener);
        $this->get(ListenerProvider::class)
            ->addListener(EntityAddedToPersistenceEvent::class, 'nr_textdb.test.failing_statement');

        $translationsBefore = $this->countRows('tx_nrtextdb_domain_model_translation');
        $failedResult       = new ImportResult();
        $thrown             = null;

        try {
            $this->importService->importEntries($entries, 0, false, $failedResult);
        } catch (Throwable $throwable) {
            $thrown = $throwable;
        }

        self::assertInstanceOf(DBALException::class, $thrown, 'The database error did not reach the caller.');
        self::assertStringContainsString('tx_nrtextdb_missing_table', $thrown->getMessage());
        self::assertSame([], $failedResult->getErrors(), 'The database error reaches the caller as the exception only.');
        self::assertSame(0, $failedResult->getImported(), 'A rolled-back file counts no entry.');
        self::assertSame($translationsBefore, $this->countRows('tx_nrtextdb_domain_model_translation'));
        self::assertSame(0, $this->countRows('tx_nrtextdb_domain_model_component', 'atomiccomponent'));
        self::assertSame(0, $this->countRows('tx_nrtextdb_domain_model_component', 'brokencomponent'));

        // The cause is gone, the same process imports the file again.
        $listener->enabled = false;
        $result            = new ImportResult();
        $this->importService->importEntries($entries, 0, false, $result);

        self::assertSame([], $result->getErrors());
        self::assertSame(4, $result->getImported());
        self::assertSame($translationsBefore + 4, $this->countRows('tx_nrtextdb_domain_model_translation'));
        self::assertSame(['first', 'fourth', 'third'], $this->importedPlaceholders('atomiccomponent'));
        self::assertSame(['second'], $this->importedPlaceholders('brokencomponent'));
    }

    #[Test]
    public function importEntriesImportsNothingWhenANameIsTooLongForItsColumnAndCanBeRepeated(): void
    {
        if ($this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_component')
            ->getDatabasePlatform() instanceof SQLitePlatform
        ) {
            self::markTestSkipped('SQLite does not enforce the length of a varchar column.');
        }

        $longName = str_repeat('x', 300);
        $entries  = [
            'atomiccomponent|label|first' => 'First',
            $longName . '|label|second'   => 'Second',
            'atomiccomponent|label|third' => 'Third',
        ];

        $translationsBefore = $this->countRows('tx_nrtextdb_domain_model_translation');
        $failedResult       = new ImportResult();
        $thrown             = null;

        try {
            $this->importService->importEntries($entries, 0, false, $failedResult);
        } catch (Throwable $throwable) {
            $thrown = $throwable;
        }

        self::assertInstanceOf(SqlErrorException::class, $thrown, 'The database error did not reach the caller.');
        self::assertSame([], $failedResult->getErrors(), 'The database error reaches the caller as the exception only.');
        self::assertSame($translationsBefore, $this->countRows('tx_nrtextdb_domain_model_translation'));
        self::assertSame(0, $this->countRows('tx_nrtextdb_domain_model_component', 'atomiccomponent'));

        // The file is fixed, the same process imports it again.
        $entries = [
            'atomiccomponent|label|first' => 'First',
            'shortcomponent|label|second' => 'Second',
            'atomiccomponent|label|third' => 'Third',
        ];
        $result = new ImportResult();
        $this->importService->importEntries($entries, 0, false, $result);

        self::assertSame([], $result->getErrors());
        self::assertSame(3, $result->getImported());
        self::assertSame(['first', 'third'], $this->importedPlaceholders('atomiccomponent'));
        self::assertSame(['second'], $this->importedPlaceholders('shortcomponent'));
    }

    #[Test]
    public function importEntriesImportsNothingWhenAValueIsTooLongForItsColumn(): void
    {
        if ($this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation')
            ->getDatabasePlatform() instanceof SQLitePlatform
        ) {
            self::markTestSkipped('SQLite does not enforce the length of a varchar column.');
        }

        // The bulk INSERT of the translations fails, after the component
        // was created.
        $entries = [
            'atomiccomponent|label|first' => 'First',
            'atomiccomponent|label|long'  => str_repeat('x', 2001),
        ];

        $translationsBefore = $this->countRows('tx_nrtextdb_domain_model_translation');
        $failedResult       = new ImportResult();
        $thrown             = null;

        try {
            $this->importService->importEntries($entries, 0, false, $failedResult);
        } catch (Throwable $throwable) {
            $thrown = $throwable;
        }

        self::assertInstanceOf(DBALException::class, $thrown, 'The database error did not reach the caller.');
        self::assertSame([], $failedResult->getErrors(), 'The database error reaches the caller as the exception only.');
        self::assertSame(0, $failedResult->getImported(), 'A rolled-back file counts no entry.');
        self::assertSame($translationsBefore, $this->countRows('tx_nrtextdb_domain_model_translation'));
        self::assertSame(0, $this->countRows('tx_nrtextdb_domain_model_component', 'atomiccomponent'));
    }

    #[Test]
    public function importEntriesWritesARecordWithTheColumnsExtbaseWrote(): void
    {
        $GLOBALS['EXEC_TIME'] = 1793253600;

        $result = new ImportResult();
        $this->importService->importEntries(['mycomponent|button|fresh_key' => 'Fresh'], 0, false, $result);

        $columns = $this->translationColumns('fresh_key', 0);
        unset($columns['uid']);

        self::assertSame(1, $result->getImported());
        self::assertSame(
            [
                'pid'              => 1,
                'tstamp'           => 1793253600,
                'crdate'           => 1793253600,
                'sys_language_uid' => 0,
                'l10n_parent'      => 0,
                'l10n_source'      => 0,
                'deleted'          => 0,
                'hidden'           => 0,
                'sorting'          => 0,
                'environment'      => 1,
                'component'        => 1,
                'type'             => 1,
                'value'            => 'Fresh',
            ],
            $columns,
        );
    }

    #[Test]
    public function importEntriesLinksATranslationToTheRecordOfTheDefaultLanguage(): void
    {
        // existing_key exists in the default language (uid 1), lonely_key
        // does not.
        $result = new ImportResult();
        $this->importService->importEntries(
            ['mycomponent|button|existing_key' => 'Vorhanden', 'mycomponent|button|lonely_key' => 'Allein'],
            1,
            false,
            $result,
        );

        self::assertSame(2, $result->getImported());
        self::assertSame(1, $this->translationColumns('existing_key', 1)['l10n_parent']);
        self::assertSame(0, $this->translationColumns('lonely_key', 1)['l10n_parent']);

        // A translation without parent gets it with forceUpdate, once the
        // record of the default language exists.
        $this->importService->importEntries(['mycomponent|button|lonely_key' => 'Lonely'], 0, false, new ImportResult());
        $parentUid = $this->translationColumns('lonely_key', 0)['uid'];

        $result = new ImportResult();
        $this->importService->importEntries(['mycomponent|button|lonely_key' => 'Allein'], 1, true, $result);

        self::assertSame(1, $result->getUpdated());
        self::assertSame($parentUid, $this->translationColumns('lonely_key', 1)['l10n_parent']);
    }

    #[Test]
    public function importEntriesDoesNotWriteARecordWhoseValueDoesNotChange(): void
    {
        $GLOBALS['EXEC_TIME'] = 1793253600;

        // existing_key is stored with "Existing Value" and tstamp 0.
        $result = new ImportResult();
        $this->importService->importEntries(
            ['mycomponent|button|existing_key' => 'Existing Value', 'mycomponent|button|auto_created_key' => 'Real'],
            0,
            true,
            $result,
        );

        self::assertSame(2, $result->getUpdated(), 'An unchanged record counts as updated, as it did through Extbase.');
        self::assertSame(0, $this->translationColumns('existing_key', 0)['tstamp']);
        self::assertSame(1793253600, $this->translationColumns('auto_created_key', 0)['tstamp']);
        self::assertSame('Real', $this->translationColumns('auto_created_key', 0)['value']);
    }

    #[Test]
    public function importEntriesFindsAHiddenRecord(): void
    {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation')
            ->update('tx_nrtextdb_domain_model_translation', ['hidden' => 1], ['uid' => 1]);

        $translationsBefore = $this->countRows('tx_nrtextdb_domain_model_translation');

        $result = new ImportResult();
        $this->importService->importEntries(['mycomponent|button|existing_key' => 'Other'], 0, true, $result);

        self::assertSame(0, $result->getImported());
        self::assertSame(1, $result->getUpdated());
        self::assertSame($translationsBefore, $this->countRows('tx_nrtextdb_domain_model_translation'));
        self::assertSame('Other', $this->translationColumns('existing_key', 0)['value']);
    }

    #[Test]
    public function importEntriesFindsARecordUnderEverySpellingTheDatabaseConsidersEqual(): void
    {
        $platform = $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation')
            ->getDatabasePlatform();

        if (!$platform instanceof AbstractMySQLPlatform) {
            self::markTestSkipped('Only MariaDB and MySQL compare the placeholder case-insensitively and without trailing spaces.');
        }

        // existing_key is stored with "Existing Value". MariaDB and MySQL
        // consider all three keys equal to it, as the lookup through Extbase
        // did, so the file holds one record three times.
        $entries = [
            'mycomponent|button|existing_key'  => 'Exact',
            'mycomponent|button|EXISTING_KEY'  => 'Upper',
            'mycomponent|button|existing_key ' => 'Trailing',
        ];

        $translationsBefore = $this->countRows('tx_nrtextdb_domain_model_translation');

        $result = new ImportResult();
        $this->importService->importEntries($entries, 0, false, $result);

        self::assertSame(0, $result->getImported());
        self::assertSame(0, $result->getUpdated());
        self::assertSame($translationsBefore, $this->countRows('tx_nrtextdb_domain_model_translation'));
        self::assertSame('Existing Value', $this->translationColumns('existing_key', 0)['value']);

        $result = new ImportResult();
        $this->importService->importEntries($entries, 0, true, $result);

        self::assertSame(0, $result->getImported());
        self::assertSame(3, $result->getUpdated());
        self::assertSame($translationsBefore, $this->countRows('tx_nrtextdb_domain_model_translation'));
        self::assertSame('Trailing', $this->translationColumns('existing_key', 0)['value']);
    }

    #[Test]
    public function importEntriesReadsNoRecordOfAComponentTheFileDoesNotName(): void
    {
        // Another component holds the placeholders of the file as well. The
        // lookups of the import read the records of the components in the
        // file only, so their result grows with the file, not with the table.
        $connectionPool = $this->get(ConnectionPool::class);
        $connectionPool->getConnectionForTable('tx_nrtextdb_domain_model_component')
            ->insert('tx_nrtextdb_domain_model_component', ['uid' => 7, 'pid' => 1, 'name' => 'othercomponent']);
        foreach (['existing_key', 'brand_new'] as $placeholder) {
            $connectionPool->getConnectionForTable('tx_nrtextdb_domain_model_translation')
                ->insert('tx_nrtextdb_domain_model_translation', [
                    'pid'         => 1,
                    'environment' => 1,
                    'component'   => 7,
                    'type'        => 1,
                    'placeholder' => $placeholder,
                    'value'       => 'Other',
                ]);
        }

        /** @var ArrayObject<int, int> $components */
        $components    = new ArrayObject();
        $importService = $this->createImportServiceWithWriter(
            new RowRecordingTranslationTableWriter($connectionPool, $this->get(ReferenceIndex::class), $components),
        );

        $result = new ImportResult();
        $importService->importEntries(
            ['mycomponent|button|existing_key' => 'Changed', 'mycomponent|button|brand_new' => 'New'],
            0,
            true,
            $result,
        );

        self::assertSame(1, $result->getUpdated());
        self::assertSame(1, $result->getImported());
        self::assertContains(1, $components->getArrayCopy(), 'The lookup did not read the record of the file.');
        self::assertNotContains(7, $components->getArrayCopy(), 'The lookup read records of a component the file does not name.');
    }

    #[Test]
    public function importEntriesWritesTheReferenceIndexTheCoreComputes(): void
    {
        $this->importService->importEntries(
            ['mycomponent|button|existing_key' => 'Changed', 'newcomponent|newtype|fresh_key' => 'Fresh'],
            0,
            true,
            new ImportResult(),
        );
        $this->importService->importEntries(
            ['mycomponent|button|existing_key' => 'Vorhanden', 'newcomponent|newtype|fresh_key' => 'Frisch'],
            1,
            false,
            new ImportResult(),
        );

        $uids = $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation')
            ->select(['uid'], 'tx_nrtextdb_domain_model_translation', ['tstamp' => $GLOBALS['EXEC_TIME']])
            ->fetchFirstColumn();

        self::assertCount(4, $uids);

        $referenceIndex = $this->get(ReferenceIndex::class);
        foreach ($uids as $uid) {
            $check = $referenceIndex->updateRefIndexTable('tx_nrtextdb_domain_model_translation', (int) $uid, true);

            self::assertSame(0, $check['addedNodes'], 'The reference index lacks a relation of record ' . $uid . '.');
            self::assertSame(0, $check['deletedNodes'], 'The reference index holds a stale relation of record ' . $uid . '.');
            self::assertGreaterThanOrEqual(3, $check['keptNodes']);
        }
    }

    /**
     * @return array<string, array{bool, int, list<string>}>
     */
    public static function existingPartlyApprovedEntryProvider(): array
    {
        return [
            'kept without forceUpdate' => [false, 0, []],
            'updated with forceUpdate' => [true, 1, ['mycomponent|button|existing_key']],
        ];
    }

    /**
     * @param list<string> $expectedPartiallyApproved
     */
    #[Test]
    #[DataProvider('existingPartlyApprovedEntryProvider')]
    public function importEntriesReportsAPartlyApprovedEntryOnlyWhenItIsWritten(
        bool $forceUpdate,
        int $expectedUpdated,
        array $expectedPartiallyApproved,
    ): void {
        // existing_key exists in the fixtures with "Existing Value".
        $result = new ImportResult();

        $this->importService->importEntries(
            ['mycomponent|button|existing_key' => 'eins'],
            0,
            $forceUpdate,
            $result,
            [],
            ['mycomponent|button|existing_key'],
        );

        self::assertSame([], $result->getErrors());
        self::assertSame($expectedUpdated, $result->getUpdated());
        self::assertSame($expectedPartiallyApproved, $result->getPartiallyApproved());
    }

    #[Test]
    public function importEntriesImportsNothingWhenAWriteInsideALaterEntryFailsAndCanBeRepeated(): void
    {
        // The new component "rvcompc" of the last entry is written by its
        // lookup, in the second chunk of 1,000 entries, after the first chunk
        // wrote "rvcompa" and its translations; the write of "rvcompc" fails
        // with an exception that does not come from the database.
        $entries = [
            'rvcompa|label|aaa'   => 'A',
            'checkout|label|bbb'  => 'B',
            'checkout|label|boom' => 'X',
        ];
        for ($i = count($entries); $i < 1000; ++$i) {
            $entries['checkout|label|fill' . $i] = 'Fill ' . $i;
        }

        $entries['rvcompc|label|ccc'] = 'C';

        $listener = new class ($this->get(ConnectionPool::class)) {
            public bool $enabled = true;

            public ?int $translationsWhenFailing = null;

            public function __construct(private readonly ConnectionPool $pool) {}

            public function __invoke(EntityAddedToPersistenceEvent $event): void
            {
                $object = $event->getObject();

                if ($this->enabled && $object instanceof Component && $object->getName() === 'rvcompc') {
                    $this->translationsWhenFailing = $this->pool
                        ->getConnectionForTable('tx_nrtextdb_domain_model_translation')
                        ->count('*', 'tx_nrtextdb_domain_model_translation', []);

                    throw new Exception('Simulated write failure', 1793253603);
                }
            }
        };
        $this->getContainer()->set('nr_textdb.test.failing_later_write', $listener);
        $this->get(ListenerProvider::class)
            ->addListener(EntityAddedToPersistenceEvent::class, 'nr_textdb.test.failing_later_write');

        $translationsBefore = $this->countRows('tx_nrtextdb_domain_model_translation');
        $failedResult       = new ImportResult();
        $thrown             = $this->importEntriesCatching($entries, $failedResult);

        self::assertInstanceOf(Exception::class, $thrown, 'The failed write did not reach the caller.');
        self::assertSame(1793253603, $thrown->getCode());
        self::assertSame(
            $translationsBefore + 1000,
            $listener->translationsWhenFailing,
            'The translations of the first chunk were not written before the failure.',
        );
        $this->assertNothingOfTheFileStored($failedResult, $translationsBefore, ['rvcompa', 'rvcompc']);

        // The cause is gone, the same process imports the file again.
        $listener->enabled = false;
        $result            = new ImportResult();

        self::assertNull($this->importEntriesCatching($entries, $result));
        self::assertSame($translationsBefore + 1001, $this->countRows('tx_nrtextdb_domain_model_translation'));
        self::assertSame(1001, $result->getImported());
        self::assertSame([], $result->getErrors());
    }

    // -------------------------------------------------------------------------
    // importEntry
    // -------------------------------------------------------------------------

    #[Test]
    public function importEntryCreatesNewRecordWhenNoneExists(): void
    {
        $result = new ImportResult();

        $this->importService->importEntry(
            0,
            'mycomponent',
            'button',
            'brand_new_placeholder',
            'Brand New Value',
            false,
            $result,
        );

        self::assertSame([], $result->getErrors());
        self::assertSame(1, $result->getImported());
        self::assertSame(0, $result->getUpdated());
    }

    #[Test]
    public function importEntryDoesNotCreateRecordWhenComponentNameIsNull(): void
    {
        $result = new ImportResult();

        $this->importService->importEntry(
            0,
            null,   // null component → must skip silently
            'button',
            'some_placeholder',
            'Some Value',
            false,
            $result,
        );

        self::assertSame([], $result->getErrors());
        self::assertSame(0, $result->getImported());
        self::assertSame(0, $result->getUpdated());
    }

    #[Test]
    public function importEntryDoesNotCreateRecordWhenTypeNameIsNull(): void
    {
        $result = new ImportResult();

        $this->importService->importEntry(
            0,
            'mycomponent',
            null,   // null type → must skip silently
            'some_placeholder',
            'Some Value',
            false,
            $result,
        );

        self::assertSame([], $result->getErrors());
        self::assertSame(0, $result->getImported());
        self::assertSame(0, $result->getUpdated());
    }

    #[Test]
    public function importEntrySkipsExistingRecordWithoutForceUpdate(): void
    {
        $result = new ImportResult();

        // existing_key already exists in fixtures (uid=1, value="Existing Value")
        $this->importService->importEntry(
            0,
            'mycomponent',
            'button',
            'existing_key',
            'Should Not Overwrite',
            false,
            $result,
        );

        self::assertSame(0, $result->getImported());
        self::assertSame(0, $result->getUpdated());
    }

    #[Test]
    public function importEntryUpdatesExistingRecordWithForceUpdate(): void
    {
        $result = new ImportResult();

        $this->importService->importEntry(
            0,
            'mycomponent',
            'button',
            'existing_key',
            'Force Updated Value',
            true,   // forceUpdate=true
            $result,
        );

        self::assertSame([], $result->getErrors());
        self::assertSame(0, $result->getImported());
        self::assertSame(1, $result->getUpdated());
    }

    #[Test]
    public function importEntryForcesUpdateOnAutoCreatedRecord(): void
    {
        // uid=2 in fixtures has value AUTO_CREATE_IDENTIFIER.
        // Even with forceUpdate=false the service must update auto-created records.
        $result = new ImportResult();

        $this->importService->importEntry(
            0,
            'mycomponent',
            'button',
            'auto_created_key',
            'Real Value Now',
            false,  // forceUpdate=false, but auto-created → should still update
            $result,
        );

        self::assertSame([], $result->getErrors());
        self::assertSame(0, $result->getImported(), 'Record already exists, so imported must stay 0.');
        self::assertSame(1, $result->getUpdated(), 'Auto-created record must be updated regardless of forceUpdate flag.');
    }

    #[Test]
    public function importEntryAutoCreatedRecordHasUpdatedValueAfterImport(): void
    {
        $result = new ImportResult();

        $this->importService->importEntry(
            0,
            'mycomponent',
            'button',
            'auto_created_key',
            'Real Value Now',
            false,
            $result,
        );

        self::assertSame([], $result->getErrors());

        $result = $this->translationRepository
            ->findAllByComponentTypePlaceholderValueAndLanguage(
                placeholder: 'auto_created_key',
                languageId: 0,
            );

        self::assertGreaterThanOrEqual(1, $result->count());

        /** @var Translation $translation */
        $translation = $result->getFirst();
        self::assertSame('Real Value Now', $translation->getValue());
        self::assertFalse(
            $translation->isAutoCreated(),
            'After import the record must no longer be flagged as auto-created.',
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Returns the placeholders of the translations of a component, sorted.
     *
     * @return list<string>
     */
    private function importedPlaceholders(string $component): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_nrtextdb_domain_model_translation');

        $placeholders = $queryBuilder
            ->select('translation.placeholder')
            ->from('tx_nrtextdb_domain_model_translation', 'translation')
            ->join(
                'translation',
                'tx_nrtextdb_domain_model_component',
                'component',
                $queryBuilder->expr()->eq('component.uid', $queryBuilder->quoteIdentifier('translation.component')),
            )
            ->where($queryBuilder->expr()->eq('component.name', $queryBuilder->createNamedParameter($component)))
            ->orderBy('translation.placeholder')
            ->executeQuery()
            ->fetchFirstColumn();

        return array_map(strval(...), $placeholders);
    }

    /**
     * Imports the entries and returns what the import threw, or null.
     *
     * @param array<string, string> $entries
     */
    private function importEntriesCatching(array $entries, ImportResult $result): ?Throwable
    {
        try {
            $this->importService->importEntries($entries, 0, false, $result);
        } catch (Throwable $throwable) {
            return $throwable;
        }

        return null;
    }

    /**
     * Asserts that a failed import left no translation and none of the
     * given components behind, and reported neither an error nor a count.
     *
     * @param list<string> $components
     */
    private function assertNothingOfTheFileStored(ImportResult $failedResult, int $translationsBefore, array $components): void
    {
        self::assertSame([], $failedResult->getErrors(), 'The failure reaches the caller as the exception only.');
        self::assertSame(0, $failedResult->getImported(), 'A rolled-back file counts no entry.');
        self::assertSame($translationsBefore, $this->countRows('tx_nrtextdb_domain_model_translation'));

        foreach ($components as $component) {
            self::assertSame(0, $this->countRows('tx_nrtextdb_domain_model_component', $component), $component . ' was kept.');
        }
    }

    /**
     * Returns the columns of the translation with the placeholder in a
     * language that the import writes, without the free texts.
     *
     * @return array<string, int|string>
     */
    private function translationColumns(string $placeholder, int $languageUid): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_nrtextdb_domain_model_translation');
        $queryBuilder->getRestrictions()->removeAll();

        $row = $queryBuilder
            ->select('uid', 'pid', 'tstamp', 'crdate', 'sys_language_uid', 'l10n_parent', 'l10n_source', 'deleted', 'hidden', 'sorting', 'environment', 'component', 'type', 'value')
            ->from('tx_nrtextdb_domain_model_translation')
            ->where(
                $queryBuilder->expr()->eq('placeholder', $queryBuilder->createNamedParameter($placeholder)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchAssociative();

        self::assertIsArray($row, 'No translation ' . $placeholder . ' in language ' . $languageUid . '.');

        $columns = [];
        foreach ($row as $column => $value) {
            $columns[$column] = ($column === 'value') ? (string) $value : (int) $value;
        }

        return $columns;
    }

    /**
     * Returns an import service whose translations get the given reference
     * index.
     */
    private function createImportService(ReferenceIndex $referenceIndex): ImportService
    {
        return $this->createImportServiceWithWriter(
            new TranslationTableWriter($this->get(ConnectionPool::class), $referenceIndex),
        );
    }

    /**
     * Returns an import service that writes the translations with the given
     * writer.
     */
    private function createImportServiceWithWriter(TranslationTableWriter $tableWriter): ImportService
    {
        return new ImportService(
            $this->get(PersistenceManagerInterface::class),
            $this->get(XliffLoader::class),
            $this->get(TranslationService::class),
            $this->get(TranslationRepository::class),
            $this->get(ComponentRepository::class),
            $this->get(TypeRepository::class),
            $this->get(EnvironmentRepository::class),
            $this->get(ConnectionPool::class),
            $tableWriter,
        );
    }

    /**
     * Returns a reference index that fails on its update for the nth
     * translation, and records how many translations there are then.
     */
    private function createFailingReferenceIndex(int $failingTranslation): ReferenceIndex
    {
        return new class ($failingTranslation, $this->get(ConnectionPool::class), $this->get(EventDispatcherInterface::class), $this->get(SoftReferenceParserFactory::class), $this->get(Registry::class), $this->get(FlexFormTools::class), $this->get(TcaSchemaFactory::class)) extends ReferenceIndex {
            public bool $enabled = true;

            public ?int $translationsWhenFailing = null;

            private int $translations = 0;

            public function __construct(
                private readonly int $failingTranslation,
                private readonly ConnectionPool $pool,
                EventDispatcherInterface $eventDispatcher,
                SoftReferenceParserFactory $softReferenceParserFactory,
                Registry $registry,
                FlexFormTools $flexFormTools,
                TcaSchemaFactory $tcaSchemaFactory,
            ) {
                parent::__construct($eventDispatcher, $softReferenceParserFactory, $pool, $registry, $flexFormTools, $tcaSchemaFactory);
            }

            public function updateRefIndexTable(string $tableName, int $uid, bool $testOnly = false, int $workspaceUid = 0, ?array $currentRecord = null): array
            {
                if ($this->enabled && ($tableName === 'tx_nrtextdb_domain_model_translation') && (++$this->translations === $this->failingTranslation)) {
                    $this->translationsWhenFailing = $this->pool
                        ->getConnectionForTable($tableName)
                        ->count('*', $tableName, []);

                    throw new RuntimeException('Simulated write failure', 1793253600);
                }

                return parent::updateRefIndexTable($tableName, $uid, $testOnly, $workspaceUid, $currentRecord);
            }
        };
    }

    /**
     * Counts the rows of a table, or those with the given name.
     */
    private function countRows(string $table, ?string $name = null): int
    {
        return $this->get(ConnectionPool::class)
            ->getConnectionForTable($table)
            ->count('*', $table, $name === null ? [] : ['name' => $name]);
    }
}
