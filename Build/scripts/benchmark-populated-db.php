#!/usr/bin/env php
<?php

/**
 * Fair Performance Comparison: Both approaches against POPULATED database
 * Tests UPDATE operations (realistic production scenario)
 */

declare(strict_types=1);

// Change to TYPO3 root
chdir('/var/www/html/v13');

// Bootstrap TYPO3 CLI
putenv('TYPO3_CONTEXT=Development');

require '/var/www/html/v13/vendor/autoload.php';

use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// Bootstrap (proper TYPO3 v13 pattern)
$classLoader = require '/var/www/html/v13/vendor/autoload.php';
SystemEnvironmentBuilder::run(1, SystemEnvironmentBuilder::REQUESTTYPE_CLI);
Bootstrap::init($classLoader, true);

function formatTime(float $seconds): string
{
    if ($seconds < 1) {
        return sprintf('%.2f ms', $seconds * 1000);
    }
    return sprintf('%.2f sec', $seconds);
}

function getDbRecordCount(): int
{
    $connection = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Database\ConnectionPool::class)
        ->getConnectionForTable('tx_nrtextdb_domain_model_translation');

    return (int)$connection->count('*', 'tx_nrtextdb_domain_model_translation', []);
}

echo "╔═══════════════════════════════════════════════════════════════════════════════════════════════╗\n";
echo "║         FAIR PERFORMANCE COMPARISON: ALL-IN-RUST vs DBAL (BOTH ON POPULATED DATABASE)        ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════════════════════════╝\n\n";

// Check database state
$recordCount = getDbRecordCount();
echo "Database status: " . number_format($recordCount) . " existing records\n";
echo "Test mode: UPDATE operations (realistic production scenario)\n\n";

if ($recordCount === 0) {
    echo "⚠️  WARNING: Database is EMPTY! Results will show INSERT performance, not UPDATE.\n";
    echo "   Run the All-in-Rust test first to populate the database.\n\n";
}

$testFile = '/var/www/nr_textdb/Build/test-data/textdb_100mb.xlf';
$fileSize = filesize($testFile);

echo "Test file: " . basename($testFile) . "\n";
echo "File size: " . number_format($fileSize / 1024 / 1024, 2) . " MB\n\n";

echo str_repeat('═', 95) . "\n";
echo "TEST 1: ALL-IN-RUST (XLIFF parsing + DB import in Rust)\n";
echo str_repeat('═', 95) . "\n\n";

$rustService = GeneralUtility::makeInstance(\Netresearch\NrTextdb\Service\RustImportService::class,
    GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager::class),
    GeneralUtility::makeInstance(\TYPO3\CMS\Core\Localization\Parser\XliffParser::class),
    GeneralUtility::makeInstance(\Netresearch\NrTextdb\Domain\Repository\ComponentRepository::class),
    GeneralUtility::makeInstance(\Netresearch\NrTextdb\Domain\Repository\TypeRepository::class),
    GeneralUtility::makeInstance(\Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository::class)
);

$imported = 0;
$updated = 0;
$errors = [];

$startTime = hrtime(true);

try {
    $rustService->importFile(
        $testFile,
        false,  // forceUpdate
        $imported,
        $updated,
        $errors
    );

    $duration = (hrtime(true) - $startTime) / 1e9;
    $throughput = ($imported + $updated) / max(0.001, $duration);

    echo "✅ All-in-Rust completed\n";
    echo sprintf("  Duration:    %s\n", formatTime($duration));
    echo sprintf("  Inserted:    %d records\n", $imported);
    echo sprintf("  Updated:     %d records\n", $updated);
    echo sprintf("  Throughput:  %.0f records/sec\n", $throughput);
    if (!empty($errors)) {
        echo sprintf("  Errors:      %d\n", count($errors));
    }

    $rustResults = [
        'duration' => $duration,
        'inserted' => $imported,
        'updated' => $updated,
        'throughput' => $throughput,
    ];

} catch (Exception $e) {
    echo '❌ ERROR: ' . $e->getMessage() . "\n";
    exit(1);
}

echo "\n" . str_repeat('═', 95) . "\n";
echo "TEST 2: DBAL BULK (PHP XLIFF parsing + DBAL bulk operations)\n";
echo str_repeat('═', 95) . "\n\n";

// Force DBAL mode by temporarily disabling Rust
// We'll use ImportService but need to ensure it doesn't use Rust
// The cleanest way is to call the DBAL methods directly

$dbalService = GeneralUtility::makeInstance(\Netresearch\NrTextdb\Service\ImportService::class,
    GeneralUtility::makeInstance(\TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager::class),
    GeneralUtility::makeInstance(\TYPO3\CMS\Core\Localization\Parser\XliffParser::class),
    GeneralUtility::makeInstance(\Netresearch\NrTextdb\Domain\Repository\ComponentRepository::class),
    GeneralUtility::makeInstance(\Netresearch\NrTextdb\Domain\Repository\TypeRepository::class),
    GeneralUtility::makeInstance(\Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository::class)
);

// Temporarily rename the Rust library so it can't be used
$rustLibPath = '/var/www/nr_textdb/Resources/Private/Bin/linux64/libxliff_parser.so';
$rustLibBackup = $rustLibPath . '.backup';

if (file_exists($rustLibPath)) {
    rename($rustLibPath, $rustLibBackup);
    echo "  (Rust library temporarily disabled for pure DBAL test)\n\n";
}

$imported = 0;
$updated = 0;
$errors = [];

$startTime = hrtime(true);

try {
    $dbalService->importFile(
        $testFile,
        false,  // forceUpdate
        $imported,
        $updated,
        $errors
    );

    $duration = (hrtime(true) - $startTime) / 1e9;
    $throughput = ($imported + $updated) / max(0.001, $duration);

    echo "✅ DBAL Bulk completed\n";
    echo sprintf("  Duration:    %s\n", formatTime($duration));
    echo sprintf("  Inserted:    %d records\n", $imported);
    echo sprintf("  Updated:     %d records\n", $updated);
    echo sprintf("  Throughput:  %.0f records/sec\n", $throughput);
    if (!empty($errors)) {
        echo sprintf("  Errors:      %d\n", count($errors));
    }

    $dbalResults = [
        'duration' => $duration,
        'inserted' => $imported,
        'updated' => $updated,
        'throughput' => $throughput,
    ];

} catch (Exception $e) {
    echo '❌ ERROR: ' . $e->getMessage() . "\n";
    // Restore Rust library before exiting
    if (file_exists($rustLibBackup)) {
        rename($rustLibBackup, $rustLibPath);
    }
    exit(1);
}

// Restore Rust library
if (file_exists($rustLibBackup)) {
    rename($rustLibBackup, $rustLibPath);
}

// Comparison
echo "\n" . str_repeat('═', 95) . "\n";
echo "COMPARISON SUMMARY\n";
echo str_repeat('═', 95) . "\n\n";

$speedup = $dbalResults['duration'] / $rustResults['duration'];

printf("All-in-Rust:  %s  (%s records/sec)\n",
    formatTime($rustResults['duration']),
    number_format($rustResults['throughput'], 0)
);

printf("DBAL Bulk:    %s  (%s records/sec)\n",
    formatTime($dbalResults['duration']),
    number_format($dbalResults['throughput'], 0)
);

echo "\n";

if ($speedup > 1) {
    printf("🚀 All-in-Rust is %.2fx FASTER than DBAL\n", $speedup);
} else {
    printf("⚠️  DBAL is %.2fx FASTER than All-in-Rust\n", 1 / $speedup);
}

echo "\n" . str_repeat('═', 95) . "\n";
