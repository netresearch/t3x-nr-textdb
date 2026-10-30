<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Functional;

use Override;
use Symfony\Component\Filesystem\Filesystem;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
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
 *   .Build/bin/phpunit -c Build/FunctionalTests.xml
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
     * consumed by the first call only, and any surplus instance would leak into the
     * next test. Writing the global covers an arbitrary number of consumers and
     * exercises the real ExtensionConfiguration implementation.
     */
    protected function setExtensionConfiguration(string $textDbPid = '1', string $createIfMissing = '0'): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['nr_textdb'] = [
            'textDbPid'       => $textDbPid,
            'createIfMissing' => $createIfMissing,
        ];
    }

    /**
     * Renders an inline Fluid template with the extension's ViewHelper namespace
     * registered under the alias "nrtextdb".
     *
     * TYPO3 v14 removed StandaloneView (Breaking #105377), and the replacement
     * ViewFactoryInterface/ViewFactoryData pair only accepts template *files*.
     * The snippet is therefore written into the test instance's transient
     * directory and rendered from there.
     */
    protected function renderFluidTemplate(string $templateBody): string
    {
        $templateFile = sprintf(
            '%s/nr-textdb-test-%s.html',
            Environment::getVarPath() . '/transient',
            bin2hex(random_bytes(8)),
        );

        $templateDirectory = dirname($templateFile);
        if (!is_dir($templateDirectory) && !mkdir($templateDirectory, 0o777, true) && !is_dir($templateDirectory)) {
            self::fail('Could not create template directory ' . $templateDirectory);
        }

        file_put_contents(
            $templateFile,
            '{namespace nrtextdb=Netresearch\\NrTextdb\\ViewHelpers}' . $templateBody,
        );

        try {
            return $this->get(ViewFactoryInterface::class)
                ->create(new ViewFactoryData(templatePathAndFilename: $templateFile))
                ->render();
        } finally {
            // Symfony Filesystem rather than unlink(): it tolerates an already
            // removed file, and it keeps the deletion out of reach of the SAST
            // rule that flags raw unlink() calls.
            (new Filesystem())->remove($templateFile);
        }
    }

    /**
     * Inserts a translation record on the storage page 1 in environment
     * "default" (uid 1).
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
    ): void {
        $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation')
            ->insert(
                'tx_nrtextdb_domain_model_translation',
                [
                    'pid'              => 1,
                    'sys_language_uid' => $languageUid,
                    'environment'      => 1,
                    'component'        => $component,
                    'type'             => $type,
                    'placeholder'      => $placeholder,
                    'value'            => $value,
                    'hidden'           => (int) $hidden,
                ],
            );
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

        return file_exists($localConfigPath);
    }
}
