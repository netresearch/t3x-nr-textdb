<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Unit\Domain\Repository;

use Netresearch\NrTextdb\Domain\Repository\ImportJobStatusRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case for ImportJobStatusRepository.
 */
#[CoversClass(ImportJobStatusRepository::class)]
final class ImportJobStatusRepositoryTest extends UnitTestCase
{
    private ImportJobStatusRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        // Repository uses GeneralUtility::makeInstance which needs functional test context
        // These tests verify the repository is properly structured but cannot test
        // actual database operations without functional test infrastructure
        $this->subject = new ImportJobStatusRepository();
    }

    #[Test]
    public function repositoryCanBeInstantiated(): void
    {
        self::assertInstanceOf(ImportJobStatusRepository::class, $this->subject);
    }

    #[Test]
    public function repositoryHasRequiredPublicMethods(): void
    {
        self::assertTrue(method_exists($this->subject, 'create'));
        self::assertTrue(method_exists($this->subject, 'updateStatus'));
        self::assertTrue(method_exists($this->subject, 'updateProgress'));
        self::assertTrue(method_exists($this->subject, 'findByJobId'));
        self::assertTrue(method_exists($this->subject, 'getStatus'));
        self::assertTrue(method_exists($this->subject, 'deleteOldJobs'));
    }

    #[Test]
    public function createMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionMethod($this->subject, 'create');

        self::assertSame(5, $reflection->getNumberOfRequiredParameters());
        self::assertTrue($reflection->hasReturnType());
        self::assertSame('int', (string) $reflection->getReturnType());
    }

    #[Test]
    public function updateStatusMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionMethod($this->subject, 'updateStatus');

        self::assertSame(2, $reflection->getNumberOfRequiredParameters());
        self::assertTrue($reflection->hasReturnType());
        self::assertSame('void', (string) $reflection->getReturnType());
    }

    #[Test]
    public function updateProgressMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionMethod($this->subject, 'updateProgress');

        self::assertSame(3, $reflection->getNumberOfRequiredParameters());
        self::assertTrue($reflection->hasReturnType());
        self::assertSame('void', (string) $reflection->getReturnType());
    }

    #[Test]
    public function findByJobIdMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionMethod($this->subject, 'findByJobId');

        self::assertSame(1, $reflection->getNumberOfRequiredParameters());
        self::assertTrue($reflection->hasReturnType());
        self::assertSame('?array', (string) $reflection->getReturnType());
    }

    #[Test]
    public function getStatusMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionMethod($this->subject, 'getStatus');

        self::assertSame(1, $reflection->getNumberOfRequiredParameters());
        self::assertTrue($reflection->hasReturnType());
        self::assertSame('?array', (string) $reflection->getReturnType());
    }

    #[Test]
    public function deleteOldJobsMethodHasCorrectSignature(): void
    {
        $reflection = new ReflectionMethod($this->subject, 'deleteOldJobs');

        self::assertSame(0, $reflection->getNumberOfRequiredParameters());
        self::assertTrue($reflection->hasReturnType());
        self::assertSame('int', (string) $reflection->getReturnType());
    }
}
