<?php

/*
 * This file is part of the package netresearch/nr-textdb.
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
use PHPUnit\Framework\MockObject\MockObject;
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

    private EnvironmentRepository&MockObject $environmentRepository;

    private ComponentRepository&MockObject $componentRepository;

    private TypeRepository&MockObject $typeRepository;

    private TranslationRepository&MockObject $translationRepository;

    private SiteFinder&MockObject $siteFinder;

    private PersistenceManagerInterface&MockObject $persistenceManager;

    private Context&MockObject $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->environmentRepository = $this->createMock(EnvironmentRepository::class);
        $this->componentRepository   = $this->createMock(ComponentRepository::class);
        $this->typeRepository        = $this->createMock(TypeRepository::class);
        $this->translationRepository = $this->createMock(TranslationRepository::class);
        $this->siteFinder            = $this->createMock(SiteFinder::class);
        $this->persistenceManager    = $this->createMock(PersistenceManagerInterface::class);
        $this->context               = $this->createMock(Context::class);

        $languageAspect = new LanguageAspect(0);
        $this->context->method('getAspect')->with('language')->willReturn($languageAspect);

        $this->subject = new TranslationService(
            $this->environmentRepository,
            $this->componentRepository,
            $this->typeRepository,
            $this->translationRepository,
            $this->siteFinder,
            $this->persistenceManager,
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
        $this->translationRepository->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturn($translation);

        // First call
        $result1 = $this->subject->translate('test.key', 'label', 'website', 'default');
        // Second call should use cache - repositories should not be called again
        $result2 = $this->subject->translate('test.key', 'label', 'website', 'default');

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
        $this->translationRepository->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturn(null);
        $this->translationRepository->method('getCreateIfMissing')->willReturn(true);
        $this->translationRepository->expects(self::once())->method('add');
        $this->persistenceManager->expects(self::once())->method('persistAll');

        $result = $this->subject->translate('auto.key', 'label', 'website', 'default');

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
}
