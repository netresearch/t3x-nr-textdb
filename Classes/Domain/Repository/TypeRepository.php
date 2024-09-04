<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Domain\Repository;

use Netresearch\NrTextdb\Domain\Model\Type;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;

/**
 * TypeRepository.
 *
 * @author  Thomas Schöne <thomas.schoene@netresearch.de>
 * @author  Axel Seemann <axel.seemann@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class TypeRepository extends AbstractRepository
{
    /**
     * Local type cache.
     *
     * @var Type[]
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
     * Find a type by name and return it. If no type is found, it will be created.
     *
     * @param string $name Name of a type
     *
     * @return Type|null
     *
     * @throws IllegalObjectTypeException
     */
    public function findByName(string $name): ?Type
    {
        $type = $this->getFromCache($name);

        if ($type instanceof Type) {
            return $type;
        }

        $query = $this->createQuery();

        $query->matching(
            $query->logicalAnd([
                $query->equals('name', $name),
                $query->equals('pid', $this->getConfiguredPageId()),
            ])
        );

        $queryResult = $query->execute();

        if (
            ($queryResult->count() === 0)
            && $this->getCreateIfMissing()
        ) {
            $type = new Type();
            $type->setName($name);
            $type->setPid($this->getConfiguredPageId());

            $this->add($type);
            $this->persistenceManager->persistAll();

            return $this->setToCache($name, $type);
        }

        /** @var Type|null $type */
        $type = $queryResult->getFirst();

        return $this->setToCache($name, $type);
    }

    /**
     * Set a type to cache and return it.
     *
     * @param string    $key  Cache key
     * @param Type|null $type Type to cache
     *
     * @return Type|null
     */
    private function setToCache(string $key, ?Type $type): ?Type
    {
        static::$localCache[$key] = $type;

        return $type;
    }

    /**
     * Return a cached type.
     *
     * @param string $key Cache key
     *
     * @return Type|null
     */
    private function getFromCache(string $key): ?Type
    {
        return static::$localCache[$key] ?? null;
    }
}
