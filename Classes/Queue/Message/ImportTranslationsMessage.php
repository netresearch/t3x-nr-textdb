<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Queue\Message;

/**
 * ImportTranslationsMessage - Symfony Messenger message for async import processing.
 *
 * Simple DTO (Data Transfer Object) containing all data needed for async XLIFF import.
 * This message is dispatched to the Symfony Messenger queue and consumed by
 * ImportTranslationsMessageHandler in a background worker.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
final readonly class ImportTranslationsMessage
{
    /**
     * @param string $jobId            Unique job identifier (UUID)
     * @param string $filePath         Absolute path to uploaded XLIFF file
     * @param string $originalFilename Original filename from upload
     * @param int    $fileSize         File size in bytes
     * @param bool   $forceUpdate      Whether to force update existing translations
     * @param int    $backendUserId    Backend user who initiated the import
     */
    public function __construct(
        public string $jobId,
        public string $filePath,
        public string $originalFilename,
        public int $fileSize,
        public bool $forceUpdate,
        public int $backendUserId,
    ) {
    }
}
