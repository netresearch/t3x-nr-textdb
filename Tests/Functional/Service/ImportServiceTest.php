<?php

/*
 * This file is part of the package netresearch/nr-textdb.
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
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Functional tests for ImportService.
 *
 * The SiteFinder is mocked to expose a single site whose language list
 * contains English (uid=0, locale en_US) so that XLIFF files named without a
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
    private ImportService $importService;

    private TranslationRepository $translationRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Provide storage page 1 and allow auto-creation
        $extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $extensionConfigurationMock
            ->method('get')
            ->withAnyParameters()
            ->willReturnCallback(static function (string $extension, string $path): string {
                return match ($path) {
                    'textDbPid'       => '1',
                    'createIfMissing' => '1',
                    default           => '0',
                };
            });

        GeneralUtility::addInstance(ExtensionConfiguration::class, $extensionConfigurationMock);

        // Build a minimal SiteLanguage for English (language id 0)
        $siteLanguage = $this->buildSiteLanguage(0, 'en');
        $siteMock     = $this->createMock(Site::class);
        $siteMock->method('getAllLanguages')->willReturn([$siteLanguage]);

        $siteFinderMock = $this->createMock(SiteFinder::class);
        $siteFinderMock->method('getAllSites')->willReturn([$siteMock]);

        GeneralUtility::addInstance(SiteFinder::class, $siteFinderMock);

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
     * Builds a minimal SiteLanguage double that provides a locale with the
     * given ISO language code.
     *
     * The locale string must use BCP-47 hyphen notation (e.g. "en-US") so that
     * TYPO3's Locale class parses it correctly and getLanguageCode() returns
     * the expected two-letter code ("en").
     *
     * @param int    $languageId   TYPO3 sys_language uid
     * @param string $languageCode ISO 639-1 language code (e.g. "en", "de")
     */
    private function buildSiteLanguage(int $languageId, string $languageCode): SiteLanguage
    {
        // BCP-47 locale string: e.g. "en-EN" so Locale::getLanguageCode() returns "en"
        $bcp47Locale = $languageCode . '-' . strtoupper($languageCode);

        return new SiteLanguage(
            $languageId,
            $bcp47Locale,
            new \TYPO3\CMS\Core\Http\Uri('https://example.com/'),
            [
                'title'         => $languageCode,
                'flag'          => $languageCode,
                'hreflang'      => $languageCode,
                'direction'     => 'ltr',
                'typo3Language' => $languageCode,
            ],
        );
    }
}
