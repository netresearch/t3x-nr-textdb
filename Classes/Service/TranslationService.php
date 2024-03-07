<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Service;

use JsonException;
use Netresearch\NrTextdb\Domain\Model\Component;
use Netresearch\NrTextdb\Domain\Model\Environment;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Model\Type;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;

/**
 * The translation service.
 *
 * @author  Thomas Schöne <thomas.schoene@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class TranslationService
{
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
     * @var SiteFinder
     */
    private readonly SiteFinder $siteFinder;

    /**
     * Translation constructor.
     *
     * @param EnvironmentRepository $environmentRepository
     * @param ComponentRepository   $componentRepository
     * @param TypeRepository        $typeRepository
     * @param TranslationRepository $translationRepository
     * @param SiteFinder            $siteFinder
     */
    public function __construct(
        EnvironmentRepository $environmentRepository,
        ComponentRepository $componentRepository,
        TypeRepository $typeRepository,
        TranslationRepository $translationRepository,
        SiteFinder $siteFinder
    ) {
        $this->environmentRepository = $environmentRepository;
        $this->componentRepository   = $componentRepository;
        $this->typeRepository        = $typeRepository;
        $this->translationRepository = $translationRepository;
        $this->siteFinder            = $siteFinder;
    }

    /**
     * Translate method.
     *
     * @param string $placeholder The translation key
     * @param string $type        List of parameters for translation string
     * @param string $component   Flag if to escape html
     * @param string $environment The type of translation is done
     *
     * @return string
     *
     * @throws IllegalObjectTypeException
     * @throws JsonException
     */
    public function translate(
        string $placeholder,
        string $type,
        string $component,
        string $environment
    ): string {
        if ($placeholder === '') {
            return $placeholder;
        }

        $environmentFound = $this->environmentRepository->findByName($environment);
        $componentFound   = $this->componentRepository->findByName($component);
        $typeFound        = $this->typeRepository->findByName($type);

        if (
            (!$environmentFound instanceof Environment)
            || (!$componentFound instanceof Component)
            || (!$typeFound instanceof Type)
        ) {
            return $placeholder;
        }

        $translation = $this->translationRepository->find(
            $environmentFound,
            $componentFound,
            $typeFound,
            $placeholder,
            $this->getCurrentLanguage()
        );

        if (!$translation instanceof Translation) {
            return $placeholder;
        }

        return $translation->getValue() !== '' ? $translation->getValue() : $translation->getPlaceholder();
    }

    /**
     * Get All languages, configured.
     *
     * @return SiteLanguage[]
     */
    public function getAllLanguages(): array
    {
        $sites = $this->siteFinder->getAllSites();

        return reset($sites)->getAllLanguages();
    }

    /**
     * Get the current language.
     *
     * @return int
     */
    protected function getCurrentLanguage(): int
    {
        try {
            /** @var Context $context */
            $context = GeneralUtility::makeInstance(Context::class);

            /** @var LanguageAspect $languageAspect */
            $languageAspect = $context->getAspect('language');
        } catch (AspectNotFoundException) {
            return 0;
        }

        return $languageAspect->getId();
    }
}
