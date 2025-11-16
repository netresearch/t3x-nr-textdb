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
 * Generate large textdb_import XLIFF file for performance testing.
 */
$count  = (int) ($argv[1] ?? 3000);
$output = $argv[2] ?? '/home/cybot/projects/t3x-nr-textdb/Resources/Private/Language/perftest.textdb_import.xlf';

echo "Generating $count trans-units to $output\n";

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<xliff version="1.2" xmlns="urn:oasis:names:tc:xliff:document:1.2">' . "\n";
$xml .= '    <file source-language="en" datatype="plaintext" original="messages" date="2024-11-14T00:00:00Z" product-name="nr_textdb">' . "\n";
$xml .= '        <header/>' . "\n";
$xml .= '        <body>' . "\n";

$components = ['auth', 'user', 'admin', 'content', 'navigation', 'form', 'table', 'modal', 'notification', 'dashboard'];
$types      = ['label', 'button', 'message', 'title', 'description', 'placeholder', 'tooltip', 'error', 'warning', 'success'];

for ($i = 0; $i < $count; ++$i) {
    $component   = $components[$i % count($components)];
    $type        = $types[($i >> 1) % count($types)];
    $placeholder = sprintf('item_%d', $i);

    $id     = "$component|$type|$placeholder";
    $source = "Source text $i";
    $target = "Target translation $i - Lorem ipsum dolor sit amet, consectetur adipiscing elit";

    $xml .= sprintf('            <trans-unit id="%s">' . "\n", htmlspecialchars($id));
    $xml .= sprintf('                <source>%s</source>' . "\n", htmlspecialchars($source));
    $xml .= sprintf('                <target>%s</target>' . "\n", htmlspecialchars($target));
    $xml .= '            </trans-unit>' . "\n";
}

$xml .= '        </body>' . "\n";
$xml .= '    </file>' . "\n";
$xml .= '</xliff>' . "\n";

file_put_contents($output, $xml);

echo 'Generated: ' . number_format(strlen($xml)) . ' bytes (' . number_format($count) . " trans-units)\n";
echo 'File size: ' . number_format(filesize($output)) . " bytes\n";
