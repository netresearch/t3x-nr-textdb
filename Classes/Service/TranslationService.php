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
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * The translation service.
 *
 * @author  Thomas Schöne <thomas.schoene@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
class TranslationService
{
    private readonly EnvironmentRepository $environmentRepository;

    private readonly ComponentRepository $componentRepository;

    private readonly TypeRepository $typeRepository;

    private readonly TranslationRepository $translationRepository;

    private readonly SiteFinder $siteFinder;

    private readonly PersistenceManagerInterface $persistenceManager;

    private readonly Context $context;

    /**
     * In-memory translation cache to avoid repeated DB queries within a single request.
     *
     * @var array<string, string>
     */
    private array $translationCache = [];

    /**
     * In-memory cache of the fallback lookups, kept apart from the cache of
     * translate(): one request can ask for the same key with and without the
     * fallback (textdb:textdb and textdb:translate), and each must get its own
     * answer. Keyed by language, so the entry does not depend on the chain.
     *
     * @var array<string, string|null>
     */
    private array $fallbackCache = [];

    /**
     * Translation constructor.
     */
    public function __construct(
        EnvironmentRepository $environmentRepository,
        ComponentRepository $componentRepository,
        TypeRepository $typeRepository,
        TranslationRepository $translationRepository,
        SiteFinder $siteFinder,
        PersistenceManagerInterface $persistenceManager,
        Context $context,
    ) {
        $this->environmentRepository = $environmentRepository;
        $this->componentRepository   = $componentRepository;
        $this->typeRepository        = $typeRepository;
        $this->translationRepository = $translationRepository;
        $this->siteFinder            = $siteFinder;
        $this->persistenceManager    = $persistenceManager;
        $this->context               = $context;
    }

    /**
     * Translate method.
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

        $languageUid = $this->getCurrentLanguage();

        // Check in-memory cache first to avoid repeated DB queries within same request
        $cacheKey = sprintf('%s|%s|%s|%s|%d', $environmentName, $componentName, $typeName, $placeholder, $languageUid);
        if (array_key_exists($cacheKey, $this->translationCache)) {
            return $this->translationCache[$cacheKey];
        }

        $environment = $this->environmentRepository->findByName($environmentName);
        $component   = $this->componentRepository->findByName($componentName);
        $type        = $this->typeRepository->findByName($typeName);

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
                $languageUid,
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
                Translation::AUTO_CREATE_IDENTIFIER,
            );

            if ($languageUid !== 0) {
                // Look up parent translation (sys_language_uid = 0)
                $parentTranslation = $this->translationRepository
                    ->findByEnvironmentComponentTypeAndPlaceholder(
                        $environment,
                        $component,
                        $type,
                        $placeholder,
                    );

                // No parent so far, create one to maintain translation order
                if (!$parentTranslation instanceof Translation) {
                    $parentTranslation = $this->createTranslation(
                        $environment,
                        $component,
                        $type,
                        $placeholder,
                        0,
                        Translation::AUTO_CREATE_IDENTIFIER,
                    );

                    $this->translationRepository->add($parentTranslation);

                    // Persist the new parent to ensure we got a valid UID for the new record
                    $this->persistenceManager->persistAll();

                    $parentUid = $parentTranslation->getUid();
                    if ($parentUid !== null) {
                        $translation->setL10nParent($parentUid);
                    }
                }
            }

            $this->translationRepository->add($translation);

            // Persist the new translation record
            $this->persistenceManager->persistAll();
        }

        if (!$translation instanceof Translation) {
            $this->translationCache[$cacheKey] = $placeholder;

            return $placeholder;
        }

        $result = $translation->getValue() !== '' ? $translation->getValue() : $translation->getPlaceholder();

        // Store in cache for subsequent lookups within same request
        $this->translationCache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Returns the value of the first usable entry along the language fallback
     * chain of the current language, or null when there is none.
     *
     * The chain is the one of the current language aspect: its integer entries
     * in order, then the default language 0. The current language itself is
     * skipped, because translate() has already looked there. An entry is
     * usable when its value is not empty and it was not auto-created.
     *
     * Unlike translate(), it never creates a translation record. With
     * createIfMissing enabled, the repositories still create a missing
     * environment, component or type, as they do for translate().
     * Callers use it only where translate() returned the bare placeholder.
     */
    public function translateFromFallbackLanguages(
        string $placeholder,
        string $typeName,
        string $componentName,
        string $environmentName,
    ): ?string {
        if ($placeholder === '') {
            return null;
        }

        foreach ($this->getFallbackLanguages() as $languageUid) {
            $cacheKey = sprintf('%s|%s|%s|%s|%d', $environmentName, $componentName, $typeName, $placeholder, $languageUid);

            if (!array_key_exists($cacheKey, $this->fallbackCache)) {
                $this->fallbackCache[$cacheKey] = $this->findUsableValue(
                    $placeholder,
                    $typeName,
                    $componentName,
                    $environmentName,
                    $languageUid,
                );
            }

            if ($this->fallbackCache[$cacheKey] !== null) {
                return $this->fallbackCache[$cacheKey];
            }
        }

        return null;
    }

