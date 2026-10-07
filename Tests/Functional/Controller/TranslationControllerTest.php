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

namespace Netresearch\NrTextdb\Tests\Functional\Controller;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Netresearch\NrTextdb\Controller\TranslationController;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use Netresearch\NrTextdb\Service\ImportService;
use Netresearch\NrTextdb\Service\TranslationService;
use Netresearch\NrTextdb\Tests\Functional\AbstractFunctionalTestCase;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use ReflectionClass;
use RuntimeException;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Crypto\HashAlgo;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Core\Bootstrap;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request as ExtbaseRequest;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Security\HashScope;
use ZipArchive;

/**
 * Regression tests for the backend module's save path.
 *
 * Reproduces the three defects reported in issue #100 "Backend module: Save
 * creates orphaned duplicate row (environment/component/type = 0), further
 * saves fail with 503":
 *
 *   1. The "untranslated" column submits one `new[<language>]` value per
 *      language, and the action inserted a record for each of them without
 *      checking whether that language already had one — the second save then
 *      collided with the unique key and surfaced as a raw 503.
 *   2. Empty textareas were submitted as empty values and stored as records.
 *   3. persistAll() ran unguarded, so any persistence error reached the editor
 *      as an exception page instead of a flash message.
 *
 * Fixtures (Fixtures/TranslationRepository/*.csv), all on pid 1:
 *   uid 1  language 0  environment 1 / component 1 / type 1 / "submit"    = "Submit"
 *   uid 5  language 1  l10n_parent 1                          "submit"    = "Absenden"
 *   uid 2  language 0  environment 1 / component 1 / type 2 / "email"     = "Email Address"
 *
 * The second group of tests covers the module's filter config. A backend user
 * without a stored config used to receive an empty array. listAction() then
 * logged "Undefined array key" for placeholder and value, and the "no filter
 * selected" guard of exportAction() compared against null.
 *
 * The third group covers issue #129: translateRecordAction() trusted the
 * array keys and values of $new/$update coming straight from the request.
 * Extbase's property mapping does not enforce the documented element shape at
 * runtime, so a non-int key, a non-string value, or a language id that is not
 * configured on any site used to crash the action or silently persist an
 * unreachable translation.
 *
 * @see https://github.com/netresearch/t3x-nr-textdb/issues/100
 * @see https://github.com/netresearch/t3x-nr-textdb/issues/129
 */
#[CoversClass(TranslationController::class)]
final class TranslationControllerTest extends AbstractFunctionalTestCase
{
    private const FILTER_REQUIRED_MESSAGE
        = 'Please select at least one type or component to create an export.';

    private const TEXTDB_TABLES
        = 'tx_nrtextdb_domain_model_translation,tx_nrtextdb_domain_model_component,'
        . 'tx_nrtextdb_domain_model_type,tx_nrtextdb_domain_model_environment';

    private TranslationController $controller;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->setExtensionConfiguration(textDbPid: '1', createIfMissing: '0');

        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.com/typo3/module/netresearch/textdb', 'POST'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $this->importFixture('Pages.csv');
        $this->importFixture('BackendUser.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/TranslationRepository/Environments.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/TranslationRepository/Components.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/TranslationRepository/Types.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/TranslationRepository/Translations.csv');

        $this->writeSiteConfiguration();

        // Flash messages of the backend module are session bound, so a backend
        // user has to be present for the queue to accept them.
        $this->setUpBackendUser(1);

