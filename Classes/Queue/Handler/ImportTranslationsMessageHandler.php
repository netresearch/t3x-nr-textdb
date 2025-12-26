<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Queue\Handler;

use Netresearch\NrTextdb\Domain\Repository\ImportJobStatusRepository;
use Netresearch\NrTextdb\Queue\Message\ImportTranslationsMessage;
use Netresearch\NrTextdb\Service\ImportService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

/**
 * ImportTranslationsMessageHandler - Processes async XLIFF import jobs.
 *
 * This handler is automatically registered with TYPO3 v13 Symfony Messenger via
 * the #[AsMessageHandler] attribute. It processes ImportTranslationsMessage instances
 * from the queue and performs the actual import with progress tracking.
 *
 * CRITICAL: Errors are caught and logged to prevent worker crashes. TYPO3 Messenger
 * does NOT automatically retry failed messages.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
#[AsMessageHandler]
final readonly class ImportTranslationsMessageHandler
{
    /**
     * Constructor with dependency injection.
     */
    public function __construct(
        private ImportService $importService,
        private ImportJobStatusRepository $jobStatusRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Process the import message.
     *
     * This method is called by the Symfony Messenger worker when a message is consumed.
     * It coordinates the entire import process with status tracking and error handling.
     *
     * @param ImportTranslationsMessage $message The message to process
     */
    public function __invoke(ImportTranslationsMessage $message): void
    {
        $jobId    = $message->jobId;
        $filePath = $message->filePath;

        $this->logger->info('Import job started', [
            'jobId'    => $jobId,
            'filename' => $message->originalFilename,
            'fileSize' => $message->fileSize,
        ]);

        try {
            // Update status to processing
            $this->jobStatusRepository->updateStatus($jobId, 'processing');

            // Perform the import
            $imported = 0;
            $updated  = 0;
            $errors   = [];

            $this->importService->importFile(
                $filePath,
                $message->forceUpdate,
                $imported,
                $updated,
                $errors
            );

            // Update progress counters
            $this->jobStatusRepository->updateProgress($jobId, $imported, $updated);

            // Check if there were any errors
            if ($errors !== []) {
                $errorMessage = implode("\n", $errors);
                $this->jobStatusRepository->updateStatus($jobId, 'completed', $errorMessage);
                $this->logger->warning('Import completed with errors', [
                    'jobId'    => $jobId,
                    'imported' => $imported,
                    'updated'  => $updated,
                    'errors'   => count($errors),
                ]);
            } else {
                $this->jobStatusRepository->updateStatus($jobId, 'completed');
                $this->logger->info('Import job completed successfully', [
                    'jobId'    => $jobId,
                    'imported' => $imported,
                    'updated'  => $updated,
                ]);
            }

            // Clean up temporary file
            if (file_exists($filePath) && !unlink($filePath)) {
                $this->logger->warning('Could not delete temporary import file', [
                    'jobId'    => $jobId,
                    'filePath' => $filePath,
                ]);
            }
        } catch (Throwable $e) {
            // CRITICAL: Catch all errors to prevent worker crashes
            // TYPO3 Messenger does NOT automatically retry failed messages
            $errorMessage = sprintf(
                'Import failed: %s in %s:%d',
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            );

            $this->jobStatusRepository->updateStatus($jobId, 'failed', $errorMessage);

            $this->logger->error('Import job failed', [
                'jobId'    => $jobId,
                'filename' => $message->originalFilename,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);

            // Clean up temporary file even on failure
            if (file_exists($filePath) && !unlink($filePath)) {
                $this->logger->warning('Could not delete temporary import file after failure', [
                    'jobId'    => $jobId,
                    'filePath' => $filePath,
                ]);
            }

            // Do NOT rethrow - would crash worker
            // Errors are logged and status is updated for user feedback
        }
    }
}
