<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\ViewHelpers;

use function count;

use Netresearch\NrTextdb\Service\TranslationService;
use RuntimeException;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Fluid <a:translate/> implementation
 * Provides a way import LLL Keys from f:translate to textdb.
 *
 * On first render, this ViewHelper imports the LLL translation into the TextDB
 * database. On subsequent renders it returns the value from TextDB via the
 * cached TranslationService, avoiding redundant DB queries.
 *
 * @author  Tobias Hein <tobias.hein@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 */
class TranslateViewHelper extends AbstractViewHelper
{
    private readonly TranslationService $translationService;

    /**
     * Component which can be used for a migration step.
     */
    public static string $component = '';

    public function __construct(
        TranslationService $translationService,
    ) {
        $this->translationService = $translationService;
    }

    /**
     * Initializes arguments (attributes).
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument(
            'key',
            'string',
            'key file',
            true,
        );

        $this->registerArgument(
            'extensionName',
            'string',
            'extensionName',
        );

        $this->registerArgument(
            'environment',
            'string',
            'TextDB environment',
            false,
            'default',
        );
    }

    /**
     * Render translated string.
     *
     * Delegates to TranslationService::translate() which uses the in-memory
     * translation cache. If the entry does not exist and createIfMissing is
     * enabled, the LLL value is used as the initial value for the auto-created
     * TextDB record.
     *
     * @return string The translated key or tag body if key doesn't exist
     *
     * @throws IllegalObjectTypeException
     */
    public function render(): string
    {
        if (static::$component === '') {
            throw new RuntimeException(
                'Please set a component in your controller via TranslateViewHelper::$component = "my-component".',
            );
        }

        $placeholder = $this->arguments['key'];
        $extension   = $this->arguments['extensionName'] ?? null;

        assert(is_string($placeholder));
        assert(is_string($extension) || $extension === null);

        // Extract the actual key from LLL:EXT:ext_name/path.xlf:key format
        $placeholderParts = explode(':', $placeholder);
        $textdbKey        = $placeholder;

        if (count($placeholderParts) > 3) {
            $textdbKey = implode(':', array_slice($placeholderParts, 3));
        }

        $environmentName = $this->arguments['environment'];
        assert(is_string($environmentName));

        // Delegate to TranslationService which has in-memory caching
        $result = $this->translationService->translate(
            $textdbKey,
            'label',
            static::$component,
            $environmentName,
        );

        // If the result is the placeholder itself (auto-created or missing),
        // try to return the LLL translation instead
        if ($result === $textdbKey) {
            $lllTranslation = LocalizationUtility::translate($placeholder, $extension);

            if ($lllTranslation !== null && $lllTranslation !== '') {
                return $lllTranslation;
            }
        }

        return $result;
    }
}
