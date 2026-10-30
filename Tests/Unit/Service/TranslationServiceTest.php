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

namespace Netresearch\NrTextdb\Tests\Unit\Service;

use Netresearch\NrTextdb\Domain\Model\Component;
use Netresearch\NrTextdb\Domain\Model\Environment;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Model\Type;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use Netresearch\NrTextdb\Service\TranslationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(TranslationService::class)]
#[UsesClass(Translation::class)]
#[UsesClass(Component::class)]
#[UsesClass(Environment::class)]
#[UsesClass(Type::class)]
final class TranslationServiceTest extends UnitTestCase
{
    private TranslationService $subject;

    private EnvironmentRepository&Stub $environmentRepository;

    private ComponentRepository&Stub $componentRepository;

    private TypeRepository&Stub $typeRepository;

    private TranslationRepository&Stub $translationRepository;

    private SiteFinder&Stub $siteFinder;

    private PersistenceManagerInterface&Stub $persistenceManager;

    private Context&Stub $context;

    private LanguageAspect $languageAspect;

    protected function setUp(): void
    {
        parent::setUp();

        $this->environmentRepository = self::createStub(EnvironmentRepository::class);
        $this->componentRepository   = self::createStub(ComponentRepository::class);
        $this->typeRepository        = self::createStub(TypeRepository::class);
        $this->translationRepository = self::createStub(TranslationRepository::class);
        $this->siteFinder            = self::createStub(SiteFinder::class);
        $this->persistenceManager    = self::createStub(PersistenceManagerInterface::class);
        $this->context               = self::createStub(Context::class);

        $this->languageAspect = new LanguageAspect(0);
        // with() requires expects(), and PHPUnit 13 deprecated both any() and
        // stubbing with*() without expects(). The callback keeps the argument
        // assertion without pinning an invocation count — not every test under
        // test reaches getCurrentLanguage(). It reads the property, so a test
        // can switch the language after setUp().
        $this->context
            ->method('getAspect')
            ->willReturnCallback(
                function (string $aspectName): LanguageAspect {
                    self::assertSame('language', $aspectName);

                    return $this->languageAspect;
                },
            );

        $this->subject = $this->createSubject();
    }

    /**
     * The doubles set up above are stubs. A test that verifies an invocation
     * passes its own mock for that collaborator.
     */
    private function createSubject(
        ?TranslationRepository $translationRepository = null,
        ?PersistenceManagerInterface $persistenceManager = null,
    ): TranslationService {
        return new TranslationService(
            $this->environmentRepository,
            $this->componentRepository,
            $this->typeRepository,
            $translationRepository ?? $this->translationRepository,
            $this->siteFinder,
            $persistenceManager ?? $this->persistenceManager,
            $this->context,
        );
    }

    #[Test]
    public function translateReturnsEmptyStringForEmptyPlaceholder(): void
    {
        $result = $this->subject->translate('', 'label', 'website', 'default');

        self::assertSame('', $result);
    }

    #[Test]
    public function translateReturnsCachedValueOnSecondCall(): void
    {
        $environment = new Environment();
        $component   = new Component();
        $type        = new Type();
        $translation = new Translation();
        $translation->setValue('Translated value');
        $translation->setPlaceholder('test.key');

        $this->environmentRepository->method('findByName')->willReturn($environment);
        $this->componentRepository->method('findByName')->willReturn($component);
        $this->typeRepository->method('findByName')->willReturn($type);
        // The second call is served from the cache, so the translation is
        // looked up once.
        $translationRepository = $this->createMock(TranslationRepository::class);
        $translationRepository
            ->expects(self::once())
            ->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturn($translation);

        $subject = $this->createSubject($translationRepository);

        $result1 = $subject->translate('test.key', 'label', 'website', 'default');
        $result2 = $subject->translate('test.key', 'label', 'website', 'default');

        self::assertSame('Translated value', $result1);
        self::assertSame('Translated value', $result2);
    }

    #[Test]
    public function translateReturnsPlaceholderWhenEnvironmentNotFound(): void
    {
        $this->environmentRepository->method('findByName')->willReturn(null);
        $this->componentRepository->method('findByName')->willReturn(new Component());
        $this->typeRepository->method('findByName')->willReturn(new Type());

        $result = $this->subject->translate('test.key', 'label', 'website', 'default');

        self::assertSame('test.key', $result);
    }

    #[Test]
    public function translateReturnsPlaceholderWhenComponentNotFound(): void
    {
        $this->environmentRepository->method('findByName')->willReturn(new Environment());
        $this->componentRepository->method('findByName')->willReturn(null);
        $this->typeRepository->method('findByName')->willReturn(new Type());

        $result = $this->subject->translate('test.key', 'label', 'website', 'default');

        self::assertSame('test.key', $result);
    }

    #[Test]
    public function translateReturnsPlaceholderWhenTypeNotFound(): void
    {
        $this->environmentRepository->method('findByName')->willReturn(new Environment());
        $this->componentRepository->method('findByName')->willReturn(new Component());
        $this->typeRepository->method('findByName')->willReturn(null);

        $result = $this->subject->translate('test.key', 'label', 'website', 'default');

        self::assertSame('test.key', $result);
    }

