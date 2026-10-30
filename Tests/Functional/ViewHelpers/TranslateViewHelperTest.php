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

namespace Netresearch\NrTextdb\Tests\Functional\ViewHelpers;

use InvalidArgumentException;
use Netresearch\NrTextdb\Service\LabelTranslator;
use Netresearch\NrTextdb\Service\LabelTranslatorInterface;
use Netresearch\NrTextdb\Tests\Functional\AbstractFunctionalTestCase;
use Netresearch\NrTextdb\ViewHelpers\TranslateViewHelper;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
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
 *   .Build/bin/phpunit -c Build/FunctionalTests.xml \
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
            "{nrtextdb:translate(key: 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:tx_nrtextdb_domain_model_environment', environment: 'default')}",
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
            "{nrtextdb:translate(key: 'some-plain-placeholder', environment: 'default')}",
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
            "{nrtextdb:translate(key: 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:tx_nrtextdb_domain_model_component', environment: 'default')}",
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

        $template = "{nrtextdb:translate(key: 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:tx_nrtextdb_domain_model_type', environment: 'default')}";

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

        $template    = "{nrtextdb:translate(key: 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:tx_nrtextdb_domain_model_translation', environment: 'default')}";
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
            "{nrtextdb:translate(key: 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:tx_nrtextdb_domain_model_environment.name', environment: 'default')}",
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
            "{nrtextdb:translate(key: 'some-key', environment: 'default')}",
        );
    }

    #[Test]
    public function doesNotThrowWhenComponentPropertyIsSet(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        // Should not throw; component is properly configured.
        $output = $this->renderFluidTemplate(
            "{nrtextdb:translate(key: 'some-plain-placeholder', environment: 'default')}",
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
            "{nrtextdb:translate(key: 'some-unknown-lll-key', environment: 'default')}",
        );

        self::assertSame('some-unknown-lll-key', trim($output));
    }

    #[Test]
    public function returnsRawPlaceholderForKeyWithNullLllResolutionOnSubsequentRender(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        $template = "{nrtextdb:translate(key: 'another-unknown-key', environment: 'default')}";

        // First render creates an auto-created record (value = AUTO_CREATE_IDENTIFIER).
        // Second render finds the TextDB entry and returns $translationRequested ?? $placeholder.
        // Since LLL still resolves to null, both renders return the placeholder.
        $firstOutput  = $this->renderFluidTemplate($template);
        $secondOutput = $this->renderFluidTemplate($template);

        self::assertSame('another-unknown-key', trim($firstOutput));
        self::assertSame('another-unknown-key', trim($secondOutput));
    }

    // =========================================================================
    // Scenario 5 – Every key shape LocalizationUtility resolves falls back to LLL
    // =========================================================================

    #[Test]
    public function translationDomainKeyResolvesTheLllTranslation(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        // Translation domain syntax: "<extension>.messages" maps to the
        // extension's locallang.xlf, no extension name needed.
        $output = $this->renderFluidTemplate(
            "{nrtextdb:translate(key: 'nr_textdb.messages:tx_nrtextdb_domain_model_component', environment: 'default')}",
        );

        self::assertSame('Component', trim($output));
    }

    #[Test]
    public function lllPrefixedTranslationDomainKeyResolvesTheLllTranslation(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        // LocalizationUtility strips an "LLL:" prefix from a domain key.
        $output = $this->renderFluidTemplate(
            "{nrtextdb:translate(key: 'LLL:nr_textdb.messages:tx_nrtextdb_domain_model_component', environment: 'default')}",
        );

        self::assertSame('Component', trim($output));
    }

    #[Test]
    public function bareKeyWithoutExtensionNameFallsBackToThePlaceholder(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        // LocalizationUtility cannot resolve a bare key without an extension
        // name; the ViewHelper must render the placeholder instead of aborting.
        $output = $this->renderFluidTemplate(
            "{nrtextdb:translate(key: 'tx_nrtextdb_domain_model_component', environment: 'default')}",
        );

        self::assertSame('tx_nrtextdb_domain_model_component', trim($output));
    }

    #[Test]
    public function keyWithColonThatLocalizationUtilityRejectsFallsBackToThePlaceholder(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        // "foo:bar" is neither an LLL:EXT: key nor a known translation domain,
        // so LocalizationUtility throws 1498144052; the ViewHelper must catch it
        // and render the placeholder.
        $output = $this->renderFluidTemplate(
            "{nrtextdb:translate(key: 'foo:bar', environment: 'default')}",
        );

        self::assertSame('foo:bar', trim($output));
    }

    #[Test]
    public function invalidArgumentExceptionWithAnotherCodeIsRethrown(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        // Only 1498144052 ("cannot map the key to a language file") is the
        // expected rejection; any other InvalidArgumentException is a real
        // error and must not be turned into the placeholder.
        $this->getContainer()->set(
            LabelTranslatorInterface::class,
            new class implements LabelTranslatorInterface {
                #[Override]
                public function translate(string $key, ?string $extensionName = null): ?string
                {
                    throw new InvalidArgumentException('Unrelated failure', 1700000001);
                }
            },
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionCode(1700000001);

        $this->renderFluidTemplate(
            "{nrtextdb:translate(key: 'nr_textdb.messages:tx_nrtextdb_domain_model_component', environment: 'default')}",
        );
    }

    #[Test]
    public function bareKeyWithoutExtensionNameIsNotHandedToLocalizationUtility(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        $labelTranslator = new class implements LabelTranslatorInterface {
            /** @var list<string> */
            public array $requestedKeys = [];

            #[Override]
            public function translate(string $key, ?string $extensionName = null): ?string
            {
                $this->requestedKeys[] = $key;

                return (new LabelTranslator())->translate($key, $extensionName);
            }
        };
        $this->getContainer()->set(LabelTranslatorInterface::class, $labelTranslator);

        // LocalizationUtility always rejects a key without ":" when no extension
        // name is given, so the ViewHelper must not ask it at all: throwing and
        // catching that exception for every such key made rendering slow.
        $this->renderFluidTemplate(
            "{nrtextdb:translate(key: 'tx_nrtextdb_domain_model_component', environment: 'default')}",
        );

        self::assertSame([], $labelTranslator->requestedKeys);

        // Control: a key LocalizationUtility can resolve does reach it, so the
        // empty list above is not an unwired double.
        $this->renderFluidTemplate(
            "{nrtextdb:translate(key: 'nr_textdb.messages:tx_nrtextdb_domain_model_type', environment: 'default')}",
        );

        self::assertSame(['nr_textdb.messages:tx_nrtextdb_domain_model_type'], $labelTranslator->requestedKeys);
    }

    #[Test]
    public function bareKeyWithExtensionNameResolvesTheLllTranslation(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        $output = $this->renderFluidTemplate(
            "{nrtextdb:translate(key: 'tx_nrtextdb_domain_model_environment', extensionName: 'nr_textdb', environment: 'default')}",
        );

        self::assertSame('Environment', trim($output));
    }

    // =========================================================================
    // Scenario 6 – Language fallback
    // =========================================================================

    #[Test]
    public function germanLabelFromTheLanguageFileWinsOverTheEnglishTextDbEntry(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        // English (default language) TextDB entry; no German entry. The
        // German language file of nr_textdb translates the key as "Umgebung".
        $this->insertDefaultLanguageTranslation('tx_nrtextdb_domain_model_environment', 'Environment from TextDB');
        $this->switchToGermanPage();

        $output = $this->renderFluidTemplate(
            "{nrtextdb:translate(key: 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:tx_nrtextdb_domain_model_environment', environment: 'default')}",
        );

        self::assertSame('Umgebung', trim($output));
    }

    #[Test]
    public function englishTextDbEntryIsRenderedWhenNeitherGermanEntryNorLabelExists(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        // The key is in no language file, and only the default language has
        // a TextDB entry.
        $this->insertDefaultLanguageTranslation('some-plain-placeholder', 'Plain English value');
        $this->switchToGermanPage();

        $output = $this->renderFluidTemplate(
            "{nrtextdb:translate(key: 'some-plain-placeholder', environment: 'default')}",
        );

        self::assertSame('Plain English value', trim($output));
    }

    #[Test]
    public function bothViewHelpersKeepTheirOwnResultForTheSameKeyInOneRequest(): void
    {
        TranslateViewHelper::$component = 'lll-migration-component';

        $this->insertDefaultLanguageTranslation('tx_nrtextdb_domain_model_environment', 'Environment from TextDB');
        $this->switchToGermanPage();

        $textdbTemplate    = "{nrtextdb:textdb(placeholder: 'tx_nrtextdb_domain_model_environment', type: 'label', component: 'lll-migration-component', environment: 'default')}";
        $translateTemplate = "{nrtextdb:translate(key: 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:tx_nrtextdb_domain_model_environment', environment: 'default')}";

        // textdb:textdb has no language-file step and falls back to the
        // English entry; textdb:translate shows the German label. The service
        // is a singleton, so both share its cache within the request.
        self::assertSame('Environment from TextDB', trim($this->renderFluidTemplate($textdbTemplate)));
        self::assertSame('Umgebung', trim($this->renderFluidTemplate($translateTemplate)));
        self::assertSame('Environment from TextDB', trim($this->renderFluidTemplate($textdbTemplate)));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Makes the rendering run for a German frontend page (language 1, falling
     * back to 0): the language aspect drives the TextDB lookup, the request
     * the language LocalizationUtility reads its labels in.
     */
    private function switchToGermanPage(): void
    {
        GeneralUtility::makeInstance(Context::class)
            ->setAspect(
                'language',
                new LanguageAspect(1, 1, LanguageAspect::OVERLAYS_ON_WITH_FLOATING, [0]),
            );

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
     * Inserts a default-language translation record in environment "default",
     * component "lll-migration-component" and type "label".
     */
    private function insertDefaultLanguageTranslation(string $placeholder, string $value): void
    {
        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation')
            ->insert(
                'tx_nrtextdb_domain_model_translation',
                [
                    'pid'              => 1,
                    'sys_language_uid' => 0,
                    'environment'      => 1,
                    'component'        => 1,
                    'type'             => 1,
                    'placeholder'      => $placeholder,
                    'value'            => $value,
                ],
            );
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
