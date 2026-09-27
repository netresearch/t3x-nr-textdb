<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Functional\Imaging;

use Netresearch\NrTextdb\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;

/**
 * The record icons and the module group icon must reach the backend in a
 * form that inherits currentColor, otherwise they cannot follow the colour
 * scheme: <svg><use> or inline SVG, never <img>.
 */
#[CoversNothing]
final class BackendIconRegistrationTest extends AbstractFunctionalTestCase
{
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

    #[Test]
    #[DataProvider('recordTableProvider')]
    public function recordIconRendersAsSpriteUseInBothMarkups(string $table): void
    {
        $icon = $this->get(IconFactory::class)->getIconForRecord($table, ['uid' => 1, 'pid' => 0], IconSize::SMALL);

        foreach (['default' => $icon->render(), 'inline' => $icon->render('inline')] as $variant => $markup) {
            self::assertStringNotContainsString('<img', $markup, $variant . ' markup of ' . $table);
            self::assertMatchesRegularExpression(
                // TYPO3 inserts a cache-busting query string before the fragment.
                '/<use [^>]*href="[^"]*' . preg_quote($table, '/') . '\.svg(\?[^"#]*)?#' . preg_quote($table, '/') . '"/',
                $markup,
                $variant . ' markup of ' . $table,
            );
        }
    }

    #[Test]
    public function moduleGroupIconInlineMarkupDrawsTheGlyphInCurrentColor(): void
    {
        $icon = $this->get(IconFactory::class)->getIcon('extension-netresearch-module', IconSize::SMALL);

        $markup = $icon->getAlternativeMarkup('inline');

        self::assertStringContainsString('<svg', $markup);
        self::assertStringContainsString('fill="currentColor"', $markup);
        self::assertStringContainsString('fill="#2F99A4"', $markup);
    }
}
