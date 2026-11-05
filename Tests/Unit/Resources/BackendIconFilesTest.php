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

namespace Netresearch\NrTextdb\Tests\Unit\Resources;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Pins the SVG icons to glyphs that follow the backend colour scheme.
 */
#[CoversNothing]
final class BackendIconFilesTest extends UnitTestCase
{
    private const BRAND_TEAL = '#2F99A4';

    private const BRAND_ORANGE = '#FF4D00';

    /**
     * The accent fill of the flat v14 module icons across the Netresearch
     * extensions: a custom property a theme can override, with the brand teal
     * as fallback.
     */
    private const ICON_ACCENT = 'var(--nr-icon-accent, #2F99A4)';

    /**
     * The translate pictogram on the 64 grid: the source character, then the
     * target letter A with its counter. Module.svg and Extension.svg draw the
     * same two shapes.
     */
    private const TRANSLATE_SOURCE_PATH = 'M33.74 38.14l-5.08-5.02 0.06-0.06c3.48-3.88 5.96-8.34 7.42-13.06H42V16h-14V12H24v4H10v3.98h22.34C31 23.84 28.88 27.5 26 30.7 24.14 28.64 22.6 26.38 21.38 24h-4c1.46 3.26 3.46 6.34 5.96 9.12l-10.18 10.04L16 46l10-10 6.22 6.22 1.52-4.08Z';

    private const TRANSLATE_TARGET_PATH = 'M45 28h-4L32 52h4l2.24-6h9.5L50 52h4l-9-24ZM39.76 42l3.24-8.66L46.24 42h-6.48Z';

    /**
     * The rounded tile every Netresearch extension icon sits on.
     */
    private const TILE_PATH = 'M8 0h48a8 8 0 0 1 8 8v48a8 8 0 0 1-8 8H8a8 8 0 0 1-8-8V8a8 8 0 0 1 8-8Z';

    private const GEOMETRY_ATTRIBUTES = ['d', 'points', 'transform', 'viewBox'];

    private const RECORD_VIEWBOX = '0 0 64 64';

    private const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    private const ICON_DIR = __DIR__ . '/../../../Resources/Public/Icons/';

    /**
     * Every record icon as its full expected tree, in the flat v14 grammar of
     * Module.svg: filled shapes only, the main shape in currentColor,
     * secondary detail at opacity .4, one accent in ICON_ACCENT. The icon
     * registry references "<file>#<table>", so the symbol carries the table
     * name as id, and the file itself draws the symbol with <use> so it also
     * shows as a standalone image.
     *
     * Rows are the children of the symbol: element, depth below the symbol,
     * attributes.
     *
     * @return iterable<string, array{string, list<array{string, int, array<string, string>, string}>}>
     */
    public static function recordIconProvider(): iterable
    {
        yield 'tx_nrtextdb_domain_model_component' => self::recordIcon('tx_nrtextdb_domain_model_component', [
            ['path', 0, ['d' => 'M15 12h11a3 3 0 0 1 3 3v11a3 3 0 0 1-3 3H15a3 3 0 0 1-3-3V15a3 3 0 0 1 3-3Z', 'fill' => 'currentColor']],
            ['g', 0, ['opacity' => '.4']],
            ['path', 1, ['d' => 'M38 12h11a3 3 0 0 1 3 3v11a3 3 0 0 1-3 3H38a3 3 0 0 1-3-3V15a3 3 0 0 1 3-3ZM15 35h11a3 3 0 0 1 3 3v11a3 3 0 0 1-3 3H15a3 3 0 0 1-3-3V38a3 3 0 0 1 3-3Z', 'fill' => 'currentColor']],
            ['path', 0, ['d' => 'M38 35h11a3 3 0 0 1 3 3v11a3 3 0 0 1-3 3H38a3 3 0 0 1-3-3V38a3 3 0 0 1 3-3Z', 'fill' => self::ICON_ACCENT]],
        ]);

        yield 'tx_nrtextdb_domain_model_environment' => self::recordIcon('tx_nrtextdb_domain_model_environment', [
            ['path', 0, ['d' => 'M14 12h36a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H14a4 4 0 0 1-4-4v-8a4 4 0 0 1 4-4ZM14 16v8h36v-8Z', 'fill' => 'currentColor', 'fill-rule' => 'evenodd']],
            ['g', 0, ['opacity' => '.4']],
            ['path', 1, ['d' => 'M14 36h36a4 4 0 0 1 4 4v8a4 4 0 0 1-4 4H14a4 4 0 0 1-4-4v-8a4 4 0 0 1 4-4ZM14 40v8h36v-8Z', 'fill' => 'currentColor', 'fill-rule' => 'evenodd']],
            ['path', 0, ['d' => 'M43 17.5a2.5 2.5 0 1 0 0 5 2.5 2.5 0 1 0 0-5Z', 'fill' => self::ICON_ACCENT]],
        ]);

        yield 'tx_nrtextdb_domain_model_translation' => self::recordIcon('tx_nrtextdb_domain_model_translation', [
            ['path', 0, ['d' => self::TRANSLATE_SOURCE_PATH, 'fill' => 'currentColor']],
            ['path', 0, ['d' => self::TRANSLATE_TARGET_PATH, 'fill' => self::ICON_ACCENT, 'fill-rule' => 'evenodd']],
        ]);

        yield 'tx_nrtextdb_domain_model_type' => self::recordIcon('tx_nrtextdb_domain_model_type', [
            ['path', 0, ['d' => 'M10 32L22 18H50a4 4 0 0 1 4 4V42a4 4 0 0 1-4 4H22ZM15.27 32L23.84 22H50V42H23.84Z', 'fill' => 'currentColor', 'fill-rule' => 'evenodd']],
            ['g', 0, ['opacity' => '.4']],
            ['path', 1, ['d' => 'M35 30h9a2 2 0 0 1 0 4h-9a2 2 0 0 1 0-4Z', 'fill' => 'currentColor']],
            ['path', 0, ['d' => 'M26 28.5a3.5 3.5 0 1 0 0 7 3.5 3.5 0 1 0 0-7Z', 'fill' => self::ICON_ACCENT]],
        ]);
    }

