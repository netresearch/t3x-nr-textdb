<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Unit\Controller;

use Netresearch\NrTextdb\Controller\TranslationController;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use Netresearch\NrTextdb\Service\TranslationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;
use Throwable;
use TypeError;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Extbase\Http\ForwardResponse;
use TYPO3\CMS\Extbase\Mvc\Controller\Arguments;
use TYPO3\CMS\Extbase\Mvc\ExtbaseRequestParameters;
use TYPO3\CMS\Extbase\Mvc\Request as ExtbaseRequest;
use TYPO3\CMS\Extbase\Pagination\QueryResultPaginator;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Security\Cryptography\HashService;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

use function glob;
use function preg_match;
use function rmdir;
use function serialize;
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
 * A third, more severe defect is specific to this branch and TYPO3-12 (both
 * serialize the config with serialize()/unserialize() instead of main's and
 * TYPO3_13's json_encode()/json_decode()): getConfigFromBeUserData() declares
 * `: array`, but unserialize() on a payload that is not a valid serialized
 * array returns false, not an array. array is a non-scalar type and is never
 * coerced, so that is an uncaught TypeError regardless of strict_types.
 *
 * There is no functional test infrastructure on this branch (no
 * FunctionalTests.xml, no AbstractFunctionalTestCase), so these are Unit
 * tests. getConfigFromBeUserData() and the two normalizers have no I/O beyond
 * $GLOBALS['BE_USER']->getModuleData(), which this double replaces with a
 * plain array read, so testing them through reflection on a real controller
 * instance is a faithful, side-effect-free reproduction of the actual code
 * path, not a mock of the behaviour under test.
 *
 * The third group covers issue #129: translateRecordAction() trusted the
 * array keys and values of new[]/update[] coming straight from the request.
 * A non-int update[] key reached findRecordByUid('foo') and raised an
 * uncaught TypeError under strict_types, an array value raised one from
 * setValue(), and a syntactically valid but unconfigured sys_language_uid
 * (new[999]=Text) was persisted regardless, permanently occupying a slot of
 * the unique key for a translation the module's own "untranslated" list
 * could never show again. errorAction() is covered too: Extbase's own
 * default implementation forwards back to the referring view using a
 * referrer whose HMAC signature is installation-global rather than scoped to
 * this extension, so a validly-signed referrer copied from any other Extbase
 * backend module could target a foreign controller/action.
 *
 * @author Rico Sonntag <rico.sonntag@netresearch.de>
 */
#[CoversClass(TranslationController::class)]
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
        // return type makes this an uncaught TypeError.
        //
        // unserialize() on genuinely malformed input also raises an E_NOTICE
        // of its own, core's own AbstractUserAuthentication::unpack_uc()
        // calls unserialize() the exact same unsuppressed way, not something
        // this fix introduces. composer.json pins this branch to PHP ~8.1.0,
        // where that is an E_NOTICE, not the E_WARNING it becomes on 8.3, and
        // Build/UnitTests.xml only sets failOnWarning="true", not
        // failOnNotice, so no local error handler is needed here.
        $GLOBALS['BE_USER'] = $this->backendUserWithStoredPayload('not valid serialized data');

        self::assertSame(
            ['component' => 0, 'type' => 0, 'placeholder' => null, 'value' => null],
            $this->readConfig(),
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
        return $this->invokePrivateMethod('getConfigFromBeUserData');
    }

    private function invokeNormalizeRecordFilter(mixed $value): int
    {
        return $this->invokePrivateMethod(
            'normalizeRecordFilter',
            $value,
        );
    }

    private function invokeNormalizeTextFilter(mixed $value): ?string
    {
        return $this->invokePrivateMethod(
            'normalizeTextFilter',
            $value,
        );
    }

    /**
     * Invokes a private method of the controller under test via reflection.
     */
    private function invokePrivateMethod(string $method, mixed ...$arguments): mixed
    {
        return (new ReflectionMethod(
            TranslationController::class,
            $method,
        ))->invoke(
            $this->controller,
            ...$arguments
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

            // Untyped, no return type, matching AbstractUserAuthentication's own
            // signature on this branch's TYPO3 core exactly, PHP does not allow a
            // child to add a parameter type the parent does not declare.
            public function getModuleData($module, $type = ''): mixed
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

            // Untyped, no return type, matching AbstractUserAuthentication's own
            // signature on this branch's TYPO3 core exactly, PHP does not allow a
            // child to add a parameter type the parent does not declare.
            public function getModuleData($module, $type = ''): mixed
            {
                return null;
            }

            public function pushModuleData($module, $data, $noSave = 0): void
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

    #[Test]
    public function translateRecordActionRejectsANonIntegerUpdateKeyWithoutCrashing(): void
    {
        // Case 1 of issue #129: update['foo']=x used to reach
        // findRecordByUid('foo') and raise an uncaught TypeError under
        // strict_types before the key was ever validated.
        $translationRepository = self::createMock(TranslationRepository::class);
        $translationRepository->method('findByUid')->willReturn(null);
        $translationRepository->expects(self::never())->method('findRecordByUid');
        $translationRepository->expects(self::never())->method('update');

        $this->wireTranslateRecordActionDependencies($translationRepository);

        try {
            $this->controller->translateRecordAction(1, [], ['foo' => 'x']);
        } catch (Throwable $throwable) {
            self::assertNotInstanceOf(
                TypeError::class,
                $throwable,
                'A non-int update[] key must be rejected before the lookup, not crash it.',
            );
        }
    }

    #[Test]
    public function translateRecordActionRejectsAnArrayUpdateValueWithoutCrashing(): void
    {
        // Case 2 of issue #129: update[1][]=x used to reach setValue() with
        // an array and raise an uncaught TypeError under strict_types.
        $translationRepository = self::createMock(TranslationRepository::class);
        $translationRepository->method('findByUid')->willReturn(null);
        $translationRepository->expects(self::never())->method('findRecordByUid');
        $translationRepository->expects(self::never())->method('update');

        $this->wireTranslateRecordActionDependencies($translationRepository);

        try {
            $this->controller->translateRecordAction(1, [], [1 => ['x']]);
        } catch (Throwable $throwable) {
            self::assertNotInstanceOf(
                TypeError::class,
                $throwable,
                'An array update[] value must be rejected before setValue(), not crash it.',
            );
        }
    }

    #[Test]
    public function translateRecordActionRejectsAWellFormedButNonExistentUpdateUid(): void
    {
        // A record deleted between page load and submit, or a tampered but
        // well-formed uid: findRecordByUid() returns null, and the pre-fix
        // code called setValue() on it unconditionally.
        $translationRepository = self::createMock(TranslationRepository::class);
        $translationRepository->method('findByUid')->willReturn(null);
        $translationRepository->method('findRecordByUid')->willReturn(null);
        $translationRepository->expects(self::never())->method('update');

        $this->wireTranslateRecordActionDependencies($translationRepository);

        try {
            $this->controller->translateRecordAction(1, [], [1 => 'x']);
        } catch (Throwable) {
        }
    }

    #[Test]
    public function translateRecordActionRejectsAnUnconfiguredLanguageId(): void
    {
        // Case 3 of issue #129: new[999]=Text was persisted regardless of
        // whether language 999 is configured on any site, permanently
        // occupying a slot of the unique key.
        $parentTranslation = self::createStub(Translation::class);

        $translationRepository = self::createMock(TranslationRepository::class);
        $translationRepository->method('findByUid')->willReturn($parentTranslation);
        $translationRepository->expects(self::never())->method('add');

        $translationService = self::createMock(TranslationService::class);
        $translationService->method('getAllLanguages')->willReturn([0 => self::createStub(SiteLanguage::class)]);
        $translationService->expects(self::never())->method('createTranslationFromParent');

        $this->wireTranslateRecordActionDependencies($translationRepository, $translationService);

        try {
            $this->controller->translateRecordAction(1, [999 => 'Text'], []);
        } catch (Throwable) {
        }
    }

    #[Test]
    public function translateRecordActionAcceptsAWellFormedSubmission(): void
    {
        // The guard above must not reject valid input: a configured language
        // id with a string value still has to reach createTranslationFromParent().
        $parentTranslation = self::createStub(Translation::class);
        $newTranslation    = self::createStub(Translation::class);

        $translationRepository = self::createMock(TranslationRepository::class);
        $translationRepository->method('findByUid')->willReturn($parentTranslation);
        $translationRepository->expects(self::once())->method('add')->with($newTranslation);

        $translationService = self::createMock(TranslationService::class);
        $translationService->method('getAllLanguages')->willReturn([1 => self::createStub(SiteLanguage::class)]);
        $translationService->expects(self::once())
            ->method('createTranslationFromParent')
            ->with($parentTranslation, 1, 'Send it')
            ->willReturn($newTranslation);

        $this->wireTranslateRecordActionDependencies($translationRepository, $translationService);

        try {
            $this->controller->translateRecordAction(1, [1 => 'Send it'], []);
        } catch (Throwable) {
        }
    }

    /**
     * Wires the repositories/services translateRecordAction() needs, stubbed
     * just enough to observe whether the malformed-input guard let a call
     * through. persistAll() and addFlashMessageToQueue() run unconditionally
     * at the end of the action and touch a real backend user session this
     * reflection-constructed controller never sets up, callers wrap the
     * action call in try/catch(Throwable) and assert the repository/service
     * expectations above, which are already satisfied by the time that
     * happens.
     */
    private function wireTranslateRecordActionDependencies(
        TranslationRepository $translationRepository,
        ?TranslationService $translationService = null,
    ): void {
        // addFlashMessageToQueue() registers a real FlashMessageService
        // singleton via GeneralUtility::makeInstance(), which UnitTestCase's
        // tearDown() integrity check flags unless the test declares it expects
        // that framework state change.
        $this->resetSingletonInstances = true;

        $GLOBALS['LANG'] = self::createStub(LanguageService::class);

        $this->setControllerProperty('translationRepository', $translationRepository);
        $this->setControllerProperty('persistenceManager', self::createStub(PersistenceManager::class));
        $this->setControllerProperty(
            'translationService',
            $translationService ?? self::createStub(TranslationService::class),
        );
    }

    #[Test]
    public function errorActionBlocksAForwardIntoAForeignExtension(): void
    {
        // The referrer's HMAC signature is installation-global, not scoped to
        // this extension, so a validly-signed referrer copied from any other
        // Extbase backend module must still be rejected here (issue #129).
        // GeneralUtility::hmac() (which HashService delegates to) needs a
        // non-empty encryption key, UnitTestCase does not seed one.
        $GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = str_repeat('a', 96);

        $hashService = new HashService();

        $referrerRequest = $hashService->appendHmac(
            json_encode([
                '@extension'  => 'SomeOtherExtension',
                '@controller' => 'Foreign',
                '@action'     => 'index',
            ]),
        );

        $this->wireControllerRequest([
            '__referrer' => [
                '@request' => $referrerRequest,
            ],
        ]);
        $this->setControllerProperty('hashService', $hashService);
        // forwardToReferringRequest() attaches $this->arguments->validate() to
        // the built ForwardResponse; $this->arguments is only ever populated
        // by initializeActionMethodArguments(), which a Unit test does not run.
        $this->setControllerProperty('arguments', new Arguments());
        $this->wireErrorActionResponseFactories();

        $response = $this->invokePrivateMethod('errorAction');

        self::assertSame(400, $response->getStatusCode());
    }

    /**
     * Both errorAction() branches under test fall through to htmlResponse(),
     * which needs the PSR-17 factories a real Bootstrap-dispatched controller
     * injects via injectResponseFactory()/injectStreamFactory(), neither of
     * which runs on this reflection-constructed instance.
     */
    private function wireErrorActionResponseFactories(): void
    {
        $this->setControllerProperty('responseFactory', new ResponseFactory());
        $this->setControllerProperty('streamFactory', new StreamFactory());
    }

    private function setControllerProperty(string $name, object $value): void
    {
        $property = new ReflectionProperty(
            TranslationController::class,
            $name,
        );
        $property->setValue(
            $this->controller,
            $value,
        );
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

        $language = self::createStub(SiteLanguage::class);
        $language->method('getLanguageId')->willReturn(0);
        $language->method('getTwoLetterIsoCode')->willReturn('en');

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

        return $this->invokePrivateMethod(
            'getPagination',
            $queryResult,
            $settings ?? ['enablePagination' => true, 'itemsPerPage' => $itemsPerPage],
        );
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