        // The module renders its messages through $GLOBALS['LANG'], which only
        // the backend request handler sets up.
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)
            ->createFromUserPreferences($GLOBALS['BE_USER']);

        $this->controller = $this->get(TranslationController::class);
    }

    #[Override]
    protected function tearDown(): void
    {
        // Both are set per test and are not part of the state the testing
        // framework restores.
        unset($GLOBALS['LANG'], $GLOBALS['TYPO3_REQUEST']);

        parent::tearDown();
    }

    #[Test]
    public function translateRecordUpdatesTheDefaultLanguageRecordSubmittedAsNew(): void
    {
        // The dialog renders a new[0] textarea whenever the default-language row
        // is not part of its "translated" list. Before the fix this inserted a
        // second language-0 row for the same placeholder.
        $this->dispatchTranslateRecord(['parent' => '1', 'new' => [0 => 'Send it']]);

        self::assertSame(
            1,
            $this->countRows(placeholder: 'submit', languageUid: 0),
            'Submitting new[0] for an existing default record must not insert a second row.',
        );
        self::assertSame('Send it', $this->fetchValue(1));
        self::assertSame(
            ['OK' => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.saved')]],
            $this->flashMessagesGroupedBySeverity(),
            'A fully valid submission must not also queue the rejected-entries warning.',
        );
    }

    #[Test]
    public function translateRecordUpdatesAnExistingLocalizedRecordSubmittedAsNew(): void
    {
        $this->dispatchTranslateRecord(['parent' => '1', 'new' => [1 => 'Abschicken']]);

        self::assertSame(
            1,
            $this->countRows(placeholder: 'submit', languageUid: 1),
            'Submitting new[1] for an existing German record must not insert a second row.',
        );
        self::assertSame('Abschicken', $this->fetchValue(5));
    }

    #[Test]
    public function translateRecordSavedTwiceDoesNotFail(): void
    {
        // The reported reproducer: the first save silently created a duplicate,
        // the second one aborted with "Duplicate entry … for key 'translation'".
        $this->dispatchTranslateRecord(['parent' => '1', 'new' => [0 => 'First']]);
        $this->dispatchTranslateRecord(['parent' => '1', 'new' => [0 => 'Second']]);

        self::assertSame(1, $this->countRows(placeholder: 'submit', languageUid: 0));
        self::assertSame('Second', $this->fetchValue(1));
        self::assertSame(
            ['OK' => array_fill(0, 2, $GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.saved'))],
            $this->flashMessagesGroupedBySeverity(),
            'Two successful saves must not also queue a warning or an error message.',
        );
    }

    #[Test]
    public function translateRecordSkipsEmptySubmittedValues(): void
    {
        // Language 2 has no record at all; an untouched textarea must not create one.
        $this->dispatchTranslateRecord(['parent' => '1', 'new' => [2 => '   ']]);

        self::assertSame(0, $this->countRows(placeholder: 'submit', languageUid: 2));
        // A blank value is skipped before it reaches saveNewTranslation(), so
        // it counts as neither accepted nor rejected. The WARNING below comes
        // not from a rejection but from $nothingWasSaved: something was
        // submitted and nothing was accepted, so the (misleading, nothing was
        // saved) success message must not appear either (issue #129 follow-up).
        self::assertSame(
            [
                'WARNING' => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.rejected')],
            ],
            $this->flashMessagesGroupedBySeverity(),
        );
    }

    #[Test]
    public function translateRecordCreatesRecordForALanguageThatHasNone(): void
    {
        $this->dispatchTranslateRecord(['parent' => '1', 'new' => [2 => 'Envoyer']]);

        self::assertSame(1, $this->countRows(placeholder: 'submit', languageUid: 2));
    }

    #[Test]
    public function translateRecordUpdatesLocalizedRecordAddressedByUid(): void
    {
        // update[<uid>] carries the localized uid. Repository::findByUid() is
        // language aware and returned null for the German row in a backend
        // context, so the edit was silently dropped.
        $this->dispatchTranslateRecord(['parent' => '1', 'update' => [5 => 'Absenden!']]);

        self::assertSame('Absenden!', $this->fetchValue(5));
    }

    #[Test]
    public function translateRecordSurfacesAPersistenceFailureAsFlashMessage(): void
    {
        $persistenceManager = self::createStub(PersistenceManagerInterface::class);
        $persistenceManager
            ->method('persistAll')
            ->willThrowException(new RuntimeException('Duplicate entry'));

        $controller = new TranslationController(
            $this->get(ModuleTemplateFactory::class),
            $this->get(ExtensionConfiguration::class),
            $this->get(IconFactory::class),
            $this->get(EnvironmentRepository::class),
            $this->get(TranslationRepository::class),
            $this->get(TranslationService::class),
            $persistenceManager,
            $this->get(ComponentRepository::class),
            $this->get(TypeRepository::class),
            $this->get(ImportService::class),
            $this->get(FlashMessageService::class),
            $this->get(ComponentFactory::class),
        );

        // translateRecordAction() always redirects now, which needs $request/
        // $uriBuilder populated by ActionController::processRequest(), and
        // this manually-constructed instance (needed for the mocked
        // PersistenceManagerInterface) never goes through it, so both are
        // built by hand here, minimally, just enough for redirectToUri() not
        // to crash. Extbase controllers are not shared in the container
        // (each dispatch gets its own instance), so there is no already-
        // initialized instance to borrow this state from either.
        $extbaseRequestParameters = new ExtbaseRequestParameters(TranslationController::class);
        $extbaseRequestParameters
            ->setControllerExtensionName('NrTextdb')
            ->setPluginName('netresearch_textdb')
            ->setControllerName('Translation')
            ->setControllerActionName('translateRecord')
            ->setFormat('html');

        $extbaseRequest = new ExtbaseRequest(
            (new ServerRequest('https://example.com/typo3/module/netresearch/textdb', 'POST'))
                ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
                ->withAttribute('extbase', $extbaseRequestParameters),
        );

        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $uriBuilder->setRequest($extbaseRequest);

        $reflectionClass = new ReflectionClass(TranslationController::class);
        $reflectionClass->getProperty('request')->setValue($controller, $extbaseRequest);
        $reflectionClass->getProperty('uriBuilder')->setValue($controller, $uriBuilder);

        $controller->translateRecordAction(1, [0 => 'Send it']);

        $messages = $this->errorFlashMessages();

        self::assertCount(1, $messages);
        self::assertStringContainsString('Duplicate entry', $messages[0]);
    }

    #[Test]
    public function translateRecordSkipsAnUpdateValueThatIsNotAString(): void
    {
        // Extbase's property mapping does not enforce the array value type
        // declared in the PHPDoc at runtime. update[5][]=x reaches this method
        // as ['x'], which used to crash trim() with a TypeError (issue #129,
        // case 2).
        $this->dispatchTranslateRecord(['parent' => '1', 'update' => [5 => ['x']]]);

        self::assertSame('Absenden', $this->fetchValue(5), 'A malformed update value must leave the record untouched.');
        self::assertSame(
            [
                'WARNING' => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.rejected')],
            ],
            $this->flashMessagesGroupedBySeverity(),
        );
    }

    #[Test]
    public function translateRecordSkipsANewValueThatIsNotAString(): void
    {
        // Same defect on the new[] side: new[0][]=x reaches this method as
        // [0 => ['x']] and used to crash trim() with a TypeError.
        $this->dispatchTranslateRecord(['parent' => '1', 'new' => [0 => ['x']]]);

        self::assertSame(1, $this->countRows(placeholder: 'submit', languageUid: 0));
        self::assertSame('Submit', $this->fetchValue(1), 'A malformed new value must not overwrite the existing record.');
        self::assertSame(
            [
                'WARNING' => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.rejected')],
            ],
            $this->flashMessagesGroupedBySeverity(),
        );
    }

    #[Test]
    public function translateRecordSkipsAnUpdateKeyThatIsNotAnInteger(): void
    {
        // update[foo]=x reaches this method with a string array key, which used
        // to crash findRawByUid() with a TypeError under strict_types (issue
        // #129, case 1).
        $this->dispatchTranslateRecord(['parent' => '1', 'update' => ['foo' => 'x']]);

        self::assertSame('Absenden', $this->fetchValue(5), 'A malformed update key must leave every record untouched.');
        self::assertSame(
            [
                'WARNING' => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.rejected')],
            ],
            $this->flashMessagesGroupedBySeverity(),
        );
    }

    #[Test]
    public function translateRecordSkipsANewKeyThatIsNotAnInteger(): void
    {
        // Same defect on the new[] side: a non-numeric array key (e.g. from a
        // hand-crafted request) reaches this method as a string.
        $this->dispatchTranslateRecord(['parent' => '1', 'new' => ['foo' => 'x']]);

        self::assertSame(1, $this->countRows(placeholder: 'submit', languageUid: 0));
        self::assertSame(
            [
                'WARNING' => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.rejected')],
            ],
            $this->flashMessagesGroupedBySeverity(),
        );
    }

    #[Test]
    public function translateRecordSkipsAnUpdateForANonPositiveUid(): void
    {
        // update[0]=x and a negative uid can never address a record, findRawByUid()
        // would just return null for either, and the not-found case counts as
        // rejected too (see the else branch in translateRecordAction()). The
        // early guard here only spares a pointless lookup, it is not what
        // decides the outcome.
        $this->dispatchTranslateRecord(['parent' => '1', 'update' => [0 => 'x', -3 => 'y']]);

        self::assertSame(
            [
                'WARNING' => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.rejected')],
            ],
            $this->flashMessagesGroupedBySeverity(),
        );
    }

    #[Test]
    public function translateRecordSkipsAnUpdateForAUidThatDoesNotExist(): void
    {
        // update[999999]=x is well-formed (a positive int key, a string value)
        // but does not resolve to a record, e.g. deleted between page load and
        // submit, or a tampered value. Before this fix it was neither accepted
        // nor rejected, so it was dropped without any signal, same as a
        // malformed entry used to be before issue #129.
        $this->dispatchTranslateRecord(['parent' => '1', 'update' => [5 => 'Absenden!', 999999 => 'Ghost']]);

        self::assertSame('Absenden!', $this->fetchValue(5));
        self::assertSame(
            [
                'WARNING' => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.rejected')],
                'OK'      => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.saved')],
            ],
            $this->flashMessagesGroupedBySeverity(),
        );
    }

    #[Test]
    public function translateRecordSkipsANewEntryWhenTheParentHasNoEnvironmentComponentOrType(): void
    {
        // Parent uid 12 carries environment/component/type = 0, the orphaned-row
        // shape issue #100 already guards against on the update[] side.
        // saveNewTranslation() returns false for it (createTranslationFromParent()
        // refuses to build a translation without a real parent relation), and
        // that must count as rejected too, or a tampered/orphaned parent
        // silently swallows new[] entries with no signal.
        $this->dispatchTranslateRecord(['parent' => '12', 'new' => [1 => 'Some text']]);

        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation');

        self::assertSame(
            0,
            (int) $connection->count(
                'uid',
                'tx_nrtextdb_domain_model_translation',
                [
                    'placeholder'      => 'orphan_test',
                    'sys_language_uid' => 1,
                ],
            ),
            'A parent without environment/component/type must not produce a new row.',
        );
        self::assertSame(
            [
                'WARNING' => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.rejected')],
            ],
            $this->flashMessagesGroupedBySeverity(),
        );
    }

    #[Test]
    public function translateRecordSkipsTheWholeNewPayloadWhenTheParentDoesNotExist(): void
    {
        // $parent itself does not resolve to a record (tampered or stale
        // uid). Every new[] entry is unreachable in that case, and dropping
        // the whole payload without a signal would defeat the same guarantee
        // the per-entry checks give: a mixed request with a valid update[]
        // entry alongside it must still warn about the lost new[] payload,
        // not just report the unrelated update[] success.
        $this->dispatchTranslateRecord(['parent' => '999999', 'new' => [0 => 'Some text'], 'update' => [5 => 'Absenden!']]);

        self::assertSame('Absenden!', $this->fetchValue(5));
        self::assertSame(
            [
                'WARNING' => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.rejected')],
                'OK'      => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.saved')],
            ],
            $this->flashMessagesGroupedBySeverity(),
        );
    }

    #[Test]
    public function translateRecordDoesNotWarnWhenTheInvalidParentHasNoNewPayload(): void
    {
        // The parent-not-found rejection only applies when new[] actually
        // carries a payload that becomes unreachable. An update[]-only save
        // against a stale/tampered parent id must not spuriously warn about a
        // "lost" new[] submission that was never there.
        $this->dispatchTranslateRecord(['parent' => '999999', 'update' => [5 => 'Absenden!']]);

        self::assertSame('Absenden!', $this->fetchValue(5));
        self::assertSame(
            ['OK' => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.saved')]],
            $this->flashMessagesGroupedBySeverity(),
        );
    }

    #[Test]
    public function translateRecordDoesNotWarnWhenTheInvalidParentsNewPayloadIsAllBlank(): void
    {
        // A real dialog submit posts one empty new[<language>] textarea per
        // untranslated language on every save. If the parent happened to be
        // deleted in the meantime, an editor who only touched the update[]
        // side must not be told their untouched textareas "could not be
        // saved", there was nothing there to lose.
        $this->dispatchTranslateRecord(['parent' => '999999', 'new' => [1 => '   '], 'update' => [5 => 'Absenden!']]);

        self::assertSame('Absenden!', $this->fetchValue(5));
        self::assertSame(
            ['OK' => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.saved')]],
            $this->flashMessagesGroupedBySeverity(),
        );
    }

    #[Test]
    public function translateRecordWarnsWhenTheInvalidParentsNewPayloadIsMalformed(): void
    {
        // A malformed (non-string) new[] value is a payload, not a blank, so
        // it must count towards the "lost payload" warning the same way a
        // real typed value does, even though the parent doesn't exist either.
        $this->dispatchTranslateRecord(['parent' => '999999', 'new' => [0 => ['x']], 'update' => [5 => 'Absenden!']]);

        self::assertSame('Absenden!', $this->fetchValue(5));
        self::assertSame(
            [
                'WARNING' => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.rejected')],
                'OK'      => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.saved')],
            ],
            $this->flashMessagesGroupedBySeverity(),
        );
    }

    #[Test]
    public function translateRecordQueuesNoFlashMessageForAnEmptySubmission(): void
    {
        // parent alone, without any new[]/update[] payload, is a no-op: not a
        // rejection (nothing was submitted to reject) and not a save either.
        $this->dispatchTranslateRecord(['parent' => '1']);

        self::assertSame([], $this->flashMessagesGroupedBySeverity());
    }

    #[Test]
    public function translateRecordSkipsANewEntryForALanguageThatIsNotConfiguredOnTheFirstSite(): void
    {
        // new[999]=Text reaches this method with a syntactically valid but
        // meaningless language id. Before the fix this silently persisted a
        // translation that could never be reached again through the translation
        // dialog, since that only lists the site's configured languages (issue
        // #129, case 3). getAllLanguages() resolves the first configured site
        // only (see TranslationService::getAllLanguages()), hence "first site"
        // rather than "any site" in this test's name.
        $this->dispatchTranslateRecord(['parent' => '1', 'new' => [999 => 'Some text']]);

        self::assertSame(0, $this->countRows(placeholder: 'submit', languageUid: 999));
        self::assertSame(
            [
                'WARNING' => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.rejected')],
            ],
            $this->flashMessagesGroupedBySeverity(),
        );
    }

    #[Test]
    public function translateRecordSkipsANewEntryForANegativeLanguageId(): void
    {
        // The issue notes negative language ids pass through "in the same way"
        // as out-of-range positive ones. getAllLanguages() never configures a
        // negative language id, so this is rejected the same way as case 3.
        $this->dispatchTranslateRecord(['parent' => '1', 'new' => [-5 => 'Some text']]);

        self::assertSame(0, $this->countRows(placeholder: 'submit', languageUid: -5));
        self::assertSame(
            [
                'WARNING' => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.rejected')],
            ],
            $this->flashMessagesGroupedBySeverity(),
        );
    }

    #[Test]
    public function translateRecordSavesAMixOfValidAndRejectedEntries(): void
    {
        // The valid entry is saved and reported. The rejected one is not
        // silently dropped either, a real dialog submission always carries at
        // least one valid update[] entry (the record itself, echoed back), so
        // an "only warn when nothing at all was saved" rule would never fire
        // for a tampered entry submitted alongside a normal save.
        $this->dispatchTranslateRecord(['parent' => '1', 'new' => [0 => 'Send it', 999 => 'Rejected']]);

        self::assertSame('Send it', $this->fetchValue(1));
        self::assertSame(0, $this->countRows(placeholder: 'submit', languageUid: 999));
        self::assertSame(
            [
                'WARNING' => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.rejected')],
                'OK'      => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.translation.saved')],
            ],
            $this->flashMessagesGroupedBySeverity(),
        );
    }

    #[Test]
    public function translateRecordThroughTheRouteAcceptsAMalformedUpdateKey(): void
    {
        // Confirms the premise of issue #129 end to end: dispatched through the
        // real Extbase bootstrap rather than called directly, translateRecordAction()
        // still receives update[foo] with the string key intact, proving Extbase's
        // property mapping does not enforce the documented array<int, string> shape.
        $response = $this->dispatchTranslateRecord([
            'parent' => '1',
            'update' => ['foo' => 'x'],
        ]);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('Translation/translated', $response->getHeaderLine('Location'));
        self::assertSame('Absenden', $this->fetchValue(5));
    }

    #[Test]
    public function translatedViewLabelsEachTextareaByTheRowHeaderOfItsOwnLanguage(): void
    {
        // uid 1 has a record in languages 0 and 1; with Italian added the site
        // also has languages 2 and 3, so both row types occur twice: two
        // "translated" and two "untranslated" rows. Each textarea is named by
        // the row header of its own row, so the row-header ids must be unique
        // across all rows of the rendered view.
        $this->writeSiteConfiguration(withItalian: true);

        $html = (string) $this->dispatchModuleAction('translated', ['uid' => '1'])->getBody();

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);

        $rowHeaders = $xpath->query('//table//tbody/tr/th[@scope="row"]');
        self::assertNotFalse($rowHeaders);
        self::assertSame(4, $rowHeaders->length);

        $ids = [];

        foreach ($rowHeaders as $rowHeader) {
            self::assertInstanceOf(DOMElement::class, $rowHeader);
            $ids[] = $rowHeader->getAttribute('id');
        }

        self::assertSame(array_values(array_unique($ids)), $ids, 'row header ids must be unique: ' . implode(', ', $ids));

        $textareas = $xpath->query('//table//tbody/tr/td/textarea');
        self::assertNotFalse($textareas);
        self::assertSame(4, $textareas->length);

        $untranslated = $xpath->query('//table//tbody/tr[contains(concat(" ", normalize-space(@class), " "), " untranslated ")]');
        self::assertNotFalse($untranslated);
        self::assertSame(2, $untranslated->length);

        foreach ($textareas as $textarea) {
            self::assertInstanceOf(DOMElement::class, $textarea);

            $labelledBy = $textarea->getAttribute('aria-labelledby');
            $ownHeader  = $xpath->query('ancestor::tr[1]/th[@scope="row"]', $textarea);
            self::assertNotFalse($ownHeader);

            $header = $ownHeader->item(0);
            self::assertInstanceOf(DOMElement::class, $header);
            self::assertSame(
                $header->getAttribute('id'),
                $labelledBy,
                'textarea "' . $textarea->getAttribute('name') . '" must be labelled by its own row header',
            );
            self::assertSame($header, $document->getElementById($labelledBy));
        }
    }

    #[Test]
    public function translateRecordThroughTheRouteAcceptsAMalformedArrayValue(): void
    {
        // The action itself rejects update[1][]=x before touching the record
        // (see translateRecordSkipsAnUpdateValueThatIsNotAString), but the
        // forward this action used to return re-rendered the "translated"
        // view within the SAME request, and Fluid's f:form.textarea
        // repopulates a field from the request's own submitted value
        // whenever the field name matches, even a rejected one. Casting the
        // rejected array to a string to render it crashed the page with
        // "Array to string conversion", live-reproduced against a real
        // TYPO3 v14.3 backend before this was changed to a redirect, which
        // starts a fresh GET with no submitted body left to
        // repopulate from. A direct translateRecordAction() call cannot
        // reproduce this, it never renders the forward target's view.
        $response = $this->dispatchTranslateRecord([
            'parent' => '1',
            'update' => [1 => ['x']],
        ]);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('Translation/translated', $response->getHeaderLine('Location'));
        self::assertSame('Absenden', $this->fetchValue(5));
    }

    #[Test]
    public function translateRecordThroughTheRouteRedirectsInsteadOfForwardingWhenTheParentArgumentFailsToMap(): void
    {
        // parent=abc fails Extbase's own int property mapping before
        // translateRecordAction() ever runs, so this never reaches its
        // validation at all. The default ActionController::errorAction()
        // forwards back to the referring "translated" action via a
        // ForwardResponse, which the Extbase Dispatcher resolves by
        // re-dispatching within the same request/response, so the browser
        // never sees it as a real navigation: the address bar and history
        // entry stay on the failed request (a POST in real module usage,
        // this dispatch helper always issues a GET, the forward mechanism
        // is identical either way), and a page refresh would repeat it.
        // TranslationController overrides errorAction() to redirect there
        // instead, verified against this exact input: without the
        // override, this dispatch renders the referring view inline with a
        // 200 (Failed asserting that 200 is identical to 303); with it,
        // the browser gets a real 303 to a fresh GET.
        //
        // forwardToReferringRequest() only takes that path if a real,
        // HMAC-signed __referrer is present, exactly what the "translated"
        // view's own f:form renders into its hidden fields, so this
        // extracts one from a real render of that view instead of hand-
        // building an HMAC.
        $referrer = $this->extractReferrerFields(
            (string) $this->dispatchModuleAction(
                'translated',
                ['uid' => '1'],
            )->getBody(),
        );

        $response = $this->dispatchTranslateRecord([
            'parent'     => 'abc',
            '__referrer' => $referrer,
        ]);

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('Translation/translated', $response->getHeaderLine('Location'));
        self::assertSame(
            ['ERROR' => [$GLOBALS['LANG']->sL('LLL:EXT:nr_textdb/Resources/Private/Language/locallang.xlf:message.error.translation.request')]],
            $this->flashMessagesGroupedBySeverity(),
        );
    }

    #[Test]
    public function translateRecordThroughTheRouteFallsBackToTheDefaultErrorResponseWithoutAReferrer(): void
    {
        // Without a __referrer at all (e.g. a direct API call), there is no
        // '@request' to read, so forwardToReferringRequest() returns null
        // and errorAction() falls back to the framework's own default
        // response instead of trying to redirect anywhere. A __referrer
        // that IS present but fails its HMAC validation is a different
        // case: that throws, it does not fall back.
        $response = $this->dispatchTranslateRecord(['parent' => 'abc']);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function translateRecordThroughTheRouteRejectsAReferrerTargetingAnUnroutableAction(): void
    {
        // The referrer HMAC scope is installation-global, not bound to this
        // module, so a validly signed __referrer can name any action,
        // including one that does not exist as a method at all, or this
        // controller's own non-routed errorAction() itself. Neither is a
        // registered route under this module (Configuration/Backend/
        // Modules.php's controllerActions), so uriFor() cannot build a URI
        // for it (mutation-verified: an actual allowlist of action names
        // added ahead of this test did not change its outcome, uriFor()'s
        // own route lookup already rejects it), and errorAction() falls
        // through to a plain error response instead of ever redirecting
        // anywhere.
        $response = $this->dispatchTranslateRecord([
            'parent'     => 'abc',
            '__referrer' => $this->buildSignedReferrer('notARegisteredAction'),
        ]);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function translateRecordThroughTheRouteRejectsAReferrerFromAnUnroutableForeignController(): void
    {
        // Same installation-global HMAC scope as above, but here the
        // referrer names a real, routable action, just on a different
        // controller (as any other Extbase backend module's own form would
        // sign). "SomeOtherController_list" is not a route this module
        // registers either, so this hits the exact same uriFor() rejection
        // as the unroutable-action case above, not a dedicated controller
        // check (mutation-verified, this test still passes with no
        // controller-name check present at all).
        $response = $this->dispatchTranslateRecord([
            'parent'     => 'abc',
            '__referrer' => $this->buildSignedReferrer('list', 'SomeOtherController'),
        ]);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function translateRecordThroughTheRouteRejectsAReferrerFromAForeignExtension(): void
    {
        // Same as the two cases above, but with a matching controller/
        // action pair from a different extension entirely ('Translation'/
        // 'list' happens to also be a plausible controller/action pair
        // elsewhere). uriFor()'s backend routing builds its route
        // identifier from THIS request's own module, not the referrer, and
        // never consults extensionName at all, so unlike the two cases
        // above, this one WOULD build a valid URI for this module's own
        // Translation/list route (silently honouring a foreign referrer
        // onto our own module, not redirecting to the foreign extension
        // itself) and 303 there without errorAction()'s explicit
        // extensionName check (mutation-verified, removing that check
        // turns this into a 303).
        $response = $this->dispatchTranslateRecord([
            'parent'     => 'abc',
            '__referrer' => $this->buildSignedReferrer('list', 'Translation', 'SomeOtherExtension'),
        ]);

        self::assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function exportWithoutAStoredFilterIsRefused(): void
    {
        // A backend user that has never opened the module has no stored config.
        // Reading it used to yield an empty array, which left the destructured
        // component at null, and null === 0 never matched the guard below.
        self::assertNull($GLOBALS['BE_USER']->getModuleData(TranslationController::class));

        $response = $this->dispatchModuleAction('export');

        self::assertSame(303, $response->getStatusCode());
        self::assertStringContainsString('Translation/list', $response->getHeaderLine('Location'));
        self::assertSame([self::FILTER_REQUIRED_MESSAGE], $this->errorFlashMessages());
    }

    #[Test]
    public function exportWithAStoredFilterIsCarriedOut(): void
    {
        $this->storeFilterConfig(['component' => 1]);

        $response = $this->dispatchModuleAction('export');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/zip; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringStartsWith(
            'PK',
            (string) $response->getBody(),
            'The response has to carry the archive itself, not just its headers.',
        );
        self::assertSame([], $this->errorFlashMessages());
    }

    #[Test]
    public function exportWithAStoredTypeOnlyFilterIsCarriedOut(): void
    {
        // The export filters by component and type, and either one on its own is
        // a filter. Only both being unset means "no filter selected".
        $this->storeFilterConfig(['type' => 2]);

        $response = $this->dispatchModuleAction('export');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $this->errorFlashMessages());
    }

    /**
     * @param string $storedConfig Raw payload, as it sits in be_users.uc
     */
    #[Test]
    #[DataProvider('unusableStoredFilterProvider')]
    public function exportWithAnUnusableStoredFilterIsRefused(string $storedConfig): void
    {
        // be_users.uc is writable by the backend user itself through the
        // usersettings endpoint of the core, so none of these payloads is
        // exotic. Each one has to end up as "no filter selected" rather than as
        // a value that passes the guard and exports everything.
        $GLOBALS['BE_USER']->pushModuleData(TranslationController::class, $storedConfig);

        $response = $this->dispatchModuleAction('export');

        self::assertSame(303, $response->getStatusCode());
        self::assertSame([self::FILTER_REQUIRED_MESSAGE], $this->errorFlashMessages());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unusableStoredFilterProvider(): array
    {
        return [
            'numeric string'     => ['{"component":"0","type":"0"}'],
            'negative uid'       => ['{"component":-1,"type":-1}'],
            'non numeric string' => ['{"component":"12abc","type":"none"}'],
            'wrong value type'   => ['{"component":true,"type":{"uid":1}}'],
            'legacy serialized'  => ['a:1:{s:9:"component";i:1;}'],
            'json scalar'        => ['"component"'],
        ];
    }

    #[Test]
    public function exportWithStoredTextFiltersOfTheWrongTypeIsCarriedOut(): void
    {
        // The repository signature is (int, int, ?string, ?string), so an array
        // or an int reaching it would be a TypeError under strict_types.
        $this->storeFilterConfig(
            [
                'component'   => 1,
                'placeholder' => ['submit'],
                'value'       => 42,
            ],
        );

        $response = $this->dispatchModuleAction('export');

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function listingTheModuleWithoutAStoredFilterEmitsNoWarning(): void
    {
        // The reported defect. listAction() read placeholder and value without a
        // fallback and logged "Undefined array key" twice on the first request of
        // every backend user. Warnings raised in Classes/ fail this suite, so the
        // status code is only half of what this test asserts.
        $response = $this->dispatchModuleAction('list');

        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function listingTheModuleStoresACompleteFilterConfig(): void
    {
        $this->dispatchModuleAction('list');

        self::assertSame(
            ['component' => 0, 'type' => 0, 'placeholder' => null, 'value' => null],
            $this->readStoredFilterConfig(),
        );
    }

    #[Test]
    public function listingTheModuleKeepsTheStoredFilterAndAppliesIt(): void
    {
        // The whole point of the config: the filter of the editor survives the
        // next call of the module, and it reaches the query. component and type
        // are deliberately different values, so a swap of the two in the
        // controller (a read or a write of the wrong argument) is observable.
        $this->storeFilterConfig(['component' => 1, 'type' => 2]);

        $response = $this->dispatchModuleAction('list');

        self::assertSame(
            ['component' => 1, 'type' => 2, 'placeholder' => null, 'value' => null],
            $this->readStoredFilterConfig(),
        );

        // The row ids of the partial, because the placeholder "submit" also
        // appears as the button type of the filter form in every request.
        $body = (string) $response->getBody();

        self::assertStringContainsString('id="entry-2"', $body, 'The filtered record has to be listed.');
        self::assertStringNotContainsString(
            'id="entry-1"',
            $body,
            'Record 1 shares the component and differs in the type, so it pins that operand.',
        );
        self::assertStringNotContainsString(
            'id="entry-4"',
            $body,
            'Record 4 differs in both, so it pins the filter as a whole.',
        );
    }

    #[Test]
    public function listingTheModuleStoresTheSubmittedFilter(): void
    {
        // component and type are deliberately different, so a swap of the two
        // arguments in the controller does not go unnoticed.
        $this->dispatchModuleAction(
            'list',
            [
                'component'   => '1',
                'type'        => '2',
                'placeholder' => ' submit ',
                'value'       => ' Submit ',
            ],
        );

        self::assertSame(
            ['component' => 1, 'type' => 2, 'placeholder' => 'submit', 'value' => 'Submit'],
            $this->readStoredFilterConfig(),
        );
    }

    #[Test]
    public function listingTheModuleDiscardsASubmittedFilterOfTheWrongType(): void
    {
        // Backend module arguments arrive as the raw query parameters, so a filter
        // can be submitted as an array. Casting it used to raise an "Array to
        // string conversion" warning for the text filter, and it silently turned
        // the record filter into uid 1.
        $this->dispatchModuleAction(
            'list',
            [
                'component'   => ['1'],
                'type'        => ['1'],
                'placeholder' => ['submit'],
                // A query parameter carries arbitrary bytes, and json_encode()
                // throws on malformed UTF-8 when the config is written back.
                'value' => "\x80",
            ],
        );

        self::assertSame(
            ['component' => 0, 'type' => 0, 'placeholder' => null, 'value' => null],
            $this->readStoredFilterConfig(),
        );
    }

    /**
     * @param string|string[] $currentPage
     */
    #[Test]
    #[DataProvider('unusablePageNumberProvider')]
    public function listingTheModuleSurvivesAnUnusablePageNumber(string|array $currentPage): void
    {
        // The page field of the pagination partial has no "required", so the core
        // puts an emptied value into the navigation URL as currentPage=. The
        // paginator rejects everything below its first page with an exception.
        $response = $this->dispatchModuleAction('list', ['currentPage' => $currentPage]);

        self::assertSame(200, $response->getStatusCode());
    }

    /**
     * @return array<string, array{string|string[]}>
     */
    public static function unusablePageNumberProvider(): array
    {
        return [
            'emptied field' => [''],
            'zero'          => ['0'],
            'negative'      => ['-1'],
            'not a number'  => ['abc'],
            'array'         => [['1']],
        ];
    }

    #[Test]
    public function translateRecordSavesForAnEditorWhoMayModifyTheTranslationTable(): void
    {
        $this->loginEditor();

        $response = $this->dispatchTranslateRecord(['parent' => '1', 'update' => [5 => 'Absenden!']]);

        self::assertSame(303, $response->getStatusCode());
        self::assertSame('Absenden!', $this->fetchValue(5));
    }

    #[Test]
    public function translateRecordRefusesAnEditorWhoMayOnlyReadTheTranslationTable(): void
    {
        $this->loginEditor(tablesModify: '');

        $response = $this->dispatchTranslateRecord(['parent' => '1', 'update' => [5 => 'Absenden!'], 'new' => [2 => 'Envoyer']]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('Absenden', $this->fetchValue(5));
        self::assertSame(0, $this->countRows(placeholder: 'submit', languageUid: 2));
    }

    #[Test]
    public function translateRecordRefusesAnEditorWithoutEditAccessToTheStoragePage(): void
    {
        $this->loginEditor(pagePermissions: Permission::PAGE_SHOW);

        $response = $this->dispatchTranslateRecord(['parent' => '1', 'update' => [5 => 'Absenden!']]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('Absenden', $this->fetchValue(5));
    }

    #[Test]
    public function translateRecordRefusesAnEditorWorkingInADraftWorkspace(): void
    {
        // The TextDB tables are not workspace-aware, so a save writes the live
        // record. DataHandler refuses that from a draft workspace.
        $this->loginEditor();
        $GLOBALS['BE_USER']->workspace = 1;

        $response = $this->dispatchTranslateRecord(['parent' => '1', 'update' => [5 => 'Absenden!']]);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('Absenden', $this->fetchValue(5));
    }

    #[Test]
    public function translateRecordSavesOnlyTheLanguagesTheEditorMayEdit(): void
    {
        // Language 0 is allowed, German (1) and French (2) are not.
        $this->loginEditor(allowedLanguages: '0');

        $this->dispatchTranslateRecord([
            'parent' => '1',
            'new'    => [0 => 'Send it', 2 => 'Envoyer'],
            'update' => [5 => 'Absenden!'],
        ]);

        self::assertSame('Send it', $this->fetchValue(1));
        self::assertSame('Absenden', $this->fetchValue(5));
        self::assertSame(0, $this->countRows(placeholder: 'submit', languageUid: 2));
        self::assertNotSame([], $this->flashMessagesOfSeverity(ContextualFeedbackSeverity::WARNING));
    }

    #[Test]
    public function translateRecordRejectsARecordOnAPageTheEditorMayNotEdit(): void
    {
        // uid 8 lies on page 2, outside the editor's mount and permissions.
        $this->loginEditor();

        $this->dispatchTranslateRecord(['parent' => '1', 'update' => [5 => 'Absenden!', 8 => 'Changed']]);

        self::assertSame('Absenden!', $this->fetchValue(5));
        self::assertSame('Submit PID2', $this->fetchValue(8));
        self::assertNotSame([], $this->flashMessagesOfSeverity(ContextualFeedbackSeverity::WARNING));
    }

    #[Test]
    public function translateRecordRejectsANewTranslationOfARecordOnAPageTheEditorMayNotSee(): void
    {
        // uid 8 lies on page 2; a translation of it would be a localisation
        // of a record the editor may not see.
        $this->loginEditor();

        $this->dispatchTranslateRecord(['parent' => '8', 'new' => [2 => 'Envoyer']]);

        self::assertSame(0, $this->countChildrenOf(8));
        self::assertNotSame([], $this->flashMessagesOfSeverity(ContextualFeedbackSeverity::WARNING));
    }

    #[Test]
    public function exportLeavesOutTranslationsOnPagesTheEditorMayNotSee(): void
    {
        GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation')
            ->insert('tx_nrtextdb_domain_model_translation', [
                'uid'              => 50,
                'pid'              => 2,
                'sys_language_uid' => 1,
                'l10n_parent'      => 2,
                'environment'      => 1,
                'component'        => 1,
                'type'             => 2,
                'placeholder'      => 'email',
                'value'            => 'Only on page two',
            ]);

        $this->loginEditor(tablesModify: '');
        $this->storeFilterConfig(['component' => 1, 'type' => 0, 'placeholder' => null, 'value' => null]);

        $response = $this->dispatchModuleAction('export');

        self::assertSame('application/zip; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertStringNotContainsString('Only on page two', $this->exportedText($response));
        self::assertStringContainsString('Absenden', $this->exportedText($response));
    }

    #[Test]
    public function translatedRefusesARecordOnAPageTheEditorMayNotSee(): void
    {
        $this->loginEditor();

        self::assertSame(403, $this->dispatchModuleAction('translated', ['uid' => '8'])->getStatusCode());
        self::assertSame(200, $this->dispatchModuleAction('translated', ['uid' => '1'])->getStatusCode());
    }

    #[Test]
    public function listShowsTheTranslationsToAnEditorWhoMayReadTheTables(): void
    {
        $this->loginEditor(tablesModify: '');

        $response = $this->dispatchModuleAction('list', ['placeholder' => 'submit']);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Staging Submit', (string) $response->getBody());
    }

    #[Test]
    public function listShowsAnEditorOnlyTheRecordsOfTheStoragePage(): void
    {
        // uid 8 ("Submit PID2") lies on page 2, which the editor may not see.
        $this->loginEditor();

        $html = (string) $this->dispatchModuleAction('list', ['placeholder' => 'submit'])->getBody();

        self::assertStringContainsString('Staging Submit', $html);
        self::assertStringNotContainsString('Submit PID2', $html);
    }

    #[Test]
    public function listOffersAnEditorOnlyTheComponentsAndTypesOfTheStoragePage(): void
    {
        $this->insertComponentAndTypeOnPageTwo();
        $this->loginEditor();

        $html = (string) $this->dispatchModuleAction('list')->getBody();

        self::assertStringNotContainsString('component-on-page-two', $html);
        self::assertStringNotContainsString('type-on-page-two', $html);
    }

    #[Test]
    public function listOffersAnAdminTheComponentsAndTypesOfEveryPage(): void
    {
        $this->insertComponentAndTypeOnPageTwo();

        $html = (string) $this->dispatchModuleAction('list')->getBody();

        self::assertStringContainsString('component-on-page-two', $html);
        self::assertStringContainsString('type-on-page-two', $html);
    }

    #[Test]
    public function listShowsAnAdminTheRecordsOfEveryPage(): void
    {
        $html = (string) $this->dispatchModuleAction('list', ['placeholder' => 'submit'])->getBody();

        self::assertStringContainsString('Submit PID2', $html);
    }

    #[Test]
    public function listRefusesAnEditorWhoMayNotReadTheTranslationTable(): void
    {
        $this->loginEditor(
            tablesSelect: 'tx_nrtextdb_domain_model_component,tx_nrtextdb_domain_model_type,tx_nrtextdb_domain_model_environment',
            tablesModify: '',
        );

        $response = $this->dispatchModuleAction('list');

        self::assertSame(403, $response->getStatusCode());
        self::assertStringNotContainsString('Absenden', (string) $response->getBody());
    }

    #[Test]
    public function translatedRefusesAnEditorWithoutAccessToTheStoragePage(): void
    {
        $this->loginEditor(pagePermissions: 0);

        $response = $this->dispatchModuleAction('translated', ['uid' => '1']);

        self::assertSame(403, $response->getStatusCode());
        self::assertStringNotContainsString('Absenden', (string) $response->getBody());
    }

    #[Test]
    public function exportRefusesAnEditorWhoMayNotReadTheTranslationTable(): void
    {
        $this->loginEditor(tablesSelect: '', tablesModify: '');
        $this->storeFilterConfig(['component' => 1, 'type' => 0, 'placeholder' => null, 'value' => null]);

        $response = $this->dispatchModuleAction('export');

        self::assertSame(403, $response->getStatusCode());
        self::assertStringNotContainsString('application/zip', $response->getHeaderLine('Content-Type'));
    }

    #[Test]
    public function importCreatesEntriesForAnEditorWhoMayModifyAllTextDbTables(): void
    {
        $this->loginEditor(tablesModify: self::TEXTDB_TABLES);

        $response = $this->dispatchImport('brand_new|label|greeting', 'Hello');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(1, $this->countImportedRows('greeting'));
    }

    #[Test]
    public function importRefusesAnEditorWhoMayNotCreateComponents(): void
    {
        // An import creates missing components, types and environments, so it
        // needs modify access to those tables, not only to the translations.
        $this->loginEditor();

        $response = $this->dispatchImport('brand_new|label|greeting', 'Hello');

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(0, $this->countImportedRows('greeting'));
    }

    #[Test]
    public function importSkipsALanguageTheEditorMayNotEdit(): void
    {
        // The file is for the default language (0); the editor may edit German only.
        $this->loginEditor(tablesModify: self::TEXTDB_TABLES, allowedLanguages: '1');

        $response = $this->dispatchImport('brand_new|label|greeting', 'Hello');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(0, $this->countImportedRows('greeting'));
        self::assertStringContainsString('so its entries were not imported', (string) $response->getBody());
    }

    private function insertComponentAndTypeOnPageTwo(): void
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connectionPool->getConnectionForTable('tx_nrtextdb_domain_model_component')
            ->insert('tx_nrtextdb_domain_model_component', ['uid' => 90, 'pid' => 2, 'name' => 'component-on-page-two']);
        $connectionPool->getConnectionForTable('tx_nrtextdb_domain_model_type')
            ->insert('tx_nrtextdb_domain_model_type', ['uid' => 90, 'pid' => 2, 'name' => 'type-on-page-two']);
    }

    private function countChildrenOf(int $parentUid): int
    {
        return (int) GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation')
            ->count('uid', 'tx_nrtextdb_domain_model_translation', ['l10n_parent' => $parentUid]);
    }

    /**
     * Concatenates every file of an export archive.
     */
    private function exportedText(ResponseInterface $response): string
    {
        $archive = Environment::getVarPath() . '/textdb-export-test.zip';
        file_put_contents($archive, (string) $response->getBody());

        $zip = new ZipArchive();
        self::assertTrue($zip->open($archive));

        $text = '';
        for ($index = 0; $index < $zip->numFiles; ++$index) {
            $text .= (string) $zip->getFromIndex($index);
        }

        $zip->close();

        return $text;
    }

    /**
     * Counts translation rows with the placeholder in any component, type or
     * environment, which an import creates as it goes.
     */
    private function countImportedRows(string $placeholder): int
    {
        return (int) GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation')
            ->count('uid', 'tx_nrtextdb_domain_model_translation', ['placeholder' => $placeholder]);
    }

    /**
     * Logs in backend user 2, a non-admin member of group 1, after giving the
     * group the passed rights and everybody the passed permissions on the
     * storage page (uid 1). Defaults: read access to all four TextDB tables,
     * modify access to the translation table, every language, every page
     * permission.
     */
    private function loginEditor(
        string $tablesSelect = self::TEXTDB_TABLES,
        string $tablesModify = 'tx_nrtextdb_domain_model_translation',
        string $allowedLanguages = '',
        int $pagePermissions = Permission::ALL,
    ): void {
        $this->importFixture('EditorBackendUser.csv');

        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connectionPool->getConnectionForTable('be_groups')->update(
            'be_groups',
            [
                'tables_select'     => $tablesSelect,
                'tables_modify'     => $tablesModify,
                'allowed_languages' => $allowedLanguages,
            ],
            ['uid' => 1],
        );
        $connectionPool->getConnectionForTable('pages')->update(
            'pages',
            ['perms_everybody' => $pagePermissions],
            ['uid' => 1],
        );

        $this->setUpBackendUser(2);

        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)
            ->createFromUserPreferences($GLOBALS['BE_USER']);
    }

    /**
     * Runs importAction() with an uploaded default-language XLIFF file holding
     * one entry.
     */
    private function dispatchImport(string $key, string $source): ResponseInterface
    {
        $file = Environment::getVarPath() . '/textdb_import.xlf';
        file_put_contents(
            $file,
            '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<xliff version="1.2"><file source-language="en" datatype="plaintext" original="textdb"><body>'
            . '<trans-unit id="' . htmlspecialchars($key) . '"><source>' . htmlspecialchars($source) . '</source></trans-unit>'
            . '</body></file></xliff>',
        );

        return $this->dispatchModuleAction(
            'import',
            [],
            ['translationFile' => new UploadedFile($file, (int) filesize($file), UPLOAD_ERR_OK, 'textdb_import.xlf')],
        );
    }

    /**
     * Runs one action of the backend module through the Extbase bootstrap.
     *
     * Calling the action on the controller instance directly skips
     * ActionController::processRequest(), which is the only place that assigns
     * $this->uriBuilder, so the export redirect dies before it is built. The
     * route attribute is what BackendViewFactory and the Extbase request builder
     * read the module and its controller from, and the normalized parameters are
     * what the page renderer of the module template resolves asset paths with.
     *
     * @param array<string, string|array<array-key, string|string[]>> $queryParams
     *                                                                               Backend module
     *                                                                               arguments arrive
     *                                                                               without a plugin
     *                                                                               namespace
     * @param array<string, UploadedFile>                             $uploadedFiles
     */
    private function dispatchModuleAction(string $action, array $queryParams = [], array $uploadedFiles = []): ResponseInterface
    {
        $route = $this->get(Router::class)
            ->getRoute('netresearch_textdb.Translation_' . $action);

        $request = (new ServerRequest('https://example.com/typo3/module/netresearch/textdb', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('route', $route)
            ->withAttribute('module', $route->getOption('module'))
            ->withQueryParams($queryParams)
            ->withUploadedFiles($uploadedFiles);

        $request = $request->withAttribute(
            'normalizedParams',
            NormalizedParams::createFromRequest($request),
        );

        $GLOBALS['TYPO3_REQUEST'] = $request;

        return $this->get(Bootstrap::class)
            ->handleBackendRequest($request);
    }

    /**
     * Dispatches translateRecordAction() through the real Extbase bootstrap.
     *
     * Calling the action directly skips ActionController::processRequest(),
     * which is the only place that assigns $this->uriBuilder, so the
     * translated-view redirect this action returns dies before it is built
     * (same root cause as dispatchModuleAction()'s own docblock, now hit by
     * every call since translateRecordAction() started redirecting there
     * unconditionally, a follow-up hardening found while verifying issue
     * #129's fix rather than part of #129's original scope).
     *
     * @param array<string, string|array<array-key, string|string[]>> $queryParams
     */
    private function dispatchTranslateRecord(array $queryParams): ResponseInterface
    {
        return $this->dispatchModuleAction('translateRecord', $queryParams);
    }

    /**
     * Publishes a site with three languages (English, German, French), or
     * with Italian as a fourth one.
     *
     * Called from setUp() for every test: translateRecordAction() now checks
     * new[] language ids against the site's configured languages (issue
     * #129), and the export actions iterate the languages of the first site
     * and produce no file at all without one. typo3/testing-framework 9 has
     * no helper for this any more, and a SiteFinder mock via
     * GeneralUtility::addInstance() does not work here either, because the
     * controller is built by the container. The configuration is therefore
     * written to the test instance, where tearDown() removes it again
     * together with its cache.
     */
    private function writeSiteConfiguration(bool $withItalian = false): void
    {
        $siteDirectory = Environment::getConfigPath() . '/sites/textdb';

        if (!is_dir($siteDirectory) && !mkdir($siteDirectory, 0777, true) && !is_dir($siteDirectory)) {
            self::fail('Could not create site directory ' . $siteDirectory);
        }

        $written = file_put_contents(
            $siteDirectory . '/config.yaml',
            <<<'YAML'
                rootPageId: 1
                base: 'https://example.com/'
                languages:
                  - languageId: 0
                    title: English
                    locale: en_US.UTF-8
                    base: /
                  - languageId: 1
                    title: German
                    locale: de_DE.UTF-8
                    base: /de/
                  - languageId: 2
                    title: French
                    locale: fr_FR.UTF-8
                    base: /fr/

                YAML
            . ($withItalian ? <<<'YAML'
                  - languageId: 3
                    title: Italian
                    locale: it_IT.UTF-8
                    base: /it/

                YAML : ''),
        );

        self::assertNotFalse($written, 'Could not write the site configuration.');

        // The site configuration is read through a cache that was already built.
        $this->get(SiteConfiguration::class)->getAllExistingSites(false);
    }

    /**
     * Writes a filter config the way the module itself does.
     *
     * @param array<string, int|string|string[]|null> $config
     */
    private function storeFilterConfig(array $config): void
    {
        $GLOBALS['BE_USER']->pushModuleData(
            TranslationController::class,
            json_encode($config, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * @return array<string, int|string|null>
     */
    private function readStoredFilterConfig(): array
    {
        $rawConfig = $GLOBALS['BE_USER']->getModuleData(TranslationController::class);

        self::assertIsString($rawConfig, 'The module did not store a filter config.');

        $storedConfig = json_decode($rawConfig, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($storedConfig);

        return $storedConfig;
    }

    /**
     * Counts non-deleted rows for a placeholder in one language.
     */
    private function countRows(string $placeholder, int $languageUid): int
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation');

        // Scoped exactly like the unique key: the fixtures deliberately contain
        // the same placeholder on another pid (uid 8) and in another
        // environment (uid 11), which must not be touched.
        return (int) $connection->count(
            'uid',
            'tx_nrtextdb_domain_model_translation',
            [
                'placeholder'      => $placeholder,
                'sys_language_uid' => $languageUid,
                'pid'              => 1,
                'environment'      => 1,
                'component'        => 1,
                'type'             => 1,
                'deleted'          => 0,
            ],
        );
    }

    private function fetchValue(int $uid): string
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation');

        $value = $connection->select(
            ['value'],
            'tx_nrtextdb_domain_model_translation',
            ['uid' => $uid],
        )->fetchAssociative();

        self::assertIsArray($value, 'Translation record ' . $uid . ' is gone.');

        return (string) $value['value'];
    }

    /**
     * Builds a validly HMAC-signed __referrer[@request] targeting a chosen
     * controller/action, the same shape forwardToReferringRequest() expects
     * (ActionController.php), for exercising referrer targets a real
     * rendered view never points at, such as an action this module does
     * not register, or a controller belonging to a different module
     * entirely (the referrer HMAC scope is installation-global).
     *
     * @return array<string, string>
     */
    private function buildSignedReferrer(
        string $action,
        string $controller = 'Translation',
        string $extension = 'NrTextdb',
    ): array {
        $request = json_encode([
            '@extension'  => $extension,
            '@controller' => $controller,
            '@action'     => $action,
        ], JSON_THROW_ON_ERROR);

        return [
            '@request' => $this->get(HashService::class)->appendHmac(
                $request,
                HashScope::ReferringRequest->prefix(),
                HashAlgo::SHA3_256,
            ),
        ];
    }

    /**
     * Extracts the hidden __referrer[...] fields Fluid's f:form renders into
     * a real view, so a request can be replayed with a real, HMAC-signed
     * referrer instead of one built by hand.
     *
     * @return array<string, string>
     */
    private function extractReferrerFields(string $html): array
    {
        preg_match_all(
            '/name="__referrer\[([^"]+)\]" value="([^"]*)"/',
            $html,
            $matches,
            PREG_SET_ORDER,
        );

        $referrer = [];

        foreach ($matches as $match) {
            $referrer[$match[1]] = htmlspecialchars_decode($match[2]);
        }

        self::assertArrayHasKey(
            '@request',
            $referrer,
            'The rendered view must contain a __referrer form to extract fields from.',
        );

        return $referrer;
    }

    /**
     * Returns the texts of all queued error flash messages.
     *
     * @return string[]
     */
    private function errorFlashMessages(): array
    {
        return $this->flashMessagesOfSeverity(ContextualFeedbackSeverity::ERROR);
    }

    /**
     * Returns the texts of all queued flash messages of the given severity.
     * Flushes the whole queue, so a second call (of this or any of the three
     * severity-specific helpers above) returns an empty result. Call at most
     * once per test, use flashMessagesGroupedBySeverity() directly if more
     * than one severity needs checking.
     *
     * @return string[]
     */
    private function flashMessagesOfSeverity(ContextualFeedbackSeverity $severity): array
    {
        return $this->flashMessagesGroupedBySeverity()[$severity->name] ?? [];
    }

    /**
     * Returns the texts of all queued flash messages, grouped by severity
     * name. Flushes the whole queue, so call this (or one of the
     * severity-specific helpers above) at most once per test.
     *
     * @return array<string, string[]>
     */
    private function flashMessagesGroupedBySeverity(): array
    {
        $messages = $this->get(FlashMessageService::class)
            ->getMessageQueueByIdentifier()
            ->getAllMessagesAndFlush();

        $grouped = [];

        foreach ($messages as $message) {
            $grouped[$message->getSeverity()->name][] = $message->getMessage();
        }

        return $grouped;
    }
}
