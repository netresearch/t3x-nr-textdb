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

namespace Netresearch\NrTextdb\Tests\Functional\Command;

use Netresearch\NrTextdb\Command\ImportCommand;
use Netresearch\NrTextdb\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Functional tests for the nr_textdb:import command.
 *
 * The fixture extension textdb_import_test carries a German XLIFF 1.2 and a
 * French XLIFF 2.0 file. Each holds one approved unit, one that is not
 * approved, one without text and a plural unit with one form that is not
 * approved; the French one also a unit without <target>, which XliffLoader leaves out of the catalogue entirely. An
 * Italian file holds twelve units without text, the first with a key that
 * contains a console style tag.
 */
#[CoversClass(ImportCommand::class)]
final class ImportCommandTest extends AbstractFunctionalTestCase
{
    /**
     * @var non-empty-string[]
     */
    protected array $testExtensionsToLoad = [
        'netresearch/nr-textdb',
        __DIR__ . '/../Fixtures/Extensions/textdb_import_test',
    ];

    /**
     * $GLOBALS['TYPO3_CONF_VARS']['LANG'] as it was before the test.
     */
    private mixed $languageConfigurationBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->languageConfigurationBackup = $GLOBALS['TYPO3_CONF_VARS']['LANG'] ?? null;

        $this->setExtensionConfiguration(textDbPid: '1', createIfMissing: '1');
    }

    protected function tearDown(): void
    {
        if ($this->languageConfigurationBackup === null) {
            unset($GLOBALS['TYPO3_CONF_VARS']['LANG']);
        } else {
            $GLOBALS['TYPO3_CONF_VARS']['LANG'] = $this->languageConfigurationBackup;
        }

        parent::tearDown();
    }

    #[Test]
    public function importNamesTheUnitsItSkippedPerFile(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations'] = true;

        $display = $this->runImport();

        $unapproved = "Translations not imported because they are not approved and \$GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations'] is enabled (1): skipcomponent|label|draft";
        $empty      = 'Translations not imported because they have no text (1): skipcomponent|label|untranslated';
        $partly     = "Plural translations imported without their forms that are not approved, because \$GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations'] is enabled (1): skipcomponent|label|plural";

        // Once for the German XLIFF 1.2 file, once for the French XLIFF 2.0 file.
        self::assertSame(2, substr_count($display, $unapproved), $display);
        self::assertSame(2, substr_count($display, $empty), $display);
        // Without a site both files import into language 0. The French file
        // finds the plural record the German file wrote and keeps it, so it
        // does not name it.
        self::assertSame(1, substr_count($display, $partly), $display);
        self::assertStringNotContainsString('without_target', $display);
        self::assertStringContainsString('Imported: 2, Updated: 0', $display);
    }

    #[Test]
    public function importPrintsTheFirstTenKeysAsTheyAre(): void
    {
        $display = $this->runImport();

        self::assertStringContainsString(
            'Translations not imported because they have no text (12): skipcomponent|label|<info>styled</info>, skipcomponent|label|untranslated_01, ',
            $display,
        );
        self::assertStringContainsString('skipcomponent|label|untranslated_09 and 2 more', $display);
        self::assertStringNotContainsString('untranslated_10', $display);
    }

    #[Test]
    public function importNamesNoUnapprovedUnitWhenApprovalIsNotRequired(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['LANG']['requireApprovedLocalizations'] = false;

        $display = $this->runImport();

        self::assertStringNotContainsString('not approved', $display);
        self::assertStringNotContainsString('Plural translations', $display);
        self::assertSame(
            2,
            substr_count($display, 'Translations not imported because they have no text (1): skipcomponent|label|untranslated'),
            $display,
        );
        self::assertStringContainsString('Imported: 3, Updated: 0', $display);
    }

    /**
     * Runs the command for the fixture extension and returns its output.
     */
    private function runImport(): string
    {
        $tester = new CommandTester($this->get(ImportCommand::class));
        $tester->execute(['extensionKey' => 'textdb_import_test']);

        self::assertSame(0, $tester->getStatusCode());

        return $tester->getDisplay();
    }
}
