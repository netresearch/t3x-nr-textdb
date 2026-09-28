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
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Imaging\IconSize;

/**
 * The module menu renders the group icon with the "inline" markup. Only
 * inline SVG inherits currentColor, so the letter of the [n] logo follows the
 * backend colour scheme while the frame keeps the brand teal.
 */
#[CoversNothing]
final class ModuleGroupIconTest extends AbstractFunctionalTestCase
{
    /**
     * The base class leaves out extensionmanager, so the container cannot
     * autowire ImportCommand and every test is skipped during setup. Loading
     * it here makes these tests run.
     *
     * @var non-empty-string[]
     */
    protected array $coreExtensionsToLoad = [
        'extbase',
        'fluid',
        'extensionmanager',
    ];

    #[Test]
    public function groupModuleUsesTheSharedGroupIcon(): void
    {
        $module = $this->get(ModuleProvider::class)->getModule('netresearch_module');

        self::assertNotNull($module);
        self::assertSame('extension-netresearch-module', $module->getIconIdentifier());
    }

    #[Test]
    public function moduleGroupIconInlineMarkupDrawsTheLetterInCurrentColor(): void
    {
        $icon = $this->get(IconFactory::class)->getIcon('extension-netresearch-module', IconSize::MEDIUM);

        $markup = $icon->getAlternativeMarkup('inline');

        self::assertStringContainsString('<svg', $markup);
        self::assertStringContainsString('fill="currentColor"', $markup);
        self::assertStringContainsString('fill="#2F99A4"', $markup);
    }
}
