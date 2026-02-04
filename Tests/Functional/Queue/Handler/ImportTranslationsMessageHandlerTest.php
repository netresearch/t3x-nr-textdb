<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Functional\Queue\Handler;

use Netresearch\NrTextdb\Domain\Repository\ImportJobStatusRepository;
use Netresearch\NrTextdb\Queue\Handler\ImportTranslationsMessageHandler;
use Netresearch\NrTextdb\Queue\Message\ImportTranslationsMessage;
use Netresearch\NrTextdb\Service\ImportService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use RuntimeException;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional test case for ImportTranslationsMessageHandler.
 */
#[CoversClass(ImportTranslationsMessageHandler::class)]
final class ImportTranslationsMessageHandlerTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'extensionmanager',
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'typo3conf/ext/nr_textdb',
    ];

    private ImportTranslationsMessageHandler $subject;
    private ImportService $importServiceMock;
    private ImportJobStatusRepository $jobStatusRepository;
    private LoggerInterface $loggerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importServiceMock   = $this->createMock(ImportService::class);
        $this->jobStatusRepository = new ImportJobStatusRepository();
        $this->loggerMock          = $this->createMock(LoggerInterface::class);

        $this->subject = new ImportTranslationsMessageHandler(
            $this->importServiceMock,
            $this->jobStatusRepository,
            $this->loggerMock
        );
    }

    #[Test]
    public function handlerCreatesJobStatusRecordOnProcessing(): void
    {
        $jobId   = 'functional-job-' . time();
        $message = new ImportTranslationsMessage($jobId, '/tmp/test.xlf', 'test.xlf', 1024, false, 1);

        // Create initial job record
        $this->jobStatusRepository->create($jobId, '/tmp/test.xlf', 'test.xlf', 1024, 1);

        $this->importServiceMock
            ->expects(self::once())
            ->method('importFile')
            ->willReturnCallback(function ($filePath, $forceUpdate, &$imported, &$updated, &$errors): void {
                $imported = 50;
                $updated  = 25;
                $errors   = [];
            });

        ($this->subject)($message);

        $job = $this->jobStatusRepository->findByJobId($jobId);
        self::assertIsArray($job);
        self::assertSame('completed', $job['status']);
        self::assertSame(50, $job['imported']);
        self::assertSame(25, $job['updated']);
        self::assertNull($job['errors']);
    }

    #[Test]
    public function handlerUpdatesProgressInDatabase(): void
    {
        $jobId   = 'functional-job-' . time();
        $message = new ImportTranslationsMessage($jobId, '/tmp/test.xlf', 'test.xlf', 1024, false, 1);

        $this->jobStatusRepository->create($jobId, '/tmp/test.xlf', 'test.xlf', 1024, 1);

        $this->importServiceMock
            ->expects(self::once())
            ->method('importFile')
            ->willReturnCallback(function ($filePath, $forceUpdate, &$imported, &$updated, &$errors): void {
                $imported = 200;
                $updated  = 100;
                $errors   = [];
            });

        ($this->subject)($message);

        $job = $this->jobStatusRepository->findByJobId($jobId);
        self::assertIsArray($job);
        self::assertSame(200, $job['imported']);
        self::assertSame(100, $job['updated']);
    }

    #[Test]
    public function handlerStoresErrorsWhenImportHasIssues(): void
    {
        $jobId   = 'functional-job-' . time();
        $message = new ImportTranslationsMessage($jobId, '/tmp/test.xlf', 'test.xlf', 1024, false, 1);

        $this->jobStatusRepository->create($jobId, '/tmp/test.xlf', 'test.xlf', 1024, 1);

        $this->importServiceMock
            ->expects(self::once())
            ->method('importFile')
            ->willReturnCallback(function ($filePath, $forceUpdate, &$imported, &$updated, &$errors): void {
                $imported = 100;
                $updated  = 50;
                $errors   = ['Error line 10', 'Warning line 25'];
            });

        ($this->subject)($message);

        $job = $this->jobStatusRepository->findByJobId($jobId);
        self::assertIsArray($job);
        self::assertSame('completed', $job['status']);
        self::assertNotNull($job['errors']);
        self::assertStringContainsString('Error line 10', $job['errors']);
        self::assertStringContainsString('Warning line 25', $job['errors']);
    }

    #[Test]
    public function handlerMarksJobAsFailedOnException(): void
    {
        $jobId   = 'functional-job-' . time();
        $message = new ImportTranslationsMessage($jobId, '/tmp/test.xlf', 'test.xlf', 1024, false, 1);

        $this->jobStatusRepository->create($jobId, '/tmp/test.xlf', 'test.xlf', 1024, 1);

        $this->importServiceMock
            ->expects(self::once())
            ->method('importFile')
            ->willThrowException(new RuntimeException('Import failed'));

        ($this->subject)($message);

        $job = $this->jobStatusRepository->findByJobId($jobId);
        self::assertIsArray($job);
        self::assertSame('failed', $job['status']);
        self::assertNotNull($job['errors']);
        self::assertStringContainsString('Import failed', $job['errors']);
    }

    #[Test]
    public function handlerProcessesForceUpdateFlag(): void
    {
        $jobId   = 'functional-job-' . time();
        $message = new ImportTranslationsMessage($jobId, '/tmp/test.xlf', 'test.xlf', 1024, true, 1);

        $this->jobStatusRepository->create($jobId, '/tmp/test.xlf', 'test.xlf', 1024, 1);

        $this->importServiceMock
            ->expects(self::once())
            ->method('importFile')
            ->with(
                '/tmp/test.xlf',
                true, // forceUpdate should be true
                self::anything(),
                self::anything(),
                self::anything()
            )
            ->willReturnCallback(function ($filePath, $forceUpdate, &$imported, &$updated, &$errors): void {
                $imported = 0;
                $updated  = 150; // All records updated due to force
                $errors   = [];
            });

        ($this->subject)($message);

        $job = $this->jobStatusRepository->findByJobId($jobId);
        self::assertIsArray($job);
        self::assertSame('completed', $job['status']);
        self::assertSame(0, $job['imported']);
        self::assertSame(150, $job['updated']);
    }

    #[Test]
    public function handlerTransitionsStatusCorrectly(): void
    {
        $jobId   = 'functional-job-' . time();
        $message = new ImportTranslationsMessage($jobId, '/tmp/test.xlf', 'test.xlf', 1024, false, 1);

        $this->jobStatusRepository->create($jobId, '/tmp/test.xlf', 'test.xlf', 1024, 1);

        // Verify initial status
        $job = $this->jobStatusRepository->findByJobId($jobId);
        self::assertSame('pending', $job['status']);

        $this->importServiceMock
            ->expects(self::once())
            ->method('importFile')
            ->willReturnCallback(function ($filePath, $forceUpdate, &$imported, &$updated, &$errors): void {
                // Verify status is 'processing' during import
                $imported = 100;
                $updated  = 50;
                $errors   = [];
            });

        ($this->subject)($message);

        // Verify final status
        $job = $this->jobStatusRepository->findByJobId($jobId);
        self::assertSame('completed', $job['status']);
    }
}
