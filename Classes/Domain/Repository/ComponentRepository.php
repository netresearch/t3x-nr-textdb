<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Domain\Repository;

use Netresearch\NrTextdb\Domain\Model\Component;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;

/**
 * ComponentRepository.
 *
 * @author  Thomas Schöne <thomas.schoene@netresearch.de>
 * @author  Axel Seemann <axel.seemann@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class ComponentRepository extends AbstractRepository
{
    /**
     * Local component cache.
     *
     * @var Component[]
     */
    public static array $localCache = [];

    /**
     * Initialize the object.
     *
     * @return void
     */
    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);

        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Find a Component by name and create one if not found.
     *
     * @param string $name Name of Component
     *
     * @return Component|null
     *
     * @throws IllegalObjectTypeException
     */
    public function findByName(string $name): ?Component
    {
        $component = $this->getFromCache($name);

        if ($component instanceof Component) {
            return $component;
        }

        $query = $this->createQuery();

        $query->matching(
            $query->logicalAnd(
                $query->equals('name', $name),
                $query->equals('pid', $this->getConfiguredPageId())
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

        /** @var Component|null $component */
        $component = $queryResult->getFirst();

        return $this->setToCache($name, $component);
    }

    /**
     * Set a Component to Cache and return it.
     *
     * @param string         $key       CacheKey
     * @param Component|null $component Component to cache
     *
     * @return Component|null
     */
    private function setToCache(string $key, ?Component $component): ?Component
    {
        static::$localCache[$key] = $component;

        return $component;
    }

    /**
     * Returns a component from cache.
     *
     * @param string $key Cache Key
     *
     * @return Component|null
     */
    private function getFromCache(string $key): ?Component
    {
        return static::$localCache[$key] ?? null;
    }
}
