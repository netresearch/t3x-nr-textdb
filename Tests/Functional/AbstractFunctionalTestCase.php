<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Functional;

use Override;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Base class for nr_textdb functional tests.
 *
 * Provides shared setup for loading the extension under test and common
 * utility methods. Skips gracefully when no database driver is configured,
 * so the CI matrix can run unit tests without a database.
 *
 * Required environment variables (or configure typo3DatabaseDriver for SQLite):
 *   typo3DatabaseDriver=pdo_sqlite   (recommended for local use)
 *
 * Run functional tests:
 *   .build/bin/phpunit -c Build/FunctionalTests.xml
 */
abstract class AbstractFunctionalTestCase extends FunctionalTestCase
{
    /**
     * Extension under test plus required dependencies.
     *
     * The composer package name is used so the testing-framework can resolve
     * the path from the installed vendor tree.
     *
     * @var non-empty-string[]
     */
    protected array $testExtensionsToLoad = [
        'netresearch/nr-textdb',
    ];

    /**
     * Core extensions required beyond the default set.
     *
     * @var non-empty-string[]
     */
    protected array $coreExtensionsToLoad = [
        'extbase',
        'fluid',
        // Required so the container can autowire ImportCommand, which depends on
        // TYPO3\CMS\Extensionmanager\Utility\ListUtility. Without it every
        // functional test aborts during container compilation.
        'extensionmanager',
    ];

    protected bool $initializeDatabase = true;

    /**
     * Only a genuinely missing database is a reason to skip. Every other
     * initialisation problem — a container that cannot be compiled, a missing
     * extension, a broken fixture — has to fail loudly: swallowing it turns the
     * whole functional suite green while nothing is actually executed.
     */
    #[Override]
    protected function setUp(): void
    {
        if (!$this->canRunFunctionalTests()) {
            self::markTestSkipped(
                'Functional tests require a database. '
                . 'Set the typo3DatabaseDriver environment variable (e.g. pdo_sqlite) to enable them.',
            );
        }

        parent::setUp();
    }

    /**
     * Import a CSV fixture file from the shared Fixtures directory.
     *
     * @param string $filename Filename relative to Tests/Functional/Fixtures/
     */
    protected function importFixture(string $filename): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/' . $filename);
    }

    /**
     * Publishes the extension configuration the production code reads through
     * ExtensionConfiguration::get('nr_textdb', …).
     *
     * The real global is written instead of registering an ExtensionConfiguration
     * mock via GeneralUtility::addInstance(): every repository and service resolves
     * ExtensionConfiguration lazily through makeInstance(), an added instance is
     * consumed by the first call only. Writing the global covers an arbitrary
     * number of consumers and exercises the real ExtensionConfiguration
     * implementation.
     */
    protected function setExtensionConfiguration(string $textDbPid = '1', string $createIfMissing = '0'): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_textdb'] = [
            'textDbPid'       => $textDbPid,
            'createIfMissing' => $createIfMissing,
        ];
    }

    /**
     * Inserts a translation record on the storage page 1 in environment
     * "default" (uid 1) and returns its uid.
     *
     * @param int<-1, max> $languageUid
     */
    protected function insertTranslation(
        int $languageUid,
        int $component,
        int $type,
        string $placeholder,
        string $value,
        bool $hidden = false,
        int $l10nParent = 0,
    ): int {
        $connection = $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation');

        $connection->insert(
            'tx_nrtextdb_domain_model_translation',
            [
                'pid'              => 1,
                'sys_language_uid' => $languageUid,
                'l10n_parent'      => $l10nParent,
                'environment'      => 1,
                'component'        => $component,
                'type'             => $type,
                'placeholder'      => $placeholder,
                'value'            => $value,
                'hidden'           => (int) $hidden,
            ],
        );

        return (int) $connection->lastInsertId();
    }

    /**
     * Counts the translation rows of one language for the given placeholder,
     * hidden rows included; deleted rows are not counted.
     */
    protected function countTranslationRowsInLanguage(string $placeholder, int $languageUid): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_nrtextdb_domain_model_translation');
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(new DeletedRestriction());

        return (int) $queryBuilder
            ->count('uid')
            ->from('tx_nrtextdb_domain_model_translation')
            ->where(
                $queryBuilder->expr()->eq('placeholder', $queryBuilder->createNamedParameter($placeholder)),
                $queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($languageUid, Connection::PARAM_INT)),
            )
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * Returns a column of a translation row, hidden rows included.
     */
    protected function fetchTranslationColumn(int $uid, string $column = 'value'): string
    {
        $queryBuilder = $this->get(ConnectionPool::class)
            ->getQueryBuilderForTable('tx_nrtextdb_domain_model_translation');
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(new DeletedRestriction());

        $value = $queryBuilder
            ->select($column)
            ->from('tx_nrtextdb_domain_model_translation')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();

        self::assertNotFalse($value, 'Translation record ' . $uid . ' is gone.');

        return (string) $value;
    }

    /**
     * Determines whether the environment provides the minimum configuration
     * needed to spin up a TYPO3 functional test instance.
     */
    private function canRunFunctionalTests(): bool
    {
        if (getenv('typo3DatabaseDriver') !== false) {
            return true;
        }

        // Accept an existing LocalConfiguration as fallback (e.g. ddev environments).
        $localConfigPath = dirname(__DIR__, 2) . '/typo3conf/LocalConfiguration.php';
        if (file_exists($localConfigPath)) {
            return true;
        }

        return false;
    }
}
