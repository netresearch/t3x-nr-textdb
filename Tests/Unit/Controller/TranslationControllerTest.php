<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Unit\Controller;

use Error;
use Netresearch\NrTextdb\Controller\TranslationController;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use Netresearch\NrTextdb\Service\TranslationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Throwable;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\Locale;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request as ExtbaseRequest;
use TYPO3\CMS\Extbase\Mvc\Web\Routing\UriBuilder;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

use function glob;
use function preg_match;
use function restore_error_handler;
use function rmdir;
use function serialize;
use function set_error_handler;
use function unserialize;

/**
 * Regression tests for the module's filter config normalization.
 *
 * be_users.uc is writable by the backend user itself through the usersettings
 * endpoint of the core, and a backend module reads its arguments as the raw
 * query parameters, arrays included. getConfigFromBeUserData() used to return
 * whatever was stored, unfiltered, and normalizeRecordFilter()/
 * normalizeTextFilter() did not exist. Two defects followed from that:
 *
 *   1. On main and TYPO3_13, listAction() indexed placeholder/value directly
 *      and logged "Undefined array key" for a backend user without a stored
 *      config. On this branch that exact symptom never reproduced, both
 *      already went through ??, but the underlying gap, no normalization on
 *      the way in, is the same, see the TypeError and unusable-payload cases
 *      below.
 *   2. exportAction() destructured the empty array, component became null,
 *      and null === 0 never matched its "no filter selected" guard, so the
 *      export ran unfiltered.
 *
 * A third, more severe defect was specific to this branch: getConfigFromBeUserData()
 * declares `: array`, but unserialize() on a payload that is not a valid
 * serialized array returns false, not an array. array is a non-scalar type
 * and is never coerced, so that is an uncaught TypeError regardless of
 * strict_types, reproduced live against the sobol typo3-12 test instance
 * before this fix (installed v2.0.11).
 *
 * There is no functional test infrastructure on this branch (no
 * FunctionalTests.xml, no AbstractFunctionalTestCase), so these are Unit
 * tests. getConfigFromBeUserData() and the two normalizers have no I/O beyond
 * $GLOBALS['BE_USER']->getModuleData(), which this double replaces with a
 * plain array read, so testing them through reflection on a real controller
 * instance is a faithful, side-effect-free reproduction of the actual code
 * path, not a mock of the behaviour under test.
 */
