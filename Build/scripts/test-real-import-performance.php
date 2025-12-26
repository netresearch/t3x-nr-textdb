#!/usr/bin/env php
<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

/**
 * REAL Import Performance Test.
 *
 * Tests actual XLIFF import with REAL database operations
 * NO SIMULATIONS - measures actual ImportService performance
 */

// Bootstrap TYPO3
putenv('TYPO3_CONTEXT=Development');
$_SERVER['argv'] = [$_SERVER['argv'][0] ?? 'test-real-import-performance.php'];
require '/var/www/html/v13/vendor/autoload.php';

use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;

SystemEnvironmentBuilder::run(0, SystemEnvironmentBuilder::REQUESTTYPE_CLI);
Bootstrap::init(TYPO3\CMS\Core\Core\Environment::class, true);

$container = Bootstrap::createBasicClassLoadingInformationForPackageManagement()->buildContainer();

// Get ImportService from container
$importService = $container->get(Netresearch\NrTextdb\Service\ImportService::class);

function formatTime(float $seconds): string
{
    if ($seconds < 1) {
        return sprintf('%.0f ms', $seconds * 1000);
    }
    if ($seconds < 60) {
        return sprintf('%.2f sec', $seconds);
    }

    return sprintf('%.2f min', $seconds / 60);
}

function formatBytes(int $bytes): string
{
    $units  = ['B', 'KB', 'MB', 'GB'];
    $factor = floor((strlen((string) $bytes) - 1) / 3);

    return sprintf('%.2f %s', $bytes / pow(1024, $factor), $units[$factor]);
}

// Test file path
$testFile = $argv[1] ?? '/var/www/nr_textdb/Build/scripts/test-sample-1mb.xlf';

if (!file_exists($testFile)) {
    echo "ERROR: Test file not found: $testFile\n";
    echo "Usage: php test-real-import-performance.php <xliff-file>\n";
    exit(1);
}

$fileSize = filesize($testFile);
$fileName = basename($testFile);

echo "\n" . str_repeat('═', 100) . "\n";
echo "REAL IMPORT PERFORMANCE TEST\n";
echo str_repeat('═', 100) . "\n\n";
echo "File: $fileName\n";
echo 'Size: ' . formatBytes($fileSize) . "\n";
echo "Mode: REAL DATABASE OPERATIONS (no simulation)\n\n";

// Variables for import tracking
$imported = 0;
$updated  = 0;
$errors   = [];

// Start timing
$startTime   = microtime(true);
$startMemory = memory_get_usage();

try {
    // REAL import using ImportService
    $importService->importFile(
        $testFile,
        false,  // forceUpdate
        $imported,
        $updated,
        $errors
    );

    $endTime    = microtime(true);
    $endMemory  = memory_get_usage();
    $peakMemory = memory_get_peak_usage();

    $duration   = $endTime - $startTime;
    $memoryUsed = $endMemory - $startMemory;

    echo '┌─ RESULTS ─' . str_repeat('─', 87) . "┐\n";
    echo sprintf("  ✅ Import completed successfully\n");
    echo sprintf("  Time: %s\n", formatTime($duration));
    echo sprintf("  Memory used: %s\n", formatBytes($memoryUsed));
    echo sprintf("  Peak memory: %s\n", formatBytes($peakMemory));
    echo sprintf("  Imported: %d records\n", $imported);
    echo sprintf("  Updated: %d records\n", $updated);
    echo sprintf("  Throughput: %.0f trans-units/sec\n", ($imported + $updated) / max(0.001, $duration));

    if (!empty($errors)) {
        echo sprintf("  ⚠️  Errors: %d\n", count($errors));
        foreach (array_slice($errors, 0, 5) as $error) {
            echo "    - $error\n";
        }
    }
    echo '└' . str_repeat('─', 99) . "┘\n\n";
} catch (Exception $e) {
    echo '❌ ERROR: ' . $e->getMessage() . "\n";
    echo 'Trace: ' . $e->getTraceAsString() . "\n";
    exit(1);
}
