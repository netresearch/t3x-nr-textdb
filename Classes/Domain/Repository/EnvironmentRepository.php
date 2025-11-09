<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Domain\Repository;

use Netresearch\NrTextdb\Domain\Model\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;

/**
 * EnvironmentRepository.
 *
 * @author  Thomas Schöne <thomas.schoene@netresearch.de>
 * @author  Axel Seemann <axel.seemann@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 *
 * @extends AbstractRepository<Environment>
 */
class EnvironmentRepository extends AbstractRepository
{
    /**
     * Local environment cache.
     *
     * @var Environment[]
     */
    public static array $localCache = [];

    /**
     * Initialize the object.
     */
    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);

        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * @param string $name Name of environment
     *
     * @throws IllegalObjectTypeException
     */
    public function findByName(string $name): ?Environment
    {
        $environment = $this->getFromCache($name);

        if ($environment instanceof Environment) {
            return $environment;
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
            $environment = new Environment();
            $environment->setName($name);
            $environment->setPid($this->getConfiguredPageId());

            $this->add($environment);
            $this->persistenceManager->persistAll();

            return $this->setToCache($name, $environment);
        }

        /** @var Environment|null $environment */
        $environment = $queryResult->getFirst();

        return $this->setToCache($name, $environment);
    }

    /**
     * Set environment to local cache.
     *
     * @param string           $key         Cache Key
     * @param Environment|null $environment Environment which is set to cache
     */
    private function setToCache(string $key, ?Environment $environment): ?Environment
    {
        static::$localCache[$key] = $environment;

        return $environment;
    }

    /**
     * Returns the environment from Cache.
     *
     * @param string $key Cache key
     */
    private function getFromCache(string $key): ?Environment
    {
        return static::$localCache[$key] ?? null;
    }
}
