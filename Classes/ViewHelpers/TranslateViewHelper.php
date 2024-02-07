<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\ViewHelpers;

use Exception;
use JsonException;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use RuntimeException;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

use function count;

/**
 * Fluid <a:translate/> implementation
 * Provides a way import LLL Keys from f:translate to textdb
 *
 * @author  Tobias Hein <tobias.hein@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class TranslateViewHelper extends AbstractViewHelper
{
    /**
     * English UID to import.
     *
     * @var int
     */
    final public const LANGUAGE_UID_EN = 1;

    /**
     * Translation service instance
     *
     * @var null|TranslationRepository
     */
    protected ?TranslationRepository $translationRepository = null;

    /**
     * Component which can be used for a migration step
     *
     * @var string
     */
    public static string $component = '';

    /**
     * Initializes arguments (attributes)
     *
     * @return void
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument(
            'key',
            'string',
            'key file',
            true
        );

        $this->registerArgument(
            'extensionName',
            'string',
            'extensionName'
        );

        $this->registerArgument(
            'environment',
            'string',
            'TextDB environment',
            false,
            'default'
        );
    }

    /**
     * Render translated string
     *
     * @return string The translated key or tag body if key doesn't exist
     *
     * @throws IllegalObjectTypeException
     * @throws JsonException
     */
    public function render(): string
    {
        if (static::$component === '') {
            throw new RuntimeException(
                'Please set a component in your controler via TranslateViewHelper::component="my-component".'
            );
        }

        $placeholder = $this->arguments['key'];
        $extension   = $this->arguments['extensionName'] ?: null;
        $environment = $this->arguments['environment'];

        $translationRequested = LocalizationUtility::translate(
            $placeholder,
            $extension
        );

        $translationOriginal = LocalizationUtility::translate(
            $placeholder,
            $extension,
            []
        );

        $placeholderParts = explode(':', (string) $placeholder);
        if (count($placeholderParts) > 1) {
            $placeholder = $placeholderParts[3];
        }

        if ($this->hasTextDbEntry($placeholder)) {
             return (string) $translationRequested;
        }

        try {
            $this->getTranslationRepository()
                ->createTranslation(
                    static::$component,
                    $environment,
                    'label',
                    $placeholder,
                    $this->getLanguageUid(),
                    (string) $translationRequested
                );

            $this->getTranslationRepository()
                ->createTranslation(
                    static::$component,
                    $environment,
                    'label',
                    $placeholder,
                    self::LANGUAGE_UID_EN,
                    (string) $translationOriginal
                );
        } catch (Exception) {
        }

        return (string) $translationRequested;
    }

    /**
     * Getter for translationRepository.
     *
     * @return TranslationRepository
     */
    public function getTranslationRepository(): TranslationRepository
    {
        if (!isset($this->translationRepository)) {
            $this->translationRepository = GeneralUtility::makeInstance(TranslationRepository::class);
        }

        return $this->translationRepository;
    }

    /**
     * Returns the current language uid.
     *
     * @return int
     */
    private function getLanguageUid(): int
    {
        try {
            /** @var Context $context */
            $context        = GeneralUtility::makeInstance(Context::class);
            $languageAspect = $context->getAspect('language');
        } catch (AspectNotFoundException) {
            return 0;
        }

        return $languageAspect->getId();
    }

    /**
     * Returns true, if a textdb translation exisits
     *
     * @param string $placeholder
     *
     * @return bool
     *
     * @throws IllegalObjectTypeException
     * @throws JsonException
     */
    private function hasTextDbEntry(string $placeholder): bool
    {
        $textdbTranslation = $this->getTranslationRepository()->findEntry(
            static::$component,
            $this->arguments['environment'],
            'label',
            $placeholder,
            $this->getLanguageUid(),
            false
        );

        return $textdbTranslation !== null;
    }
}
