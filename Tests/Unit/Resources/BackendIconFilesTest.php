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
    public function extensionLogoUsesTheBrandColours(): void
    {
        $paths = $this->load(self::ICON_DIR . 'Extension.svg')->getElementsByTagName('path');
        self::assertSame(2, $paths->length);

        $expected = [self::BRAND_TEAL, self::BRAND_ANTHRACITE];

        foreach ($paths as $index => $path) {
            self::assertSame(['fill' => $expected[$index]], $this->paintsOf($path), 'Extension.svg path ' . $index);
        }
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
