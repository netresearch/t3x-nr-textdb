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
            self::assertContains($value, ['#2999a4', 'none'], 'ModuleGroup.svg accent: ' . $where . ' must stay the brand teal');
        }

        self::assertSame('#2999a4', $accent->getAttribute('fill'));

        foreach ($this->paintsOf($glyph) as $where => $value) {
            self::assertContains($value, ['currentColor', 'none'], 'ModuleGroup.svg glyph: ' . $where . ' must follow the backend colour scheme');
        }

        self::assertSame('currentColor', $glyph->getAttribute('fill'));
    }

    /**
     * The Extension Manager shows the logo as <img>, so it cannot follow the
     * scheme through currentColor. Measured with TYPO3 14.3.7's backend.css on
     * the plain rows of the extension list (striped and unstriped, at rest and
     * hovered, fresh, modern and classic theme, light and dark scheme), both
     * fills stay at 3.11:1 or better; the original #595a62 / #2999a4 dropped
     * to 1.93:1 / 2.60:1. Not covered: the tinted "insecure" and "outdated"
     * rows, which appear only with TER data.
     */
    #[Test]
    public function extensionLogoUsesColoursThatHoldInBothSchemes(): void
    {
        $svg   = $this->load(self::ICON_DIR . 'Extension.svg');
        $fills = [];

        foreach ($svg->getElementsByTagName('path') as $path) {
            $fills[] = $path->getAttribute('fill');
        }

        self::assertSame(['#248791', '#7b7b7b'], $fills);
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
