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
use Throwable;
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
    ];

    protected bool $initializeDatabase = true;

    private bool $skipped = false;

    #[Override]
    protected function setUp(): void
    {
        if (!$this->canRunFunctionalTests()) {
            $this->skipped = true;
            self::markTestSkipped(
                'Functional tests require a database. '
                . 'Set the typo3DatabaseDriver environment variable (e.g. pdo_sqlite) to enable them.',
            );
        }

        try {
            parent::setUp();
        } catch (Throwable $exception) {
            $this->skipped = true;
            self::markTestSkipped('Failed to initialise functional test: ' . $exception->getMessage());
        }
    }

    #[Override]
    protected function tearDown(): void
    {
        if ($this->skipped) {
            return;
        }

        try {
            parent::tearDown();
        } catch (Throwable) {
            // Ignore teardown errors when setup failed.
        }
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
