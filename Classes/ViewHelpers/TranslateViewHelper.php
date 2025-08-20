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
use Netresearch\NrTextdb\Domain\Model\Component;
use Netresearch\NrTextdb\Domain\Model\Environment;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Model\Type;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use Netresearch\NrTextdb\Service\TranslationService;
use RuntimeException;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

use function count;

/**
 * Fluid <a:translate/> implementation
 * Provides a way import LLL Keys from f:translate to textdb.
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
     * @var EnvironmentRepository
     */
    private readonly EnvironmentRepository $environmentRepository;

    /**
     * @var ComponentRepository
     */
    private readonly ComponentRepository $componentRepository;

    /**
     * @var TypeRepository
     */
    private readonly TypeRepository $typeRepository;

    /**
     * @var TranslationRepository
     */
    private readonly TranslationRepository $translationRepository;

    /**
     * @var TranslationService
     */
    private readonly TranslationService $translationService;

    /**
     * Component which can be used for a migration step.
     *
     * @var string
     */
    public static string $component = '';

    /**
     * Translation constructor.
     *
     * @param EnvironmentRepository $environmentRepository
     * @param ComponentRepository   $componentRepository
     * @param TypeRepository        $typeRepository
     * @param TranslationRepository $translationRepository
     * @param TranslationService    $translationService
     */
    public function __construct(
        EnvironmentRepository $environmentRepository,
        ComponentRepository $componentRepository,
        TypeRepository $typeRepository,
        TranslationRepository $translationRepository,
        TranslationService $translationService,
    ) {
        $this->environmentRepository = $environmentRepository;
        $this->componentRepository   = $componentRepository;
        $this->typeRepository        = $typeRepository;
        $this->translationRepository = $translationRepository;
        $this->translationService    = $translationService;
    }

    /**
     * Initializes arguments (attributes).
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
     * Render translated string.
     *
     * @return string The translated key or tag body if key doesn't exist
     *
     * @throws IllegalObjectTypeException
     */
    public function render(): string
    {
        if (static::$component === '') {
            throw new RuntimeException(
                'Please set a component in your controller via TranslateViewHelper::$component = "my-component".'
            );
        }

        $placeholder = $this->arguments['key'];
        $extension   = $this->arguments['extensionName'] ?? null;

        $translationRequested = LocalizationUtility::translate($placeholder, $extension);
        $translationOriginal  = LocalizationUtility::translate($placeholder, $extension, []);

        $placeholderParts = explode(':', (string) $placeholder);
        if (count($placeholderParts) > 1) {
            $placeholder = $placeholderParts[3];
        }

        $environment = $this->environmentRepository->findByName($this->arguments['environment']);
        $component   = $this->componentRepository->findByName(static::$component);
        $type        = $this->typeRepository->findByName('label');

        if (
            !($environment instanceof Environment)
            || !($component instanceof Component)
            || !($type instanceof Type)
        ) {
            return $placeholder;
        }

        if ($this->hasTextDbEntry($environment, $component, $type, $placeholder)) {
            return (string) $translationRequested;
        }

        try {
            $this->translationService
                ->createTranslation(
                    $environment,
                    $component,
                    $type,
                    $placeholder,
                    $this->getLanguageUid(),
                    (string) $translationRequested
                );

            $this->translationService
                ->createTranslation(
                    $environment,
                    $component,
                    $type,
                    $placeholder,
                    self::LANGUAGE_UID_EN,
                    (string) $translationOriginal
                );
        } catch (Exception) {
        }

        return (string) $translationRequested;
    }

    /**
     * Returns the current language uid.
     *
     * @return int<-1, max>
     */
    private function getLanguageUid(): int
    {
        try {
            /** @var Context $context */
            $context = GeneralUtility::makeInstance(Context::class);

            /** @var LanguageAspect $languageAspect */
            $languageAspect = $context->getAspect('language');
        } catch (AspectNotFoundException) {
            return 0;
        }

        return max(-1, $languageAspect->getId());
    }

    /**
     * Returns true, if a textdb translation exists.
     *
     * @param Environment $environment
     * @param Component   $component
     * @param Type        $type
     * @param string      $placeholder
     *
     * @return bool
     */
    private function hasTextDbEntry(
        Environment $environment,
        Component $component,
        Type $type,
        string $placeholder,
    ): bool {
        $textdbTranslation = $this->translationRepository
            ->findByEnvironmentComponentTypePlaceholderAndLanguage(
                $environment,
                $component,
                $type,
                $placeholder,
                $this->getLanguageUid()
            );

        return $textdbTranslation instanceof Translation;
    }
}