    #[Test]
    public function translateReturnsTranslationValue(): void
    {
        $environment = new Environment();
        $component   = new Component();
        $type        = new Type();
        $translation = new Translation();
        $translation->setValue('Hello World');
        $translation->setPlaceholder('greeting');

        $this->environmentRepository->method('findByName')->willReturn($environment);
        $this->componentRepository->method('findByName')->willReturn($component);
        $this->typeRepository->method('findByName')->willReturn($type);
        $this->translationRepository->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturn($translation);

        $result = $this->subject->translate('greeting', 'label', 'website', 'default');

        self::assertSame('Hello World', $result);
    }

    #[Test]
    public function translateReturnsPlaceholderWhenValueIsEmpty(): void
    {
        $environment = new Environment();
        $component   = new Component();
        $type        = new Type();
        $translation = new Translation();
        $translation->setValue('');
        $translation->setPlaceholder('greeting');

        $this->environmentRepository->method('findByName')->willReturn($environment);
        $this->componentRepository->method('findByName')->willReturn($component);
        $this->typeRepository->method('findByName')->willReturn($type);
        $this->translationRepository->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturn($translation);

        $result = $this->subject->translate('greeting', 'label', 'website', 'default');

        self::assertSame('greeting', $result);
    }

    #[Test]
    public function translateReturnsPlaceholderWhenNoTranslationAndCreateIfMissingFalse(): void
    {
        $environment = new Environment();
        $component   = new Component();
        $type        = new Type();

        $this->environmentRepository->method('findByName')->willReturn($environment);
        $this->componentRepository->method('findByName')->willReturn($component);
        $this->typeRepository->method('findByName')->willReturn($type);
        $this->translationRepository->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturn(null);
        $this->translationRepository->method('getCreateIfMissing')->willReturn(false);

        $result = $this->subject->translate('missing.key', 'label', 'website', 'default');

        self::assertSame('missing.key', $result);
    }

    #[Test]
    public function translateAutoCreatesTranslationWhenCreateIfMissingTrue(): void
    {
        $environment = new Environment();
        $environment->setPid(1);

        $component = new Component();
        $type      = new Type();

        $this->environmentRepository->method('findByName')->willReturn($environment);
        $this->environmentRepository->method('getConfiguredPageId')->willReturn(1);
        $this->componentRepository->method('findByName')->willReturn($component);
        $this->typeRepository->method('findByName')->willReturn($type);

        $translationRepository = $this->createMock(TranslationRepository::class);
        $translationRepository->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturn(null);
        $translationRepository->method('getCreateIfMissing')->willReturn(true);
        $translationRepository->expects(self::once())->method('add');

        $persistenceManager = $this->createMock(PersistenceManagerInterface::class);
        $persistenceManager->expects(self::once())->method('persistAll');

        $result = $this->createSubject($translationRepository, $persistenceManager)
            ->translate('auto.key', 'label', 'website', 'default');

        self::assertSame('auto.key', $result);
    }

    #[Test]
    public function createTranslationReturnsTranslationWithCorrectProperties(): void
    {
        $environment = new Environment();
        $environment->setName('default');

        $component = new Component();
        $component->setName('website');

        $type = new Type();
        $type->setName('label');

        $this->environmentRepository->method('getConfiguredPageId')->willReturn(42);
        $this->translationRepository->method('findByEnvironmentComponentTypeAndPlaceholder')
            ->willReturn(null);

        $translation = $this->subject->createTranslation(
            $environment,
            $component,
            $type,
            'test.placeholder',
            0,
            'Test Value',
        );

        self::assertSame('test.placeholder', $translation->getPlaceholder());
        self::assertSame('Test Value', $translation->getValue());
        self::assertSame($environment, $translation->getEnvironment());
        self::assertSame($component, $translation->getComponent());
        self::assertSame($type, $translation->getType());
    }

    #[Test]
    public function createTranslationFromParentReturnsNullWhenParentMissingEnvironment(): void
    {
        $parent = new Translation();
        $parent->setEnvironment(null);
        $parent->setComponent(new Component());
        $parent->setType(new Type());

        $result = $this->subject->createTranslationFromParent($parent, 1, 'value');

        self::assertNull($result);
    }

    #[Test]
    public function createTranslationFromParentReturnsTranslation(): void
    {
        $environment = new Environment();
        $component   = new Component();
        $type        = new Type();

        $parent = new Translation();
        $parent->setEnvironment($environment);
        $parent->setComponent($component);
        $parent->setType($type);
        $parent->setPlaceholder('original.key');

        $this->environmentRepository->method('getConfiguredPageId')->willReturn(1);

        $result = $this->subject->createTranslationFromParent($parent, 1, 'Translated');

        self::assertNotNull($result);
        self::assertSame('Translated', $result->getValue());
        self::assertSame('original.key', $result->getPlaceholder());
    }

    #[Test]
    public function getAllLanguagesReturnsEmptyArrayWhenNoSites(): void
    {
        $this->siteFinder->method('getAllSites')->willReturn([]);

        $result = $this->subject->getAllLanguages();

        self::assertSame([], $result);
    }

