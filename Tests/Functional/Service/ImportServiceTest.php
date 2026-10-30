<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Functional\Service;

use Doctrine\DBAL\Exception as DBALException;
use Netresearch\NrTextdb\Domain\Model\Component;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Service\ImportResult;
use Netresearch\NrTextdb\Service\ImportService;
use Netresearch\NrTextdb\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Throwable;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Platform\SQLitePlatform;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
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
 * Fixtures use pid=1 as storage page. The extension configuration is set to
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

    protected function setUp(): void
    {
        parent::setUp();

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
        ];

        // Fails the write of the second new translation, after the first
        // translation and the new component have reached the database.
        $listener = new class {
            public bool $enabled = true;

            private int $translations = 0;

            public function __invoke(EntityAddedToPersistenceEvent $event): void
            {
                if ($this->enabled && $event->getObject() instanceof Translation && ++$this->translations === 2) {
                    throw new RuntimeException('Simulated write failure', 1793253600);
                }
            }
        };
        $this->getContainer()->set('nr_textdb.test.failing_write', $listener);
        $this->get(ListenerProvider::class)
            ->addListener(EntityAddedToPersistenceEvent::class, 'nr_textdb.test.failing_write');

        // Without the fixture's environment, the import creates the
        // environment, the component and the type, all three inside the
        // transaction that is rolled back.
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_environment')
            ->delete('tx_nrtextdb_domain_model_environment', ['name' => 'default']);

        $translationsBefore = $this->countRows('tx_nrtextdb_domain_model_translation');
        $failedResult       = new ImportResult();

        try {
            $this->importService->importEntries($entries, 0, false, $failedResult);
            self::fail('The simulated write failure did not reach the caller.');
        } catch (RuntimeException $exception) {
            self::assertSame(1793253600, $exception->getCode());
        }

        self::assertSame($translationsBefore, $this->countRows('tx_nrtextdb_domain_model_translation'));
        self::assertSame(0, $failedResult->getImported(), 'A rolled-back file counts no entry.');

        foreach (self::REFERENCE_RECORDS as $table => $name) {
            self::assertSame(0, $this->countRows($table, $name), $table . ' keeps a rolled-back row.');
        }

        // The same process imports the file again, as the CLI command does
        // with its next file. Each record it references must be a row again.
        $listener->enabled = false;
        $result            = new ImportResult();
        $this->importService->importEntries($entries, 0, false, $result);

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

    /**
     * The lookup used to load a translation with language overlays. A hidden
     * translation came back as its default-language parent, and the import
     * wrote the translated text and an l10n_parent into the parent.
     */
    #[Test]
    public function importEntryUpdatesAHiddenTranslationAndLeavesItsParent(): void
    {
        $translation = $this->insertTranslation(1, 1, 1, 'existing_key', 'Bestehender Wert', hidden: true, l10nParent: 1);

        $result = new ImportResult();

        $this->importService->importEntry(
            1,
            'mycomponent',
            'button',
            'existing_key',
            'Neuer Wert',
            true,
            $result,
        );

        $this->get(PersistenceManagerInterface::class)->persistAll();

        self::assertSame([], $result->getErrors());
        self::assertSame('Existing Value', $this->fetchTranslationColumn(1));
        self::assertSame('0', $this->fetchTranslationColumn(1, 'l10n_parent'));
        self::assertSame('Neuer Wert', $this->fetchTranslationColumn($translation));
        self::assertSame(1, $this->countTranslationRowsInLanguage('existing_key', 1));
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
     * Counts the rows of a table, or those with the given name.
     */
    private function countRows(string $table, ?string $name = null): int
    {
        return $this->get(ConnectionPool::class)
            ->getConnectionForTable($table)
            ->count('*', $table, $name === null ? [] : ['name' => $name]);
    }

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
}
