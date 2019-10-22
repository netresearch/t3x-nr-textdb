<?php
namespace Netresearch\NrTextdb\Domain\Repository;

use Netresearch\NrTextdb\Domain\Model\Component;
use Netresearch\NrTextdb\Domain\Model\Environment;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Model\Type;
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

        /** @var Component $component */
        $component   = $this->componentRepository->findByName($component);
        /** @var Environment $environment */
        $environment = $this->environmentRepository->findByName($environment);
        /** @var Type $type */
        $type        = $this->typeRepository->findByName($type);

        $query->matching(
            $query->logicalAnd(
                [
                    $query->equals('placeholder', $placeholder),
                    $query->equals('pid', $this->getConfiguredPageId()),
                    $query->equals('type', $type->getUid()),
                    $query->equals('component', $component->getUid()),
                ]
            )
        );

        $queryResult = $query->execute();

        $translation = null;

        /** @var Translation $translation */
        foreach ($queryResult as $result) {
            if ($result->getEnvironment()->getName() === $environment->getName()) {
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
     * @param Component   $component   Component of the translation
     * @param Environment $environment Environment of the translation
     * @param Type        $type        Type of the translation
     * @param string      $placeholder Placeholder of the translation
     * @param int         $languageUid the uid of the language
     * @param string      $value       Value of the translation
     *
     * @return Translation
     */
    public function createTranslation($component, $environment, $type, $placeholder, $languageUid, $value = '')
    {
        $pid = $this->getConfiguredPageId();

        $translation = new \Netresearch\NrTextdb\Domain\Model\Translation();
        $translation->setPid($pid);
        $translation->setComponent($component);
        $translation->setEnvironment($environment);
        $translation->setType($type);
        $translation->setPlaceholder($placeholder);
        $translation->setValue($value);
        $translation->setLanguageUid($languageUid);

        if ($languageUid != 0) {
            $origTranslation = $this->findEntry($component->getName(), $environment->getName(), $type->getName(), $placeholder, 0);
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
}
