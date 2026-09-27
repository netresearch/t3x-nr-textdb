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

        $painted = $xpath->query('//*[@stroke or @fill]');
        self::assertNotFalse($painted);
        self::assertGreaterThan(0, $painted->length);

        foreach ($painted as $element) {
            self::assertInstanceOf(DOMElement::class, $element);

            foreach (['stroke', 'fill'] as $attribute) {
                if (!$element->hasAttribute($attribute)) {
                    continue;
                }

                self::assertContains(
                    $element->getAttribute($attribute),
                    ['currentColor', 'none'],
                    $table . '.svg: ' . $attribute . ' must follow the backend colour scheme',
                );
            }
        }
    }

    #[Test]
    public function moduleGroupIconDrawsTheGlyphInCurrentColorAndKeepsTheAccent(): void
    {
        $svg   = $this->load(self::ICON_DIR . 'ModuleGroup.svg');
        $fills = [];

        foreach ($svg->getElementsByTagName('path') as $path) {
            $fills[] = $path->getAttribute('fill');
        }

        self::assertSame(['#2999a4', 'currentColor'], $fills);
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
