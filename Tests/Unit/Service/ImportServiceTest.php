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
use Netresearch\NrTextdb\Service\LoadedXliffFile;
use Netresearch\NrTextdb\Service\TranslationService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
#[UsesClass(ImportResult::class)]
#[UsesClass(LoadedXliffFile::class)]
final class ImportServiceTest extends UnitTestCase
{
    private ImportService $subject;

    private PersistenceManagerInterface&Stub $persistenceManager;

    private TranslationRepository&Stub $translationRepository;

    private ComponentRepository&Stub $componentRepository;

    private TypeRepository&Stub $typeRepository;

    private EnvironmentRepository&Stub $environmentRepository;

    private TranslationService&Stub $translationService;

    /**
     * $GLOBALS['TYPO3_CONF_VARS'] as it was before the test.
     */
    private mixed $configurationBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurationBackup = $GLOBALS['TYPO3_CONF_VARS'] ?? null;

        $this->persistenceManager    = self::createStub(PersistenceManagerInterface::class);
        $this->translationService    = self::createStub(TranslationService::class);
        $this->translationRepository = self::createStub(TranslationRepository::class);
        $this->componentRepository   = self::createStub(ComponentRepository::class);
        $this->typeRepository        = self::createStub(TypeRepository::class);
        $this->environmentRepository = self::createStub(EnvironmentRepository::class);

