<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Service;

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
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

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
        SiteFinder $siteFinder,
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
     * @param string $placeholder
     * @param string $typeName
     * @param string $componentName
     * @param string $environmentName
     *
     * @return string
     *
     * @throws IllegalObjectTypeException
     */
    public function translate(
        string $placeholder,
        string $typeName,
        string $componentName,
        string $environmentName,
    ): string {
        if ($placeholder === '') {
            return $placeholder;
        }

        $environment = $this->environmentRepository->findByName($environmentName);
        $component   = $this->componentRepository->findByName($componentName);
        $type        = $this->typeRepository->findByName($typeName);
        $languageUid = $this->getCurrentLanguage();

        if (
            !($environment instanceof Environment)
            || !($component instanceof Component)
            || !($type instanceof Type)
        ) {
            return $placeholder;
        }

        $translation = $this->translationRepository
            ->findByEnvironmentComponentTypePlaceholderAndLanguage(
                $environment,
                $component,
                $type,
                $placeholder,
                $languageUid
            );

        // Create a new translation
        if (
            !($translation instanceof Translation)
            && $this->translationRepository->getCreateIfMissing()
        ) {
            $translation = $this->createTranslation(
                $environment,
                $component,
                $type,
                $placeholder,
                $languageUid,
                Translation::AUTO_CREATE_IDENTIFIER
            );

            if ($languageUid !== 0) {
                // Look up parent translation (sys_language_uid = 0)
                $parentTranslation = $this->translationRepository
                    ->findByEnvironmentComponentTypeAndPlaceholder(
                        $environment,
                        $component,
                        $type,
                        $placeholder
                    );

                // No parent so far, create one to maintain translation order
                if (!($parentTranslation instanceof Translation)) {
                    $parentTranslation = $this->createTranslation(
                        $environment,
                        $component,
                        $type,
                        $placeholder,
                        0,
                        Translation::AUTO_CREATE_IDENTIFIER
                    );

                    $this->translationRepository->add($parentTranslation);

                    // Persist the new parent to ensure we got a valid UID for the new record
                    GeneralUtility::makeInstance(PersistenceManagerInterface::class)
                        ->persistAll();

                    $translation->setL10nParent($parentTranslation->getUid());
                }
            }

            $this->translationRepository->add($translation);

            // Persist the new translation record
            GeneralUtility::makeInstance(PersistenceManagerInterface::class)
                ->persistAll();
        }

        if (!($translation instanceof Translation)) {
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
    private function getCurrentLanguage(): int
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

    /**
     * Creates a new translation.
     *
     * @param Translation $parentTranslation The parent translation record
     * @param int         $sysLanguageUid    The uid of the language
     * @param string      $value             The value of the translation
     *
     * @return Translation|null
     */
    public function createTranslationFromParent(
        Translation $parentTranslation,
        int $sysLanguageUid,
        string $value,
    ): ?Translation {
        if (!($parentTranslation->getEnvironment() instanceof Environment)
            || !($parentTranslation->getComponent() instanceof Component)
            || !($parentTranslation->getType() instanceof Type)
        ) {
            return null;
        }

        $translation = GeneralUtility::makeInstance(Translation::class);
        $translation
            ->setEnvironment($parentTranslation->getEnvironment())
            ->setComponent($parentTranslation->getComponent())
            ->setType($parentTranslation->getType())
            ->setPlaceholder($parentTranslation->getPlaceholder())
            ->setValue($value)
            ->setSysLanguageUid($sysLanguageUid)
            ->setPid($this->environmentRepository->getConfiguredPageId());

        if ($sysLanguageUid !== 0) {
            $translation->setL10nParent($parentTranslation->getUid());
        }

        return $translation;
    }

    /**
     * Creates a new translation.
     *
     * @param Environment $environment    The environment of the translation
     * @param Component   $component      The component of the translation
     * @param Type        $type           The type of the translation
     * @param string      $placeholder    The placeholder of the translation
     * @param int         $sysLanguageUid The uid of the language
     * @param string      $value          The value of the translation
     *
     * @return Translation
     */
    public function createTranslation(
        Environment $environment,
        Component $component,
        Type $type,
        string $placeholder,
        int $sysLanguageUid = 0,
        string $value = '',
    ): Translation {
        $translation = GeneralUtility::makeInstance(Translation::class);
        $translation
            ->setEnvironment($environment)
            ->setComponent($component)
            ->setType($type)
            ->setPlaceholder($placeholder)
            ->setValue($value)
            ->setSysLanguageUid($sysLanguageUid)
            ->setPid($this->environmentRepository->getConfiguredPageId());

        if ($sysLanguageUid !== 0) {
            $parentTranslation = $this->translationRepository->findByEnvironmentComponentTypeAndPlaceholder(
                $environment,
                $component,
                $type,
                $placeholder
            );

            if ($parentTranslation instanceof Translation) {
                $translation->setL10nParent($parentTranslation->getUid());
            }
        }

        return $translation;
    }
}
