<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Functional\Service;

use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use Netresearch\NrTextdb\Service\ImportService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\Parser\XliffParser;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Functional test case for ImportService bulk operations.
 *
 * Tests the critical batching logic that processes >1000 records
 * to ensure array_chunk and batch iteration work correctly.
 */
#[CoversClass(ImportService::class)]
final class ImportServiceTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'extensionmanager',
        'scheduler',
    ];

    protected array $testExtensionsToLoad = [
        'typo3conf/ext/nr_textdb',
    ];

    private ImportService $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new ImportService(
            $this->get(PersistenceManager::class),
            $this->get(XliffParser::class),
            $this->get(ComponentRepository::class),
            $this->get(TypeRepository::class),
            $this->get(EnvironmentRepository::class)
        );
    }

    /**
     * Tests bulk import with >1000 records to validate batching logic.
     *
     * The ImportService uses array_chunk($data, 1000) for both INSERT and UPDATE operations.
     * This test ensures the batching logic correctly handles multiple batches by importing
     * 1500 records, which should trigger 2 batches (1000 + 500).
     */
    #[Test]
    public function importLargeFileValidatesBatchingLogic(): void
    {
        $tempFile = $this->generateLargeXliffFile(1500);

        try {
            $imported = 0;
            $updated  = 0;
            $errors   = [];

            $this->subject->importFile($tempFile, false, $imported, $updated, $errors);

            // Verify all records were imported
            self::assertSame(1500, $imported, 'All 1500 records should be imported');
            self::assertSame(0, $updated, 'No records should be updated on first import');
            self::assertSame([], $errors, 'No errors should occur during import');

            // Verify records exist in database
            $connection = $this->getConnectionPool()->getConnectionForTable('tx_nrtextdb_domain_model_translation');
            $count      = $connection->count('*', 'tx_nrtextdb_domain_model_translation', []);

            self::assertSame(1500, $count, 'Database should contain exactly 1500 translation records');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Tests bulk UPDATE with >1000 records to validate UPDATE batching logic.
     *
     * Tests the CASE expression batching for UPDATE operations by:
     * 1. Importing 1500 records
     * 2. Re-importing with forceUpdate=true
     * 3. Verifying all 1500 records are updated across multiple batches
     */
    #[Test]
    public function updateLargeFileValidatesBatchingLogic(): void
    {
        $tempFile = $this->generateLargeXliffFile(1500);

        try {
            // First import
            $imported = 0;
            $updated  = 0;
            $errors   = [];

            $this->subject->importFile($tempFile, false, $imported, $updated, $errors);
            self::assertSame(1500, $imported);

            // Second import with forceUpdate=true to trigger UPDATE batching
            $imported = 0;
            $updated  = 0;
            $errors   = [];

            $this->subject->importFile($tempFile, true, $imported, $updated, $errors);

            // Verify all records were updated via batched CASE expression
            self::assertSame(0, $imported, 'No new records should be imported');
            self::assertSame(1500, $updated, 'All 1500 records should be updated across 2 batches (1000 + 500)');
            self::assertSame([], $errors, 'No errors should occur during update');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Tests edge case: exactly 1000 records (single batch).
     */
    #[Test]
    public function importExactlyOneBatchSize(): void
    {
        $tempFile = $this->generateLargeXliffFile(1000);

        try {
            $imported = 0;
            $updated  = 0;
            $errors   = [];

            $this->subject->importFile($tempFile, false, $imported, $updated, $errors);

            self::assertSame(1000, $imported, 'Exactly 1000 records should be imported in single batch');
            self::assertSame(0, $updated);
            self::assertSame([], $errors);
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Tests edge case: 2001 records (3 batches: 1000 + 1000 + 1).
     */
    #[Test]
    public function importMultipleBatchesPlusOne(): void
    {
        $tempFile = $this->generateLargeXliffFile(2001);

        try {
            $imported = 0;
            $updated  = 0;
            $errors   = [];

            $this->subject->importFile($tempFile, false, $imported, $updated, $errors);

            self::assertSame(2001, $imported, 'All 2001 records should be imported across 3 batches');
            self::assertSame(0, $updated);
            self::assertSame([], $errors);

            // Verify database count
            $connection = $this->getConnectionPool()->getConnectionForTable('tx_nrtextdb_domain_model_translation');
            $count      = $connection->count('*', 'tx_nrtextdb_domain_model_translation', []);

            self::assertSame(2001, $count, 'Database should contain exactly 2001 records');
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    /**
     * Generates a large XLIFF file with specified number of translation units.
     *
     * @param int $count Number of translation units to generate
     *
     * @return string Absolute path to generated temporary file
     */
    private function generateLargeXliffFile(int $count): string
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'xliff_test_');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">' . "\n";
        $xml .= '  <file source-language="en" target-language="de" datatype="plaintext" original="messages">' . "\n";
        $xml .= '    <header/>' . "\n";
        $xml .= '    <body>' . "\n";

        for ($i = 1; $i <= $count; ++$i) {
            $xml .= sprintf(
                '      <trans-unit id="TestComponent|batch_type|test_placeholder_%d" xml:space="preserve">' . "\n" .
                '        <source>Source Text %d</source>' . "\n" .
                '        <target>Batch Test Translation %d</target>' . "\n" .
                '      </trans-unit>' . "\n",
                $i,
                $i,
                $i
            );
        }

        $xml .= '    </body>' . "\n";
        $xml .= '  </file>' . "\n";
        $xml .= '</xliff>';

        file_put_contents($tempFile, $xml);

        return $tempFile;
    }
}
