<?php
namespace Netresearch\NrTextdb\Domain\Repository;

use Netresearch\NrTextdb\Domain\Model\Translation;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

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
class TranslationRepository extends \TYPO3\CMS\Extbase\Persistence\Repository
{
    /**
     * @var boolean
     */
    private $useLanguageFilter;

    /**
     * @var integer
     */
    private $languageUid;

    public function __construct(\TYPO3\CMS\Extbase\Object\ObjectManagerInterface $objectManager)
    {
        parent::__construct($objectManager);

        $querySettings = $this->objectManager->get(Typo3QuerySettings::class);
        $querySettings->setRespectStoragePage(false);

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
     *
     * @return Translation
     */
    public function findEntry(string $component, string $environment, string $type, string $placeholder): Translation
    {
        $query = $this->createQuery();

        $query->matching(
            $query->logicalAnd(
                [
                    $query->equals('placeholder', $placeholder),
                    $query->equals('pid', $this->getConfiguredPageId())
                ]
            )
        );

        $queryResult = $query->execute();
        $translation = $queryResult->getFirst();

        foreach ($queryResult as $translation) {
            if ($translation->getComponent()->getName() === $component
                && $translation->getEnvironment()->getName() === $environment
                && $translation->getType()->getName() === $type) {
                return $translation;
            }
        }

        $query->getQuerySettings()->setIgnoreEnableFields(true);
        $queryResult = $query->execute();
        $translation = $queryResult->getFirst();

        if ($translation === null) {
            $translation = $this->createTranslation($component, $environment, $type, $placeholder);
            $this->add($translation);
            $this->persistenceManager->persistAll();
            return  $translation;
        }

        $translation = new \Netresearch\NrTextdb\Domain\Model\Translation();
        return $translation;
    }

    /**
     * Create a new translation.
     *
     * @param string $component   Component of the translation
     * @param string $environment Environment of the translation
     * @param string $type        Type of the translation
     * @param string $placeholder Value of the translation
     *
     * @return Translation
     */
    private function createTranslation($component, $environment, $type, $placeholder)
    {
        $pid = $this->getConfiguredPageId();

        $objComponent = new \Netresearch\NrTextdb\Domain\Model\Component();
        $objComponent->setName($component);
        $objComponent->setPid($pid);
        $objEnvironment = new \Netresearch\NrTextdb\Domain\Model\Environment();
        $objEnvironment->setName($environment);
        $objEnvironment->setPid($pid);
        $objType = new \Netresearch\NrTextdb\Domain\Model\Type();
        $objType->setName($type);
        $objType->setPid($pid);

        $translation = new \Netresearch\NrTextdb\Domain\Model\Translation();
        $translation->setPid($pid);
        $translation->setComponent($objComponent);
        $translation->setEnvironment($objEnvironment);
        $translation->setType($objType);
        $translation->setPlaceholder($placeholder);
        $translation->setValue($placeholder);

        return $translation;
    }

    /**
     * Get the extension configuration.
     *
     * @return mixed
     */
    protected function getExtensionConfiguration()
    {
        return \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
            \TYPO3\CMS\Core\COnfiguration\ExtensionConfiguration::class
        )->get('nr_textdb');
    }

    /**
     * Get the configured pid from extension configuration.
     *
     * @return mixed
     */
    protected function getConfiguredPageId()
    {
        $configuration = $this->getExtensionConfiguration();
        return $configuration['textDbPid'];
    }
}
