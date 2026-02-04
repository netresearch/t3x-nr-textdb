<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Unit\Queue\Message;

use Netresearch\NrTextdb\Queue\Message\ImportTranslationsMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case for ImportTranslationsMessage.
 */
#[CoversClass(ImportTranslationsMessage::class)]
final class ImportTranslationsMessageTest extends UnitTestCase
{
    #[Test]
    public function constructorSetsJobId(): void
    {
        $jobId   = 'test-job-123';
        $message = new ImportTranslationsMessage($jobId, '/path/to/file.xlf', 'original.xlf', 1024, false, 1);

        self::assertSame($jobId, $message->jobId);
    }

    #[Test]
    public function constructorSetsFilePath(): void
    {
        $filePath = '/var/tmp/upload_abc123.xlf';
        $message  = new ImportTranslationsMessage('job-1', $filePath, 'test.xlf', 2048, false, 1);

        self::assertSame($filePath, $message->filePath);
    }

    #[Test]
    public function constructorSetsOriginalFilename(): void
    {
        $originalFilename = 'my-translations.xlf';
        $message          = new ImportTranslationsMessage('job-1', '/tmp/file.xlf', $originalFilename, 1024, false, 1);

        self::assertSame($originalFilename, $message->originalFilename);
    }

    #[Test]
    public function constructorSetsFileSize(): void
    {
        $message = new ImportTranslationsMessage('job-1', '/tmp/file.xlf', 'test.xlf', 4096, false, 1);

        self::assertSame(4096, $message->fileSize);
    }

    #[Test]
    public function constructorSetsForceUpdate(): void
    {
        $messageTrue  = new ImportTranslationsMessage('job-1', '/tmp/file.xlf', 'test.xlf', 1024, true, 1);
        $messageFalse = new ImportTranslationsMessage('job-2', '/tmp/file.xlf', 'test.xlf', 1024, false, 1);

        self::assertTrue($messageTrue->forceUpdate);
        self::assertFalse($messageFalse->forceUpdate);
    }

    #[Test]
    public function constructorSetsBackendUserId(): void
    {
        $message = new ImportTranslationsMessage('job-1', '/tmp/file.xlf', 'test.xlf', 1024, false, 42);

        self::assertSame(42, $message->backendUserId);
    }

    #[Test]
    public function messageIsSerializable(): void
    {
        $message = new ImportTranslationsMessage('job-123', '/tmp/test.xlf', 'original.xlf', 2048, true, 5);

        $serialized   = serialize($message);
        $unserialized = unserialize($serialized);

        self::assertEquals($message, $unserialized);
        self::assertSame('job-123', $unserialized->jobId);
        self::assertSame('/tmp/test.xlf', $unserialized->filePath);
        self::assertSame('original.xlf', $unserialized->originalFilename);
        self::assertSame(2048, $unserialized->fileSize);
        self::assertTrue($unserialized->forceUpdate);
        self::assertSame(5, $unserialized->backendUserId);
    }

    #[Test]
    public function messageHandlesSpecialCharactersInFilename(): void
    {
        $filename = 'Übersetzungen-€-2024.xlf';
        $message  = new ImportTranslationsMessage('job-1', '/tmp/file.xlf', $filename, 1024, false, 1);

        self::assertSame($filename, $message->originalFilename);
    }

    #[Test]
    public function messageHandlesLongJobIds(): void
    {
        $longJobId = str_repeat('a', 255);
        $message   = new ImportTranslationsMessage($longJobId, '/tmp/file.xlf', 'test.xlf', 1024, false, 1);

        self::assertSame($longJobId, $message->jobId);
    }
}
