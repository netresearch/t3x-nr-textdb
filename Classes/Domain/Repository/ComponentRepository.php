<?php
namespace Netresearch\NrTextdb\Domain\Repository;

use Netresearch\NrTextdb\Domain\Model\Component;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;

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
class ComponentRepository extends AbstractRepository
{
    /**
     * @var Component[] Local component cache
     */
    static $localCache = [];

    /**
     * ComponentRepository constructor.
     *
     * @param \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager ObjectManager instance
     */
    public function __construct(\TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager)
    {
        parent::__construct($objectManager);

        $querySettings = $this->objectManager->get(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);

        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Find a Component by name and create one if not found.
     *
     * @param string $name Name of Component
     *
     * @return Component|null
     * @throws \TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException
     */
    public function findByName(string $name)
    {
        if ($component = $this->getFromCache($name)) {
            return $component;
        }

        $query = $this->createQuery();

        $query->matching(
            $query->logicalAnd(
                [
                    $query->equals('name', $name),
                    $query->equals('pid', $this->getConfiguredPageId())
                ]
            )
        );

        $queryResult = $query->execute();

        if ($queryResult->count() === 0 && $this->getCreateIfMissing()) {
            $component = new Component();
            $component->setName($name);
            $component->setPid($this->getConfiguredPageId());
            $this->add($component);
            $this->persistenceManager->persistAll();
            return $this->setToCache($name, $component);
        }

        return $this->setToCache($name, $queryResult->getFirst());
    }

    /**
     * Set a Component to Cache and return it.
     *
     * @param string    $key       CacheKey
     * @param ?Component $component Component to cache
     *
     * @return ?Component
     */
    private function setToCache(string $key, ?Component $component): ?Component
    {
        static::$localCache[$key] = $component;

        return $component;
    }

    /**
     * Returns a component from cache
     *
     * @param string $key Cache Key
     *
     * @return Component|null
     */
    private function getFromCache(string $key): ?Component
    {
        if (isset(static::$localCache[$key])) {
            return static::$localCache[$key];
        }

        return null;
    }
}
