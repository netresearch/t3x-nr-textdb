<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SimpleXMLElement;
use TYPO3\CMS\Core\Localization\Exception\InvalidXmlFileException;
use TYPO3\CMS\Core\Localization\Loader\XliffLoader;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * XML external entity (XXE) payloads against the parser both imports use.
 *
 * The backend import and the CLI import read XLIFF with TYPO3's XliffLoader,
 * which parses without LIBXML_NOENT. These tests send each payload through
 * that loader, so they fail if a TYPO3 update changes how it parses.
 *
 * @see https://owasp.org/www-community/vulnerabilities/XML_External_Entity_(XXE)_Processing
 */
#[CoversNothing]
final class XliffImportXxeTest extends UnitTestCase
{
    private const DATA_ENTITY_TEXT = 'resolved-external-entity';

    /**
     * @return array<string, array{string}>
     */
    public static function externalEntityProvider(): array
    {
        return [
            'local file'   => ['file:///etc/passwd'],
            'php wrapper'  => ['php://filter/convert.base64-encode/resource=/etc/passwd'],
            'data wrapper' => ['data://text/plain,' . self::DATA_ENTITY_TEXT],
        ];
    }

    #[Test]
    #[DataProvider('externalEntityProvider')]
    public function anExternalEntityIsNotResolved(string $systemId): void
    {
        $value = $this->loadSource(
            '<!DOCTYPE xliff [<!ENTITY xxe SYSTEM "' . $systemId . '">]>',
            '&xxe;',
        );

        self::assertStringNotContainsString('root:', $value);
        self::assertStringNotContainsString(self::DATA_ENTITY_TEXT, $value);

        $decoded = base64_decode($value, true);
        self::assertTrue(($decoded === false) || !str_contains($decoded, 'root:'));
    }

    #[Test]
    public function theDataPayloadResolvesWhenAParserSubstitutesEntities(): void
    {
        // Control for the test above: the data:// payload does resolve once
        // entities are substituted, so a passing test means the loader did
        // not substitute it. Unlike file:// it depends on no file of the host.
        $document = simplexml_load_string(
            '<?xml version="1.0"?><!DOCTYPE xliff [<!ENTITY xxe SYSTEM "data://text/plain,' . self::DATA_ENTITY_TEXT . '">]>'
            . '<xliff><file><body><trans-unit id="a"><source>&xxe;</source></trans-unit></body></file></xliff>',
            SimpleXMLElement::class,
            LIBXML_NOENT,
        );

        self::assertNotFalse($document);
        self::assertSame(self::DATA_ENTITY_TEXT, (string) $document->file->body->{'trans-unit'}->source);
    }

    #[Test]
    public function aFileWithoutEntitiesIsRead(): void
    {
        self::assertSame('Proceed to Checkout', $this->loadSource('', 'Proceed to Checkout'));
    }

    #[Test]
    public function aBillionLaughsPayloadIsNotExpanded(): void
    {
        // Seven levels of ten references expand to 30 MB. The loader passes
        // no flag against internal entity expansion (five levels, 300 kB, do
        // expand); what stops this is libxml's own amplification limit, which
        // makes the parse fail. The test guards that limit for both imports.
        $entities = '<!ENTITY lol0 "lol">';
        for ($level = 1; $level <= 7; ++$level) {
            $entities .= '<!ENTITY lol' . $level . ' "' . str_repeat('&lol' . ($level - 1) . ';', 10) . '">';
        }

        try {
            $value = $this->loadSource('<!DOCTYPE xliff [' . $entities . ']>', '&lol7;');
        } catch (InvalidXmlFileException) {
            $value = '';
        }

        self::assertLessThan(1000, strlen($value));
    }

    /**
     * Loads a default-language file with one unit the way both imports do,
     * and returns the text of that unit.
     */
    private function loadSource(string $doctype, string $source): string
    {
        $xliff = '<?xml version="1.0" encoding="UTF-8"?>' . $doctype
            . '<xliff version="1.0"><file source-language="en" datatype="plaintext" original="messages"><body>'
            . '<trans-unit id="component|type|placeholder"><source>' . $source . '</source></trans-unit>'
            . '</body></file></xliff>';

        $value = (new XliffLoader())->load($xliff, 'en')->get('component|type|placeholder');

        return $value === 'component|type|placeholder' ? '' : $value;
    }
}
