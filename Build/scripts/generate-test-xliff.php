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
 * Generate test XLIFF files of various sizes for performance testing.
 *
 * Usage: php generate-test-xliff.php
 * Output: Creates test files in Build/test-data/
 */
$outputDir = __DIR__ . '/../test-data';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

/**
 * Generate XLIFF file with specified number of trans-units.
 */
function generateXliffFile(string $filename, int $transUnitCount, string $language = 'en'): string
{
    $xliff = <<<XML
<?xml version="1.0" encoding="utf-8" standalone="yes"?>
<xliff version="1.0">
    <file source-language="en" datatype="plaintext" original="messages" date="2025-01-15T12:00:00Z" product-name="test">
        <header/>
        <body>

XML;

    // Generate trans-units
    for ($i = 1; $i <= $transUnitCount; ++$i) {
        $id     = sprintf('test_component|test_type|label_%06d', $i);
        $source = "Test Label {$i} - This is a test translation string for performance testing";
        $target = ($language === 'en')
            ? $source
            : "Testübersetzung {$i} - Dies ist ein Test-Übersetzungsstring für Leistungstests";

        $xliff .= <<<XML
            <trans-unit id="{$id}" xml:space="preserve">
                <source>{$source}</source>
                <target>{$target}</target>
            </trans-unit>

XML;
    }

    $xliff .= <<<XML
        </body>
    </file>
</xliff>
XML;

    file_put_contents($filename, $xliff);

    return $filename;
}

/**
 * Calculate number of trans-units needed for target file size.
 */
function calculateTransUnits(int $targetBytes): int
{
    // Average trans-unit size is ~250 bytes
    $avgTransUnitSize = 250;
    $overhead         = 500; // XML header/footer overhead

    return (int) (($targetBytes - $overhead) / $avgTransUnitSize);
}

// Define test file sizes
$testFiles = [
    '50kb'  => calculateTransUnits(50 * 1024),     // ~200 trans-units
    '1mb'   => calculateTransUnits(1 * 1024 * 1024),   // ~4,000 trans-units
    '10mb'  => calculateTransUnits(10 * 1024 * 1024),  // ~40,000 trans-units
    '100mb' => calculateTransUnits(100 * 1024 * 1024), // ~400,000 trans-units
];

echo "Generating test XLIFF files...\n\n";

foreach ($testFiles as $size => $count) {
    $filename = "{$outputDir}/test_{$size}.textdb_import.xlf";

    echo sprintf('Creating %s file with %s trans-units...', str_pad($size, 6), number_format($count));

    $start = microtime(true);
    generateXliffFile($filename, $count);
    $elapsed = microtime(true) - $start;

    $actualSize   = filesize($filename);
    $actualSizeMB = round($actualSize / 1024 / 1024, 2);

    echo sprintf(" ✓ (%s MB in %.2fs)\n", $actualSizeMB, $elapsed);
}

echo "\nTest files created in: {$outputDir}\n";
echo "\nFiles ready for import testing:\n";
foreach ($testFiles as $size => $count) {
    $filename = "{$outputDir}/test_{$size}.textdb_import.xlf";
    echo sprintf("  - test_%s.textdb_import.xlf (%s trans-units, %s MB)\n",
        $size,
        number_format($count),
        round(filesize($filename) / 1024 / 1024, 2)
    );
}
