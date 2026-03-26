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
use Netresearch\NrTextdb\Service\ImportService;
use Netresearch\NrTextdb\Service\TranslationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use RuntimeException;
use TYPO3\CMS\Core\Localization\Parser\XliffParser;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(ImportService::class)]
#[UsesClass(Translation::class)]
#[UsesClass(Component::class)]
#[UsesClass(Environment::class)]
#[UsesClass(Type::class)]
final class ImportServiceTest extends UnitTestCase
{
    private ImportService $subject;

    private PersistenceManagerInterface&MockObject $persistenceManager;

    private TranslationRepository&MockObject $translationRepository;

    private ComponentRepository&MockObject $componentRepository;

    private TypeRepository&MockObject $typeRepository;

    private EnvironmentRepository&MockObject $environmentRepository;

    private TranslationService&MockObject $translationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->persistenceManager    = $this->createMock(PersistenceManagerInterface::class);
        $xliffParser                 = $this->createMock(XliffParser::class);
        $this->translationService    = $this->createMock(TranslationService::class);
        $this->translationRepository = $this->createMock(TranslationRepository::class);
        $this->componentRepository   = $this->createMock(ComponentRepository::class);
        $this->typeRepository        = $this->createMock(TypeRepository::class);
        $this->environmentRepository = $this->createMock(EnvironmentRepository::class);
        $siteFinder                  = $this->createMock(SiteFinder::class);

        $this->subject = new ImportService(
            $this->persistenceManager,
            $xliffParser,
            $this->translationService,
            $this->translationRepository,
            $this->componentRepository,
            $this->typeRepository,
            $this->environmentRepository,
            $siteFinder,
        );
    }

    #[Test]
    public function importEntrySkipsNullComponentName(): void
    {
        $imported = 0;
        $updated  = 0;
        $errors   = [];

        $this->subject->importEntry(0, null, 'label', 'key', 'value', false, $imported, $updated, $errors);

        self::assertSame(0, $imported);
        self::assertSame(0, $updated);
    }

    #[Test]
    public function importEntrySkipsNullTypeName(): void
    {
        $imported = 0;
        $updated  = 0;
        $errors   = [];

        $this->subject->importEntry(0, 'component', null, 'key', 'value', false, $imported, $updated, $errors);

        self::assertSame(0, $imported);
        self::assertSame(0, $updated);
    }

    #[Test]
    public function importEntrySkipsWhenEnvironmentNotFound(): void
    {
        $imported = 0;
        $updated  = 0;
        $errors   = [];

        $this->environmentRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->environmentRepository->method('findByName')->willReturn(null);
        $this->componentRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->componentRepository->method('findByName')->willReturn(new Component());
        $this->typeRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->typeRepository->method('findByName')->willReturn(new Type());

        $this->subject->importEntry(0, 'comp', 'type', 'key', 'value', false, $imported, $updated, $errors);

        self::assertSame(0, $imported);
        self::assertSame(0, $updated);
    }

    #[Test]
    public function importEntryCreatesNewTranslation(): void
    {
        $imported = 0;
        $updated  = 0;
        $errors   = [];

        $environment = new Environment();
        $component   = new Component();
        $type        = new Type();
        $translation = new Translation();
        $translation->setValue('New Value');

        $this->environmentRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->environmentRepository->method('findByName')->willReturn($environment);
        $this->componentRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->componentRepository->method('findByName')->willReturn($component);
        $this->typeRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->typeRepository->method('findByName')->willReturn($type);
        $this->translationRepository->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturn(null);
        $this->translationService->method('createTranslation')->willReturn($translation);
        $this->translationRepository->expects(self::once())->method('add');

        $this->subject->importEntry(0, 'comp', 'type', 'key', 'New Value', false, $imported, $updated, $errors);

        self::assertSame(1, $imported);
        self::assertSame(0, $updated);
        self::assertSame([], $errors);
    }

    #[Test]
    public function importEntryUpdatesExistingTranslationWithForceUpdate(): void
    {
        $imported = 0;
        $updated  = 0;
        $errors   = [];

        $environment = new Environment();
        $component   = new Component();
        $type        = new Type();
        $existing    = new Translation();
        $existing->setValue('Old Value');

        $this->environmentRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->environmentRepository->method('findByName')->willReturn($environment);
        $this->componentRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->componentRepository->method('findByName')->willReturn($component);
        $this->typeRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->typeRepository->method('findByName')->willReturn($type);
        $this->translationRepository->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturn($existing);
        $this->translationRepository->expects(self::once())->method('update');

        $this->subject->importEntry(0, 'comp', 'type', 'key', 'Updated', true, $imported, $updated, $errors);

        self::assertSame(0, $imported);
        self::assertSame(1, $updated);
        self::assertSame('Updated', $existing->getValue());
    }

    #[Test]
    public function importEntrySkipsExistingWithoutForceUpdate(): void
    {
        $imported = 0;
        $updated  = 0;
        $errors   = [];

        $environment = new Environment();
        $component   = new Component();
        $type        = new Type();
        $existing    = new Translation();
        $existing->setValue('Existing');

        $this->environmentRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->environmentRepository->method('findByName')->willReturn($environment);
        $this->componentRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->componentRepository->method('findByName')->willReturn($component);
        $this->typeRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->typeRepository->method('findByName')->willReturn($type);
        $this->translationRepository->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturn($existing);

        $this->subject->importEntry(0, 'comp', 'type', 'key', 'New', false, $imported, $updated, $errors);

        self::assertSame(0, $imported);
        self::assertSame(0, $updated);
    }

    #[Test]
    public function importEntryForceUpdatesAutoCreatedEntries(): void
    {
        $imported = 0;
        $updated  = 0;
        $errors   = [];

        $environment = new Environment();
        $component   = new Component();
        $type        = new Type();
        $existing    = new Translation();
        $existing->setValue(Translation::AUTO_CREATE_IDENTIFIER);

        $this->environmentRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->environmentRepository->method('findByName')->willReturn($environment);
        $this->componentRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->componentRepository->method('findByName')->willReturn($component);
        $this->typeRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->typeRepository->method('findByName')->willReturn($type);
        $this->translationRepository->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturn($existing);
        $this->translationRepository->expects(self::once())->method('update');

        // Even without forceUpdate=true, auto-created entries should be updated
        $this->subject->importEntry(0, 'comp', 'type', 'key', 'Real value', false, $imported, $updated, $errors);

        self::assertSame(0, $imported);
        self::assertSame(1, $updated);
        self::assertSame('Real value', $existing->getValue());
    }

    #[Test]
    public function importEntryCollectsExceptionErrors(): void
    {
        $imported = 0;
        $updated  = 0;
        $errors   = [];

        $this->environmentRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->environmentRepository->method('findByName')
            ->willThrowException(new RuntimeException('DB error'));

        $this->subject->importEntry(0, 'comp', 'type', 'key', 'value', false, $imported, $updated, $errors);

        self::assertSame(0, $imported);
        self::assertSame(0, $updated);
        self::assertCount(1, $errors);
        self::assertSame('DB error', $errors[0]);
    }
}
