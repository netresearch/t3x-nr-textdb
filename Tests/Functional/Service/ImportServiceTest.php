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

use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Service\ImportResult;
use Netresearch\NrTextdb\Service\ImportService;
use Netresearch\NrTextdb\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Extbase\Event\Persistence\EntityAddedToPersistenceEvent;

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
            $this->importService->importEntries(
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
     * Counts the rows of a table, or those with the given name.
     */
    private function countRows(string $table, ?string $name = null): int
    {
        return $this->get(ConnectionPool::class)
            ->getConnectionForTable($table)
            ->count('*', $table, $name === null ? [] : ['name' => $name]);
    }
}
