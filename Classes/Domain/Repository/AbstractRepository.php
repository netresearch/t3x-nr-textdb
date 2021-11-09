<?php


namespace Netresearch\NrTextdb\Domain\Repository;

use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Repository;

/***
 *
 * This file is part of the "Netresearch TextDB" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019 Axel Seemann <axel.seemann@netresearch.de>, Netresearch
 *
 ***/
class AbstractRepository extends Repository
{
    /**
     * @var bool
     */
    private $createIfMissing = false;

    /**
     * Get the extension configuration.
     *
     * @param string $path Path to get the config for
     *
     * @return mixed
     */
    private function getExtensionConfiguration(string $path)
    {
        return GeneralUtility::makeInstance(
            ExtensionConfiguration::class
        )->get('nr_textdb', $path);
    }

    /**
     * Get the configured pid from extension configuration.
     *
     * @return int
     */
    public function getConfiguredPageId(): int
    {
        return (int) $this->getExtensionConfiguration('textDbPid');
    }

    /**
     * Set to true if a translation part should automatically be created if it is missing in database.
     * This will override the extension setting if its set to true.
     *
     * @param bool $createIfMissing
     *
     * @return $this
     */
    public function setCreateIfMissing(bool $createIfMissing): self
    {
        $this->createIfMissing = $createIfMissing;
        return $this;
    }

    /**
     * Returns true if the placeholder or parts of the translation should be created if it is missing.
     *
     * @return bool
     */
    protected function getCreateIfMissing(): bool
    {
        if ($this->createIfMissing) {
            return $this->createIfMissing;
        }
        return (bool) $this->getExtensionConfiguration('createIfMissing');
    }
}