    /**
     * Returns the value of the entry in the given language, or null when the
     * entry does not exist, is empty or was auto-created.
     *
     * @param int<-1, max> $languageUid
     */
    private function findUsableValue(
        string $placeholder,
        string $typeName,
        string $componentName,
        string $environmentName,
        int $languageUid,
    ): ?string {
        $environment = $this->environmentRepository->findByName($environmentName);
        $component   = $this->componentRepository->findByName($componentName);
        $type        = $this->typeRepository->findByName($typeName);

        if (
            !($environment instanceof Environment)
            || !($component instanceof Component)
            || !($type instanceof Type)
        ) {
            return null;
        }

        $translation = $this->translationRepository
            ->findByEnvironmentComponentTypePlaceholderAndLanguage(
                $environment,
                $component,
                $type,
                $placeholder,
                $languageUid,
            );

        if (
            !($translation instanceof Translation)
            || $translation->isAutoCreated()
            || $translation->getValue() === ''
        ) {
            return null;
        }

        return $translation->getValue();
    }

    /**
     * Returns the languages to try after the current one: the integer entries
     * of the fallback chain in their order, then the default language 0. The
     * chain can also hold strings such as "pageNotFound" or "off"; they name
     * no language and are skipped.
     *
     * @return list<int<0, max>>
     */
    private function getFallbackLanguages(): array
    {
        try {
            /** @var LanguageAspect $languageAspect */
            $languageAspect = $this->context->getAspect('language');
        } catch (AspectNotFoundException) {
            return [];
        }

        // In the default language the list stays empty: 0 is the current
        // language and therefore skipped.
        $currentLanguage = $languageAspect->getId();
        $languages       = [];

        foreach ([...$languageAspect->getFallbackChain(), 0] as $languageUid) {
            // A language listed twice is not filtered here: the second lookup
            // is answered from the fallback cache.
            if (
                !is_int($languageUid)
                || $languageUid < 0
                || $languageUid === $currentLanguage
            ) {
                continue;
            }

            $languages[] = $languageUid;
        }

        return $languages;
    }

    /**
     * Returns the languages of the site that holds the configured storage
     * page, the site the module writes the translations to.
     *
     * When no storage page is configured, or it lies outside every site (a
     * folder at root level, for example), the languages of the first site
     * are returned, as before #137; there is no site to take them from.
     *
     * @return SiteLanguage[]
     */
    public function getAllLanguages(): array
    {
        $storagePageId = $this->environmentRepository->getConfiguredPageId();

        if ($storagePageId > 0) {
            try {
                return $this->siteFinder->getSiteByPageId($storagePageId)->getAllLanguages();
            } catch (SiteNotFoundException) {
                // Falls back to the first site below.
            }
        }

        $sites     = $this->siteFinder->getAllSites();
        $firstSite = reset($sites);

        return ($firstSite instanceof Site) ? $firstSite->getAllLanguages() : [];
    }

    /**
     * Get the current language.
     *
     * @return int<-1, max>
     */
    private function getCurrentLanguage(): int
    {
        try {
            /** @var LanguageAspect $languageAspect */
            $languageAspect = $this->context->getAspect('language');
        } catch (AspectNotFoundException) {
            return 0;
        }

        return max(-1, $languageAspect->getId());
    }

    /**
     * Creates a new translation.
     *
     * @param Translation  $parentTranslation The parent translation record
     * @param int<-1, max> $sysLanguageUid    The uid of the language
     * @param string       $value             The value of the translation
     */
    public function createTranslationFromParent(
        Translation $parentTranslation,
        int $sysLanguageUid,
        string $value,
    ): ?Translation {
        if (
            !($parentTranslation->getEnvironment() instanceof Environment)
            || !($parentTranslation->getComponent() instanceof Component)
            || !($parentTranslation->getType() instanceof Type)
        ) {
            return null;
        }

        $translation = new Translation();
        $translation
            ->setEnvironment($parentTranslation->getEnvironment())
            ->setComponent($parentTranslation->getComponent())
            ->setType($parentTranslation->getType())
            ->setPlaceholder($parentTranslation->getPlaceholder())
            ->setValue($value)
            ->setSysLanguageUid($sysLanguageUid)
            ->setPid($this->environmentRepository->getConfiguredPageId());

        if ($sysLanguageUid !== 0) {
            $parentUid = $parentTranslation->getUid();
            if ($parentUid !== null) {
                $translation->setL10nParent($parentUid);
            }
        }

        return $translation;
    }

    /**
     * Creates a new translation.
     *
     * @param Environment  $environment    The environment of the translation
     * @param Component    $component      The component of the translation
     * @param Type         $type           The type of the translation
     * @param string       $placeholder    The placeholder of the translation
     * @param int<-1, max> $sysLanguageUid The uid of the language
     * @param string       $value          The value of the translation
     */
    public function createTranslation(
        Environment $environment,
        Component $component,
        Type $type,
        string $placeholder,
        int $sysLanguageUid = 0,
        string $value = '',
    ): Translation {
        $translation = new Translation();
        $translation
            ->setEnvironment($environment)
            ->setComponent($component)
            ->setType($type)
            ->setPlaceholder($placeholder)
            ->setValue($value)
            ->setSysLanguageUid($sysLanguageUid)
            ->setPid($this->environmentRepository->getConfiguredPageId());

        if ($sysLanguageUid !== 0) {
            $parentTranslation = $this->translationRepository
                ->findByEnvironmentComponentTypeAndPlaceholder(
                    $environment,
                    $component,
                    $type,
                    $placeholder,
                );

            if ($parentTranslation instanceof Translation) {
                $parentUid = $parentTranslation->getUid();
                if ($parentUid !== null) {
                    $translation->setL10nParent($parentUid);
                }
            }
        }

        return $translation;
    }
}