#[CoversClass(TranslationController::class)]
#[UsesClass(Translation::class)]
final class TranslationControllerTest extends UnitTestCase
{
    private TranslationController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = (new ReflectionClass(TranslationController::class))
            ->newInstanceWithoutConstructor();
    }

    #[Test]
    public function getConfigFromBeUserDataReturnsTheDefaultsWithoutAStoredPayload(): void
    {
        $GLOBALS['BE_USER'] = $this->backendUserWithStoredPayload(null);

        self::assertSame(
            ['component' => 0, 'type' => 0, 'placeholder' => null, 'value' => null],
            $this->readConfig(),
        );
    }

    #[Test]
    public function getConfigFromBeUserDataReturnsTheDefaultsForAMalformedSerializedPayload(): void
    {
        // unserialize() on this returns false, not an array. array is a
        // non-scalar type and is never coerced, so the declared `: array`
        // return type makes this an uncaught TypeError, reproduced live
        // against the sobol typo3-12 instance.
        //
        // unserialize() on genuinely malformed input also raises a PHP-level
        // issue of its own, an E_NOTICE on 8.1/8.2, an E_WARNING on 8.3,
        // core's own AbstractUserAuthentication::unpack_uc() calls
        // unserialize() the exact same unsuppressed way, not something this
        // fix introduces. Build/UnitTests.xml sets failOnWarning="true", so
        // the 8.3 case alone would flip this test's own, unrelated
        // assertions from green to a failing build, and PHPUnit's
        // WithoutErrorHandler attribute alone is not enough here, it stops
        // PHPUnit from turning the warning into an issue, but the warning
        // still prints directly, which PHPUnit's separate
        // beStrictAboutOutputDuringTests check then flags as risky. A local
        // handler that swallows only this one call is what actually closes
        // both paths, PHPUnit's own default handler resumes right after.
        $GLOBALS['BE_USER'] = $this->backendUserWithStoredPayload('not valid serialized data');

        set_error_handler(static fn (): bool => true);

        try {
            $config = $this->readConfig();
        } finally {
            restore_error_handler();
        }

        self::assertSame(
            ['component' => 0, 'type' => 0, 'placeholder' => null, 'value' => null],
            $config,
        );
    }

    #[Test]
    public function getConfigFromBeUserDataKeepsAValidStoredPayload(): void
    {
        $GLOBALS['BE_USER'] = $this->backendUserWithStoredPayload(
            serialize(['component' => 1, 'type' => 2, 'placeholder' => 'submit', 'value' => 'Submit']),
        );

        self::assertSame(
            ['component' => 1, 'type' => 2, 'placeholder' => 'submit', 'value' => 'Submit'],
            $this->readConfig(),
        );
    }

    #[Test]
    public function getConfigFromBeUserDataDoesNotInstantiateArbitraryClassesFromTheStoredPayload(): void
    {
        // be_users.uc is writable by the backend user itself through the
        // usersettings endpoint, so a serialized payload naming an arbitrary
        // class is attacker-reachable. With allowed_classes: true, unserialize()
        // would build a real instance of that class and run its __wakeup()
        // before is_array() below ever sees the result, that is the object
        // injection this method's allowed_classes: false closes.
        // WakeupProbe::$wasWoken only becomes true if PHP actually built an
        // instance, so it is the one thing that tells the two settings apart,
        // the returned array looks identical either way.
        WakeupProbe::$wasWoken = false;

        $GLOBALS['BE_USER'] = $this->backendUserWithStoredPayload(serialize(new WakeupProbe()));

        self::assertSame(
            ['component' => 0, 'type' => 0, 'placeholder' => null, 'value' => null],
            $this->readConfig(),
        );
        self::assertFalse(
            WakeupProbe::$wasWoken,
            'unserialize() must not instantiate arbitrary classes from a user-writable payload.',
        );
    }

    /**
     * @param array<string, bool|int|string|array<string, int>> $storedPayload
     */
    #[Test]
    #[DataProvider('unusableStoredFilterProvider')]
    public function getConfigFromBeUserDataNormalizesAnUnusableStoredPayload(array $storedPayload): void
    {
        $GLOBALS['BE_USER'] = $this->backendUserWithStoredPayload(serialize($storedPayload));

        $config = $this->readConfig();

        self::assertSame(
            0,
            $config['component'],
        );
        self::assertSame(
            0,
            $config['type'],
        );
    }

    /**
     * @return array<string, array{array<string, bool|int|string|array<string, int>>}>
     */
    public static function unusableStoredFilterProvider(): array
    {
        return [
            'numeric string' => [['component' => '0', 'type' => '0']],
            'negative uid'   => [['component' => -1, 'type' => -1]],
            'non numeric'    => [['component' => '12abc', 'type' => 'none']],
            'wrong type'     => [['component' => true, 'type' => ['uid' => 1]]],
        ];
    }

    #[Test]
    public function normalizeRecordFilterTruncatesAFractionalValue(): void
    {
        // "2" and 2.9 both become 2, PHP's own coercion, not rejection.
        self::assertSame(
            2,
            $this->invokeNormalizeRecordFilter(2.9),
        );
    }

    #[Test]
    public function normalizeRecordFilterAcceptsTheNumericStringAQueryArgumentArrivesAs(): void
    {
        // Backend module arguments arrive as raw, unmapped query parameters, so
        // this is the actual shape a submitted filter has, not the int cases
        // above.
        self::assertSame(
            5,
            $this->invokeNormalizeRecordFilter('5'),
        );
    }

    #[Test]
    #[DataProvider('unusableRecordFilterProvider')]
    public function normalizeRecordFilterRejectsUnusableInput(mixed $value): void
    {
        self::assertSame(
            0,
            $this->invokeNormalizeRecordFilter($value),
        );
    }

    /**
     * @return array<string, array{int|string|array<int, string>|bool|null}>
     */
    public static function unusableRecordFilterProvider(): array
    {
        return [
            'negative'    => [-1],
            'non numeric' => ['abc'],
            'array'       => [['1']],
            'bool'        => [true],
            'null'        => [null],
        ];
    }

    #[Test]
    public function normalizeTextFilterTrimsAValidString(): void
    {
        self::assertSame(
            'submit',
            $this->invokeNormalizeTextFilter(' submit '),
        );
    }

    #[Test]
    public function normalizeTextFilterRejectsMalformedUtf8(): void
    {
        // The term is written back with serialize(), which does not throw on
        // malformed UTF-8, unlike main's json_encode(). The guard is kept for
        // consistency and because listAction()/exportAction() may hand the
        // value to output layers that do assume valid UTF-8.
        self::assertNull($this->invokeNormalizeTextFilter("\x80"));
    }

    #[Test]
    public function normalizeTextFilterRejectsANonString(): void
    {
        self::assertNull($this->invokeNormalizeTextFilter(['submit']));
    }

    /**
     * @return array<string, int|string|null>
     */
    private function readConfig(): array
    {
        $method = new ReflectionMethod(
            TranslationController::class,
            'getConfigFromBeUserData',
        );

        return $method->invoke($this->controller);
    }

    private function invokeNormalizeRecordFilter(mixed $value): int
    {
        $method = new ReflectionMethod(
            TranslationController::class,
            'normalizeRecordFilter',
        );

        return $method->invoke(
            $this->controller,
            $value,
        );
    }

    private function invokeNormalizeTextFilter(mixed $value): ?string
    {
        $method = new ReflectionMethod(
            TranslationController::class,
            'normalizeTextFilter',
        );

        return $method->invoke(
            $this->controller,
            $value,
        );
    }

    /**
     * A BackendUserAuthentication double whose getModuleData() reads the
     * given raw payload, exactly as it would sit in be_users.uc. The parent
     * implementation touches the user session (GeneralUtility::hmac(),
     * $this->userSession), which is not set up in a Unit test and is not
     * part of what these tests exercise.
     */
    private function backendUserWithStoredPayload(?string $payload): BackendUserAuthentication
    {
        return new class($payload) extends BackendUserAuthentication {
            public function __construct(private readonly ?string $payload)
            {
                parent::__construct();
            }

            public function getModuleData(string $module, string $type = ''): mixed
            {
                return $this->payload;
            }
        };
    }

    #[Test]
    public function listActionNormalizesTheSubmittedFilterAndPersistsItUnderTheSameKeys(): void
    {
        // normalizeRecordFilter()/normalizeTextFilter()/persistConfigInBeUserData()
        // are already pinned in isolation above, but listAction() is where
        // they are actually wired together, and where the two historical
        // defects in the class docblock lived: a raw (int) cast on the
        // request argument instead of normalizeRecordFilter(), and the
        // component/type keys of the persisted array being built by hand.
        // component is negative on purpose, (int) '-3' stays -3 but
        // normalizeRecordFilter('-3') clamps to 0, only the real wiring
        // produces 0 here. component and type are also asymmetric, a swap of
        // the persist wiring flips one against the other and this test would
        // still pass with symmetric values. placeholder carries whitespace
        // and value is malformed UTF-8, a raw pass-through would keep both
        // as submitted, only normalizeTextFilter() trims the one and rejects
        // the other, this is what actually proves those two request
        // arguments are wired the same way component/type are, not just
        // that the two isolated normalizer tests above hold.

        // Inlined rather than built by a helper method: a helper typed to
        // return BackendUserAuthentication would erase $pushedModuleData
        // again below, the same reason backendUserWithStoredPayload() above
        // is never read from outside its own getModuleData().
        $backendUser = new class extends BackendUserAuthentication {
            public ?string $pushedModuleData = null;

            public function __construct()
            {
                parent::__construct();
            }

            public function getModuleData(string $module, string $type = ''): mixed
            {
                return null;
            }

            public function pushModuleData(string $module, mixed $data, bool $dontPersistImmediately = false): void
            {
                $this->pushedModuleData = $data;
            }
        };
        $GLOBALS['BE_USER'] = $backendUser;

        $capturedQueryArguments = $this->invokeListActionCapturingQueryArguments([
            'component'   => '-3',
            'type'        => '7',
            'placeholder' => ' submit ',
            'value'       => "\x80",
        ]);

        self::assertSame(
            [0, 7, 'submit', null, 0],
            $capturedQueryArguments,
            'listAction() must pass the normalized filter to the repository query.',
        );
        self::assertSame(
            ['component' => 0, 'type' => 7, 'placeholder' => 'submit', 'value' => null],
            unserialize(
                $backendUser->pushedModuleData,
                ['allowed_classes' => false],
            ),
            'listAction() must persist the filter under the same keys it normalized them into.',
        );
    }

    #[Test]
    public function listActionUsesThePersistedFilterUnmodifiedWhenNoRequestArgumentsAreSubmitted(): void
    {
        // Empirically required on PHP 8.3: without it, tearDown()'s
        // leftover-singleton integrity check fails. The exact singleton
        // involved was not pinned down (it is not registered by the
        // uninitialized-user-session crash pushModuleData() hits below,
        // that happens before any such call), so this is a measured fix,
        // not a fully traced one.
        $this->resetSingletonInstances = true;

        // The destructuring assignment at the top of listAction() that reads
        // getConfigFromBeUserData() is only ever overwritten by the four
        // request-argument branches in the test above, never observed by
        // itself, a key swap there (component/type) would be invisible to
        // every other test, submitted arguments always win once present.
        // This is the default landing-page case instead: a backend user
        // returns with a persisted filter and submits no new one.
        $GLOBALS['BE_USER'] = $this->backendUserWithStoredPayload(
            serialize(['component' => 9, 'type' => 4, 'placeholder' => 'stored', 'value' => 'value']),
        );

        // persistConfigInBeUserData() re-persists the same, unmodified
        // config right after the query, through the real
        // BackendUserAuthentication::pushModuleData(), which touches an
        // uninitialized user session on this reflection-constructed
        // controller. The capture already ran by then, same reason the
        // helper below catches Throwable rather than avoid it.
        $capturedQueryArguments = $this->invokeListActionCapturingQueryArguments([]);

        self::assertSame(
            [9, 4, 'stored', 'value', 0],
            $capturedQueryArguments,
            'listAction() must use the persisted filter unmodified when no request arguments override it.',
        );
    }

    private function setControllerProperty(string $name, object $value, ?TranslationController $controller = null): void
    {
        $property = new ReflectionProperty(
            TranslationController::class,
            $name,
        );
        $property->setValue(
            $controller ?? $this->controller,
            $value,
        );
    }

    private function stubPersistenceManager(): void
    {
        $this->setControllerProperty(
            'persistenceManager',
            self::createStub(PersistenceManager::class),
        );
    }

    private function stubTranslationRepositoryFindByUidReturning(?Translation $translation): void
    {
        $translationRepository = self::createStub(TranslationRepository::class);
        $translationRepository->method('findByUid')->willReturn($translation);
        $this->setControllerProperty(
            'translationRepository',
            $translationRepository,
        );
    }

    private function siteLanguage(int $languageId, string $locale = 'en'): SiteLanguage
    {
        return new SiteLanguage(
            $languageId,
            $locale,
            new Uri(),
            [],
        );
    }

    /**
     * Wires a uriFor()-stubbed UriBuilder onto the controller, returning an
     * object whose $arguments property is filled with the exact arguments
     * uriFor() was called with, once it has been.
     */
    private function stubUriBuilderCapturingArguments(string $returnValue, ?TranslationController $controller = null): object
    {
        $capture = new class {
            /**
             * @var list<mixed>|null
             */
            public ?array $arguments = null;
        };

        $uriBuilder = self::createStub(UriBuilder::class);
        $uriBuilder->method('reset')->willReturnSelf();
        $uriBuilder->method('uriFor')
            ->willReturnCallback(static function (...$arguments) use ($capture, $returnValue): string {
                $capture->arguments = $arguments;

                return $returnValue;
            });
        $this->setControllerProperty(
            'uriBuilder',
            $uriBuilder,
            $controller,
        );

        return $capture;
    }

    /**
     * Wires a real, minimally constructed Extbase request carrying the
     * given arguments onto the controller. ActionController::$request is
     * only ever assigned in processRequest(), which a Unit test does not
     * run, so it is set directly via setControllerProperty() instead.
     *
     * @param array<string, mixed> $arguments
     */
    private function wireControllerRequest(array $arguments): void
    {
        $parameters    = (new ExtbaseRequestParameters())->setArguments($arguments);
        $serverRequest = (new ServerRequest())->withAttribute(
            'extbase',
            $parameters,
        );

        $this->setControllerProperty(
            'request',
            new ExtbaseRequest($serverRequest),
        );
    }

    /**
     * Wires the given request arguments onto the controller for a
     * listAction() call, stubs its repositories, invokes it, and returns
     * what the repository query received. $this->view is never initialized
     * outside processRequest(), which a Unit test does not run, so
     * listAction() errors out once it reaches the view assignment, after
     * both the query and the persisted-config write it is under test for.
     * Caught as Throwable, not the more specific Error it actually is,
     * because listAction() itself declares @throws InvalidQueryException,
     * which keeps this catch block real from PHPStan's perspective too.
     *
     * @param array<string, string> $requestArguments
     *
     * @return array{int, int, string|null, string|null, int}|null
     */
    private function invokeListActionCapturingQueryArguments(array $requestArguments): ?array
    {
        $this->wireControllerRequest($requestArguments);

        $pidProperty = new ReflectionProperty(
            TranslationController::class,
            'pid',
        );
        $pidProperty->setValue(
            $this->controller,
            1,
        );

        $capturedQueryArguments = null;
        $translationRepository  = self::createStub(TranslationRepository::class);
        $translationRepository->method('findAllByComponentTypePlaceholderValueAndLanguage')
            ->willReturnCallback(static function (int $component, int $type, ?string $placeholder, ?string $value, int $languageId) use (&$capturedQueryArguments) {
                $capturedQueryArguments = [$component, $type, $placeholder, $value, $languageId];

                return self::createStub(QueryResultInterface::class);
            });

        $this->setControllerProperty(
            'componentRepository',
            self::createStub(ComponentRepository::class),
        );
        $this->setControllerProperty(
            'typeRepository',
            self::createStub(TypeRepository::class),
        );
        $this->setControllerProperty(
            'translationRepository',
            $translationRepository,
        );

        try {
            $this->controller->listAction();
        } catch (Throwable) {
        }

        return $capturedQueryArguments;
    }

    /**
     * exportAction() creates its export directory before the filter guard
     * ever runs, a real filesystem side effect of a Unit test, cleaned up
     * here regardless of whether the guard blocked the call or let it
     * through.
     */
    private function cleanupLeftoverExportDirectories(): void
    {
        $exportKeyPattern = '/^\/tmp\/[0-9a-f]{32}-textdb-export$/';

        foreach (glob('/tmp/*-textdb-export') as $leftoverDirectory) {
            if (preg_match(
                $exportKeyPattern,
                $leftoverDirectory,
            ) === 1) {
                rmdir($leftoverDirectory);
            }
        }
    }

    /**
     * Runs exportAction() and asserts it throws the RuntimeException that
     * the caller's translationService stub raises once getAllLanguages()
     * runs, proving the filter guard let it that far without needing the
     * full multi-language export/file-write pipeline that follows.
     * exportAction() creates its export directory before the guard ever
     * runs, a real filesystem side effect of a Unit test, cleaned up here.
     */
    private function expectExportActionToThrowPastTheGuard(): void
    {
        try {
            $this->controller->exportAction();

            self::fail('exportAction() should have thrown before reaching this point.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'reached past the filter guard',
                $exception->getMessage(),
            );
        } finally {
            $this->cleanupLeftoverExportDirectories();
        }
    }

    #[Test]
    public function exportActionDoesNotQueryTheRepositoryWithoutAFilter(): void
    {
        // The second historical defect this file's class docblock names:
        // without a stored config, component/type used to come back as null,
        // and null === 0 never matched this guard, so the export ran
        // unfiltered instead of being blocked. getConfigFromBeUserData() now
        // guarantees an int, this test is what actually proves the guard
        // acts on that guarantee, not just that the guarantee itself holds.
        // addFlashMessageToQueue() persists into the backend user's session,
        // which the reflection-constructed controller and its double never
        // set up, GeneralUtility::makeInstance(FlashMessageService::class)
        // also stays a registered singleton past this test's lifetime.
        $this->resetSingletonInstances = true;

        $GLOBALS['BE_USER'] = $this->backendUserWithStoredPayload(null);
        $GLOBALS['LANG']    = self::createStub(LanguageService::class);

        // Stubbed and observed for its own sake, not just to avoid an
        // unrelated crash: getAllLanguages() is the very next call after the
        // guard, and translationService is otherwise a typed, uninitialized
        // property. Without this stub, both the guard blocking correctly
        // (an Error from addFlashMessageToQueue's session write) and the
        // guard failing to block (an Error from the still-uninitialized
        // translationService) land in the same catch block below, and
        // $translationRepositoryCalled stays false either way, proving
        // nothing. $translationServiceCalled is what actually tells the two
        // apart.
        $translationServiceCalled = false;
        $translationService       = self::createStub(TranslationService::class);
        $translationService->method('getAllLanguages')
            ->willReturnCallback(static function () use (&$translationServiceCalled) {
                $translationServiceCalled = true;

                return [];
            });
        $this->setControllerProperty(
            'translationService',
            $translationService,
        );

        $translationRepositoryCalled = false;
        $translationRepository       = self::createStub(TranslationRepository::class);
        $translationRepository->method('findAllByComponentTypePlaceholderValueAndLanguage')
            ->willReturnCallback(static function () use (&$translationRepositoryCalled) {
                $translationRepositoryCalled = true;

                return self::createStub(QueryResultInterface::class);
            });
        $this->setControllerProperty(
            'translationRepository',
            $translationRepository,
        );

        try {
            // The flash message this guard queues tries to persist into the
            // backend user's session, which does not exist on this double,
            // same reason the listAction tests above catch Throwable.
            $this->controller->exportAction();
        } catch (Throwable) {
        } finally {
            $this->cleanupLeftoverExportDirectories();
        }

        self::assertFalse(
            $translationServiceCalled,
            'exportAction() must return before reaching the language loop when no filter is selected.',
        );
        self::assertFalse(
            $translationRepositoryCalled,
            'exportAction() must not query the repository when no filter is selected.',
        );
    }

    #[Test]
    public function exportActionProceedsPastTheGuardWithOnlyOneFilterSet(): void
    {
        // The guard is an &&, both component and type have to be unset for
        // it to trigger. component is set here, type is not, on purpose: a
        // guard weakened to || would treat this as "no filter" too and block
        // it, exactly what the previous test cannot tell apart from the
        // correct behaviour, since there both are 0. getAllLanguages() is
        // the very next call after the guard, throwing from it and asserting
        // that throw happened is a cheap way to prove the guard let this
        // through without needing the full multi-language export/file-write
        // pipeline that follows.
        $GLOBALS['BE_USER'] = $this->backendUserWithStoredPayload(
            serialize(['component' => 5, 'type' => 0, 'placeholder' => null, 'value' => null]),
        );
        // Only reached if the guard below is weakened to ||, addFlashMessageToQueue()
        // would otherwise TypeError on a missing $GLOBALS['LANG'] instead of
        // failing on the assertions this test is actually about.
        $GLOBALS['LANG'] = self::createStub(LanguageService::class);

        $translationService = self::createStub(TranslationService::class);
        $translationService->method('getAllLanguages')
            ->willThrowException(new RuntimeException('reached past the filter guard'));
        $this->setControllerProperty(
            'translationService',
            $translationService,
        );

        $this->expectExportActionToThrowPastTheGuard();
    }

    #[Test]
    public function exportActionProceedsPastTheGuardWithOnlyTypeSet(): void
    {
        // Mirrors the test above with component/type swapped: that one
        // alone cannot tell a correct && apart from a guard that dropped
        // type and checks component twice, ($component === 0) && ($component
        // === 0), component being 0 here still trips such a mutant into
        // blocking. type being the one and only filter set is what actually
        // exercises the type half of the guard.
        $GLOBALS['BE_USER'] = $this->backendUserWithStoredPayload(
            serialize(['component' => 0, 'type' => 5, 'placeholder' => null, 'value' => null]),
        );
        $GLOBALS['LANG'] = self::createStub(LanguageService::class);

        $translationService = self::createStub(TranslationService::class);
        $translationService->method('getAllLanguages')
            ->willThrowException(new RuntimeException('reached past the filter guard'));
        $this->setControllerProperty(
            'translationService',
            $translationService,
        );

        $this->expectExportActionToThrowPastTheGuard();
    }

    #[Test]
    public function exportActionUsesThePersistedFilterUnmodifiedInTheRepositoryQuery(): void
    {
        // Same reasoning as listActionUsesThePersistedFilterUnmodifiedWhenNoRequestArgumentsAreSubmitted():
        // the destructuring assignment at the top of exportAction() is only
        // ever read by the guard's === 0 comparisons, never by anything
        // that observes the actual component/type/placeholder/value it
        // produces, so a key swap there would pass every existing test.
        // getAllLanguages() returns one language here instead of throwing,
        // so the loop actually reaches the repository call this time, whose
        // arguments are captured and then used to short-circuit before the
        // real Zip/file-write pipeline.
        $GLOBALS['BE_USER'] = $this->backendUserWithStoredPayload(
            serialize(['component' => 9, 'type' => 4, 'placeholder' => 'stored', 'value' => 'value']),
        );
        $GLOBALS['LANG'] = self::createStub(LanguageService::class);

        $locale = self::createStub(Locale::class);
        $locale->method('getLanguageCode')->willReturn('en');
        $language = self::createStub(SiteLanguage::class);
        $language->method('getLanguageId')->willReturn(0);
        $language->method('getLocale')->willReturn($locale);

        $translationService = self::createStub(TranslationService::class);
        $translationService->method('getAllLanguages')->willReturn([$language]);
        $this->setControllerProperty(
            'translationService',
            $translationService,
        );

        $capturedQueryArguments = null;
        $translationRepository  = self::createStub(TranslationRepository::class);
        $translationRepository->method('findAllByComponentTypePlaceholderValueAndLanguage')
            ->willReturnCallback(static function (...$arguments) use (&$capturedQueryArguments) {
                $capturedQueryArguments = $arguments;

                throw new RuntimeException('reached the repository query');
            });
        $this->setControllerProperty(
            'translationRepository',
            $translationRepository,
        );

        try {
            $this->controller->exportAction();

            self::fail('exportAction() should have thrown before reaching this point.');
        } catch (RuntimeException $exception) {
            self::assertSame(
                'reached the repository query',
                $exception->getMessage(),
            );
        } finally {
            $this->cleanupLeftoverExportDirectories();
        }

        self::assertSame(
            [9, 4, 'stored', 'value', 0],
            $capturedQueryArguments,
            'exportAction() must use the persisted filter unmodified in the repository query.',
        );
    }

    #[Test]
    #[DataProvider('unusablePageNumberProvider')]
    public function getPaginationClampsAnUnusablePageNumberToTheFirstPage(mixed $rawPage): void
    {
        $result = $this->invokeGetPagination(
            $rawPage,
            itemsPerPage: 15,
        );

        self::assertArrayHasKey(
            'paginator',
            $result,
        );
        self::assertSame(
            1,
            $result['paginator']->getCurrentPageNumber(),
        );
    }

    /**
     * @return array<string, array{string|array<int, string>}>
     */
    public static function unusablePageNumberProvider(): array
    {
        return [
            'zero'         => ['0'],
            'negative'     => ['-1'],
            'not a number' => ['abc'],
            'emptied'      => [''],
            'array'        => [['1']],
        ];
    }

    #[Test]
    public function getPaginationUsesTheRequestedPageNumber(): void
    {
        // 30 items at the default 15 per page is two pages, so page 2 is a
        // real, reachable page, not one AbstractPaginator::updateInternalState()
        // clamps back down to 1. Only getCurrentPageNumber() proves currentPage
        // reaches the paginator at all, the normalizeRecordFilter() unit above
        // only proves the value it computes, not that getPagination() passes
        // it on.
        $result = $this->invokeGetPagination(
            '2',
            itemsPerPage: 15,
            totalItems: 30,
        );

        self::assertSame(
            2,
            $result['paginator']->getCurrentPageNumber(),
        );
    }

    #[Test]
    public function getPaginationUsesTheConfiguredItemsPerPage(): void
    {
        // 12 items at 5 per page is three pages. Every other test here either
        // omits itemsPerPage or leaves it at the default 15, so this is the
        // only one that proves a configured, non-default value reaches the
        // paginator instead of being silently ignored.
        $result = $this->invokeGetPagination(
            '1',
            itemsPerPage: 5,
            totalItems: 12,
        );

        self::assertSame(
            3,
            $result['paginator']->getNumberOfPages(),
        );
    }

    #[Test]
    public function getPaginationUsesTheDocumentedDefaultItemsPerPageWhenTheSettingIsMissing(): void
    {
        // enablePagination without itemsPerPage is exactly what a site TypoScript
        // override that only touches the former produces. The old code read
        // itemsPerPage unguarded in the condition, which evaluated to 0 and
        // silently disabled pagination instead of falling back to the default.
        // 12 items split into pages of 15 is one page, split into pages of the
        // stale PHPDoc default of 10 would be two, so the page count pins the
        // fallback value itself, not just that pagination stayed on.
        $result = $this->invokeGetPagination(
            '1',
            settings: ['enablePagination' => true],
            totalItems: 12,
        );

        self::assertArrayHasKey(
            'paginator',
            $result,
            'Pagination must not be silently disabled.',
        );
        self::assertSame(
            1,
            $result['paginator']->getNumberOfPages(),
        );
    }

    #[Test]
    public function getPaginationStaysDisabledWithoutEnablePagination(): void
    {
        $result = $this->invokeGetPagination(
            '1',
            settings: [],
        );

        self::assertSame(
            [],
            $result,
        );
    }

    #[Test]
    public function getPaginationStaysDisabledWithAnItemsPerPageOfZero(): void
    {
        // itemsPerPage: 0 is a TypoScript misconfiguration, not an abstract
        // case, and it used to reach QueryResultPaginator's constructor
        // unchecked. AbstractPaginator::setItemsPerPage() throws
        // InvalidArgumentException for anything below 1, so the >0 guard
        // here is what turns that misconfiguration into pagination staying
        // off instead of an HTTP 500.
        $result = $this->invokeGetPagination(
            '1',
            itemsPerPage: 0,
        );

        self::assertSame(
            [],
            $result,
        );
    }

    /**
     * Invokes the private getPagination() with a real, minimally constructed
     * Extbase request, the only property it reads besides its two parameters.
     *
     * @param array<string, bool|int>|null $settings
     *
     * @return array{}|array{paginator: QueryResultPaginator, pagination: SimplePagination}
     */
    private function invokeGetPagination(
        mixed $rawPage,
        ?int $itemsPerPage = null,
        ?array $settings = null,
        int $totalItems = 3,
    ): array {
        $this->wireControllerRequest(['currentPage' => $rawPage]);

        $queryResult = self::createStub(QueryResultInterface::class);
        $queryResult->method('count')->willReturn($totalItems);
        $query = self::createStub(QueryInterface::class);
        $query->method('setLimit')->willReturnSelf();
        $query->method('setOffset')->willReturnSelf();
        $query->method('execute')->willReturn($queryResult);
        $queryResult->method('getQuery')->willReturn($query);

        $method = new ReflectionMethod(
            TranslationController::class,
            'getPagination',
        );

        return $method->invoke(
            $this->controller,
            $queryResult,
            $settings ?? ['enablePagination' => true, 'itemsPerPage' => $itemsPerPage],
        );
    }

    #[Test]
    public function errorActionRejectsAForwardToAForeignExtension(): void
    {
        // The referrer's HMAC scope is installation-global, not bound to this
        // extension. Before this fix, a crafted/tampered validation-error
        // forward naming a foreign extension reached uriFor() unchecked; this
        // is the check that now rejects it before that call.
        $controller = $this->partialMockControllerForwarding(
            (new ForwardResponse('someAction'))->withExtensionName('SomeOtherExtension'),
        );

        $response = $this->invokeErrorAction($controller);

        self::assertSame(
            400,
            $response->getStatusCode(),
        );
    }

    #[Test]
    public function errorActionRejectsAForwardThatResolvesToNoRoute(): void
    {
        // A forward naming an action/controller pair that does not resolve to
        // one of this module's own routes leaves uriFor() with nothing to
        // build a target link, uriFor() itself is the one thing this Unit
        // test cannot exercise without a container, its own stub reproduces
        // the "no route" outcome directly.
        $forwardResponse = (new ForwardResponse('unroutableAction'))
            ->withControllerName('SomeController')
            ->withExtensionName('NrTextdb')
            ->withArguments(['uid' => 1]);

        $controller = $this->partialMockControllerForwarding($forwardResponse);

        $capture = $this->stubUriBuilderCapturingArguments('', $controller);

        $response = $this->invokeErrorAction($controller);

        self::assertSame(
            400,
            $response->getStatusCode(),
        );
        self::assertSame(
            ['unroutableAction', ['uid' => 1], 'SomeController', 'NrTextdb', null],
            $capture->arguments,
            'uriFor() must receive the forward response\'s own action/arguments/controller/extension, in that order.',
        );
    }

    #[Test]
    public function errorActionProceedsPastBothGuardsForAResolvableForward(): void
    {
        // Mirrors errorActionRejectsAForwardThatResolvesToNoRoute with
        // uriFor() returning a real, non-empty URI instead of '': no
        // existing test proves the $uri === '' check does not also swallow
        // a legitimately resolvable forward into the same 400 response.
        // addFlashMessageToQueue() right after it reaches
        // LocalizationUtility::translate(), which needs a booted container
        // this Unit test does not provide and throws before this method
        // could construct any response at all, there is no path left
        // afterwards that could still produce the guards' 400, so reaching
        // that throw (instead of a clean 400 return) is itself what proves
        // neither guard tripped here.
        $this->resetSingletonInstances = true;

        $forwardResponse = (new ForwardResponse('translated'))
            ->withControllerName('Translation')
            ->withExtensionName('NrTextdb')
            ->withArguments(['uid' => 1]);

        $controller = $this->partialMockControllerForwarding($forwardResponse);

        $capture = $this->stubUriBuilderCapturingArguments(
            '/typo3/module/netresearch/textdb/Translation/translated?uid=1',
            $controller,
        );

        try {
            $response = $this->invokeErrorAction($controller);
        } catch (Throwable $throwable) {
            self::assertStringNotContainsString(
                'getExtensionName',
                $throwable->getMessage(),
                'A regression in the ForwardResponse guard would crash trying to read the extension name instead of proceeding to the flash-message translation: ' . $throwable->getMessage(),
            );
            self::assertSame(
                ['translated', ['uid' => 1], 'Translation', 'NrTextdb', null],
                $capture->arguments,
                'uriFor() must receive the forward response\'s own action/arguments/controller/extension, in that order.',
            );

            return;
        }

        self::fail('errorAction() must not resolve a well-formed, routable forward to the same 400 the foreign-extension/no-route guards return, got status ' . $response->getStatusCode() . '.');
    }

    #[Test]
    public function errorActionFallsBackToTheParentImplementationWithoutAReferrer(): void
    {
        // forwardToReferringRequest() returns null whenever the request
        // carries no __referrer, the common case for most validation
        // errors, not just a crafted/tampered one. Left unguarded, the
        // extension-name check right after it would call a method on that
        // null and turn every such error into an uncaught 500 instead of
        // TYPO3's own graceful 400. On this double, parent::errorAction()
        // itself never gets far enough to call forwardToReferringRequest()
        // a second time, its own first line (addErrorFlashMessage())
        // reaches getFlashMessageQueue(), which reads the uninitialized
        // internalExtensionService property (never wired without the
        // constructor/inject setters), exactly what the catch block below
        // already expects.
        $controller = $this->getMockBuilder(TranslationController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['forwardToReferringRequest'])
            ->getMock();
        $controller->expects(self::once())
            ->method('forwardToReferringRequest')
            ->willReturn(null);
        $this->setControllerProperty(
            'responseFactory',
            new ResponseFactory(),
            $controller,
        );
        $this->setControllerProperty(
            'streamFactory',
            new StreamFactory(),
            $controller,
        );

        try {
            $response = $this->invokeErrorAction($controller);

            self::assertSame(
                400,
                $response->getStatusCode(),
            );
        } catch (Error $error) {
            self::assertStringNotContainsString(
                'getExtensionName',
                $error->getMessage(),
                'A regression in the ForwardResponse guard would crash trying to read the extension name off a null instead of falling back cleanly: ' . $error->getMessage(),
            );
        }
    }

    private function invokeErrorAction(TranslationController $controller): ResponseInterface
    {
        $method = new ReflectionMethod(
            TranslationController::class,
            'errorAction',
        );

        return $method->invoke($controller);
    }

    /**
     * @return TranslationController&MockObject
     */
    private function partialMockControllerForwarding(ForwardResponse $forwardResponse): TranslationController
    {
        $controller = $this->getMockBuilder(TranslationController::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['forwardToReferringRequest'])
            ->getMock();
        $controller->expects(self::once())
            ->method('forwardToReferringRequest')
            ->willReturn($forwardResponse);

        // htmlResponse(), reached on both the foreign-extension and the
        // empty-uri branch, needs these two PSR-17 factories, only ever
        // assigned via ActionController's inject*() setters, which
        // disableOriginalConstructor() and the reflection-constructed
        // $this->controller both skip.
        $this->setControllerProperty(
            'responseFactory',
            new ResponseFactory(),
            $controller,
        );
        $this->setControllerProperty(
            'streamFactory',
            new StreamFactory(),
            $controller,
        );

        return $controller;
    }

    #[Test]
    public function translateRecordActionRejectsAndAcceptsNewEntriesByValidity(): void
    {
        // new[] keys/values arrive as raw, attacker-reachable POST data, not
        // as anything Extbase's property mapping validates against the
        // documented "language uid => string" shape. Each rejected entry
        // here reproduces a distinct way the pre-fix code trusted that shape:
        // a non-int key, a non-string value, and a language uid unreachable
        // through translatedAction()'s own "untranslated" list.
        $parentTranslation  = new Translation();
        $createdTranslation = new Translation();

        $translationRepository = self::createMock(TranslationRepository::class);
        $translationRepository->method('findByUid')->willReturn($parentTranslation);
        $translationRepository->expects(self::once())
            ->method('add')
            ->with(self::identicalTo($createdTranslation));
        $this->setControllerProperty(
            'translationRepository',
            $translationRepository,
        );

        $translationService = self::createMock(TranslationService::class);
        $translationService->method('getAllLanguages')->willReturn([
            0 => $this->siteLanguage(0),
            1 => $this->siteLanguage(1, 'de'),
        ]);
        $translationService->expects(self::once())
            ->method('createTranslationFromParent')
            ->with(
                self::identicalTo($parentTranslation),
                0,
                'a valid, accepted value',
            )
            ->willReturn($createdTranslation);
        $this->setControllerProperty(
            'translationService',
            $translationService,
        );

        $this->stubPersistenceManager();

        $this->invokeTranslateRecordAction(1, [
            'not-an-int' => 'value for a string key',
            2            => ['not', 'a', 'string'],
            999          => 'unconfigured language id',
            1            => '   ',
            // Padded on purpose: createTranslationFromParent()'s ->with()
            // constraint above expects the trimmed value, pinning that a
            // mutation swapping $trimmedValue back to $value would
            // otherwise go undetected on an already-clean test string.
            0 => '  a valid, accepted value  ',
        ]);
    }

    #[Test]
    public function translateRecordActionRejectsAndAcceptsUpdateEntriesByValidity(): void
    {
        $translation = new Translation();

        $translationRepository = self::createMock(TranslationRepository::class);
        $translationRepository->method('findByUid')->willReturn($translation);
        $translationRepository->expects(self::once())
            ->method('update')
            ->with(self::callback(static fn (Translation $updated): bool => $updated->getValue() === 'trimmed'));
        $this->setControllerProperty(
            'translationRepository',
            $translationRepository,
        );

        // $parent (0) resolves through the same findByUid() stub as the
        // update-loop lookups, entering the new[] branch that reads
        // getAllLanguages() unconditionally, even though $new is empty here.
        $translationService = self::createStub(TranslationService::class);
        $translationService->method('getAllLanguages')->willReturn([]);
        $this->setControllerProperty(
            'translationService',
            $translationService,
        );

        $this->stubPersistenceManager();

        $this->invokeTranslateRecordAction(0, [], [
            'not-an-int' => 'value for a string key',
            -1           => 'a negative uid',
            5            => ['not', 'a', 'string'],
            7            => '  trimmed  ',
        ]);
    }

    #[Test]
    public function translateRecordActionRejectsAZeroUpdateUidEvenIfATranslationWouldResolveForIt(): void
    {
        // The actual guard is ($translationUid <= 0). The negative-uid case
        // above only proves values below 0 are rejected, that alone cannot
        // tell this guard apart from one weakened to ($translationUid < 0),
        // which would let exactly 0 slip past into a pointless findByUid(0)
        // lookup instead of being rejected up front. uid 0 is never a real
        // TYPO3 record, but the double below returns one for any argument on
        // purpose, so only the guard itself, not the repository, is what
        // keeps update() from being called.
        $translation = new Translation();

        $translationRepository = self::createMock(TranslationRepository::class);
        $translationRepository->method('findByUid')->willReturn($translation);
        $translationRepository->expects(self::never())
            ->method('update');
        $this->setControllerProperty(
            'translationRepository',
            $translationRepository,
        );

        // $parent (0) resolves through the same findByUid() stub as the
        // update-loop lookup, entering the new[] branch that reads
        // getAllLanguages() unconditionally, even though $new is empty here,
        // same reasoning as the sibling test above.
        $translationService = self::createStub(TranslationService::class);
        $translationService->method('getAllLanguages')->willReturn([]);
        $this->setControllerProperty(
            'translationService',
            $translationService,
        );

        $this->stubPersistenceManager();

        $this->invokeTranslateRecordAction(
            0,
            [],
            [0 => 'a zero uid'],
        );
    }

    #[Test]
    public function translateRecordActionRedirectsToTheTranslatedViewWithoutAnyPayload(): void
    {
        // The one call this suite can observe end to end: with both new[]
        // and update[] empty, addFlashMessageToQueue() (and the booted
        // container it needs) is never reached, translateRecordAction()
        // runs through to redirectToUri() clean.
        $this->stubTranslationRepositoryFindByUidReturning(null);

        $this->stubPersistenceManager();

        $this->stubUriBuilderCapturingArguments('/typo3/module/netresearch/textdb/Translation/translated?uid=1');

        $response = $this->controller->translateRecordAction(1);

        self::assertSame(
            303,
            $response->getStatusCode(),
        );
    }

    #[Test]
    public function translateRecordActionRedirectsToTheTranslatedActionForTheGivenParent(): void
    {
        // The sibling test above only asserts the status code, not which
        // action/argument uriFor() was actually asked to build a link for,
        // so a hardcoded or wrong uid there would go unnoticed.
        $this->stubTranslationRepositoryFindByUidReturning(null);

        $this->stubPersistenceManager();

        $capture = $this->stubUriBuilderCapturingArguments('/typo3/module/netresearch/textdb/Translation/translated?uid=42');

        $this->controller->translateRecordAction(42);

        self::assertSame(
            ['translated', ['uid' => 42], null, null, null],
            $capture->arguments,
            'translateRecordAction() must redirect to its own translated action for the given parent, not a hardcoded or wrong uid.',
        );
    }

    #[Test]
    public function translateRecordActionRejectsANegativeLanguageIdEvenIfConfigured(): void
    {
        // TYPO3 core does not forbid a negative languageId anywhere (see the
        // commit that restored this guard), only its own configuration UI
        // never offers one, so array_key_exists() against getAllLanguages()
        // alone cannot be trusted to always exclude it, unlike every other
        // test here, this one arranges a site language actually configured
        // under a negative id to prove the guard still rejects it.
        $parentTranslation = new Translation();

        $this->stubTranslationRepositoryFindByUidReturning($parentTranslation);

        $translationService = self::createMock(TranslationService::class);
        $translationService->method('getAllLanguages')->willReturn([
            -2 => $this->siteLanguage(-2),
        ]);
        $translationService->expects(self::never())
            ->method('createTranslationFromParent');
        $this->setControllerProperty(
            'translationService',
            $translationService,
        );

        $this->stubPersistenceManager();

        $this->invokeTranslateRecordAction(
            1,
            [-2 => 'a value for a negatively configured language'],
        );
    }

    #[Test]
    public function translateRecordActionAcceptsTheDocumentedNegativeOneLanguageBoundary(): void
    {
        // The actual guard is ($language < -1), so -1 itself is the
        // documented floor (@param ... array<int<-1, max>, string>) and must
        // pass it. Every other language test in this class only proves a
        // value at or below -2 is rejected, none of them can tell this exact
        // guard apart from one tightened to ($language < 0), which would
        // reject -1 too, only a language actually configured under -1
        // reaching createTranslationFromParent() does.
        $parentTranslation  = new Translation();
        $createdTranslation = new Translation();

        $translationRepository = self::createMock(TranslationRepository::class);
        $translationRepository->method('findByUid')->willReturn($parentTranslation);
        $translationRepository->expects(self::once())
            ->method('add')
            ->with(self::identicalTo($createdTranslation));
        $this->setControllerProperty(
            'translationRepository',
            $translationRepository,
        );

        $translationService = self::createMock(TranslationService::class);
        $translationService->method('getAllLanguages')->willReturn([
            -1 => $this->siteLanguage(-1),
        ]);
        $translationService->expects(self::once())
            ->method('createTranslationFromParent')
            ->with(
                self::identicalTo($parentTranslation),
                -1,
                'a value for the documented -1 boundary',
            )
            ->willReturn($createdTranslation);
        $this->setControllerProperty(
            'translationService',
            $translationService,
        );

        $this->stubPersistenceManager();

        $this->invokeTranslateRecordAction(
            1,
            [-1 => 'a value for the documented -1 boundary'],
        );
    }

    #[Test]
    public function translateRecordActionNeverAddsANewEntryForAnUnresolvableParent(): void
    {
        // A non-blank new[] entry is unreachable once $parent fails to
        // resolve (there is no record left to attach it to). The
        // documented rejectedCount side effect of this branch isn't
        // separately observable here (its only consumer is a flash
        // message that needs LocalizationUtility::translate(), which this
        // Unit test's missing container can't provide), this only pins
        // that no record gets created for it either.
        $translationRepository = self::createMock(TranslationRepository::class);
        $translationRepository->method('findByUid')->willReturn(null);
        $translationRepository->expects(self::never())
            ->method('add');
        $this->setControllerProperty(
            'translationRepository',
            $translationRepository,
        );

        $this->stubPersistenceManager();

        $this->invokeTranslateRecordAction(
            1,
            [0 => 'orphaned payload'],
        );
    }

    #[Test]
    public function translateRecordActionNeverAddsANullTranslationFromTheService(): void
    {
        // createTranslationFromParent() returns null when the parent is
        // missing its environment/component/type (see its implementation),
        // the else branch of the if ($translation instanceof Translation)
        // check right after it is what has to catch that, add() must never
        // run on a null. Its documented rejectedCount side effect isn't
        // separately observable here, see the sibling test above.
        $parentTranslation = new Translation();

        $translationRepository = self::createMock(TranslationRepository::class);
        $translationRepository->method('findByUid')->willReturn($parentTranslation);
        $translationRepository->expects(self::never())
            ->method('add');
        $this->setControllerProperty(
            'translationRepository',
            $translationRepository,
        );

        $translationService = self::createStub(TranslationService::class);
        $translationService->method('getAllLanguages')->willReturn([
            0 => $this->siteLanguage(0),
        ]);
        $translationService->method('createTranslationFromParent')->willReturn(null);
        $this->setControllerProperty(
            'translationService',
            $translationService,
        );

        $this->stubPersistenceManager();

        $this->invokeTranslateRecordAction(
            1,
            [0 => 'a value the service refuses to accept'],
        );
    }

    #[Test]
    public function translateRecordActionClearsPersistenceStateOnAPersistenceFailure(): void
    {
        // Without this guard a persistence error (e.g. a collision with the
        // unique key on the translation table) escaped the module as a raw
        // 500 and the editor lost the entered text without any feedback.
        $this->stubTranslationRepositoryFindByUidReturning(null);

        $persistenceManager = self::createMock(PersistenceManager::class);
        $persistenceManager->method('persistAll')
            ->willThrowException(new RuntimeException('Duplicate entry'));
        $persistenceManager->expects(self::once())
            ->method('clearState');
        $this->setControllerProperty(
            'persistenceManager',
            $persistenceManager,
        );

        // The exception detail must reach the log, not the editor-facing
        // flash message the previous commit stopped exposing it in.
        $logger = self::createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Translation could not be saved: {reason}',
                self::callback(static fn (array $context): bool => (($context['reason'] ?? null) === 'Duplicate entry')
                    && (($context['exception'] ?? null) instanceof RuntimeException)),
            );
        $this->controller->setLogger($logger);

        $this->invokeTranslateRecordAction(
            1,
            [],
            [1 => 'value'],
        );
    }

    /**
     * addFlashMessageToQueue() reaches LocalizationUtility::translate(),
     * which needs a booted container this Unit test does not provide, and
     * registers a Locales singleton as a side effect before it gets there.
     * By the time it throws, every repository/persistence-manager
     * interaction the validation logic under test produces has already run,
     * which is what these tests actually assert on.
     *
     * @param array<array-key, string|array<array-key, string>> $new
     * @param array<array-key, string|array<array-key, string>> $update
     */
    private function invokeTranslateRecordAction(int $parent, array $new = [], array $update = []): void
    {
        $this->resetSingletonInstances = true;

        try {
            $this->controller->translateRecordAction(
                $parent,
                $new,
                $update,
            );
        } catch (Throwable) {
        }
    }
}

/**
 * Exists only to make PHP object injection observable in a Unit test.
 * $wasWoken becomes true only if unserialize() actually built an instance
 * of this class from a serialized string, which is exactly what
 * getConfigFromBeUserData()'s allowed_classes: false must prevent.
 */
final class WakeupProbe
{
    public static bool $wasWoken = false;

    public function __wakeup(): void
    {
        self::$wasWoken = true;
    }
}
