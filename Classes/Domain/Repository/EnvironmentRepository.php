<?php
namespace Netresearch\NrTextdb\Domain\Repository;

use Netresearch\NrTextdb\Domain\Model\Environment;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;

/***
 *
 * This file is part of the "Netresearch TextDB" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019 Thomas Schöne <thomas.schoene@netresearch.de>, Netresearch
 *
 ***/
class EnvironmentRepository extends AbstractRepository
{
    static $localCache = [];

    /**
     * EnvironmentRepository constructor.
     *
     * @param \TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager
     */
    public function __construct(\TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager)
    {
        parent::__construct($objectManager);

        $querySettings = $this->objectManager->get(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);

        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * Returns a translation.
     *
     * @param string $component   Component of the translation
     * @param string $environment Environment of the translation
     * @param string $type        Type of the translation
     * @param string $placeholder Value of the translation
     *
     * @return Environment
     */
    public function findByName(string $name)
    {
        if ($environment = $this->getFromCache($name)) {
            return $environment;
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

        if ($queryResult->count() === 0) {
            $environment = new Environment();
            $environment->setName($name);
            $environment->setPid($this->getConfiguredPageId());
            $this->add($environment);
            $this->persistenceManager->persistAll();
            return $this->setToCache($name, $environment);
        }

        return $this->setToCache($name, $queryResult->getFirst());
    }

    private function setToCache(string $key, Environment $environment): Environment
    {
        static::$localCache[$key] = $environment;

        return $environment;
    }

    private function getFromCache(string $key): ?Environment
    {
        if (isset(static::$localCache[$key])) {
            return static::$localCache[$key];
        }

        return null;
    }
}
