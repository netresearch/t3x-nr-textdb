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

use ArrayObject;
use Closure;
use Netresearch\NrTextdb\Domain\Model\Component;
use Netresearch\NrTextdb\Domain\Model\Environment;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Model\Type;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use Netresearch\NrTextdb\Service\ImportResult;
use Netresearch\NrTextdb\Service\ImportService;
use Netresearch\NrTextdb\Service\TranslationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\Stub;
use RuntimeException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\Loader\XliffLoader;
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

    private PersistenceManagerInterface&Stub $persistenceManager;

    private TranslationRepository&Stub $translationRepository;

    private ComponentRepository&Stub $componentRepository;

    private TypeRepository&Stub $typeRepository;

    private EnvironmentRepository&Stub $environmentRepository;

    private TranslationService&Stub $translationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->persistenceManager    = self::createStub(PersistenceManagerInterface::class);
        $this->translationService    = self::createStub(TranslationService::class);
        $this->translationRepository = self::createStub(TranslationRepository::class);
        $this->componentRepository   = self::createStub(ComponentRepository::class);
        $this->typeRepository        = self::createStub(TypeRepository::class);
        $this->environmentRepository = self::createStub(EnvironmentRepository::class);

        $this->subject = $this->createSubject($this->translationRepository);
    }

    /**
     * The doubles set up above are stubs. A test that verifies an invocation
     * passes its own translation repository mock or connection pool.
     */
    private function createSubject(
        TranslationRepository $translationRepository,
        ?ConnectionPool $connectionPool = null,
    ): ImportService {
        return new ImportService(
            $this->persistenceManager,
            // XliffLoader is final in TYPO3 v14 and cannot be doubled. The
            // methods exercised here never touch it, so the real loader is
            // handed in.
            new XliffLoader(),
            $this->translationService,
            $translationRepository,
            $this->componentRepository,
            $this->typeRepository,
            $this->environmentRepository,
            $connectionPool ?? self::createStub(ConnectionPool::class),
        );
    }

    /**
     * Makes the three reference repositories return the given records.
     */
    private function stubReferenceRecords(?Environment $environment, Component $component, Type $type): void
    {
        $this->environmentRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->environmentRepository->method('findByName')->willReturn($environment);
        $this->componentRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->componentRepository->method('findByName')->willReturn($component);
        $this->typeRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->typeRepository->method('findByName')->willReturn($type);
    }

    #[Test]
    public function importEntrySkipsNullComponentName(): void
    {
        $result = new ImportResult();

        $this->subject->importEntry(0, null, 'label', 'key', 'value', false, $result);

        self::assertSame(0, $result->getImported());
        self::assertSame(0, $result->getUpdated());
    }

    #[Test]
    public function importEntrySkipsNullTypeName(): void
    {
        $result = new ImportResult();

        $this->subject->importEntry(0, 'component', null, 'key', 'value', false, $result);

        self::assertSame(0, $result->getImported());
        self::assertSame(0, $result->getUpdated());
    }

    #[Test]
    public function importEntrySkipsWhenEnvironmentNotFound(): void
    {
        $result = new ImportResult();

        $this->stubReferenceRecords(null, new Component(), new Type());

        $this->subject->importEntry(0, 'comp', 'type', 'key', 'value', false, $result);

        self::assertSame(0, $result->getImported());
        self::assertSame(0, $result->getUpdated());
    }

    #[Test]
    public function importEntryCreatesNewTranslation(): void
    {
        $result = new ImportResult();

        $translation = new Translation();
        $translation->setValue('New Value');

        $this->stubReferenceRecords(new Environment(), new Component(), new Type());
        $this->translationService->method('createTranslation')->willReturn($translation);

        $translationRepository = $this->createMock(TranslationRepository::class);
        $translationRepository->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturn(null);
        $translationRepository->expects(self::once())->method('add');

        $this->createSubject($translationRepository)->importEntry(0, 'comp', 'type', 'key', 'New Value', false, $result);

        self::assertSame(1, $result->getImported());
        self::assertSame(0, $result->getUpdated());
        self::assertSame([], $result->getErrors());
    }

    #[Test]
    public function importEntryUpdatesExistingTranslationWithForceUpdate(): void
    {
        $result = new ImportResult();

        $existing = new Translation();
        $existing->setValue('Old Value');

        $this->stubReferenceRecords(new Environment(), new Component(), new Type());

        $translationRepository = $this->createMock(TranslationRepository::class);
        $translationRepository->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturn($existing);
        $translationRepository->expects(self::once())->method('update');

        $this->createSubject($translationRepository)->importEntry(0, 'comp', 'type', 'key', 'Updated', true, $result);

        self::assertSame(0, $result->getImported());
        self::assertSame(1, $result->getUpdated());
        self::assertSame('Updated', $existing->getValue());
    }

    #[Test]
    public function importEntrySkipsExistingWithoutForceUpdate(): void
    {
        $result = new ImportResult();

        $existing = new Translation();
        $existing->setValue('Existing');

        $this->stubReferenceRecords(new Environment(), new Component(), new Type());
        $this->translationRepository->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturn($existing);

        $this->subject->importEntry(0, 'comp', 'type', 'key', 'New', false, $result);

        self::assertSame(0, $result->getImported());
        self::assertSame(0, $result->getUpdated());
    }

    #[Test]
    public function importEntryForceUpdatesAutoCreatedEntries(): void
    {
        $result = new ImportResult();

        $existing = new Translation();
        $existing->setValue(Translation::AUTO_CREATE_IDENTIFIER);

        $this->stubReferenceRecords(new Environment(), new Component(), new Type());

        $translationRepository = $this->createMock(TranslationRepository::class);
        $translationRepository->method('findByEnvironmentComponentTypePlaceholderAndLanguage')
            ->willReturn($existing);
        $translationRepository->expects(self::once())->method('update');

        // Even without forceUpdate=true, auto-created entries should be updated
        $this->createSubject($translationRepository)->importEntry(0, 'comp', 'type', 'key', 'Real value', false, $result);

        self::assertSame(0, $result->getImported());
        self::assertSame(1, $result->getUpdated());
        self::assertSame('Real value', $existing->getValue());
    }

    #[Test]
    public function importEntryCollectsExceptionErrors(): void
    {
        $result = new ImportResult();

        $this->environmentRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->environmentRepository->method('findByName')
            ->willThrowException(new RuntimeException('DB error'));

        $this->subject->importEntry(0, 'comp', 'type', 'key', 'value', false, $result);

        self::assertSame(0, $result->getImported());
        self::assertSame(0, $result->getUpdated());
        self::assertSame(['DB error'], $result->getErrors());
    }

    #[Test]
    public function importEntriesWritesTheEntriesInOneTransaction(): void
    {
        /** @var ArrayObject<int, string> $calls */
        $calls = new ArrayObject();

        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())
            ->method('transactional')
            ->willReturnCallback(static function (Closure $work) use ($calls): mixed {
                $calls[] = 'begin';
                $value   = $work();
                $calls[] = 'commit';

                return $value;
            });

        $connectionPool = self::createStub(ConnectionPool::class);
        $connectionPool->method('getConnectionForTable')->willReturn($connection);

        $persistenceManager = $this->createMock(PersistenceManagerInterface::class);
        $persistenceManager->expects(self::once())
            ->method('persistAll')
            ->willReturnCallback(static function () use ($calls): void {
                $calls[] = 'persistAll';
            });
        $this->persistenceManager = $persistenceManager;

        $this->createSubject($this->translationRepository, $connectionPool)
            ->importEntries(['comp|type|key' => 'value'], 0, false, new ImportResult());

        self::assertSame(['begin', 'persistAll', 'commit'], $calls->getArrayCopy());
    }

    #[Test]
    public function importEntriesReportsTheEntryErrorsOfARolledBackFileButNoCounts(): void
    {
        // The work runs, then the commit fails: the file is rolled back.
        $connection = self::createStub(Connection::class);
        $connection->method('transactional')
            ->willReturnCallback(static function (Closure $work): never {
                $work();

                throw new RuntimeException('Commit failed', 1793253602);
            });

        $connectionPool = self::createStub(ConnectionPool::class);
        $connectionPool->method('getConnectionForTable')->willReturn($connection);

        // The first entry fails on its own and records its error.
        $this->environmentRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->environmentRepository->method('findByName')
            ->willThrowException(new RuntimeException('Environment write failed'));

        $result = new ImportResult();

        try {
            $this->createSubject($this->translationRepository, $connectionPool)
                ->importEntries(['comp|type|key' => 'value'], 0, false, $result);
            self::fail('The failed commit did not reach the caller.');
        } catch (RuntimeException $exception) {
            self::assertSame(1793253602, $exception->getCode());
        }

        self::assertSame(['Environment write failed'], $result->getErrors());
        self::assertSame(0, $result->getImported());
    }

    #[Test]
    public function importEntriesClearsThePersistenceSessionAfterEachThousandEntries(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('transactional')
            ->willReturnCallback(static fn (Closure $work): mixed => $work());

        $connectionPool = self::createStub(ConnectionPool::class);
        $connectionPool->method('getConnectionForTable')->willReturn($connection);

        $persistenceManager = $this->createMock(PersistenceManagerInterface::class);
        $persistenceManager->expects(self::exactly(2))->method('persistAll');
        $persistenceManager->expects(self::once())->method('clearState');
        $this->persistenceManager = $persistenceManager;

        $entries = [];
        for ($i = 0; $i < 1000; ++$i) {
            $entries['comp|type|key' . $i] = 'value';
        }

        $this->createSubject($this->translationRepository, $connectionPool)
            ->importEntries($entries, 0, false, new ImportResult());
    }
}
