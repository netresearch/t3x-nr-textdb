<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Functional\ViewHelpers;

use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Tests\Functional\AbstractFunctionalTestCase;
use Netresearch\NrTextdb\ViewHelpers\TextdbViewHelper;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * End-to-end rendering tests for TextdbViewHelper.
 *
 * These tests verify actual Fluid template rendering output when the
 * <nrtextdb:textdb> ViewHelper is invoked, covering:
 *
 *   1. Renders the stored translation value when a record exists.
 *   2. Falls back to placeholder when no record exists and createIfMissing=false.
 *   3. Auto-creates a record and returns placeholder when createIfMissing=true.
 *   4. Handles different environment names independently.
 *   5. Handles multi-language (sys_language_uid) lookups.
 *
 * All tests run against a real (SQLite) database set up by the TYPO3
 * testing-framework. Run:
 *
 *   .build/bin/phpunit -c Build/FunctionalTests.xml \
 *       Tests/Functional/ViewHelpers/TextdbViewHelperTest.php
 */
#[CoversClass(TextdbViewHelper::class)]
final class TextdbViewHelperTest extends AbstractFunctionalTestCase
{
    /**
     * Extensions to load. Inherits from AbstractFunctionalTestCase so we only
     * need to declare it here if we want to override.
     *
     * @var non-empty-string[]
     */
    protected array $testExtensionsToLoad = [
        'netresearch/nr-textdb',
    ];

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        // Point the extension at pid=1 and disable auto-creation by default.
        // Individual tests that need createIfMissing=true call
        // $this->setExtensionConfiguration() explicitly.
        $this->mockExtensionConfiguration(textDbPid: '1', createIfMissing: '0');

        // Load a page record so that pid=1 is a valid storage page.
        $this->importFixture('Pages.csv');