    #[Test]
    public function translateReturnsAutoCreatedPlaceholderValue(): void
    {
        $environment = new Environment();
        $component   = new Component();
        $type        = new Type();

        $translation = new Translation();
        $translation->setValue(Translation::AUTO_CREATE_IDENTIFIER);
        $translation->setPlaceholder('auto.placeholder');

        $this->environmentRepository->method('findByName')->willReturn($environment);
        $this->componentRepository->method('findByName')->willReturn($component);
        $this->typeRepository->method('findByName')->willReturn($type);
        $this->translationRepository->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturn($translation);

        $result = $this->subject->translate('auto.placeholder', 'label', 'website', 'default');

        // Auto-created translations return placeholder as value
        self::assertSame('auto.placeholder', $result);
    }

    #[Test]
    public function translateFromFallbackLanguagesReturnsNullForTheDefaultLanguageWithoutLookup(): void
    {
        $this->stubLookupTables();

        $translationRepository = $this->createMock(TranslationRepository::class);
        $translationRepository
            ->expects(self::never())
            ->method('findByEnvironmentComponentTypePlaceholderAndLanguage');

        $result = $this->createSubject($translationRepository)
            ->translateFromFallbackLanguages('greeting', 'label', 'website', 'default');

        self::assertNull($result);
    }

    #[Test]
    public function translateFromFallbackLanguagesReturnsNullForAnEmptyPlaceholder(): void
    {
        $this->languageAspect = new LanguageAspect(1, 1, LanguageAspect::OVERLAYS_ON_WITH_FLOATING, [0]);
        $this->stubLookupTables();

        $translationRepository = $this->createMock(TranslationRepository::class);
        $translationRepository
            ->expects(self::never())
            ->method('findByEnvironmentComponentTypePlaceholderAndLanguage');

        $result = $this->createSubject($translationRepository)
            ->translateFromFallbackLanguages('', 'label', 'website', 'default');

        self::assertNull($result);
    }

    #[Test]
    public function translateFromFallbackLanguagesTriesTheIntegerChainEntriesInOrderThenLanguageZero(): void
    {
        // The current language, strings and negative ids are skipped; a
        // repeated id is looked up once, and 0 comes last.
        $this->languageAspect = new LanguageAspect(
            3,
            3,
            LanguageAspect::OVERLAYS_ON_WITH_FLOATING,
            [3, 'pageNotFound', 5, -1, 2, 5, 'off'],
        );
        $this->stubLookupTables();

        $requestedLanguages    = [];
        $translationRepository = $this->createMock(TranslationRepository::class);
        $translationRepository
            ->expects(self::exactly(3))
            ->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturnCallback(
                static function (
                    Environment $environment,
                    Component $component,
                    Type $type,
                    string $placeholder,
                    int $languageUid,
                ) use (&$requestedLanguages): ?Translation {
                    $requestedLanguages[] = $languageUid;

                    return null;
                },
            );

        $result = $this->createSubject($translationRepository)
            ->translateFromFallbackLanguages('greeting', 'label', 'website', 'default');

        self::assertNull($result);
        self::assertSame([5, 2, 0], $requestedLanguages);
    }

    #[Test]
    public function translateFromFallbackLanguagesReturnsTheFirstUsableValueAndCachesEachLanguage(): void
    {
        $this->languageAspect = new LanguageAspect(3, 3, LanguageAspect::OVERLAYS_ON_WITH_FLOATING, [5, 2, 0]);
        $this->stubLookupTables();

        $empty = new Translation();
        $empty->setPlaceholder('greeting')->setValue('');

        $autoCreated = new Translation();
        $autoCreated->setPlaceholder('greeting')->setValue(Translation::AUTO_CREATE_IDENTIFIER);

        $usable = new Translation();
        $usable->setPlaceholder('greeting')->setValue('Hallo');

        $byLanguage = [5 => $empty, 2 => $autoCreated, 0 => $usable];

        $requestedLanguages    = [];
        $translationRepository = $this->createMock(TranslationRepository::class);
        $translationRepository
            ->expects(self::exactly(3))
            ->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturnCallback(
                static function (
                    Environment $environment,
                    Component $component,
                    Type $type,
                    string $placeholder,
                    int $languageUid,
                ) use (&$requestedLanguages, $byLanguage): ?Translation {
                    $requestedLanguages[] = $languageUid;

                    return $byLanguage[$languageUid] ?? null;
                },
            );

        $subject = $this->createSubject($translationRepository);

        // The second call is served from the cache: three lookups in total.
        self::assertSame('Hallo', $subject->translateFromFallbackLanguages('greeting', 'label', 'website', 'default'));
        self::assertSame('Hallo', $subject->translateFromFallbackLanguages('greeting', 'label', 'website', 'default'));
        self::assertSame([5, 2, 0], $requestedLanguages);
    }

    private function stubLookupTables(): void
    {
        $this->environmentRepository->method('findByName')->willReturn(new Environment());
        $this->componentRepository->method('findByName')->willReturn(new Component());
        $this->typeRepository->method('findByName')->willReturn(new Type());
    }
}
