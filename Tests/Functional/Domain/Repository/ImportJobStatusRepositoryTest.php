<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Functional\Domain\Repository;

use Netresearch\NrTextdb\Domain\Repository\ImportJobStatusRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional test case for ImportJobStatusRepository.
 */
#[CoversClass(ImportJobStatusRepository::class)]
final class ImportJobStatusRepositoryTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'extensionmanager',
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'typo3conf/ext/nr_textdb',
    ];

    private ImportJobStatusRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new ImportJobStatusRepository();
    }

    #[Test]
    public function createJobInsertsRecordInDatabase(): void
    {
        $jobId            = 'test-job-' . time();
        $filePath         = '/tmp/test.xlf';
        $originalFilename = 'test.xlf';
        $fileSize         = 2048;
        $backendUserId    = 1;

        $uid = $this->subject->create($jobId, $filePath, $originalFilename, $fileSize, $backendUserId);

        self::assertGreaterThan(0, $uid);

        $job = $this->subject->findByJobId($jobId);
        self::assertIsArray($job);
        self::assertSame($jobId, $job['job_id']);
        self::assertSame($filePath, $job['file_path']);
        self::assertSame($originalFilename, $job['original_filename']);
        self::assertSame($fileSize, $job['file_size']);
        self::assertSame($backendUserId, $job['backend_user_id']);
        self::assertSame('pending', $job['status']);
        self::assertSame(0, $job['imported']);
        self::assertSame(0, $job['updated']);
    }

    #[Test]
    public function updateStatusModifiesExistingRecord(): void
    {
        $jobId = 'test-job-' . time();
        $this->subject->create($jobId, '/tmp/test.xlf', 'test.xlf', 1024, 1);

        $this->subject->updateStatus($jobId, 'processing');

        $job = $this->subject->findByJobId($jobId);
        self::assertIsArray($job);
        self::assertSame('processing', $job['status']);
        self::assertNull($job['errors']);

        $this->subject->updateStatus($jobId, 'completed', 'Some warning');

        $job = $this->subject->findByJobId($jobId);
        self::assertIsArray($job);
        self::assertSame('completed', $job['status']);
        self::assertSame('Some warning', $job['errors']);
    }

    #[Test]
    public function updateProgressModifiesCounters(): void
    {
        $jobId = 'test-job-' . time();
        $this->subject->create($jobId, '/tmp/test.xlf', 'test.xlf', 1024, 1);

        $this->subject->updateProgress($jobId, 150, 75);

        $job = $this->subject->findByJobId($jobId);
        self::assertIsArray($job);
        self::assertSame(150, $job['imported']);
        self::assertSame(75, $job['updated']);
    }

    #[Test]
    public function findByJobIdReturnsNullForNonExistentJob(): void
    {
        $result = $this->subject->findByJobId('non-existent-job');

        self::assertNull($result);
    }

    #[Test]
    public function getStatusReturnsJobData(): void
    {
        $jobId = 'test-job-' . time();
        $this->subject->create($jobId, '/tmp/test.xlf', 'test.xlf', 1024, 1);
        $this->subject->updateStatus($jobId, 'processing');
        $this->subject->updateProgress($jobId, 100, 50);

        $status = $this->subject->getStatus($jobId);

        self::assertIsArray($status);
        self::assertSame($jobId, $status['jobId']);
        self::assertSame('processing', $status['status']);
        self::assertSame(100, $status['imported']);
        self::assertSame(50, $status['updated']);
    }

    #[Test]
    public function getStatusReturnsNullForNonExistentJob(): void
    {
        $result = $this->subject->getStatus('non-existent-job');

        self::assertNull($result);
    }

    #[Test]
    public function deleteOldJobsRemovesExpiredRecords(): void
    {
        // Create job older than 7 days (simulate old timestamp)
        $oldJobId = 'old-job-' . time();
        $uid      = $this->subject->create($oldJobId, '/tmp/old.xlf', 'old.xlf', 1024, 1);

        // Mark as completed and manually update the completed_at timestamp to simulate old record
        $this->subject->updateStatus($oldJobId, 'completed');
        $this->getConnectionPool()
            ->getConnectionForTable('tx_nrtextdb_import_job_status')
            ->update(
                'tx_nrtextdb_import_job_status',
                ['completed_at' => time() - (8 * 24 * 60 * 60)], // 8 days ago
                ['uid'          => $uid]
            );

        // Create a recent job
        $recentJobId = 'recent-job-' . time();
        $this->subject->create($recentJobId, '/tmp/recent.xlf', 'recent.xlf', 1024, 1);

        $deletedCount = $this->subject->deleteOldJobs(7);

        self::assertSame(1, $deletedCount);

        // Old job should be deleted
        self::assertNull($this->subject->findByJobId($oldJobId));

        // Recent job should still exist
        self::assertIsArray($this->subject->findByJobId($recentJobId));
    }

    #[Test]
    public function multipleJobsCanBeCreatedAndQueried(): void
    {
        $jobId1 = 'test-job-1-' . time();
        $jobId2 = 'test-job-2-' . time();

        $this->subject->create($jobId1, '/tmp/test1.xlf', 'test1.xlf', 1024, 1);
        $this->subject->create($jobId2, '/tmp/test2.xlf', 'test2.xlf', 2048, 1);

        $this->subject->updateStatus($jobId1, 'completed');
        $this->subject->updateStatus($jobId2, 'processing');

        $job1 = $this->subject->findByJobId($jobId1);
        $job2 = $this->subject->findByJobId($jobId2);

        self::assertIsArray($job1);
        self::assertIsArray($job2);
        self::assertSame('completed', $job1['status']);
        self::assertSame('processing', $job2['status']);
        self::assertSame(1024, $job1['file_size']);
        self::assertSame(2048, $job2['file_size']);
    }
}