        // Load lookup tables before translation records.
        $this->importFixture('Environments.csv');
        $this->importFixture('Components.csv');
        $this->importFixture('Types.csv');
        $this->importFixture('Translations.csv');
    }

    // =========================================================================
    // Scenario 1 – Renders translation value when record exists
    // =========================================================================

    #[Test]
    public function rendersTranslationValueWhenRecordExists(): void
    {
        $output = $this->renderFluidTemplate(
            '{nrtextdb:textdb(placeholder: \'welcome-message\', component: \'test-component\', environment: \'default\')}',
        );

        self::assertSame('Welcome to our shop', trim($output));
    }

    #[Test]
    public function rendersTranslationForCustomTypeWhenRecordExists(): void
    {
        $output = $this->renderFluidTemplate(
            '{nrtextdb:textdb(placeholder: \'checkout-title\', type: \'label\', component: \'checkout\', environment: \'default\')}',
        );

        self::assertSame('Checkout', trim($output));
    }

    #[Test]
    public function rendersDefaultTypePWhenTypeArgumentOmitted(): void
    {
        // Records with type "P" (uid=1 in Types.csv) should be found even
        // without an explicit type argument.
        $output = $this->renderFluidTemplate(
            '{nrtextdb:textdb(placeholder: \'welcome-message\', component: \'test-component\', environment: \'default\')}',
        );

        self::assertSame('Welcome to our shop', trim($output));
    }

    #[Test]
    public function rendersSubmitButtonTranslation(): void
    {
        $output = $this->renderFluidTemplate(
            '{nrtextdb:textdb(placeholder: \'submit-button\', component: \'test-component\', environment: \'default\')}',
        );

        self::assertSame('Submit Order', trim($output));
    }

    // =========================================================================
    // Scenario 2 – Falls back to placeholder when record does not exist
    // =========================================================================

    #[Test]
    public function returnsPlaceholderWhenNoRecordExistsAndCreateIfMissingFalse(): void
    {
        $output = $this->renderFluidTemplate(
            '{nrtextdb:textdb(placeholder: \'unknown-key\', component: \'test-component\', environment: \'default\')}',
        );

        self::assertSame('unknown-key', trim($output));
    }

    #[Test]
    public function returnsPlaceholderWhenComponentNotFoundAndCreateIfMissingFalse(): void
    {
        $output = $this->renderFluidTemplate(
            '{nrtextdb:textdb(placeholder: \'welcome-message\', component: \'nonexistent-component\', environment: \'default\')}',
        );

        self::assertSame('welcome-message', trim($output));
    }

    #[Test]
    public function returnsPlaceholderWhenEnvironmentNotFoundAndCreateIfMissingFalse(): void
    {
        $output = $this->renderFluidTemplate(
            '{nrtextdb:textdb(placeholder: \'welcome-message\', component: \'test-component\', environment: \'nonexistent-env\')}',
        );

        self::assertSame('welcome-message', trim($output));
    }

    // =========================================================================
    // Scenario 3 – Auto-creates record when createIfMissing=true
    // =========================================================================

    #[Test]
    public function autoCreatesRecordAndReturnsPlaceholderWhenCreateIfMissingTrue(): void
    {
        // Override the cached createIfMissing setting on the singleton repository.
        $this->get(TranslationRepository::class)->setCreateIfMissing(true);

        $placeholder = 'newly-created-key';

        $output = $this->renderFluidTemplate(
            '{nrtextdb:textdb(placeholder: \'newly-created-key\', component: \'test-component\', environment: \'default\')}',
        );

        // The auto-created translation stores AUTO_CREATE_IDENTIFIER as its value,
        // so Translation::getValue() falls back to returning the placeholder string.
        self::assertSame($placeholder, trim($output));

        // Verify the record was actually persisted in the database.
        $count = $this->countTranslationRows($placeholder);
        self::assertGreaterThan(0, $count);
    }

    #[Test]
    public function autoCreatesEnvironmentAndComponentWhenCreateIfMissingTrue(): void
    {
        $this->get(TranslationRepository::class)->setCreateIfMissing(true);

        $output = $this->renderFluidTemplate(
            '{nrtextdb:textdb(placeholder: \'brand-new-key\', component: \'brand-new-component\', environment: \'brand-new-env\')}',
        );

        // When everything is auto-created the placeholder is the rendered result.
        self::assertSame('brand-new-key', trim($output));
    }

    // =========================================================================
    // Scenario 4 – Handles different environments
    // =========================================================================

    #[Test]
    public function rendersStagingEnvironmentTranslation(): void
    {
        // Fixture uid=4 uses environment=staging (uid=2), same placeholder.
        $output = $this->renderFluidTemplate(
            '{nrtextdb:textdb(placeholder: \'welcome-message\', component: \'test-component\', environment: \'staging\')}',
        );

        self::assertSame('Welcome to staging', trim($output));
    }

    #[Test]
    public function rendersDifferentValuesForDifferentEnvironments(): void
    {
        $defaultOutput = $this->renderFluidTemplate(
            '{nrtextdb:textdb(placeholder: \'welcome-message\', component: \'test-component\', environment: \'default\')}',
        );
        $stagingOutput = $this->renderFluidTemplate(
            '{nrtextdb:textdb(placeholder: \'welcome-message\', component: \'test-component\', environment: \'staging\')}',
        );

        self::assertNotSame(trim($defaultOutput), trim($stagingOutput));
        self::assertSame('Welcome to our shop', trim($defaultOutput));
        self::assertSame('Welcome to staging', trim($stagingOutput));
    }

    #[Test]
    public function rendersProductionEnvironmentTranslation(): void
    {
        $output = $this->renderFluidTemplate(
            '{nrtextdb:textdb(placeholder: \'production-text\', component: \'test-component\', environment: \'production\')}',
        );

        self::assertSame('Production value', trim($output));
    }

    // =========================================================================
    // Scenario 5 – Multi-language (sys_language_uid)
    // =========================================================================

    #[Test]
    public function rendersDefaultLanguageTranslationWhenLanguageUidIsZero(): void
    {
        $this->setLanguageAspect(0);

        // welcome-message uid=1 has sys_language_uid=0.
        $output = $this->renderFluidTemplate(
            '{nrtextdb:textdb(placeholder: \'welcome-message\', component: \'test-component\', environment: \'default\')}',
        );

        self::assertSame('Welcome to our shop', trim($output));
    }

    #[Test]
    public function rendersLocalizedTranslationWhenLanguageUidMatchesRecord(): void
    {
        // Fixture uid=5 has sys_language_uid=2, l10n_parent=1.
        $this->setLanguageAspect(2);

        $output = $this->renderFluidTemplate(
            '{nrtextdb:textdb(placeholder: \'welcome-message\', component: \'test-component\', environment: \'default\')}',
        );

        self::assertSame('Willkommen in unserem Shop', trim($output));
    }

    #[Test]
    public function returnsPlaceholderForLanguageWithNoTranslation(): void
    {
        // Language UID 99 has no records in the fixtures.
        $this->setLanguageAspect(99);

        $output = $this->renderFluidTemplate(
            '{nrtextdb:textdb(placeholder: \'welcome-message\', component: \'test-component\', environment: \'default\')}',
        );

        self::assertSame('welcome-message', trim($output));
    }

    #[Test]
    public function autoCreatedEntryReturnsPlaceholderAsValue(): void
    {
        // Fixture uid=6 has value='auto-created-by-repository'.
        // Translation::getValue() detects this sentinel and returns
        // getPlaceholder() instead of the raw value.
        $output = $this->renderFluidTemplate(
            '{nrtextdb:textdb(placeholder: \'auto-created-entry\', component: \'test-component\', environment: \'default\')}',
        );

        self::assertSame('auto-created-entry', trim($output));
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
     * Overrides the language aspect on the shared Context singleton so that
     * TranslationService picks up the expected sys_language_uid.
     */
    private function setLanguageAspect(int $languageUid): void
    {
        GeneralUtility::makeInstance(Context::class)
            ->setAspect('language', new LanguageAspect($languageUid));
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

    /**
     * Counts translation rows matching the given placeholder, ignoring soft-delete,
     * to verify persistence after auto-create operations.
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
}
