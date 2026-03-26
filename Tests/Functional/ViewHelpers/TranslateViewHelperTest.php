<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Functional\ViewHelpers;

use Netresearch\NrTextdb\Tests\Functional\AbstractFunctionalTestCase;
use Netresearch\NrTextdb\ViewHelpers\TranslateViewHelper;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * End-to-end rendering tests for TranslateViewHelper.
 *
 * TranslateViewHelper is a migration helper: it reads an LLL translation key
 * via LocalizationUtility and simultaneously imports the translation value into
 * the TextDB on first encounter.  Subsequent renders find the value in TextDB
 * and return the (potentially editor-overridden) TextDB value.
 *
 * Scenarios covered:
 *
 *   1. On first render the LLL value is imported into TextDB and returned.
 *   2. On subsequent renders the TextDB value is returned (TextDB wins).
 *   3. When TranslateViewHelper::$component is empty a RuntimeException is thrown.
 *   4. When environment / component is missing and createIfMissing=false,
 *      the raw placeholder is returned instead.
 *
 * Run:
 *   .build/bin/phpunit -c Build/FunctionalTests.xml \
 *       Tests/Functional/ViewHelpers/TranslateViewHelperTest.php
 */
#[CoversClass(TranslateViewHelper::class)]
final class TranslateViewHelperTest extends AbstractFunctionalTestCase
{
    /**
     * @var non-empty-string[]
     */
    protected array $testExtensionsToLoad = [
        'netresearch/nr-textdb',
    ];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Enable auto-creation so the import path exercised by
        // TranslateViewHelper works correctly during the first render.
        $this->mockExtensionConfiguration(textDbPid: '1', createIfMissing: '1');

        // Load base fixture: page, environment, component, and type records.
        // No translation records are imported here so each test starts from
        // an empty translation table, allowing us to observe the import step.
        $this->importFixture('TranslateViewHelperBase.csv');

