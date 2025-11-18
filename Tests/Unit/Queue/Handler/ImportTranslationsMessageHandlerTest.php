<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Unit\Queue\Handler;

use Netresearch\NrTextdb\Domain\Repository\ImportJobStatusRepository;
use Netresearch\NrTextdb\Queue\Handler\ImportTranslationsMessageHandler;
use Netresearch\NrTextdb\Queue\Message\ImportTranslationsMessage;
use Netresearch\NrTextdb\Service\ImportService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use RuntimeException;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case for ImportTranslationsMessageHandler.
 */
#[CoversClass(ImportTranslationsMessageHandler::class)]
final class ImportTranslationsMessageHandlerTest extends UnitTestCase
{
    private ImportTranslationsMessageHandler $subject;
    private ImportService $importServiceMock;
    private ImportJobStatusRepository $jobStatusRepositoryMock;
    private LoggerInterface $loggerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importServiceMock       = $this->createMock(ImportService::class);
        $this->jobStatusRepositoryMock = $this->createMock(ImportJobStatusRepository::class);
        $this->loggerMock              = $this->createMock(LoggerInterface::class);

        $this->subject = new ImportTranslationsMessageHandler(
            $this->importServiceMock,
            $this->jobStatusRepositoryMock,
            $this->loggerMock
        );
    }

    #[Test]
    public function successfulImportUpdatesStatusToCompleted(): void
    {
        $message = new ImportTranslationsMessage('job-123', '/tmp/test.xlf', 'test.xlf', 1024, false, 1);

        $this->jobStatusRepositoryMock
            ->expects(self::exactly(2))
            ->method('updateStatus')
            ->willReturnCallback(function (string $jobId, string $status): void {
                static $callCount = 0;
                ++$callCount;

                if ($callCount === 1) {
                    self::assertSame('job-123', $jobId);
                    self::assertSame('processing', $status);
                } elseif ($callCount === 2) {
                    self::assertSame('job-123', $jobId);
                    self::assertSame('completed', $status);
                }
            });

        $this->jobStatusRepositoryMock
            ->expects(self::once())
            ->method('updateProgress')
            ->with('job-123', 100, 50);

        $this->importServiceMock
            ->expects(self::once())
            ->method('importFile')
            ->willReturnCallback(function ($filePath, $forceUpdate, &$imported, &$updated, &$errors): void {
                $imported = 100;
                $updated  = 50;
                $errors   = [];
            });

        $this->loggerMock
            ->expects(self::exactly(2))
            ->method('info');

        ($this->subject)($message);
    }

    #[Test]
    public function importWithErrorsUpdatesStatusToCompletedWithErrors(): void
    {
        $message = new ImportTranslationsMessage('job-123', '/tmp/test.xlf', 'test.xlf', 1024, false, 1);

        $this->jobStatusRepositoryMock
            ->expects(self::exactly(2))
            ->method('updateStatus')
            ->willReturnCallback(function (string $jobId, string $status, ?string $errorMessage = null): void {
                static $callCount = 0;
                ++$callCount;

                if ($callCount === 1) {
                    self::assertSame('processing', $status);
                } elseif ($callCount === 2) {
                    self::assertSame('completed', $status);
                    self::assertNotEmpty($errorMessage);
                    self::assertStringContainsString('Line 1 error', $errorMessage);
                }
            });

        $this->importServiceMock
            ->expects(self::once())
            ->method('importFile')
            ->willReturnCallback(function ($filePath, $forceUpdate, &$imported, &$updated, &$errors): void {
                $imported = 80;
                $updated  = 40;
                $errors   = ['Line 1 error', 'Line 2 error'];
            });

        $this->loggerMock
            ->expects(self::once())
            ->method('warning');

        ($this->subject)($message);
    }

    #[Test]
    public function importFailureUpdatesStatusToFailed(): void
    {
        $message = new ImportTranslationsMessage('job-123', '/tmp/test.xlf', 'test.xlf', 1024, false, 1);

        $this->jobStatusRepositoryMock
            ->expects(self::exactly(2))
            ->method('updateStatus')
            ->willReturnCallback(function (string $jobId, string $status, ?string $errorMessage = null): void {
                static $callCount = 0;
                ++$callCount;

                if ($callCount === 1) {
                    self::assertSame('processing', $status);
                } elseif ($callCount === 2) {
                    self::assertSame('failed', $status);
                    self::assertNotEmpty($errorMessage);
                    self::assertStringContainsString('File not found', $errorMessage);
                }
            });

        $this->importServiceMock
            ->expects(self::once())
            ->method('importFile')
            ->willThrowException(new RuntimeException('File not found'));

        $this->loggerMock
            ->expects(self::once())
            ->method('error');

        // Should NOT throw exception (caught internally)
        ($this->subject)($message);

        self::assertTrue(true); // Assert we reached here
    }

    #[Test]
    public function handlerLogsImportStart(): void
    {
        $message = new ImportTranslationsMessage('job-123', '/tmp/test.xlf', 'original.xlf', 2048, false, 1);

        $this->loggerMock
            ->expects(self::exactly(2))
            ->method('info')
            ->willReturnCallback(function (string $message, array $context): void {
                static $callCount = 0;
                ++$callCount;

                if ($callCount === 1) {
                    self::assertSame('Import job started', $message);
                    self::assertSame('job-123', $context['jobId']);
                    self::assertSame('original.xlf', $context['filename']);
                    self::assertSame(2048, $context['fileSize']);
                }
            });

        $this->importServiceMock
            ->method('importFile')
            ->willReturnCallback(function ($filePath, $forceUpdate, &$imported, &$updated, &$errors): void {
                $imported = 0;
                $updated  = 0;
                $errors   = [];
            });

        ($this->subject)($message);
    }

    #[Test]
    public function handlerPassesForceUpdateToImportService(): void
    {
        $message = new ImportTranslationsMessage('job-123', '/tmp/test.xlf', 'test.xlf', 1024, true, 1);

        $this->importServiceMock
            ->expects(self::once())
            ->method('importFile')
            ->with(
                '/tmp/test.xlf',
                true, // forceUpdate should be true
                self::anything(),
                self::anything(),
                self::anything()
            );

        ($this->subject)($message);
    }
}
