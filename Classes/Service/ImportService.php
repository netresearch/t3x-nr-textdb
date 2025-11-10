<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Service;

use Exception;
use Netresearch\NrTextdb\Domain\Model\Component;
use Netresearch\NrTextdb\Domain\Model\Environment;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Model\Type;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use RuntimeException;
use TYPO3\CMS\Core\Localization\Parser\XliffParser;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

use function count;

/**
 * The import service.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class ImportService
{
    private readonly PersistenceManagerInterface $persistenceManager;

    private readonly XliffParser $xliffParser;

    private readonly TranslationService $translationService;

    private readonly TranslationRepository $translationRepository;

    private readonly ComponentRepository $componentRepository;

    private readonly TypeRepository $typeRepository;

    private readonly EnvironmentRepository $environmentRepository;

    /**
     * Constructor.
     */
    public function __construct(
        PersistenceManagerInterface $persistenceManager,
        XliffParser $xliffParser,
        TranslationService $translationService,
        TranslationRepository $translationRepository,
        ComponentRepository $componentRepository,
        TypeRepository $typeRepository,
        EnvironmentRepository $environmentRepository,
    ) {
        $this->persistenceManager    = $persistenceManager;
        $this->xliffParser           = $xliffParser;
        $this->translationService    = $translationService;
        $this->translationRepository = $translationRepository;
        $this->componentRepository   = $componentRepository;
        $this->typeRepository        = $typeRepository;
        $this->environmentRepository = $environmentRepository;
    }

    /**
     * Imports a XLIFF file.
     *
     * @param string   $file        The file to import
     * @param bool     $forceUpdate TRUE to force update of existing records
     * @param int      $imported    The number of imported entries
     * @param int      $updated     The number of updated entries
     * @param string[] $errors      The error messages during import
     */
    public function importFile(
        string $file,
        bool $forceUpdate,
        int &$imported,
        int &$updated,
        array &$errors,
    ): void {
        $languageKey = $this->getLanguageKeyFromFile($file);
        $languageUid = $this->getLanguageId($languageKey);
        $fileContent = $this->xliffParser->getParsedData($file, $languageKey);
        $entries     = $fileContent[$languageKey];

        foreach ($entries as $key => $data) {
            $componentName = $this->getComponentFromKey($key);
            if ($componentName === null) {
                throw new RuntimeException('Missing component name in key: ' . $key);
            }

            $typeName = $this->getTypeFromKey($key);
            if ($typeName === null) {
                throw new RuntimeException('Missing type name in key: ' . $key);
            }

            $placeholder = $this->getPlaceholderFromKey($key);
            if ($placeholder === null) {
                throw new RuntimeException('Missing placeholder in key: ' . $key);
            }

            $value = $data[0]['target'] ?? null;
            if ($value === null) {
                throw new RuntimeException('Missing value in key: ' . $key);
            }

            $this->importEntry(
                $languageUid,
                $componentName,
                $typeName,
                $placeholder,
                $value,
                $forceUpdate,
                $imported,
                $updated,
                $errors
            );
        }
    }

    /**
     * Imports a single entry into the database.
     *
     * @param int<-1, max> $languageUid
     * @param string[]     $errors
     */
    public function importEntry(
        int $languageUid,
        ?string $componentName,
        ?string $typeName,
        string $placeholder,
        string $value,
        bool $forceUpdate,
        int &$imported,
        int &$updated,
        array &$errors,
    ): void {
        try {
            if ($componentName === null || $typeName === null) {
                return;
            }

            $environment = $this->environmentRepository
                ->setCreateIfMissing(true)
                ->findByName('default');

            $component = $this->componentRepository
                ->setCreateIfMissing(true)
                ->findByName($componentName);

            $type = $this->typeRepository
                ->setCreateIfMissing(true)
                ->findByName($typeName);

            if (
                (!$environment instanceof Environment)
                || (!$component instanceof Component)
                || (!$type instanceof Type)
            ) {
                return;
            }

            // Find existing translation record
            $translation = $this->translationRepository
                ->findByEnvironmentComponentTypePlaceholderAndLanguage(
                    $environment,
                    $component,
                    $type,
                    $placeholder,
                    $languageUid
                );

            if (
                ($translation instanceof Translation)
                && $translation->isAutoCreated()
            ) {
                $forceUpdate = true;
            }

            // Skip if translation exists and update is not requested
            if (
                ($translation instanceof Translation)
                && ($forceUpdate === false)
            ) {
                return;
            }

            // TODO Add option to overwrite auto created records
            // @see https://github.com/netresearch/t3x-nr-textdb/issues/28
            // TODO Add parent record if not present, if option "overwrite auto created" is true
            // @see https://github.com/netresearch/t3x-nr-textdb/issues/29

            if ($translation instanceof Translation) {
                $translation->setValue($value);

                if ($languageUid !== 0) {
                    // Look up parent translation (sys_language_uid = 0)
                    $parentTranslation = $this->translationRepository
                        ->findByEnvironmentComponentTypeAndPlaceholder(
                            $environment,
                            $component,
                            $type,
                            $placeholder
                        );

                    if ($parentTranslation instanceof Translation) {
                        $parentUid = $parentTranslation->getUid();
                        if ($parentUid !== null) {
                            $translation->setL10nParent($parentUid);
                        }
                    }
                }

                $this->translationRepository->update($translation);

                ++$updated;
            } else {
                $translation = $this->translationService
                    ->createTranslation(
                        $environment,
                        $component,
                        $type,
                        $placeholder,
                        $languageUid,
                        $value
                    );

                $this->translationRepository->add($translation);

                ++$imported;
            }

            $this->persistenceManager->persistAll();
        } catch (Exception $exception) {
            $errors[] = $exception->getMessage();
        }
    }

    /**<
     * Returns the langauge key from the file name.
     *
     * @param string $file
     *
     * @return string
     */
    private function getLanguageKeyFromFile(string $file): string
    {
        $fileParts = explode('.', basename($file));

        if (count($fileParts) < 3) {
            return 'default';
        }

        return $fileParts[0];
    }

    /**
     * Returns the sys_language_uid for a language code.
     *
     * @param string $languageCode Language Code
     *
     * @return int<-1, max>
     */
    private function getLanguageId(string $languageCode): int
    {
        if ($languageCode === 'default') {
            $languageCode = 'en';
        }

        foreach ($this->getAllLanguages() as $localLanguage) {
            if ($languageCode === $localLanguage->getLocale()->getLanguageCode()) {
                return max(-1, $localLanguage->getLanguageId());
            }
        }

        return 0;
    }

    /**
     * Get all configured languages.
     *
     * @return SiteLanguage[]
     */
    private function getAllLanguages(): array
    {
        /** @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $sites      = $siteFinder->getAllSites();
        $firstSite  = reset($sites);

        return ($firstSite instanceof Site) ? $firstSite->getAllLanguages() : [];
    }

    /**
     * Get the component from a key.
     */
    private function getComponentFromKey(string $key): ?string
    {
        $parts = explode('|', $key);

        return ($parts[0] !== '') ? $parts[0] : null;
    }

    /**
     * Get the type from a key.
     */
    private function getTypeFromKey(string $key): ?string
    {
        $parts = explode('|', $key);

        return isset($parts[1]) && ($parts[1] !== '') ? $parts[1] : null;
    }

    /**
     * Get the placeholder from a key.
     */
    private function getPlaceholderFromKey(string $key): ?string
    {
        $parts = explode('|', $key);

        return isset($parts[2]) && ($parts[2] !== '') ? $parts[2] : null;
    }
}
