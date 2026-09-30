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

namespace Netresearch\NrTextdb\ViewHelpers;

use function count;

use InvalidArgumentException;
use Netresearch\NrTextdb\Service\LabelTranslatorInterface;
use Netresearch\NrTextdb\Service\TranslationService;
use RuntimeException;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
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
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
final class TranslateViewHelper extends AbstractViewHelper
{
    private readonly TranslationService $translationService;

    /**
     * Component which can be used for a migration step.
     */
    public static string $component = '';

    private readonly LabelTranslatorInterface $labelTranslator;

    public function __construct(
        TranslationService $translationService,
        LabelTranslatorInterface $labelTranslator,
    ) {
        $this->translationService = $translationService;
        $this->labelTranslator    = $labelTranslator;
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
        if (self::$component === '') {
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
            self::$component,
            $environmentName,
        );

        // If the result is the placeholder itself (auto-created or missing),
        // try to return the LLL translation instead.
        if ($result === $textdbKey) {
            $lllTranslation = $this->translateLabel($placeholder, $extension);

            if ($lllTranslation !== null && $lllTranslation !== '') {
                return $lllTranslation;
            }
        }

        return $result;
    }

    /**
     * Resolves a label through LocalizationUtility, or returns null when the
     * key cannot be resolved to a language file.
     *
     * LocalizationUtility::translate() decides itself which keys it can resolve:
     * "LLL:EXT:…" keys, translation domain keys such as
     * "my_ext.messages:some.label", and bare keys together with an extension
     * name. For anything else it throws an InvalidArgumentException
     * (1498144052), which would abort the whole rendering instead of falling
     * through to the TextDB value.
     *
     * A key without any ":" and without an extension name is skipped up front:
     * LocalizationUtility always throws 1498144052 for it, and throwing and
     * catching that exception for every unresolved bare key on a page is
     * expensive. Every other key is handed to LocalizationUtility, because the
     * domain detection core uses (TranslationDomainResolver,
     * TranslationDomainMapper) is not public API.
     *
     * The exception is the safety net for the remaining keys core rejects.
     * Core's own f:translate ViewHelper catches every InvalidArgumentException;
     * this one deliberately catches only code 1498144052 and rethrows anything
     * else, so an unrelated error is not silently turned into the placeholder.
     */
    private function translateLabel(string $key, ?string $extensionName): ?string
    {
        if (!str_contains($key, ':') && ($extensionName === null || $extensionName === '')) {
            return null;
        }

        try {
            return $this->labelTranslator->translate($key, $extensionName);
        } catch (InvalidArgumentException $exception) {
            if ($exception->getCode() !== 1498144052) {
                throw $exception;
            }

            return null;
        }
    }
}
