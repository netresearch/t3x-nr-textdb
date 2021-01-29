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
     * Get the extension configuration.
     *
     * @return mixed
     */
    private function getExtensionConfiguration()
    {
        return GeneralUtility::makeInstance(
            ExtensionConfiguration::class
        )->get('nr_textdb');
    }

    /**
     * Get the configured pid from extension configuration.
     *
     * @return int
     */
    public function getConfiguredPageId(): int
    {
        $configuration = $this->getExtensionConfiguration();
        return (int) $configuration['textDbPid'];
    }
}
