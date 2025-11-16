<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * ImportJobStatusRepository - Lightweight DBAL-based repository for job tracking.
 *
 * This repository uses direct DBAL access instead of Extbase ORM for performance.
 * Manages import job status tracking for async Symfony Messenger processing.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class ImportJobStatusRepository
{
    private const TABLE_NAME = 'tx_nrtextdb_import_job_status';

    /**
     * Create a new import job record.
     *
     * @param string $jobId            Unique job identifier (UUID)
     * @param string $filePath         Path to uploaded XLIFF file
     * @param string $originalFilename Original filename from upload
     * @param int    $fileSize         File size in bytes
     * @param int    $backendUserId    Backend user who initiated import
     *
     * @return int The UID of the created record
     */
    public function create(
        string $jobId,
        string $filePath,
        string $originalFilename,
        int $fileSize,
        int $backendUserId,
    ): int {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(self::TABLE_NAME);

        $connection->insert(
            self::TABLE_NAME,
            [
                'job_id'            => $jobId,
                'status'            => 'pending',
                'file_path'         => $filePath,
                'original_filename' => $originalFilename,
                'file_size'         => $fileSize,
                'backend_user_id'   => $backendUserId,
                'created_at'        => time(),
            ]
        );

        return (int) $connection->lastInsertId();
    }

    /**
     * Update job status.
     *
     * @param string      $jobId  Job identifier
     * @param string      $status New status (pending, processing, completed, failed)
     * @param string|null $errors Optional error messages for failed jobs
     */
    public function updateStatus(string $jobId, string $status, ?string $errors = null): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(self::TABLE_NAME);

        $data = ['status' => $status];

        // Set appropriate timestamps based on status
        if ($status === 'processing') {
            $data['started_at'] = time();
        } elseif (in_array($status, ['completed', 'failed'], true)) {
            $data['completed_at'] = time();
        }

        if ($errors !== null) {
            $data['errors'] = $errors;
        }

        $connection->update(
            self::TABLE_NAME,
            $data,
            ['job_id' => $jobId]
        );
    }

    /**
     * Update import progress counters.
     *
     * @param string $jobId    Job identifier
     * @param int    $imported Number of records imported
     * @param int    $updated  Number of records updated
     */
    public function updateProgress(string $jobId, int $imported, int $updated): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(self::TABLE_NAME);

        $connection->update(
            self::TABLE_NAME,
            [
                'imported' => $imported,
                'updated'  => $updated,
            ],
            ['job_id' => $jobId]
        );
    }

    /**
     * Get job status by job ID.
     *
     * @param string $jobId Job identifier
     *
     * @return array<string, mixed>|null Job data or null if not found
     */
    public function findByJobId(string $jobId): ?array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::TABLE_NAME);

        $result = $queryBuilder
            ->select('*')
            ->from(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->eq(
                    'job_id',
                    $queryBuilder->createNamedParameter($jobId)
                )
            )
            ->executeQuery()
            ->fetchAssociative();

        return $result !== false ? $result : null;
    }

    /**
     * Get job status for AJAX API.
     *
     * Returns formatted status information for frontend polling.
     *
     * @param string $jobId Job identifier
     *
     * @return array<string, mixed>|null Formatted status data or null if not found
     */
    public function getStatus(string $jobId): ?array
    {
        $job = $this->findByJobId($jobId);

        if ($job === null) {
            return null;
        }

        return [
            'jobId'            => $job['job_id'],
            'status'           => $job['status'],
            'originalFilename' => $job['original_filename'],
            'fileSize'         => (int) ($job['file_size'] ?? 0),
            'imported'         => (int) ($job['imported'] ?? 0),
            'updated'          => (int) ($job['updated'] ?? 0),
            'errors'           => $job['errors'],
            'createdAt'        => (int) ($job['created_at'] ?? 0),
            'startedAt'        => (int) ($job['started_at'] ?? 0),
            'completedAt'      => (int) ($job['completed_at'] ?? 0),
        ];
    }

    /**
     * Delete old completed/failed jobs older than specified days.
     *
     * Deletes in batches to prevent table locks and database overload.
     * Should be called repeatedly until it returns 0 for complete cleanup.
     *
     * @param int $daysOld   Number of days to keep completed jobs (default: 7)
     * @param int $batchSize Maximum records to delete per call (default: 1000)
     *
     * @return int Number of deleted records
     */
    public function deleteOldJobs(int $daysOld = 7, int $batchSize = 1000): int
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable(self::TABLE_NAME);

        $timestamp = time() - ($daysOld * 86400);

        return $queryBuilder
            ->delete(self::TABLE_NAME)
            ->where(
                $queryBuilder->expr()->lt(
                    'completed_at',
                    $queryBuilder->createNamedParameter($timestamp, Connection::PARAM_INT)
                ),
                $queryBuilder->expr()->in(
                    'status',
                    $queryBuilder->createNamedParameter(
                        ['completed', 'failed'],
                        Connection::PARAM_STR_ARRAY
                    )
                )
            )
            ->setMaxResults($batchSize)
            ->executeStatement();
    }
}
