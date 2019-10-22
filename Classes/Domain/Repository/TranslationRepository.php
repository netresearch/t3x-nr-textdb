<?php
namespace Netresearch\NrTextdb\Domain\Repository;

use Netresearch\NrTextdb\Domain\Model\Component;
use Netresearch\NrTextdb\Domain\Model\Environment;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Model\Type;
use TYPO3\CMS\Backend\Exception;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;

/***
 *
 * This file is part of the "Netresearch TextDB" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019 Thomas Schöne <thomas.schoene@netresearch.de>, Netresearch
 *  (c) 2019 Axel Seemann <axel.seemann@netresearch.de>, Netresearch
 *
 ***/
class TranslationRepository extends AbstractRepository
{
    /**
     * @var boolean
     */
    private $useLanguageFilter;

    /**
     * @var ComponentRepository
     */
    private $componentRepository;

    /**
     * @var EnvironmentRepository
     */
    private $environmentRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var integer
     */
    private $languageUid;

    /**
     * @var Translation[]
     */
    static $localCache = [];

    /**
     * TranslationRepository constructor.
     *
     * @param \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
     */
    public function __construct(\TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager)
    {
        parent::__construct($objectManager);

        /** @var Typo3QuerySettings $querySettings */
        $querySettings = $this->objectManager->get(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);
        $querySettings->setRespectSysLanguage(true);

        $this->componentRepository   = $this->objectManager->get(ComponentRepository::class);
        $this->environmentRepository = $this->objectManager->get(EnvironmentRepository::class);
        $this->typeRepository        = $this->objectManager->get(TypeRepository::class);

        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Returns all objects of this repository.
     *
     * @return QueryResultInterface|array
     */
    public function findAllWithHidden()
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setIgnoreEnableFields(true);
        return $query->execute();
    }

    /**
     * Find all records for a given language.
     *
     * @param integer $languageUid
     *
     * @return array|\TYPO3\CMS\Extbase\Persistence\QueryResultInterface
     */
    public function findAllByLanguage($languageUid)
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setLanguageUid($languageUid);
        return $query->execute();
    }

    /**
     * Set to true if the language filter should be used
     *
     * @param boolean $useLanguageFilter
     *
     * @return self
     */
    public function setUseLanguageFilter($useLanguageFilter = true)
    {
        $this->useLanguageFilter = $useLanguageFilter;
        return $this;
    }

    /**
     * Set the uid of the language.
     *
     * @return self
     */
    public function setLanguageUid($languageUid)
    {
        $this->languageUid = $languageUid;
        return $this;
    }

    /**
     * Returns a translation.
     *
     * @param string $component   Component of the translation
     * @param string $environment Environment of the translation
     * @param string $type        Type of the translation
     * @param string $placeholder Value of the translation
     * @param int    $languageUid uid of the language
     *
     * @throws \Exception
     *
     * @return Translation
     */
    public function findEntry(string $component, string $environment, string $type, string $placeholder, int $languageUid): Translation
    {
        $cacheKey = md5(json_encode(func_get_args()));

        if ($translation = $this->getFromCache($cacheKey)) {
            return $translation;
        }

        $query = $this->createQuery();
        $query->getQuerySettings()->setIgnoreEnableFields(true);
        $query->getQuerySettings()->setLanguageUid($languageUid);

        $query->matching(
            $query->logicalAnd(
                [
                    $query->equals('placeholder', $placeholder),
                    $query->equals('pid', $this->getConfiguredPageId()),
                    $query->equals('type.name', $type),
                    $query->equals('component.name', $component),
                ]
            )
        );

        $queryResult = $query->execute();

        $translation = null;

        /** @var Translation $translation */
        foreach ($queryResult as $result) {
            if ($result->getEnvironment()->getName() === $environment) {
                $translation = $result;
            } elseif ($result->getEnvironment()->getName() === 'default') {
                $translation = $result;
            }
        }

        if ($translation instanceof Translation) {
            if ($translation->getHidden() || $translation->_getProperty('deleted')) {
                return $this->setToCache($cacheKey, new Translation());
            }

            return $this->setToCache($cacheKey, $translation);
        }

        return $this->setToCache(
            $cacheKey,
            $this->createTranslation($component, $environment, $type, $placeholder, $languageUid, $placeholder)
        );
    }

    /**
     * Set a translation to cache and return the translation
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
     * Returns a cached translation
     *
     * @param string $key Cache key
     *
     * @return Translation|null
     */
    private function getFromCache(string $key): ?Translation
    {
        if (isset(static::$localCache[$key])) {
            return static::$localCache[$key];
        }

        return null;
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
     * @throws \Exception
     *
     * @return Translation
     */
    public function createTranslation(string $component, string $environment, string $type, string $placeholder, int $languageUid, string $value = '')
    {
        $pid = $this->getConfiguredPageId();

        $translation = new Translation();
        $translation->setPid($pid);
        $translation->setComponent($this->componentRepository->findByName($component));
        $translation->setEnvironment($this->environmentRepository->findByName($environment));
        $translation->setType($this->typeRepository->findByName($type));
        $translation->setPlaceholder($placeholder);
        $translation->setValue($value);
        $translation->setLanguageUid($languageUid);

        if ($languageUid != 0) {
            $origTranslation = $this->findEntry($component, $environment, $type, $placeholder, 0);
            $translation->setL10nParent($origTranslation->getUid());
        }

        $this->add($translation);
        $this->persistenceManager->persistAll();

        return $translation;
    }

    /**
     * Returns a array with translations for a record
     *
     * @param int $uid Uid of original
     *
     * @return array
     */
    public function getTranslatedRecords(int $uid)
    {
        $query = $this->createQuery();

        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->getQuerySettings()->setRespectSysLanguage(false);
        $query->getQuerySettings()->setEnableFieldsToBeIgnored(true);
        $query->matching(
            $query->logicalAnd(
                [
                    $query->equals('l10nParent', $uid),
                    $query->equals('pid', $this->getConfiguredPageId())
                ]
            )
        );

        return $query->execute()->toArray();
    }

    /**
     * Returns a record found by its uid without any restrictions
     *
     * @param int $uid UID
     *
     * @return object
     */
    public function findRecordByUid(int $uid)
    {
        $query = $this->createQuery();

        $query->getQuerySettings()->setRespectSysLanguage(false);
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->getQuerySettings()->setEnableFieldsToBeIgnored(true);

        $query->matching(
            $query->equals('uid', $uid)
        );

        return $query->execute()->getFirst();
    }

    /**
     * Returns all records by given filters
     *
     * @param int    $component   Component ID
     * @param int    $type        Type ID
     * @param string $placeholder Placeholder to search for
     *
     * @return array|QueryResultInterface
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException
     */
    public function getAllRecordsByIdentifier(int $component = null, int $type = null, string $placeholder = null)
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setIgnoreEnableFields(true);

        $constraints = [];
        if (!empty($component)) {
            $constraints[] = $query->equals('component', $component);
        }
        if (!empty($type)) {
            $constraints[] = $query->equals('type', $type);
        }
        if (!empty($placeholder)) {
            $constraints[] = $query->like('placeholder', '%' . $placeholder . '%');
        }
        if (!empty($constraints)) {
            $query->matching(
                $query->logicalAnd($constraints)
            );
        }

        return $query->execute();

    }
}
