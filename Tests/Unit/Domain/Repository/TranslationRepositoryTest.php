<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Unit\Domain\Repository;

use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\Stub;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\AndInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\ComparisonInterface;
use TYPO3\CMS\Extbase\Persistence\Generic\QuerySettingsInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The export reads its records in batches and relies on the order the
 * queries set: without it the database chooses, and SQLite and PostgreSQL
 * happen to return the same order through their indexes, so the functional
 * export test cannot tell the difference.
 */
#[CoversClass(TranslationRepository::class)]
final class TranslationRepositoryTest extends UnitTestCase
{
    /**
     * @var QueryInterface<object>&Stub
     */
    private QueryInterface&Stub $query;

    /**
     * @var list<array{string, mixed}>
     */
    private array $greaterThan = [];

    /**
     * @var list<array<mixed>>
     */
    private array $orderings = [];

    private ?int $limit = null;

    private TranslationRepository $subject;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $querySettings = self::createStub(QuerySettingsInterface::class);
        $querySettings->method('setIgnoreEnableFields')->willReturnSelf();
        $querySettings->method('setRespectStoragePage')->willReturnSelf();
        $querySettings->method('setRespectSysLanguage')->willReturnSelf();
        $querySettings->method('setLanguageAspect')->willReturnSelf();

        $this->query = self::createStub(QueryInterface::class);
        $this->query->method('getQuerySettings')->willReturn($querySettings);
        $this->query->method('matching')->willReturnSelf();
        $comparison = self::createStub(ComparisonInterface::class);
        $this->query->method('equals')->willReturn($comparison);
        $this->query->method('in')->willReturn($comparison);
        $this->query->method('like')->willReturn($comparison);
        $this->query->method('execute')->willReturn(self::createStub(QueryResultInterface::class));
        $this->query->method('greaterThan')->willReturnCallback(
            function (string $property, mixed $operand) use ($comparison): ComparisonInterface {
                $this->greaterThan[] = [$property, $operand];

                return $comparison;
            },
        );
        $this->query->method('logicalAnd')->willReturn(self::createStub(AndInterface::class));
        $this->query->method('setOrderings')->willReturnCallback(
            function (array $orderings): QueryInterface {
                $this->orderings[] = $orderings;

                return $this->query;
            },
        );
        $this->query->method('setLimit')->willReturnCallback(
            function (int $limit): QueryInterface {
                $this->limit = $limit;

                return $this->query;
            },
        );

        $persistenceManager = self::createStub(PersistenceManagerInterface::class);
        $persistenceManager->method('createQueryForType')->willReturn($this->query);

        $this->subject = new TranslationRepository();
        $this->subject->injectPersistenceManager($persistenceManager);
    }

    #[Test]
    public function findByTranslationsAndLanguageOrdersByOriginalAndUid(): void
    {
        $this->subject->findByTranslationsAndLanguage([3, 1, 2], 1);

        self::assertSame(
            [[
                'l10nParent' => QueryInterface::ORDER_ASCENDING,
                'uid'        => QueryInterface::ORDER_ASCENDING,
            ]],
            $this->orderings,
        );
    }

    #[Test]
    public function aLimitedFindAllOrdersByUidAndContinuesAfterTheGivenUid(): void
    {
        $this->subject->findAllByComponentTypePlaceholderValueAndLanguage(1, 0, null, null, 0, null, 500, 41);

        self::assertSame([['uid', 41]], $this->greaterThan);
        self::assertSame([['uid' => QueryInterface::ORDER_ASCENDING]], $this->orderings);
        self::assertSame(500, $this->limit);
    }

    #[Test]
    public function anUnlimitedFindAllSetsNoOrderAndNoLimit(): void
    {
        // The list view pages through this result itself.
        $this->subject->findAllByComponentTypePlaceholderValueAndLanguage(1);

        self::assertSame([], $this->greaterThan);
        self::assertSame([], $this->orderings);
        self::assertNull($this->limit);
    }
}
