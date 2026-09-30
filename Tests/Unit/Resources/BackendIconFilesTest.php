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

    private const BRAND_ANTHRACITE = '#585961';

    /**
     * The [n] logo as netresearch-branding/references/typo3-extension-branding.md
     * ships it: frame path, then letter path, both with the same transform.
     */
    private const BRAND_FRAME_PATH = 'M209.6,0V31.62h32.77a26.38,26.38,0,0,1,26.44,26.43V242a26.38,26.38,0,0,1-26.44,26.44H209.6V300h47.93a42.77,42.77,0,0,0,42.86-42.86V42.89A42.76,42.76,0,0,0,257.53,0ZM43.25,0A42.76,42.76,0,0,0,.39,42.89V257.18A42.76,42.76,0,0,0,43.25,300H91.18V268.46H58.4A26.38,26.38,0,0,1,32,242v-184A26.37,26.37,0,0,1,58.4,31.62H91.18V0Z';

    private const BRAND_LETTER_PATH = 'M221.44,120.41c0-34.48-13.94-57.82-48.93-57.82-26.62,0-48.54,7.74-64.17,26.56l-.7-22.06-28.31.06V232.94h31.59V124.69c7.14-18.38,32.14-34.8,53-34.5,27.38.4,25.2,26.24,26,45.81v96.94h31.58';

    private const BRAND_TRANSFORM = 'translate(-0.39 -0.04)';

    private const GEOMETRY_ATTRIBUTES = ['d', 'points', 'transform', 'viewBox'];

    private const RECORD_VIEWBOX = '0 0 48 48';

    /**
     * The rounded frame the component, environment and type icons draw
     * around their letter.
     */
    private const RECORD_FRAME = ['path', ['d' => 'M40.5,5.5H7.5a2,2,0,0,0-2,2v33a2,2,0,0,0,2,2h33a2,2,0,0,0,2-2V7.5A2,2,0,0,0,40.5,5.5Z'], ''];

    private const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    private const ICON_DIR = __DIR__ . '/../../../Resources/Public/Icons/';

    /**
     * Every record icon as its full expected tree. The icon registry
     * references "<file>#<table>", so the symbol carries the table name as id,
     * and the file itself draws the symbol with <use> so it also shows as a
     * standalone image. Every glyph is a currentColor stroke of width 3.
     *
     * @return iterable<string, array{string, list<array{string, int, array<string, string>, string}>}>
     */
    public static function recordIconProvider(): iterable
    {
        yield 'tx_nrtextdb_domain_model_component' => self::recordIcon('tx_nrtextdb_domain_model_component', [
            self::RECORD_FRAME,
            ['path', ['d' => self::geometry('M33,28.45a9,9,0,0,1-9,9.05h0a9,9,0,0,1-9-9v-8.9a9,9,0,0,1,9-9h0a9,9,0,0,1,9,9.05')], ''],
        ]);

        yield 'tx_nrtextdb_domain_model_environment' => self::recordIcon('tx_nrtextdb_domain_model_environment', [
            self::RECORD_FRAME,
            ['line', ['x1' => '17.25', 'x2' => '30.75', 'y1' => '37.5', 'y2' => '37.5'], ''],
            ['line', ['x1' => '17.25', 'x2' => '30.75', 'y1' => '10.5', 'y2' => '10.5'], ''],
            ['line', ['x1' => '17.25', 'x2' => '26.05', 'y1' => '24', 'y2' => '24'], ''],
            ['line', ['x1' => '17.25', 'x2' => '17.25', 'y1' => '10.5', 'y2' => '37.5'], ''],
        ]);

        yield 'tx_nrtextdb_domain_model_translation' => self::recordIcon('tx_nrtextdb_domain_model_translation', self::translationGlyph());

        yield 'tx_nrtextdb_domain_model_type' => self::recordIcon('tx_nrtextdb_domain_model_type', [
            self::RECORD_FRAME,
            ['line', ['x1' => '13.84', 'x2' => '34.16', 'y1' => '10.5', 'y2' => '10.5'], ''],
            ['line', ['x1' => '24', 'x2' => '24', 'y1' => '37.5', 'y2' => '10.5'], ''],
        ]);
    }

    /**
     * The expected tree of a record icon: a <symbol> with the table name as
     * id, one group drawing its shapes with a currentColor stroke of width 3
     * and no fill, and a <use> of the symbol, so the file also shows as a
     * standalone image.
     *
     * @param list<array{string, array<string, string>, string}> $shapes
     *
     * @return array{string, list<array{string, int, array<string, string>, string}>}
     */
    private static function recordIcon(string $table, array $shapes): array
    {
        return [
            $table,
            [
                ['svg', 0, ['height' => '16', 'viewBox' => self::geometry(self::RECORD_VIEWBOX), 'width' => '16'], ''],
                ['symbol', 1, ['id' => $table, 'viewBox' => self::geometry(self::RECORD_VIEWBOX)], ''],
                ['g', 2, ['fill' => 'none', 'stroke' => 'currentColor', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'stroke-width' => '3'], ''],
                ...self::at(3, $shapes),
                ['use', 1, ['href' => '#' . $table], ''],
            ],
        ];
    }

    /**
     * Places depth-less expected rows (the children of one element) at the
     * given depth, and applies geometry() to their geometry attributes, as
     * normalisedTree() does to the actual tree.
     *
     * @param list<array{string, array<string, string>, string}> $rows
     *
     * @return list<array{string, int, array<string, string>, string}>
     */
    private static function at(int $depth, array $rows): array
    {
        $placed = [];

        foreach ($rows as [$name, $attributes, $text]) {
            foreach ($attributes as $attribute => $value) {
                if (in_array($attribute, self::GEOMETRY_ATTRIBUTES, true)) {
                    $attributes[$attribute] = self::geometry($value);
                }
            }

            $placed[] = [$name, $depth, $attributes, $text];
        }

        return $placed;
    }

    /**
     * The translation glyph: the text-to-character pictogram in its frame.
     * The translation record icon and the Module.svg tile draw the same
     * shapes.
     *
     * @return list<array{string, array<string, string>, string}>
     */
    private static function translationGlyph(): array
    {
        return [
            ['line', ['x1' => '10.3148', 'x2' => '14.4567', 'y1' => '35.6362', 'y2' => '24.4924'], ''],
            ['line', ['x1' => '18.4271', 'x2' => '14.4567', 'y1' => '35.6694', 'y2' => '24.4924'], ''],
            ['line', ['x1' => '17.0988', 'x2' => '11.6921', 'y1' => '31.9306', 'y2' => '31.9306'], ''],
            ['line', ['x1' => '25.8582', 'x2' => '38.3148', 'y1' => '13.3468', 'y2' => '13.3468'], ''],
            ['line', ['x1' => '32.0865', 'x2' => '32.0865', 'y1' => '10.879', 'y2' => '13.3468'], ''],
            ['path', ['d' => self::geometry('M35.5727,13.3468c0,3.408-3.9563,9.0486-7.9125,9.91')], ''],
            ['path', ['d' => self::geometry('M28.2871,16.4414c.3917,2.35,4.4656,6.2674,8.089,6.8158')], ''],
            ['path', ['d' => self::geometry('M26.7456,34.933a5.1656,5.1656,0,0,0,5.1656-5.1655V27.1924')], ''],
            ['polyline', ['points' => self::geometry('29.581 29.522 31.911 27.192 34.242 29.522')], ''],
            ['path', ['d' => self::geometry('M19.5371,13.3468a5.1656,5.1656,0,0,0-5.1655,5.1656v2.5751')], ''],
            ['polyline', ['points' => self::geometry('16.701 18.758 14.372 21.087 12.04 18.758')], ''],
            ['path', ['d' => self::geometry('M40.5,5.5H7.5a2,2,0,0,0-2,2h0v33a2,2,0,0,0,2,2h33a2,2,0,0,0,2-2h0V7.5a2,2,0,0,0-2-2Z')], ''],
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
     * ModuleGroup.svg is the [n] logo as the Netresearch group icon in the
     * module menu: the frame in the brand teal and the letter in currentColor,
     * so the letter takes the menu's text colour. Pinned as its full tree, so
     * an added attribute, element, style, clip, mask or transform fails.
     */
    #[Test]
    public function moduleGroupIconIsTheLogoWithACurrentColorLetter(): void
    {
        $svg = $this->load(self::ICON_DIR . 'ModuleGroup.svg')->documentElement;
        self::assertInstanceOf(DOMElement::class, $svg);

        self::assertSame(
            [
                ['svg', 0, ['height' => '16', 'viewBox' => self::geometry('0 0 300 300'), 'width' => '16'], ''],
                ['path', 1, ['d' => self::geometry(self::BRAND_FRAME_PATH), 'fill' => self::BRAND_TEAL, 'transform' => self::geometry(self::BRAND_TRANSFORM)], ''],
                ['path', 1, ['d' => self::geometry(self::BRAND_LETTER_PATH), 'fill' => 'currentColor', 'transform' => self::geometry(self::BRAND_TRANSFORM)], ''],
            ],
            $this->normalisedTree($svg),
        );
    }

    /**
     * Extension.svg is the Netresearch [n] logo exactly as the
     * netresearch-branding skill specifies it (typo3-extension-branding.md):
     * frame #2F99A4, letter #585961, the only valid brand values for the
     * symbol. The Extension Manager shows it as <img>, so it cannot follow
     * the colour scheme. Measured with TYPO3 14.3.7's backend.css on the plain
     * rows of the extension list (striped and unstriped, at rest and hovered,
     * fresh, modern and classic theme), the lowest ratios are: frame 2.58:1
     * light / 3.91:1 dark, letter 5.32:1 light / 1.90:1 dark. WCAG 1.4.3 and
     * 1.4.11 exempt logotypes. Keeping the brand logo was the user's decision
     * over a non-brand #7b7b7b / #248791 pair (at least 3.11:1) and a teal
     * tile with a white letter (not a sanctioned form of the logo).
     */
    #[Test]
    public function extensionLogoIsExactlyTheBrandSpec(): void
    {
        $svg = $this->load(self::ICON_DIR . 'Extension.svg')->documentElement;
        self::assertInstanceOf(DOMElement::class, $svg);

        // Every element with every attribute, so an added opacity, stroke,
        // filter or element fails as well as a changed path or colour.
        self::assertSame(
            [
                ['svg', 0, ['viewBox' => '0 0 300 300'], ''],
                ['title', 1, [], 'Netresearch DTT GmbH'],
                ['path', 1, ['d' => self::geometry(self::BRAND_FRAME_PATH), 'fill' => self::BRAND_TEAL, 'transform' => self::geometry(self::BRAND_TRANSFORM)], ''],
                ['path', 1, ['d' => self::geometry(self::BRAND_LETTER_PATH), 'fill' => self::BRAND_ANTHRACITE, 'transform' => self::geometry(self::BRAND_TRANSFORM)], ''],
            ],
            $this->normalisedTree($svg),
        );
    }

    /**
     * Module.svg is the TextDB module icon, a tile in the brand teal with the
     * feature glyph drawn in white on top (typo3-extension-branding.md asks for
     * #2F99A4 in module icons). White on #2F99A4 is 3.38:1.
     */
    #[Test]
    public function moduleIconIsAWhiteGlyphOnTheBrandTealTile(): void
    {
        $svg = $this->load(self::ICON_DIR . 'Module.svg')->documentElement;
        self::assertInstanceOf(DOMElement::class, $svg);

        // Every element with every attribute: the #2F99A4 tile, the white
        // stroke of the glyph, its width, and no opacity or visibility
        // setting anywhere, so neither part can be recoloured, thinned out
        // or faded without failing here.
        self::assertSame(
            [
                ['svg', 0, ['height' => '64px', 'stroke-width' => '1.5', 'viewBox' => self::geometry('0 0 48.00 48.00'), 'width' => '64px'], ''],
                ['rect', 1, ['fill' => '#2F99A4', 'height' => '48.00', 'rx' => '0', 'stroke-width' => '0', 'width' => '48.00', 'x' => '0', 'y' => '0'], ''],
                ['g', 1, ['fill' => 'none', 'stroke' => '#ffffff', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round'], ''],
                ...self::at(2, self::translationGlyph()),
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
