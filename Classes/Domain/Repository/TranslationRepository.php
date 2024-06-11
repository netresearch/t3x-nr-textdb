<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Domain\Repository;

use JsonException;
use Netresearch\NrTextdb\Domain\Model\Component;
use Netresearch\NrTextdb\Domain\Model\Environment;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Model\Type;
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;


use TYPO3\CMS\Core\Database\ConnectionPool;
use Doctrine\DBAL\ParameterType;

use function count;
use function func_get_args;

/**
 * TranslationRepository.
 *
 * @author  Thomas Schöne <thomas.schoene@netresearch.de>
 * @author  Axel Seemann <axel.seemann@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 *
 * @template T of \Netresearch\NrTextdb\Domain\Model\Translation
 *
 * @extends AbstractRepository<T>
 */
class TranslationRepository extends AbstractRepository
{
    final public const AUTO_CREATE_IDENTIFIER = 'auto-created-by-repository';

    /**
     * @var ComponentRepository
     */
    private readonly ComponentRepository $componentRepository;

    /**
     * @var EnvironmentRepository
     */
    private readonly EnvironmentRepository $environmentRepository;

    /**
     * @var TypeRepository
     */
    private readonly TypeRepository $typeRepository;

    /**
     * @var Translation[]
     */
    public static array $localCache = [];

    /**
     * TranslationRepository constructor.
     *
     * @param ComponentRepository   $componentRepository
     * @param EnvironmentRepository $environmentRepository
     * @param TypeRepository        $typeRepository
     */
    public function __construct(
        ComponentRepository $componentRepository,
        EnvironmentRepository $environmentRepository,
        TypeRepository $typeRepository
    ) {
        parent::__construct();

        $this->componentRepository   = $componentRepository;
        $this->environmentRepository = $environmentRepository;
        $this->typeRepository        = $typeRepository;
    }

    /**
     * Initialize the object.
     *
     * @return void
     */
    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $querySettings->setRespectSysLanguage(true);

        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Returns all objects of this repository.
     *
     * @return QueryResultInterface
     */
    public function findAllWithHidden(): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setIgnoreEnableFields(true);

