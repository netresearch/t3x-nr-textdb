<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\ViewHelpers;

use JsonException;
use Netresearch\NrTextdb\Service\TranslationService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Fluid <a:textdb/> implementation
 * Provides a way to use textdb from fluid.
 *
 * @author  Thomas Schöne <thomas.schoene@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class TextdbViewHelper extends AbstractViewHelper
{
    /**
     * Translation service instance.
     *
     * @var TranslationService|null
     */
    protected ?TranslationService $translationService = null;

    /**
     * Initializes arguments (attributes).
     *
     * @return void
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument(
            'placeholder',
            'string',
            'TextDB value',
            true
        );

        $this->registerArgument(
            'type',
            'string',
            'TextDB type',
            true,
            'P'
        );

        $this->registerArgument(
            'component',
            'string',
            'TextDb component',
            true
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
     * Render translated string.
     *
     * @return string The translated key or tag body if key doesn't exist
     *
     * @throws IllegalObjectTypeException
     * @throws JsonException
     */
    public function render(): string
    {
        return $this->getTranslationService()
            ->translate(
                $this->arguments['placeholder'],
                $this->arguments['type'],
                $this->arguments['component'],
                $this->arguments['environment']
            );
    }

    /**
     * Getter for translationService.
     *
     * @return TranslationService
     */
    public function getTranslationService(): TranslationService
    {
        if (!$this->translationService instanceof TranslationService) {
            $this->translationService = GeneralUtility::makeInstance(TranslationService::class);
        }

        return $this->translationService;
    }
}
