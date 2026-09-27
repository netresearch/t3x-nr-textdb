<?php

/*
 * This file is part of the package netresearch/nr-textdb.
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

    private const PAINT_PROPERTIES = ['stroke', 'fill', 'color', 'stop-color', 'flood-color', 'lighting-color'];

    private const ICON_DIR = __DIR__ . '/../../../Resources/Public/Icons/';

    /**
     * @return iterable<string, array{string}>
     */
    public static function recordTableProvider(): iterable
    {
        foreach (['component', 'environment', 'translation', 'type'] as $type) {
            $table = 'tx_nrtextdb_domain_model_' . $type;

            yield $table => [$table];
        }
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

    #[Test]
    #[DataProvider('iconFileProvider')]
    public function iconHasNoStyleBlock(string $file): void
    {
        $svg = $this->load($file);

        self::assertSame(0, $svg->getElementsByTagName('style')->length, basename($file) . ' must not carry a <style> block');
    }

    #[Test]
    #[DataProvider('recordTableProvider')]
    public function recordIconIsASymbolDrawnInCurrentColor(string $table): void
    {
        $svg   = $this->load(self::ICON_DIR . $table . '.svg');
        $xpath = new DOMXPath($svg);
        $xpath->registerNamespace('svg', 'http://www.w3.org/2000/svg');

        // The icon registry references "<file>#<table>", so the symbol must carry that id.
        $symbol = $xpath->query('//svg:symbol[@id="' . $table . '"]');
        self::assertNotFalse($symbol);
        self::assertSame(1, $symbol->length, 'sprite symbol #' . $table . ' is missing');

        $painted = $xpath->query('//*[@stroke or @fill or @color or @style]');
        self::assertNotFalse($painted);
        self::assertGreaterThan(0, $painted->length);

        foreach ($painted as $element) {
            self::assertInstanceOf(DOMElement::class, $element);

            foreach ($this->paintsOf($element) as $where => $value) {
                self::assertContains($value, ['currentColor', 'none'], $table . '.svg: ' . $where . ' must follow the backend colour scheme');
            }
        }
    }

    #[Test]
    public function moduleGroupIconDrawsTheGlyphInCurrentColorAndKeepsTheAccent(): void
    {
        $paths = $this->load(self::ICON_DIR . 'ModuleGroup.svg')->getElementsByTagName('path');
        self::assertSame(2, $paths->length);

        [$accent, $glyph] = [$paths->item(0), $paths->item(1)];
        self::assertInstanceOf(DOMElement::class, $accent);
        self::assertInstanceOf(DOMElement::class, $glyph);

        foreach ($this->paintsOf($accent) as $where => $value) {
            self::assertContains($value, [self::BRAND_TEAL, 'none'], 'ModuleGroup.svg accent: ' . $where . ' must stay the brand teal');
        }

        self::assertSame(self::BRAND_TEAL, $accent->getAttribute('fill'));

        foreach ($this->paintsOf($glyph) as $where => $value) {
            self::assertContains($value, ['currentColor', 'none'], 'ModuleGroup.svg glyph: ' . $where . ' must follow the backend colour scheme');
        }

        self::assertSame('currentColor', $glyph->getAttribute('fill'));
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
                ['svg', ['viewBox' => '0 0 300 300'], ''],
                ['title', [], 'Netresearch DTT GmbH'],
                ['path', ['d' => self::BRAND_FRAME_PATH, 'fill' => self::BRAND_TEAL, 'transform' => self::BRAND_TRANSFORM], ''],
                ['path', ['d' => self::BRAND_LETTER_PATH, 'fill' => self::BRAND_ANTHRACITE, 'transform' => self::BRAND_TRANSFORM], ''],
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

        $tiles = $svg->getElementsByTagName('rect');
        self::assertSame(1, $tiles->length);

        $tile = $tiles->item(0);
        self::assertInstanceOf(DOMElement::class, $tile);
        self::assertSame(['fill' => self::BRAND_TEAL], $this->paintsOf($tile));

        $glyph = $svg->getElementsByTagName('g');
        self::assertSame(1, $glyph->length);

        $group = $glyph->item(0);
        self::assertInstanceOf(DOMElement::class, $group);
        self::assertSame(['stroke' => '#ffffff', 'fill' => 'none'], $this->paintsOf($group));

        foreach ($group->getElementsByTagName('*') as $shape) {
            self::assertSame([], $this->paintsOf($shape), 'Module.svg: glyph shapes inherit the white stroke');
        }
    }

    /**
     * @return list<array{string, array<string, string>, string}>
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
            $attributes = [];

            foreach ($element->attributes as $attribute) {
                $attributes[$attribute->nodeName] = $attribute->nodeValue ?? '';
            }

            ksort($attributes);

            $text   = $element->localName === 'title' ? trim($element->textContent) : '';
            $tree[] = [(string) $element->localName, $attributes, $text];
        }

        return $tree;
    }

    /**
     * The colours an element paints with: its stroke, fill and color
     * attributes, and the colour properties of its style attribute, which
     * win over the attributes.
     *
     * @return array<string, string> where => value, "!important" removed
     */
    private function paintsOf(DOMElement $element): array
    {
        $paints = [];

        foreach (['stroke', 'fill', 'color'] as $attribute) {
            if ($element->hasAttribute($attribute)) {
                $paints[$attribute] = $element->getAttribute($attribute);
            }
        }

        foreach (explode(';', $element->getAttribute('style')) as $declaration) {
            [$property, $value] = array_pad(explode(':', $declaration, 2), 2, null);
            $property           = strtolower(trim((string) $property));

            if ($value !== null && in_array($property, self::PAINT_PROPERTIES, true)) {
                $paints['style ' . $property] = $value;
            }
        }

        return array_map(
            static fn (string $value): string => trim(str_ireplace('!important', '', $value)),
            $paints,
        );
    }

    private function load(string $file): DOMDocument
    {
        $contents = file_get_contents($file);
        self::assertIsString($contents);

        $document = new DOMDocument();
        self::assertTrue($document->loadXML($contents), basename($file) . ' is not well-formed XML');

        return $document;
    }
}