    /**
     * The expected tree of a record icon: a <symbol> with the table name as
     * id holding the given rows, and a <use> of the symbol.
     *
     * @param list<array{string, int, array<string, string>}> $rows
     *
     * @return array{string, list<array{string, int, array<string, string>, string}>}
     */
    private static function recordIcon(string $table, array $rows): array
    {
        $children = [];

        foreach ($rows as [$name, $depth, $attributes]) {
            foreach ($attributes as $attribute => $value) {
                if (in_array($attribute, self::GEOMETRY_ATTRIBUTES, true)) {
                    $attributes[$attribute] = self::geometry($value);
                }
            }

            ksort($attributes);
            $children[] = [$name, $depth + 2, $attributes, ''];
        }

        return [
            $table,
            [
                ['svg', 0, ['height' => '16', 'viewBox' => self::geometry(self::RECORD_VIEWBOX), 'width' => '16'], ''],
                ['symbol', 1, ['id' => $table, 'viewBox' => self::geometry(self::RECORD_VIEWBOX)], ''],
                ...$children,
                ['use', 1, ['href' => '#' . $table], ''],
            ],
        ];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function iconFileProvider(): iterable
    {
        $files = glob(self::ICON_DIR . '*.svg');
        self::assertIsArray($files);
        self::assertNotSame([], $files);

        foreach ($files as $file) {
            yield basename($file) => [$file];
        }
    }

    /**
     * Covers an icon file added later, before it gets a pinned tree.
     */
    #[Test]
    #[DataProvider('iconFileProvider')]
    public function iconHasNoStyleBlock(string $file): void
    {
        $svg = $this->load($file);

        self::assertSame(0, $svg->getElementsByTagName('style')->length, basename($file) . ' must not carry a <style> block');
    }

    /**
     * @param list<array{string, int, array<string, string>, string}> $expected
     */
    #[Test]
    #[DataProvider('recordIconProvider')]
    public function recordIconIsExactlyItsPinnedTree(string $table, array $expected): void
    {
        $svg = $this->load(self::ICON_DIR . $table . '.svg')->documentElement;
        self::assertInstanceOf(DOMElement::class, $svg);

        self::assertSame($expected, $this->normalisedTree($svg));
    }

    /**
     * Extension.svg is the extension tile shown in the Extension Manager and
     * on TER, in the form the Netresearch TYPO3 v14 extensions share: a
     * rounded #2F99A4 tile, the feature glyph in white, one #FF4D00 accent
     * dot. On 2026-11-05 the user asked for this form, replacing the [n] logo
     * chosen on 2026-09-29; that decision was among variants of the logo, the
     * feature tile was not one of them. White on #2F99A4 is 3.38:1. The
     * Extension Manager shows the file as <img>, so it uses fixed colours.
     */
    #[Test]
    public function extensionIconIsTheWhiteTranslateGlyphOnTheBrandTile(): void
    {
        $svg = $this->load(self::ICON_DIR . 'Extension.svg')->documentElement;
        self::assertInstanceOf(DOMElement::class, $svg);

        // Every element with every attribute, so an added opacity, stroke,
        // filter or element fails as well as a changed path or colour.
        self::assertSame(
            [
                ['svg', 0, ['viewBox' => self::geometry('0 0 64 64')], ''],
                ['title', 1, [], 'TextDB - Netresearch DTT GmbH'],
                ['path', 1, ['d' => self::geometry(self::TILE_PATH), 'fill' => self::BRAND_TEAL], ''],
                ['path', 1, ['d' => self::geometry(self::TRANSLATE_SOURCE_PATH), 'fill' => '#FFFFFF'], ''],
                ['path', 1, ['d' => self::geometry(self::TRANSLATE_TARGET_PATH), 'fill' => '#FFFFFF', 'fill-rule' => 'evenodd'], ''],
                ['circle', 1, ['cx' => '51', 'cy' => '13', 'fill' => self::BRAND_ORANGE, 'r' => '5'], ''],
            ],
            $this->normalisedTree($svg),
        );
    }

    /**
     * Module.svg is the TextDB module icon in the flat TYPO3 v14 grammar the
     * Netresearch extensions share: filled shapes only, the source character
     * in currentColor, so it takes the module menu's text colour in the light
     * and the dark scheme, and the target letter in the shared accent. No
     * tile: the v14 module menu draws core icons without one.
     */
    #[Test]
    public function moduleIconIsTheFlatTranslateGlyphInCurrentColorAndAccent(): void
    {
        $svg = $this->load(self::ICON_DIR . 'Module.svg')->documentElement;
        self::assertInstanceOf(DOMElement::class, $svg);

        self::assertSame(
            [
                ['svg', 0, ['viewBox' => self::geometry('0 0 64 64')], ''],
                ['path', 1, ['d' => self::geometry(self::TRANSLATE_SOURCE_PATH), 'fill' => 'currentColor'], ''],
                ['path', 1, ['d' => self::geometry(self::TRANSLATE_TARGET_PATH), 'fill' => self::ICON_ACCENT, 'fill-rule' => 'evenodd'], ''],
            ],
            $this->normalisedTree($svg),
        );
    }

    /**
     * Geometry as its token list, joined by single spaces. Tokens follow the
     * SVG path and transform grammar: a run of letters (a command or a
     * function name), a number (sign, digits, decimal point, exponent), or
     * any other single non-whitespace character (comma, parenthesis).
     * Whitespace between tokens is dropped, whitespace inside a token splits
     * it. So "M209.6, 0 V31.62" equals "M209.6,0V31.62" and
     * "translate( -0.39 -0.04 )" equals "translate(-0.39 -0.04)", while
     * "32 .77", "1 e-5", "1e -5" and "trans late(" each differ from the
     * token they break. Numbers and runs of command letters are compared as
     * written, not by what they draw: "-.7" and "-0.7", ".39" and "0.39",
     * "ZM" and "Z M", compacted arc flags, a leading "+" and exponent forms
     * all differ from their written-out equivalents although they render
     * the same. A comma and a space are not treated as the same separator
     * either, although SVG allows both.
     */
    private static function geometry(string $value): string
    {
        preg_match_all('/[A-Za-z]+|[-+]?(?:\d+\.?\d*|\.\d+)(?:[eE][-+]?\d+)?|\S/', $value, $tokens);

        return implode(' ', $tokens[0]);
    }

    /**
     * @return list<array{string, int, array<string, string>, string}>
     */
    private function normalisedTree(DOMElement $root): array
    {
        // A plain list instead of spreading the DOMNodeList: PHP 8.2 does not
        // spread a DOMNodeList reliably (CI saw a truncated list).
        $elements = [$root];

        foreach ($root->getElementsByTagName('*') as $descendant) {
            $elements[] = $descendant;
        }

        $tree = [];

        foreach ($elements as $element) {
            self::assertSame(self::SVG_NAMESPACE, $element->namespaceURI, '<' . $element->localName . '> is not in the SVG namespace');
            // TYPO3's SvgSanitizer drops a prefixed element such as <s:g>, even
            // with the prefix bound to the SVG namespace.
            self::assertSame($element->localName, $element->nodeName, '<' . $element->nodeName . '> must not carry a namespace prefix');

            $attributes = [];

            foreach ($element->attributes as $attribute) {
                $value = $attribute->nodeValue ?? '';

                $attributes[$attribute->nodeName] = in_array($attribute->nodeName, self::GEOMETRY_ATTRIBUTES, true)
                    ? self::geometry($value)
                    : $value;
            }

            ksort($attributes);

            // The number of parentNode steps to the root: with it, the
            // document-order list fixes every element's parent, so moving an
            // element into or out of another one changes the list.
            $depth = 0;

            for ($node = $element; $node !== $root && $node->parentNode instanceof DOMElement; $node = $node->parentNode) {
                ++$depth;
            }

            $text   = $element->localName === 'title' ? trim($element->textContent) : '';
            $tree[] = [$element->localName, $depth, $attributes, $text];
        }

        return $tree;
    }

    private function load(string $file): DOMDocument
    {
        $contents = file_get_contents($file);
        self::assertIsString($contents);

        $document = new DOMDocument();
        self::assertTrue($document->loadXML($contents), basename($file) . ' is not well-formed XML');

        // A DOCTYPE can define entities that add content, and an
        // xml-stylesheet processing instruction can restyle the whole icon;
        // neither shows up in the element tree compared below. Processing
        // instructions are rejected anywhere in the document: before, inside
        // or after the root element.
        self::assertNull($document->doctype, basename($file) . ' must not carry a DOCTYPE');

        $instructions = (new DOMXPath($document))->query('//processing-instruction()');
        self::assertNotFalse($instructions);
        self::assertSame(0, $instructions->length, basename($file) . ' must not carry a processing instruction');

        return $document;
    }
}
