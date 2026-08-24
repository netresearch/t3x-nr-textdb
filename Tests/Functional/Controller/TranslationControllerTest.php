<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Functional\Controller;

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
use RuntimeException;
use TYPO3\CMS\Backend\Routing\Router;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Core\Bootstrap;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

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
 * @see https://github.com/netresearch/t3x-nr-textdb/issues/100
 */
#[CoversClass(TranslationController::class)]
final class TranslationControllerTest extends AbstractFunctionalTestCase
{
    private const FILTER_REQUIRED_MESSAGE
        = 'Please select at least one type or component to create an export.';

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
        $this->controller->translateRecordAction(1, [0 => 'Send it']);

        self::assertSame(
            1,
            $this->countRows(placeholder: 'submit', languageUid: 0),
            'Submitting new[0] for an existing default record must not insert a second row.',
        );
        self::assertSame('Send it', $this->fetchValue(1));
    }

    #[Test]
    public function translateRecordUpdatesAnExistingLocalizedRecordSubmittedAsNew(): void
    {
        $this->controller->translateRecordAction(1, [1 => 'Abschicken']);

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
        $this->controller->translateRecordAction(1, [0 => 'First']);
        $this->controller->translateRecordAction(1, [0 => 'Second']);

        self::assertSame(1, $this->countRows(placeholder: 'submit', languageUid: 0));
        self::assertSame('Second', $this->fetchValue(1));
        self::assertSame([], $this->errorFlashMessages(), 'A successful save must not queue an error message.');
    }

    #[Test]
    public function translateRecordSkipsEmptySubmittedValues(): void
    {
        // Language 2 has no record at all; an untouched textarea must not create one.
        $this->controller->translateRecordAction(1, [2 => '   ']);

        self::assertSame(0, $this->countRows(placeholder: 'submit', languageUid: 2));
    }

    #[Test]
    public function translateRecordCreatesRecordForALanguageThatHasNone(): void
    {
        $this->controller->translateRecordAction(1, [2 => 'Envoyer']);

        self::assertSame(1, $this->countRows(placeholder: 'submit', languageUid: 2));
    }

    #[Test]
    public function translateRecordUpdatesLocalizedRecordAddressedByUid(): void
    {
        // update[<uid>] carries the localized uid. Repository::findByUid() is
        // language aware and returned null for the German row in a backend
        // context, so the edit was silently dropped.
        $this->controller->translateRecordAction(1, [], [5 => 'Absenden!']);

        self::assertSame('Absenden!', $this->fetchValue(5));
    }

    #[Test]
    public function translateRecordSurfacesAPersistenceFailureAsFlashMessage(): void
    {
        $persistenceManager = $this->createMock(PersistenceManagerInterface::class);
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

        $controller->translateRecordAction(1, [0 => 'Send it']);

        $messages = $this->errorFlashMessages();

        self::assertCount(1, $messages);
        self::assertStringContainsString('Duplicate entry', $messages[0]);
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
        $this->writeSiteConfiguration();
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
        $this->writeSiteConfiguration();
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
        $this->writeSiteConfiguration();
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
     * @param array<string, string|string[]> $queryParams Backend module arguments
     *                                                    arrive without a plugin
     *                                                    namespace
     */
    private function dispatchModuleAction(string $action, array $queryParams = []): ResponseInterface
    {
        $route = $this->get(Router::class)
            ->getRoute('netresearch_textdb.Translation_' . $action);

        $request = (new ServerRequest('https://example.com/typo3/module/netresearch/textdb', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('route', $route)
            ->withAttribute('module', $route->getOption('module'))
            ->withQueryParams($queryParams);

        $request = $request->withAttribute(
            'normalizedParams',
            NormalizedParams::createFromRequest($request),
        );

        $GLOBALS['TYPO3_REQUEST'] = $request;

        return $this->get(Bootstrap::class)
            ->handleBackendRequest($request);
    }

    /**
     * Publishes a site with two languages.
     *
     * The export iterates the languages of the first site. Without one it
     * produces no file at all. typo3/testing-framework 9 has no helper for this
     * any more, and a SiteFinder mock via GeneralUtility::addInstance() does not
     * work here either, because the controller is built by the container. The
     * configuration is therefore written to the test instance, where tearDown()
     * removes it again together with its cache.
     */
    private function writeSiteConfiguration(): void
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
                YAML,
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
     * Returns the texts of all queued error flash messages.
     *
     * @return string[]
     */
    private function errorFlashMessages(): array
    {
        $messages = $this->get(FlashMessageService::class)
            ->getMessageQueueByIdentifier()
            ->getAllMessagesAndFlush();

        $texts = [];

        foreach ($messages as $message) {
            if ($message->getSeverity() === ContextualFeedbackSeverity::ERROR) {
                $texts[] = $message->getMessage();
            }
        }

        return $texts;
    }
}
