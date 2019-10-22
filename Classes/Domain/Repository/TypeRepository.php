<?php
namespace Netresearch\NrTextdb\Domain\Repository;

use Netresearch\NrTextdb\Domain\Model\Type;
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
class TypeRepository extends AbstractRepository
{
    static $localCache = [];


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
     * @return Type
     */
    public function findByName(string $name)
    {
        if ($type = $this->getFromCache($name)) {
            return $type;
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
            $type = new Type();
            $type->setName($name);
            $type->setPid($this->getConfiguredPageId());
            $this->add($type);
            $this->persistenceManager->persistAll();
            return $this->setToCache($name, $type);
        }

        return $this->setToCache($name, $queryResult->getFirst());
    }

    private function setToCache(string $key, Type $type): Type
    {
        static::$localCache[$key] = $type;

        return $type;
    }

    private function getFromCache(string $key): ?Type
    {
        if (isset(static::$localCache[$key])) {
            return static::$localCache[$key];
        }

        return null;
    }
}
