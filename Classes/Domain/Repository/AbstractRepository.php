<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Domain\Repository;

use Exception;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * AbstractRepository
 *
 * @author  Axel Seemann <axel.seemann@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 *
 * @template T of \TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface
 * @extends Repository<T>
 */
class AbstractRepository extends Repository
{
    /**
     * @var bool
     */
    private bool $createIfMissing = false;

    /**
     * Get the extension configuration.
     *
     * @param string $path Path to get the config for
     *
     * @return mixed
     */
    private function getExtensionConfiguration(string $path): mixed
    {
        try {
            /** @var ExtensionConfiguration $extensionConfiguration */
            $extensionConfiguration = GeneralUtility::makeInstance(ExtensionConfiguration::class);
            return $extensionConfiguration->get('nr_textdb', $path);
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Get the configured page ID, used to store the translation in, from extension configuration.
     *
     * @return int
     */
    public function getConfiguredPageId(): int
    {
        return (int) ($this->getExtensionConfiguration('textDbPid') ?? 0);
    }

    /**
     * Set to true if a translation part should automatically be created if it is missing in a database.
     * This will override the extension setting if it's set to true.
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

        return (bool) ($this->getExtensionConfiguration('createIfMissing') ?? false);
    }
}
