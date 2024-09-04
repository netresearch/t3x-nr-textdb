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
use Netresearch\NrTextdb\Domain\Model\Environment;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Model\Type;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * TranslationRepository.
 *
 * @author  Thomas Schöne <thomas.schoene@netresearch.de>
 * @author  Axel Seemann <axel.seemann@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class TranslationRepository extends AbstractRepository
{
    /**
     * Initialize the object.
     *
     * @return void
     */
    public function initializeObject(): void
    {
        $querySettings = GeneralUtility::makeInstance(Typo3QuerySettings::class);
        $querySettings
            ->setRespectStoragePage(false)
            ->setRespectSysLanguage(true);

        $this->setDefaultQuerySettings($querySettings);
    }

    /**
     * @param int[] $originals
     * @param int   $languageUid
     *
     * @return QueryResultInterface
     *
     * @throws InvalidQueryException
     */
    public function findByTranslationsAndLanguage(array $originals, int $languageUid): QueryResultInterface
    {
        $query = $this->createQuery();
        $query
            ->getQuerySettings()
            ->setIgnoreEnableFields(true)
            ->setRespectStoragePage(false)
            ->setRespectSysLanguage(false);

        $query->matching(
            $query->logicalAnd([
                $query->equals('sys_language_uid', $languageUid),
                $query->in('l10nParent', $originals),
            ])
        );

        return $query->execute();
    }

    /**
     * Returns an array with translations for a record.
     *
     * @param int $uid Uid of original
     *
     * @return Translation[]
     */
    public function findByPidAndLanguage(int $uid): array
    {
        $query = $this->createQuery();
        $query
            ->getQuerySettings()
            ->setIgnoreEnableFields(true)
            ->setRespectStoragePage(true)
            ->setStoragePageIds([$this->getConfiguredPageId()])
            ->setRespectSysLanguage(false);

        $query->matching(
            $query->logicalAnd(
                $query->equals('l10nParent', $uid)
            )
        );

        return $query
            ->execute()
            ->toArray();
    }

    /**
     * Returns all records by given filters.
     *
     * @param int         $component   Component ID
     * @param int         $type        Type ID
     * @param string|null $placeholder Placeholder to search for
     * @param string|null $value       Value to search for
     * @param int         $languageId  Language ID
     *
     * @return QueryResultInterface
     *
     * @throws InvalidQueryException
     */
    public function findAllByComponentTypePlaceholderValueAndLanguage(
        int $component = 0,
        int $type = 0,
        ?string $placeholder = null,
        ?string $value = null,
        int $languageId = 0
    ): QueryResultInterface {
        $query = $this->createQuery();
        $query
            ->getQuerySettings()
            ->setIgnoreEnableFields(true);

        $constraints = [];

        if ($component !== 0) {
            $constraints[] = $query->equals('component', $component);
        }

        if ($type !== 0) {
            $constraints[] = $query->equals('type', $type);
        }

        if ($placeholder !== null) {
            $constraints[] = $query->like('placeholder', '%' . $placeholder . '%');
        }

        if ($value !== null) {
            $constraints[] = $query->like('value', '%' . $value . '%');
        }

        if ($languageId !== 0) {
            $constraints[] = $query->equals('_languageUid', $languageId);
        }

        if ($constraints !== []) {
            $query->matching(
                $query->logicalAnd(...$constraints)
            );
        }

        return $query->execute();
    }

    /**
     * Finds a translation record by given environment, component, type and placeholder.
     *
     * @param Environment $environment
     * @param Component   $component
     * @param Type        $type
     * @param string      $placeholder
     *
     * @return Translation|null
     */
    public function findByEnvironmentComponentTypeAndPlaceholder(
        Environment $environment,
        Component $component,
        Type $type,
        string $placeholder,
    ): ?object {
        $query = $this->createQuery();
        $query
            ->getQuerySettings()
            ->setIgnoreEnableFields(true)
            ->setRespectStoragePage(true)
            ->setStoragePageIds([$this->getConfiguredPageId()])
            ->setRespectSysLanguage(false);

        return $query
            ->matching(
                $query->logicalAnd([
                    $query->equals('sys_language_uid', 0),
                    $query->equals('environment', $environment),
                    $query->equals('component', $component),
                    $query->equals('type', $type),
                    $query->equals('placeholder', $placeholder),
                ])
            )
            ->execute()
            ->getFirst();
    }

    /**
     * Finds a translation record by given environment, component, type, placeholder and language UID.
     *
     * @param Environment $environment
     * @param Component   $component
     * @param Type        $type
     * @param string      $placeholder
     * @param int         $languageUid
     *
     * @return Translation|null
     */
    public function findByEnvironmentComponentTypePlaceholderAndLanguage(
        Environment $environment,
        Component $component,
        Type $type,
        string $placeholder,
        int $languageUid
    ): ?object {
        $query = $this->createQuery();

        $query->getQuerySettings()
            ->setIgnoreEnableFields(true)
            ->setRespectStoragePage(true)
            ->setStoragePageIds([$this->getConfiguredPageId()])
            ->setRespectSysLanguage(false);

        return $query
            ->matching(
                $query->logicalAnd([
                    $query->equals('sys_language_uid', $languageUid),
                    $query->equals('environment', $environment),
                    $query->equals('component', $component),
                    $query->equals('type', $type),
                    $query->equals('placeholder', $placeholder),
                ])
            )
            ->execute()
            ->getFirst();
    }

    /**
     * Returns a record found by its uid without any restrictions.
     *
     * @param int $uid UID
     *
     * @return Translation|null
     */
    public function findRecordByUid(int $uid): ?object
    {
        $query = $this->createQuery();

        $query->getQuerySettings()
            ->setRespectSysLanguage(false)
            ->setRespectStoragePage(false)
            ->setIgnoreEnableFields(true);

        $query->matching(
            $query->equals('uid', $uid)
        );

        return $query->execute()->getFirst();
    }
}
