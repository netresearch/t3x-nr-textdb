<?php

/*
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
use TYPO3\CMS\Core\Context\LanguageAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Exception\InvalidQueryException;
use TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * TranslationRepository.
 *
 * @author  Thomas Schöne <thomas.schoene@netresearch.de>
 * @author  Axel Seemann <axel.seemann@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 *
 * @see    https://www.netresearch.de
 *
 * @extends AbstractRepository<Translation>
 */
class TranslationRepository extends AbstractRepository
{
    /**
     * Initialize the object.
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
     * Returns the translations of the originals, ordered by original and uid.
     *
     * @param int[]    $originals
     * @param int|null $pageId    Only records on this page; null for records on any page
     *
     * @return QueryResultInterface<int, Translation>
     *
     * @throws InvalidQueryException
     */
    public function findByTranslationsAndLanguage(array $originals, int $languageUid, ?int $pageId = null): QueryResultInterface
    {
        $query = $this->createQuery();
        $query
            ->getQuerySettings()
            ->setIgnoreEnableFields(true)
            ->setRespectStoragePage(false)
            ->setRespectSysLanguage(false)
            ->setLanguageAspect($this->rawLanguageAspect($languageUid));

        $constraints = [
            $query->equals('sys_language_uid', $languageUid),
            $query->in('l10nParent', $originals),
        ];

        if ($pageId !== null) {
            $constraints[] = $query->equals('pid', $pageId);
        }

        $query
            ->matching($query->logicalAnd(...$constraints))
            ->setOrderings([
                'l10nParent' => QueryInterface::ORDER_ASCENDING,
                'uid'        => QueryInterface::ORDER_ASCENDING,
            ]);

        return $query->execute();
    }

    /**
     * Returns the raw record for a uid, regardless of its language.
     *
     * Repository::findByUid() honours the language of the current request, so a
     * localized row addressed by its own uid resolves to null in a backend
     * context (language 0). The backend module edits rows of every language by
     * uid, and silently getting null there is what made saving a translation
     * discard the change (issue #100).
     *
     * The language aspect is pinned to "overlays off" (see rawLanguageAspect()),
     * so a translation whose parent was deleted while the module's dialog was
     * open can still be loaded.
     */
    public function findRawByUid(int $uid): ?Translation
    {
        $query = $this->createQuery();
        $query
            ->getQuerySettings()
            ->setIgnoreEnableFields(true)
            ->setRespectStoragePage(false)
            ->setRespectSysLanguage(false)
            ->setLanguageAspect($this->rawLanguageAspect(0));

        return $query
            ->matching($query->equals('uid', $uid))
            ->execute()
            ->getFirst();
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
            ->setRespectSysLanguage(false)
            ->setLanguageAspect($this->rawLanguageAspect(0));

        $query->matching(
            $query->equals('l10nParent', $uid),
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
     * @param int|null    $pageId      Only records on this page; null for records on any page
     * @param int         $limit       Maximum number of records, ordered by uid; 0 for all records in no defined order
     * @param int         $afterUid    Only records with a higher uid, to read the next batch after the last uid read
     *
     * @return QueryResultInterface<int, Translation>
     *
     * @throws InvalidQueryException
     */
    public function findAllByComponentTypePlaceholderValueAndLanguage(
        int $component = 0,
        int $type = 0,
        ?string $placeholder = null,
        ?string $value = null,
        int $languageId = 0,
        ?int $pageId = null,
        int $limit = 0,
        int $afterUid = 0,
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
            // Filter on the language column directly. "_languageUid" is not a
            // mapped property: Extbase turned it into a "_language_uid" column
            // that does not exist, so the query failed with an SQL error. The
            // language restriction of the default query settings has to be
            // lifted as well, otherwise it adds "sys_language_uid IN (0,-1)"
            // for the current (default) language and no row can ever match.
            $query->getQuerySettings()->setRespectSysLanguage(false);

            $constraints[] = $query->equals('sys_language_uid', $languageId);
        }

        if ($pageId !== null) {
            $constraints[] = $query->equals('pid', $pageId);
        }

        if ($afterUid > 0) {
            $constraints[] = $query->greaterThan('uid', $afterUid);
        }

        if ($constraints !== []) {
            $query->matching(
                $query->logicalAnd(...$constraints),
            );
        }

        if ($limit > 0) {
            $query
                ->setOrderings(['uid' => QueryInterface::ORDER_ASCENDING])
                ->setLimit($limit);
        }

        return $query->execute();
    }

    /**
     * Finds a translation record by given environment, component, type and placeholder.
     */
    public function findByEnvironmentComponentTypeAndPlaceholder(
        Environment $environment,
        Component $component,
        Type $type,
        string $placeholder,
    ): ?Translation {
        $query = $this->createQuery();
        $query
            ->getQuerySettings()
            ->setIgnoreEnableFields(true)
            ->setRespectStoragePage(true)
            ->setStoragePageIds([$this->getConfiguredPageId()])
            ->setRespectSysLanguage(false);

        return $query
            ->matching(
                $query->logicalAnd(
                    $query->equals('sys_language_uid', 0),
                    $query->equals('environment', $environment),
                    $query->equals('component', $component),
                    $query->equals('type', $type),
                    $query->equals('placeholder', $placeholder),
                ),
            )
            ->execute()
            ->getFirst();
    }

    /**
     * Finds a translation record by given environment, component, type, placeholder and language UID.
     */
    public function findByEnvironmentComponentTypePlaceholderAndLanguage(
        Environment $environment,
        Component $component,
        Type $type,
        string $placeholder,
        int $languageUid,
    ): ?Translation {
        $query = $this->createQuery();

        $query->getQuerySettings()
            ->setIgnoreEnableFields(true)
            ->setRespectStoragePage(true)
            ->setStoragePageIds([$this->getConfiguredPageId()])
            ->setRespectSysLanguage(false);

        return $query
            ->matching(
                $query->logicalAnd(
                    $query->equals('sys_language_uid', $languageUid),
                    $query->equals('environment', $environment),
                    $query->equals('component', $component),
                    $query->equals('type', $type),
                    $query->equals('placeholder', $placeholder),
                ),
            )
            ->execute()
            ->getFirst();
    }

    /**
     * Builds the language aspect for a query that has to return the raw row.
     *
     * TextDB rows are addressed as raw records: the repository selects an exact
     * `sys_language_uid` itself and must receive that very row back, never a
     * language overlay of it. Extbase resolves overlays from the query settings'
     * language aspect, which defaults to the one in the global Context, so the
     * aspect has to be pinned explicitly per query.
     *
     * With overlays on, Extbase on TYPO3 13.4 replaces a localized row by its
     * default-language parent and looks the translation up again. That second
     * lookup skips hidden rows and rows on another page than the parent and
     * then returns the parent itself; when the parent is deleted, the row is
     * dropped altogether.
     */
    private function rawLanguageAspect(int $languageUid): LanguageAspect
    {
        return new LanguageAspect($languageUid, $languageUid, LanguageAspect::OVERLAYS_OFF);
    }
}