        $this->subject = $this->createSubject($this->translationRepository);
    }

    protected function tearDown(): void
    {
        $GLOBALS['TYPO3_CONF_VARS'] = $this->configurationBackup;

        parent::tearDown();
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
            // XliffLoader is final in TYPO3 v14 and cannot be doubled, so the
            // real loader is handed in. loadFile() reads XML strings with it.
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

        // The first entry is imported and counted. The other two fail on
        // their own with the same error.
        $calls = 0;
        $this->componentRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->componentRepository->method('findByName')->willReturn(new Component());
        $this->typeRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->typeRepository->method('findByName')->willReturn(new Type());
        $this->environmentRepository->method('setCreateIfMissing')->willReturnSelf();
        $this->environmentRepository->method('findByName')
            ->willReturnCallback(static function () use (&$calls): Environment {
                if (++$calls > 1) {
                    throw new RuntimeException('Environment write failed');
                }

                return new Environment();
            });
        $this->translationService->method('createTranslation')->willReturn(new Translation());

        $result = new ImportResult();

        try {
            $this->createSubject($this->translationRepository, $connectionPool)
                ->importEntries(['comp|type|one' => 'One', 'comp|type|two' => 'Two', 'comp|type|three' => 'Three'], 0, false, $result);
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

    #[Test]
    public function importEntriesReportsTheSkippedUnitsOfACommittedFile(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('transactional')
            ->willReturnCallback(static fn (Closure $work): mixed => $work());

        $connectionPool = self::createStub(ConnectionPool::class);
        $connectionPool->method('getConnectionForTable')->willReturn($connection);

        $result = new ImportResult();

        $this->createSubject($this->translationRepository, $connectionPool)
            ->importEntries(
                ['comp|type|empty' => '', 'comp|type|blank' => "  \n "],
                0,
                false,
                $result,
                ['comp|type|draft'],
                ['comp|type|plural'],
            );

        self::assertSame(['comp|type|draft'], $result->getSkippedUnapproved());
        self::assertSame(['comp|type|plural'], $result->getPartiallyApproved());
        self::assertSame(['comp|type|empty', 'comp|type|blank'], $result->getSkippedEmpty());
    }

    #[Test]
    public function importEntriesReportsNoSkippedUnitOfARolledBackFile(): void
    {
        $connection = self::createStub(Connection::class);
        $connection->method('transactional')
            ->willReturnCallback(static function (Closure $work): never {
                $work();

                throw new RuntimeException('Commit failed', 1793253603);
            });

        $connectionPool = self::createStub(ConnectionPool::class);
        $connectionPool->method('getConnectionForTable')->willReturn($connection);

        $result = new ImportResult();

        try {
            $this->createSubject($this->translationRepository, $connectionPool)
                ->importEntries(['comp|type|empty' => ''], 0, false, $result, ['comp|type|draft'], ['comp|type|plural']);
            self::fail('The failed commit did not reach the caller.');
        } catch (RuntimeException $exception) {
            self::assertSame(1793253603, $exception->getCode());
        }

        self::assertSame([], $result->getSkippedUnapproved());
        self::assertSame([], $result->getSkippedEmpty());
        self::assertSame([], $result->getPartiallyApproved());
    }

    /**
     * Plural units of translation files. The loader keeps the approved forms
     * of a unit and drops the others.
     *
     * @return array<string, array{string, list<string>, list<string>, list<string>}>
     */
    public static function pluralFileProvider(): array
    {
        $xliff12 = static fn (string $body): string => '<?xml version="1.0" encoding="UTF-8"?>'
            . '<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">'
            . '<file source-language="en" target-language="de" datatype="plaintext" original="messages"><body>'
            . $body
            . '</body></file></xliff>';
        $xliff20 = static fn (string $body): string => '<?xml version="1.0" encoding="UTF-8"?>'
            . '<xliff version="2.0" xmlns="urn:oasis:names:tc:xliff:document:2.0" srcLang="en" trgLang="de">'
            . '<file id="messages">' . $body . '</file></xliff>';

        return [
            'XLIFF 1.2, one form not approved' => [
                $xliff12(
                    '<group id="c|t|p" restype="x-gettext-plurals">'
                    . '<trans-unit id="c|t|p[0]"><source>one</source><target>eins</target></trans-unit>'
                    . '<trans-unit id="c|t|p[1]" approved="no"><source>many</source><target>viele</target></trans-unit>'
                    . '</group>',
                ),
                ['c|t|p'],
                [],
                ['c|t|p'],
            ],
            'XLIFF 1.2, no form approved' => [
                $xliff12(
                    '<group id="c|t|p" restype="x-gettext-plurals">'
                    . '<trans-unit id="c|t|p[0]" approved="no"><source>one</source><target>eins</target></trans-unit>'
                    . '<trans-unit id="c|t|p[1]" approved="no"><source>many</source><target>viele</target></trans-unit>'
                    . '</group>',
                ),
                [],
                ['c|t|p'],
                [],
            ],
            'XLIFF 1.2, every form approved' => [
                $xliff12(
                    '<group id="c|t|p" restype="x-gettext-plurals">'
                    . '<trans-unit id="c|t|p[0]"><source>one</source><target>eins</target></trans-unit>'
                    . '<trans-unit id="c|t|p[1]"><source>many</source><target>viele</target></trans-unit>'
                    . '</group>',
                ),
                ['c|t|p'],
                [],
                [],
            ],
            'XLIFF 2.0, second segment initial' => [
                $xliff20(
                    '<unit id="c|t|p">'
                    . '<segment state="final"><source>one</source><target>eins</target></segment>'
                    . '<segment state="initial"><source>many</source><target>viele</target></segment>'
                    . '</unit>',
                ),
                ['c|t|p'],
                [],
                ['c|t|p'],
            ],
            // The loader drops a segment without <target> in both reads, so
            // the two values agree and the missing form is not reported.
            'XLIFF 2.0, second segment without target' => [
                $xliff20(
                    '<unit id="c|t|p">'
                    . '<segment state="final"><source>one</source><target>eins</target></segment>'
                    . '<segment state="final"><source>many</source></segment>'
                    . '</unit>',
                ),
                ['c|t|p'],
                [],
                [],
            ],
        ];
    }

    /**
     * @param list<string> $expectedEntries
     * @param list<string> $expectedUnapproved
     * @param list<string> $expectedPartiallyApproved
     */
    #[Test]
    #[DataProvider('pluralFileProvider')]
    public function loadFileNamesThePluralUnitsTheLoaderKeptWithoutTheirUnapprovedForms(
        string $xliff,
        array $expectedEntries,
        array $expectedUnapproved,
        array $expectedPartiallyApproved,
    ): void {
        $GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations'] = true;

        $loadedFile = $this->subject->loadFile($xliff, 'de');

        self::assertSame($expectedEntries, array_keys($loadedFile->entries));
        self::assertSame($expectedUnapproved, $loadedFile->unapprovedKeys);
        self::assertSame($expectedPartiallyApproved, $loadedFile->partiallyApprovedKeys);
    }

    /**
     * @return array<string, array{string, list<string>, list<string>}>
     */
    public static function translationFileProvider(): array
    {
        return [
            'XLIFF 1.2' => [
                '<?xml version="1.0" encoding="UTF-8"?>'
                . '<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">'
                . '<file source-language="en" target-language="de" datatype="plaintext" original="messages"><body>'
                . '<trans-unit id="c|t|approved" approved="yes"><source>A</source><target>A-de</target></trans-unit>'
                . '<trans-unit id="c|t|unmarked"><source>U</source><target>U-de</target></trans-unit>'
                . '<trans-unit id="c|t|draft" approved="no"><source>D</source><target>D-de</target></trans-unit>'
                . '<trans-unit id="c|t|untranslated"><source>N</source></trans-unit>'
                . '</body></file></xliff>',
                ['c|t|approved', 'c|t|unmarked', 'c|t|untranslated'],
                ['c|t|draft'],
            ],
            'XLIFF 2.0' => [
                '<?xml version="1.0" encoding="UTF-8"?>'
                . '<xliff version="2.0" xmlns="urn:oasis:names:tc:xliff:document:2.0" srcLang="en" trgLang="de">'
                . '<file id="messages">'
                . '<unit id="c|t|final"><segment state="final"><source>F</source><target>F-de</target></segment></unit>'
                . '<unit id="c|t|reviewed"><segment state="reviewed"><source>R</source><target>R-de</target></segment></unit>'
                . '<unit id="c|t|translated"><segment state="translated"><source>T</source><target>T-de</target></segment></unit>'
                . '<unit id="c|t|initial"><segment state="initial"><source>I</source><target>I-de</target></segment></unit>'
                . '</file></xliff>',
                ['c|t|final', 'c|t|reviewed'],
                ['c|t|translated', 'c|t|initial'],
            ],
        ];
    }

    /**
     * @param list<string> $expectedEntries
     * @param list<string> $expectedUnapproved
     */
    #[Test]
    #[DataProvider('translationFileProvider')]
    public function loadFileNamesTheUnitsTheLoaderLeftOutAsUnapproved(
        string $xliff,
        array $expectedEntries,
        array $expectedUnapproved,
    ): void {
        $GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations'] = true;

        $loadedFile = $this->subject->loadFile($xliff, 'de');

        self::assertSame($expectedEntries, array_keys($loadedFile->entries));
        self::assertSame($expectedUnapproved, $loadedFile->unapprovedKeys);
        self::assertTrue($GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations']);
    }

    #[Test]
    public function loadFileTreatsAMissingApprovalSettingAsEnabledAndLeavesItMissing(): void
    {
        unset($GLOBALS['TYPO3_CONF_VARS']['LANG']);

        $loadedFile = $this->subject->loadFile(self::translationFileProvider()['XLIFF 1.2'][0], 'de');

        self::assertSame(['c|t|draft'], $loadedFile->unapprovedKeys);
        self::assertArrayNotHasKey('LANG', $GLOBALS['TYPO3_CONF_VARS']);
    }

    #[Test]
    public function loadFileImportsUnapprovedUnitsWhenApprovalIsNotRequired(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations'] = false;

        $loadedFile = $this->subject->loadFile(self::translationFileProvider()['XLIFF 1.2'][0], 'de');

        self::assertSame([], $loadedFile->unapprovedKeys);
        self::assertArrayHasKey('c|t|draft', $loadedFile->entries);
        self::assertFalse($GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations']);
    }
}