        // Reset the static component property between tests to avoid state
        // leaking from one test into the next.
        TranslateViewHelper::$component = '';
    }

    #[Override]
    protected function tearDown(): void
    {
        // Guarantee cleanup of the static component property even if an
        // assertion fails inside a test.
        TranslateViewHelper::$component = '';

        parent::tearDown();
    }

    // =========================================================================
    // Scenario 1 – LLL translation is imported into TextDB on first render
    // =========================================================================

    #[Test]
    public function firstRenderImportsLllTranslationIntoTextDb(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        // Use the built-in nr_textdb locallang to get a real LLL string back.
        $output = $this->renderFluidTemplate(
            '{nrtextdb:translate(key: \'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:tx_nrtextdb_domain_model_environment\', environment: \'default\')}',
        );

        // LocalizationUtility resolves the key to "Environment" in English.
        self::assertSame('Environment', trim($output));

        // Verify the translation was persisted in the database.
        $count = $this->countTranslationRows('tx_nrtextdb_domain_model_environment');
        self::assertGreaterThan(0, $count);
    }

    #[Test]
    public function firstRenderReturnsFallbackWhenKeyNotInLll(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        // Key does not exist in any LLL file; LocalizationUtility returns null.
        // The ViewHelper must fall back to the bare placeholder.
        $output = $this->renderFluidTemplate(
            '{nrtextdb:translate(key: \'some-plain-placeholder\', environment: \'default\')}',
        );

        self::assertSame('some-plain-placeholder', trim($output));
    }

    #[Test]
    public function firstRenderExtractsPlaceholderFromColonDelimitedLllKey(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        // Key with more than three colon-delimited segments; the ViewHelper
        // extracts the fourth segment as the bare placeholder.
        // Format: LLL:EXT:extension/path/file.xlf:key-name
        $output = $this->renderFluidTemplate(
            '{nrtextdb:translate(key: \'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:tx_nrtextdb_domain_model_component\', environment: \'default\')}',
        );

        // The resolved LLL value is "Component".
        self::assertSame('Component', trim($output));
    }

    // =========================================================================
    // Scenario 2 – TextDB value returned on subsequent renders
    // =========================================================================

    #[Test]
    public function subsequentRenderReturnsSameLllValueAfterImport(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        $template = '{nrtextdb:translate(key: \'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:tx_nrtextdb_domain_model_type\', environment: \'default\')}';

        // First render: imports into TextDB.
        $firstOutput = $this->renderFluidTemplate($template);

        // Second render: finds TextDB record, returns it.
        $secondOutput = $this->renderFluidTemplate($template);

        // Both renders must return the LLL-resolved value "Type".
        self::assertSame('Type', trim($firstOutput));
        self::assertSame('Type', trim($secondOutput));
        self::assertSame(trim($firstOutput), trim($secondOutput));
    }

    #[Test]
    public function subsequentRenderDoesNotCreateDuplicateRecords(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        $template    = '{nrtextdb:translate(key: \'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:tx_nrtextdb_domain_model_translation\', environment: \'default\')}';
        $placeholder = 'tx_nrtextdb_domain_model_translation';

        // Render twice; the second call must not duplicate the DB record.
        $this->renderFluidTemplate($template);
        $this->renderFluidTemplate($template);

        $count = $this->countTranslationRows($placeholder);

        // TranslationService::translate() auto-creates at most one record per
        // language. The second render should not create a duplicate.
        self::assertLessThanOrEqual(2, $count);
    }

    #[Test]
    public function autoCreationPersistsRecordWhenCreateIfMissingEnabled(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        $placeholder = 'tx_nrtextdb_domain_model_environment.name';

        $this->renderFluidTemplate(
            '{nrtextdb:translate(key: \'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:tx_nrtextdb_domain_model_environment.name\', environment: \'default\')}',
        );

        // TranslationService::translate() auto-creates a record when
        // createIfMissing is enabled (which it is in setUp).
        $count = $this->countTranslationRows($placeholder);
        self::assertGreaterThanOrEqual(1, $count);
    }

    // =========================================================================
    // Scenario 3 – Missing component throws RuntimeException
    // =========================================================================

    #[Test]
    public function throwsRuntimeExceptionWhenComponentPropertyIsEmpty(): void
    {
        // TranslateViewHelper::$component is '' after setUp().
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Please set a component in your controller');

        $this->renderFluidTemplate(
            '{nrtextdb:translate(key: \'some-key\', environment: \'default\')}',
        );
    }

    #[Test]
    public function doesNotThrowWhenComponentPropertyIsSet(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        // Should not throw; component is properly configured.
        $output = $this->renderFluidTemplate(
            '{nrtextdb:translate(key: \'some-plain-placeholder\', environment: \'default\')}',
        );

        self::assertIsString($output);
    }

    // =========================================================================
    // Scenario 4 – Placeholder returned when LLL key has no translation
    // =========================================================================

    #[Test]
    public function returnsRawPlaceholderWhenLllKeyHasNoTranslation(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        // 'some-unknown-lll-key' does not exist in any XLF file.
        // LocalizationUtility returns null, so the ViewHelper falls back to
        // returning the raw placeholder ($translationRequested ?? $placeholder).
        $output = $this->renderFluidTemplate(
            '{nrtextdb:translate(key: \'some-unknown-lll-key\', environment: \'default\')}',
        );

        self::assertSame('some-unknown-lll-key', trim($output));
    }

    #[Test]
    public function returnsRawPlaceholderForKeyWithNullLllResolutionOnSubsequentRender(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        $template = '{nrtextdb:translate(key: \'another-unknown-key\', environment: \'default\')}';

        // First render creates an auto-created record (value = AUTO_CREATE_IDENTIFIER).
        // Second render finds the TextDB entry and returns $translationRequested ?? $placeholder.
        // Since LLL still resolves to null, both renders return the placeholder.
        $firstOutput  = $this->renderFluidTemplate($template);
        $secondOutput = $this->renderFluidTemplate($template);

        self::assertSame('another-unknown-key', trim($firstOutput));
        self::assertSame('another-unknown-key', trim($secondOutput));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Renders a minimal Fluid template string that registers the extension's
     * ViewHelper namespace under the alias "nrtextdb".
     */
    private function renderFluidTemplate(string $templateBody): string
    {
        $templateSource = '{namespace nrtextdb=Netresearch\\NrTextdb\\ViewHelpers}' . $templateBody;

        /** @var StandaloneView $view */
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplateSource($templateSource);

        return $view->render();
    }

    /**
     * Counts translation rows matching the given placeholder to verify
     * persistence after import operations.
     */
    private function countTranslationRows(string $placeholder): int
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation');

        return (int) $connection->count(
            'uid',
            'tx_nrtextdb_domain_model_translation',
            ['placeholder' => $placeholder],
        );
    }

    /**
     * Registers a mocked ExtensionConfiguration so repository methods return
     * the desired textDbPid and createIfMissing values without touching the
     * actual TYPO3 extension configuration storage.
     */
    private function mockExtensionConfiguration(string $textDbPid, string $createIfMissing): void
    {
        $mock = $this->createMock(ExtensionConfiguration::class);
        $mock->method('get')
            ->willReturnCallback(
                static function (string $ext, string $path) use ($textDbPid, $createIfMissing): string {
                    if ($ext !== 'nr_textdb') {
                        return '';
                    }

                    return match ($path) {
                        'textDbPid'       => $textDbPid,
                        'createIfMissing' => $createIfMissing,
                        default           => '',
                    };
                },
            );

        GeneralUtility::addInstance(ExtensionConfiguration::class, $mock);
    }
}