        return $query->execute();
    }

    /**
     * Find all records for a given language.
     *
     * @param int $languageUid
     *
     * @return QueryResultInterface
     */
    public function findAllByLanguage(int $languageUid): QueryResultInterface
    {
        $query = $this->createQuery();

        $query
            ->getQuerySettings()
            ->setLanguageAspect(
                new LanguageAspect(
                    $languageUid,
                    $languageUid,
                    $query->getQuerySettings()->getLanguageAspect()->getOverlayType()
                )
            );

        return $query->execute();
    }

    /**
     * Returns a translation.
     *
     * @param string $component   Component of the translation
     * @param string $environment Environment of the translation
     * @param string $type        Type of the translation
     * @param string $placeholder Value of the translation
     * @param int    $languageUid uid of the language
     * @param bool   $create      if set to true, translation will be automatically created if it is missing
     *
     * @return Translation|null
     *
     * @throws IllegalObjectTypeException
     * @throws JsonException
     */
    public function findEntry(
        string $component,
        string $environment,
        string $type,
        string $placeholder,
        int $languageUid,
        bool $create = true
    ): ?Translation {
        $cacheKey = md5(
            json_encode(
                func_get_args(),
                JSON_THROW_ON_ERROR
            )
        );

        $translation = $this->getFromCache($cacheKey);

        if ($translation instanceof Translation) {
            return $translation;
        }

        $query = $this->createQuery();

        $query
            ->getQuerySettings()
            ->setIgnoreEnableFields(true);

        $query
            ->getQuerySettings()
            ->setLanguageAspect(
                new LanguageAspect(
                    $languageUid,
                    $languageUid,
                    $query->getQuerySettings()->getLanguageAspect()->getOverlayType()
                )
            );

        $query->matching(
            $query->logicalAnd(
                $query->equals('placeholder', $placeholder),
                $query->equals('pid', $this->getConfiguredPageId()),
                $query->equals('type.name', $type),
                $query->equals('component.name', $component)
            )
        );

        $queryResult = $query->execute();

        /** @var Translation|null $translation */
        $translation = null;

        /** @var Translation $result */
        foreach ($queryResult as $result) {
            if ($result->getEnvironment()->getName() === $environment) {
                $translation = $result;
            } elseif ($result->getEnvironment()->getName() === 'default') {
                $translation = $result;
            }
        }

        if ($create === false) {
            return $translation;
        }

        if ($translation instanceof Translation) {
            if (
                $translation->isHidden()
                || $translation->isDeleted()
            ) {
                return $this->setToCache($cacheKey, new Translation());
            }

            return $this->setToCache($cacheKey, $translation);
        }

        return $this->setToCache(
            $cacheKey,
            $this->createTranslation(
                $component,
                $environment,
                $type,
                $placeholder,
                $languageUid,
                $placeholder
            )
        );
    }

    /**
     * Find a translation record.
     *
     * @param Environment $environment
     * @param Component   $component
     * @param Type        $type
     * @param string      $placeholder
     * @param int         $languageUid
     * @param bool        $skipCreation
     * @param bool        $fallback
     *
     * @return Translation|null
     *
     * @throws IllegalObjectTypeException
     * @throws JsonException
     */
    public function find(
        Environment $environment,
        Component $component,
        Type $type,
        string $placeholder,
        int $languageUid,
        bool $skipCreation = false,
        bool $fallback = true
    ): ?Translation {
        $cacheKey = md5(
            json_encode(
                func_get_args(),
                JSON_THROW_ON_ERROR
            )
        );

        $translation = $this->getFromCache($cacheKey);

        if ($translation instanceof Translation) {
            return $translation;
        }

        $query       = $this->createQuery();
        $overlayType = $query->getQuerySettings()->getLanguageAspect()->getOverlayType();

        if ($fallback) {
            $overlayType = 'content_fallback';
        }

        $query
            ->getQuerySettings()
            ->setLanguageAspect(
                new LanguageAspect(
                    $languageUid,
                    $languageUid,
                    $overlayType
                )
            );


        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation');

        $translationArray = $connection->executeQuery(
            'SELECT * FROM tx_nrtextdb_domain_model_translation 
            WHERE sys_language_uid = ?
            AND placeholder = ?
            AND pid = ?
            AND type = ?
            AND component = ?
            AND environment = ?',
            [$languageUid, $placeholder, $this->getConfiguredPageId(), $type->getUid(), $component->getUid(), $environment->getUid()]
        )->fetchAllAssociative();


        $translations = [];
        if ($translationArray) {
            foreach ($translationArray as $translationRecord) {
                $translation = new Translation();
                $translation->setUid((int)$translationRecord['uid']);
                $translation->setPlaceholder($translationRecord['placeholder']);
                $translation->setValue($translationRecord['value']);
                $translation->setL10nParent((int)$translationRecord['l10n_parent']);
                $translation->setSysLanguageUid((int)$translationRecord['sys_language_uid']);
                $translation->setLanguageUid((int)$translationRecord['sys_language_uid']);
                $translation->setLocalizedUid((int)$translationRecord['uid']);

                $translation->setType($type);
                $translation->setEnvironment($environment);
                $translation->setComponent($component);
                $translation->setPid($this->getConfiguredPageId());

                $translations[] = $translation;
            }
        }

        if (
            $skipCreation
            && (count($translations) === 0)
        ) {
            return null;
        }

        if (
            ($skipCreation === false)
            && (count($translations) === 0)
            && $this->getCreateIfMissing()
        ) {
            $translation = GeneralUtility::makeInstance(Translation::class);
            $translation->setEnvironment($environment);
            $translation->setComponent($component);
            $translation->setType($type);
            $translation->setPlaceholder($placeholder);
            $translation->setValue(self::AUTO_CREATE_IDENTIFIER);
            $translation->setPid($this->getConfiguredPageId());
            $translation->setLanguageUid(0);

            $this->add($translation);
            $this->persistenceManager->persistAll();

            return $this->setToCache($cacheKey, $translation);
        }

//        /** @var false|Translation $translation */
//        $translation = reset($translations);

        if ($translation === false) {
            return null;
        }

        return $this->setToCache($cacheKey, $translation);
    }

    /**
     * Set a translation to cache and return the translation.
     *
     * @param string      $key         Cache key
     * @param Translation $translation Translation to cache
     *
     * @return Translation
     */
    private function setToCache(string $key, Translation $translation): Translation
    {
        static::$localCache[$key] = $translation;

        return $translation;
    }

    /**
     * Returns a cached translation.
     *
     * @param string $key Cache key
     *
     * @return Translation|null
     */
    private function getFromCache(string $key): ?Translation
    {
        return static::$localCache[$key] ?? null;
    }

    /**
     * Create a new translation.
     *
     * @param string $component   Component of the translation
     * @param string $environment Environment of the translation
     * @param string $type        Type of the translation
     * @param string $placeholder Placeholder of the translation
     * @param int    $languageUid the uid of the language
     * @param string $value       Value of the translation
     *
     * @return Translation
     *
     * @throws IllegalObjectTypeException
     * @throws JsonException
     */
    public function createTranslation(
        string $component,
        string $environment,
        string $type,
        string $placeholder,
        int $languageUid,
        string $value = ''
    ): Translation {
        $pid = $this->getConfiguredPageId();

        $translation = new Translation();
        $translation->setPid($pid);
        $translation->setComponent($this->componentRepository->findByName($component));
        $translation->setEnvironment($this->environmentRepository->findByName($environment));
        $translation->setType($this->typeRepository->findByName($type));
        $translation->setPlaceholder($placeholder);
        $translation->setValue($value);
        $translation->setLanguageUid($languageUid);

        if ($languageUid !== 0) {
            $origTranslation = $this->findEntry(
                $component,
                $environment,
                $type,
                $placeholder,
                0
            );

            if ($origTranslation instanceof Translation) {
                $translation->setL10nParent($origTranslation->getUid());
            }
        }

        $this->add($translation);
        $this->persistenceManager->persistAll();

        return $translation;
    }

    /**
     * Returns an array with translations for a record.
     *
     * @param int $uid Uid of original
     *
     * @return Translation[]
     */
    public function getTranslatedRecords(int $uid): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation');

        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from('tx_nrtextdb_domain_model_translation')
            ->where(
                $queryBuilder->expr()->eq('l10n_parent', ':uid'),
                $queryBuilder->expr()->eq('pid', ':pid')
            )
            ->setParameter('uid', $uid, ParameterType::INTEGER)
            ->setParameter('pid', $this->getConfiguredPageId(), ParameterType::INTEGER);

        $translatedRecords = $queryBuilder->execute()->fetchAllAssociative();

        return $translatedRecords;
    }

    /**
     * Returns a record found by its uid without any restrictions.
     *
     * @param int $uid UID
     *
     * @return Translation|null
     */
    public function findRecordByUid(int $uid): array
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation');

        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder
            ->select('*')
            ->from('tx_nrtextdb_domain_model_translation')
            ->where(
                $queryBuilder->expr()->eq('uid', ':uid'),
            )
            ->setParameter('uid', $uid, ParameterType::INTEGER);

        $translatedRecord = $queryBuilder->execute()->fetchAssociative();

        return $translatedRecord;
    }

    /**
     * Returns all records by given filters.
     *
     * @param int         $component   Component ID
     * @param int         $type        Type ID
     * @param string|null $placeholder Placeholder to search for
     * @param string|null $value       Value to search for
     * @param int         $langaugeId  Language ID
     *
     * @return QueryResultInterface
     *
     * @throws InvalidQueryException
     */
    public function getAllRecordsByIdentifier(
        int $component = 0,
        int $type = 0,
        ?string $placeholder = null,
        ?string $value = null,
        int $langaugeId = 0
    ): QueryResultInterface {
        $query = $this->createQuery();

        $query->getQuerySettings()
            ->setIgnoreEnableFields(true);

        $constraints = [];

        if ($component !== 0) {
            $constraints[] = $query->equals('component', $component);
        }

        if ($type !== 0) {
            $constraints[] = $query->equals('type', $type);
        }

        if ($placeholder !== null) {
            $constraints[] = $query->like('placeholder', '%' . $placeholder . '%');
        }

        if ($value !== null) {
            $constraints[] = $query->like('value', '%' . $value . '%');
        }

        if ($langaugeId !== 0) {
            $constraints[] = $query->equals('_languageUid', $langaugeId);
        }

        if ($constraints !== []) {
            $query->matching(
                $query->logicalAnd(...$constraints)
            );
        }

        return $query->execute();
    }

    /**
     * @param int[] $originals
     * @param int   $languageUid
     *
     * @return QueryResultInterface
     *
     * @throws InvalidQueryException
     */
    public function getTranslatedRecordsForLanguage(array $originals, int $languageUid): QueryResultInterface
    {
        $query = $this->createQuery();

        $query
            ->getQuerySettings()
            ->setIgnoreEnableFields(true);

        $query
            ->getQuerySettings()
            ->setRespectStoragePage(false);

        $query
            ->getQuerySettings()
            ->setLanguageAspect(
                new LanguageAspect(
                    $languageUid,
                    $languageUid,
                    $query->getQuerySettings()->getLanguageAspect()->getOverlayType()
                )
            );

        $query->matching(
            $query->logicalAnd(
                $query->in('l10nParent', $originals)
            )
        );

        return $query->execute();
    }
}
