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
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Console\Exception\RuntimeException;
use TYPO3\CMS\Core\Localization\Parser\XliffParser;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * The import service.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class ImportService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * @var PersistenceManagerInterface
     */
    private PersistenceManagerInterface $persistenceManager;

    /**
     * @var XliffParser
     */
    private XliffParser $xliffParser;

    /**
     * @var TranslationService
     */
    private TranslationService $translationService;

    /**
     * @var TranslationRepository
     */
    private TranslationRepository $translationRepository;

    /**
     * @var ComponentRepository
     */
    private ComponentRepository $componentRepository;

    /**
     * @var TypeRepository
     */
    private TypeRepository $typeRepository;

    /**
     * @var EnvironmentRepository
     */
    private EnvironmentRepository $environmentRepository;

    /**
     * Constructor.
     *
     * @param PersistenceManagerInterface $persistenceManager
     * @param XliffParser                 $xliffParser
     * @param TranslationService          $translationService
     * @param TranslationRepository       $translationRepository
     * @param ComponentRepository         $componentRepository
     * @param TypeRepository              $typeRepository
     * @param EnvironmentRepository       $environmentRepository
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
     *
     * @return void
     */
    public function importFile(
        string $file,
        bool $forceUpdate,
        int &$imported,
        int &$updated,
        array &$errors
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
     * @param int         $languageUid
     * @param string|null $componentName
     * @param string|null $typeName
     * @param string      $placeholder
     * @param string      $value
     * @param bool        $forceUpdate
     * @param int         $imported
     * @param int         $updated
     * @param string[]    $errors
     *
     * @return void
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
        array &$errors
    ): void {
        try {
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
                ($environment === null)
                || ($component === null)
                || ($type === null)
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
            // TODO Add parent record if not present, if option "overwrite auto created" is true

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
                        $translation->setL10nParent($parentTranslation->getUid());
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

    /**
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
     * @return int
     */
    private function getLanguageId(string $languageCode): int
    {
        if ($languageCode === 'default') {
            $languageCode = 'en';
        }

        foreach ($this->getAllLanguages() as $localLanguage) {
            if ($languageCode === $localLanguage->getLocale()->getLanguageCode()) {
                return $localLanguage->getLanguageId();
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

        $sites = $siteFinder->getAllSites();

        return reset($sites)->getAllLanguages();
    }

    /**
     * Get the component from key.
     *
     * @param string $key
     *
     * @return string|null
     */
    private function getComponentFromKey(string $key): ?string
    {
        $parts = explode('|', $key);

        return $parts[0] ?? null;
    }

    /**
     * Get the type from a key.
     *
     * @param string $key
     *
     * @return string|null
     */
    private function getTypeFromKey(string $key): ?string
    {
        $parts = explode('|', $key);

        return $parts[1] ?? null;
    }

    /**
     * Get the placeholder from key.
     *
     * @param string $key
     *
     * @return string|null
     */
    private function getPlaceholderFromKey(string $key): ?string
    {
        $parts = explode('|', $key);

        return $parts[2] ?? null;
    }
}
