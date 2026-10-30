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

namespace Netresearch\NrTextdb\Tests\Functional\Service;

use Netresearch\NrTextdb\Service\TranslationService;
use Netresearch\NrTextdb\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Which site's languages the extension works with (#137).
 *
 * Two sites: "a-other" (root page 2, English and French) sorts first, and
 * "textdb" (root page 1, English, German and Italian) holds the storage
 * page. Page 3 lies inside "a-other", page 4 at root level outside any site.
 */
#[CoversClass(TranslationService::class)]
final class TranslationServiceTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importFixture('Pages.csv');

        $pages = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('pages');
        $pages->insert('pages', ['uid' => 2, 'pid' => 0, 'title' => 'Other site']);
        $pages->insert('pages', ['uid' => 3, 'pid' => 2, 'title' => 'Page in the other site']);
        $pages->insert('pages', ['uid' => 4, 'pid' => 0, 'title' => 'Folder outside any site']);

        $this->writeSite('a-other', 2, ['en_US' => 'English', 'fr_FR' => 'French']);
        $this->writeSite('textdb', 1, ['en_US' => 'English', 'de_DE' => 'German', 'it_IT' => 'Italian']);

        $this->get(SiteConfiguration::class)->getAllExistingSites(false);

        $sites = $this->get(SiteFinder::class)->getAllSites(false);
        self::assertSame('a-other', reset($sites)->getIdentifier(), 'Precondition: the other site comes first.');
    }

    /**
     * @param list<string> $expectedTitles
     */
    #[Test]
    #[DataProvider('storagePageProvider')]
    public function getAllLanguagesReturnsTheLanguagesOfTheStoragePagesSite(string $storagePage, array $expectedTitles): void
    {
        $this->setExtensionConfiguration(textDbPid: $storagePage);

        $titles = array_values(
            array_map(
                static fn (SiteLanguage $language): string => $language->getTitle(),
                $this->get(TranslationService::class)->getAllLanguages(),
            ),
        );

        self::assertSame($expectedTitles, $titles);
    }

    /**
     * @return array<string, array{string, list<string>}>
     */
    public static function storagePageProvider(): array
    {
        return [
            'storage page in the second site' => ['1', ['English', 'German', 'Italian']],
            'storage page in the first site'  => ['3', ['English', 'French']],
            // No site to take the languages from: the first site, as before.
            'storage page outside any site' => ['4', ['English', 'French']],
            'no storage page configured'    => ['0', ['English', 'French']],
        ];
    }

    /**
     * @param array<string, string> $languages Locale prefix => title, in language id order
     */
    private function writeSite(string $identifier, int $rootPageId, array $languages): void
    {
        $directory = Environment::getConfigPath() . '/sites/' . $identifier;

        if (!is_dir($directory) && !mkdir($directory, 0o777, true) && !is_dir($directory)) {
            self::fail('Could not create site directory ' . $directory);
        }

        $yaml = 'rootPageId: ' . $rootPageId . "\nbase: 'https://" . $identifier . ".example.com/'\nlanguages:\n";
        $id   = 0;
        foreach ($languages as $locale => $title) {
            $yaml .= '  - languageId: ' . $id . "\n"
                . '    title: ' . $title . "\n"
                . '    locale: ' . $locale . ".UTF-8\n"
                . '    base: ' . ($id === 0 ? '/' : '/' . substr($locale, 0, 2) . '/') . "\n";
            ++$id;
        }

        self::assertNotFalse(file_put_contents($directory . '/config.yaml', $yaml));
    }
}
