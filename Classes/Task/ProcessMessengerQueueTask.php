<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Task;

use Exception;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Scheduler task to process Symfony Messenger queue messages.
 *
 * This task allows running the Messenger consumer via TYPO3 Scheduler instead of
 * requiring systemd/supervisor. It runs messenger:consume with a time limit to
 * process queued messages in batches.
 *
 * Setup: Add this task to Scheduler with frequency (e.g., every 5 minutes).
 * The task will process messages for a limited time (default 2 minutes) then exit,
 * allowing the next Scheduler run to pick up where it left off.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class ProcessMessengerQueueTask extends AbstractTask implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Time limit in seconds for message processing.
     * Should be LESS than the Scheduler frequency to avoid overlapping runs.
     */
    public int $timeLimit = 120; // 2 minutes

    /**
     * Transport name to consume from (default: doctrine).
     */
    public string $transport = 'doctrine';

    /**
     * Execute the Messenger consumer.
     *
     * This method is called by the TYPO3 Scheduler when the task runs.
     * It spawns messenger:consume as a subprocess with time limit.
     *
     * @return bool TRUE on success, FALSE on failure
     */
    public function execute(): bool
    {
        $vendorBin = GeneralUtility::getFileAbsFileName('typo3conf/../vendor/bin/typo3');

        if (!file_exists($vendorBin)) {
            $this->logError('TYPO3 CLI binary not found at: ' . $vendorBin);

            return false;
        }

        // Build command: messenger:consume <transport> --time-limit=<seconds>
        $command = [
            PHP_BINARY,
            $vendorBin,
            'messenger:consume',
            $this->transport,
            '--time-limit=' . $this->timeLimit,
            '--quiet', // Suppress output
        ];

        try {
            $process = new Process($command);
            $process->setTimeout($this->timeLimit + 30); // Allow 30s buffer
            $process->run();

            if (!$process->isSuccessful()) {
                $this->logError('Messenger consumer failed: ' . $process->getErrorOutput());

                return false;
            }

            // Success - messages processed (or none were pending)
            return true;
        } catch (Exception $e) {
            $this->logError('Exception during message processing: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Log error message.
     */
    private function logError(string $message): void
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->error($message);
        }
    }

    /**
     * Get additional information for task display in backend.
     */
    public function getAdditionalInformation(): string
    {
        return sprintf(
            'Transport: %s | Time limit: %d seconds',
            $this->transport,
            $this->timeLimit
        );
    }
}
