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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
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
    private const ENVIRONMENT_LABEL_TEMPLATE = "{nrtextdb:translate(key: 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:tx_nrtextdb_domain_model_environment', environment: 'default')}";

    private const PLAIN_PLACEHOLDER_TEMPLATE = "{nrtextdb:translate(key: 'some-plain-placeholder', environment: 'default')}";

    private const ENVIRONMENT_TEXTDB_VALUE = 'Environment from TextDB';

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
        $this->setExtensionConfiguration(textDbPid: '1', createIfMissing: '1');

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
        unset($GLOBALS['TYPO3_REQUEST']);

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

    #[Test]
    public function bareKeyWithExtensionNameResolvesTheLllTranslation(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        // A bare key is resolvable when an extension name is given:
        // LocalizationUtility then reads the extension's locallang.xlf.
        $output = $this->renderFluidTemplate(
            '{nrtextdb:translate(key: \'tx_nrtextdb_domain_model_environment\', extensionName: \'nr_textdb\', environment: \'default\')}',
        );

        self::assertSame('Environment', trim($output));
    }

    #[Test]
    public function lllKeyWithFilePathOtherThanExtResolvesTheLllTranslation(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        // Fully-qualified LLL keys are not limited to the "LLL:EXT:" form;
        // LocalizationUtility resolves any "LLL:<file>:<key>" reference
        // without an extension name.
        $output = $this->renderFluidTemplate(
            '{nrtextdb:translate(key: \'LLL:typo3conf/ext/nr_textdb/Resources/Private/Language/locallang.xlf:tx_nrtextdb_domain_model_component\', environment: \'default\')}',
        );

        self::assertSame('Component', trim($output));
    }

    // =========================================================================
    // Scenario 5 – Hidden entries render like missing ones
    // =========================================================================

    #[Test]
    #[DataProvider('hiddenEntryParentProvider')]
    public function hiddenGermanEntryRendersTheGermanLabelOfTheLanguageFile(bool $withParent): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        $parent = $withParent
            ? $this->insertTranslation(0, 1, 1, 'tx_nrtextdb_domain_model_environment', self::ENVIRONMENT_TEXTDB_VALUE)
            : 0;
        $this->insertTranslation(1, 1, 1, 'tx_nrtextdb_domain_model_environment', 'Hidden DE', hidden: true, l10nParent: $parent);
        $this->switchToGermanPage();

        $output = $this->renderFluidTemplate(self::ENVIRONMENT_LABEL_TEMPLATE);

        self::assertSame('Umgebung', trim($output));

        // createIfMissing is enabled in setUp(); the hidden entry is found, so
        // no second German entry is created for the key.
        self::assertSame(1, $this->countTranslationRowsInLanguage('tx_nrtextdb_domain_model_environment', 1));
    }

    /**
     * TYPO3 13.4 has no language fallback for TextDB entries, so a hidden
     * German entry of a key that no language file knows renders the key, also
     * when an English entry exists.
     */
    #[Test]
    #[DataProvider('hiddenEntryParentProvider')]
    public function hiddenGermanEntryOfAKeyInNoLanguageFileRendersTheKey(bool $withParent): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        $parent = $this->insertTranslation(0, 1, 1, 'some-plain-placeholder', 'Plain English value');
        $this->insertTranslation(1, 1, 1, 'some-plain-placeholder', 'Hidden DE', hidden: true, l10nParent: $withParent ? $parent : 0);
        $this->switchToGermanPage();

        $output = $this->renderFluidTemplate(self::PLAIN_PLACEHOLDER_TEMPLATE);

        self::assertSame('some-plain-placeholder', trim($output));
    }

    #[Test]
    public function hiddenDefaultLanguageEntryOnADefaultLanguagePageRendersTheLabelOfTheLanguageFile(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        $this->insertTranslation(0, 1, 1, 'tx_nrtextdb_domain_model_environment', 'Hidden EN', hidden: true);

        $output = $this->renderFluidTemplate(self::ENVIRONMENT_LABEL_TEMPLATE);

        self::assertSame('Environment', trim($output));
        self::assertSame(1, $this->countTranslationRowsInLanguage('tx_nrtextdb_domain_model_environment', 0));
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function hiddenEntryParentProvider(): array
    {
        return [
            'without a parent'           => [false],
            'with the default as parent' => [true],
        ];
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Makes the rendering run for a German frontend page (language 1): the
     * language aspect drives the TextDB lookup, the request the language
     * LocalizationUtility reads its labels in.
     */
    private function switchToGermanPage(): void
    {
        GeneralUtility::makeInstance(Context::class)
            ->setAspect('language', new LanguageAspect(1, 1, LanguageAspect::OVERLAYS_ON_WITH_FLOATING));

        // Extbase reads the frontend TypoScript of a frontend request; an
        // empty setup is enough for the repositories.
        $frontendTypoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $frontendTypoScript->setSetupArray([]);

        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.com/de/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE)
            ->withAttribute('frontend.typoscript', $frontendTypoScript)
            ->withAttribute(
                'language',
                new SiteLanguage(1, 'de_DE.UTF-8', new Uri('https://example.com/de/'), ['typo3Language' => 'de']),
            );
    }

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
}
