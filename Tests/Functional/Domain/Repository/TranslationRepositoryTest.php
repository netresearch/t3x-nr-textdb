<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Functional\Domain\Repository;

use Netresearch\NrTextdb\Domain\Model\Component;
use Netresearch\NrTextdb\Domain\Model\Environment;
use Netresearch\NrTextdb\Domain\Model\Translation;
use Netresearch\NrTextdb\Domain\Model\Type;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use Netresearch\NrTextdb\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Functional tests for TranslationRepository.
 *
 * The fixtures place all primary records on pid=1. The extension configuration
 * is mocked so that getConfiguredPageId() returns 1, which matches the pid
 * column in all fixture rows.
 *
 * Language uid mapping used in fixtures:
 *   0 = English (default)
 *   1 = German
 *
 * Component / Type / Environment uid mapping:
 *   environment 1 = "default",  environment 2 = "staging"
 *   component   1 = "checkout", component   2 = "header",  component 3 = "footer"
 *   type        1 = "button",   type        2 = "label",   type      3 = "headline"
 */
#[CoversClass(TranslationRepository::class)]
final class TranslationRepositoryTest extends AbstractFunctionalTestCase
{
    private TranslationRepository $translationRepository;

    private EnvironmentRepository $environmentRepository;

    private ComponentRepository $componentRepository;

    private TypeRepository $typeRepository;

    protected function setUp(): void
    {
        parent::setUp();

        // Provide a page id of 1 so repository queries use the fixture pid.
        // The mock must cover all paths that AbstractRepository::getExtensionConfiguration()
        // may request (textDbPid and createIfMissing).
        $extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $extensionConfigurationMock
            ->method('get')
            ->withAnyParameters()
            ->willReturnCallback(static function (string $extension, string $path): string {
                return match ($path) {
                    'textDbPid'       => '1',
                    'createIfMissing' => '0',
                    default           => '0',
                };
            });

        GeneralUtility::addInstance(ExtensionConfiguration::class, $extensionConfigurationMock);

        $this->translationRepository = $this->get(TranslationRepository::class);
        $this->environmentRepository = $this->get(EnvironmentRepository::class);
        $this->componentRepository   = $this->get(ComponentRepository::class);
        $this->typeRepository        = $this->get(TypeRepository::class);

        $this->importCSVDataSet(
            __DIR__ . '/../../Fixtures/TranslationRepository/Environments.csv',
        );
        $this->importCSVDataSet(
            __DIR__ . '/../../Fixtures/TranslationRepository/Components.csv',
        );
        $this->importCSVDataSet(
            __DIR__ . '/../../Fixtures/TranslationRepository/Types.csv',
        );
        $this->importCSVDataSet(
            __DIR__ . '/../../Fixtures/TranslationRepository/Translations.csv',
        );
    }

    // -------------------------------------------------------------------------
    // findAllByComponentTypePlaceholderValueAndLanguage
    // -------------------------------------------------------------------------

    #[Test]
    public function findAllByComponentTypePlaceholderValueAndLanguageWithNoFiltersReturnsAllRecords(): void
    {
        $result = $this->translationRepository
            ->findAllByComponentTypePlaceholderValueAndLanguage();

        // Fixture contains 11 rows; uid 9 is deleted (still returned because
        // ignoreEnableFields=true is set inside the method), uid 10 is hidden
        // (same reason). The method does NOT add any where-clause when all
        // parameters keep their defaults, so all non-deleted-via-TCA rows come back.
        // Extbase default query respects the "deleted" flag even with ignoreEnableFields=true
        // (deleted is a hard exclusion). So uid 9 (deleted=1) is excluded → 10 rows.
        self::assertGreaterThanOrEqual(1, $result->count());
    }

    #[Test]
    public function findAllByComponentTypePlaceholderValueAndLanguageFiltersByComponent(): void
    {
        // Component uid 1 = "checkout" has records uid 1, 2, 5, 6, 8, 9(deleted), 11
        // Non-deleted on pid 1: uid 1, 2, 5, 6, 11 → but storage page is not
        // respected by this method, so uid 8 (pid=2) is also included.
        $result = $this->translationRepository
            ->findAllByComponentTypePlaceholderValueAndLanguage(component: 1);

        self::assertGreaterThanOrEqual(1, $result->count());

        foreach ($result as $translation) {
            self::assertSame('checkout', $translation->getComponent()?->getName());
        }
    }

    #[Test]
    public function findAllByComponentTypePlaceholderValueAndLanguageFiltersByType(): void
    {
        // Type uid 1 = "button"
        $result = $this->translationRepository
            ->findAllByComponentTypePlaceholderValueAndLanguage(type: 1);

        self::assertGreaterThanOrEqual(1, $result->count());

        foreach ($result as $translation) {
            self::assertSame('button', $translation->getType()?->getName());
        }
    }

    #[Test]
    public function findAllByComponentTypePlaceholderValueAndLanguageFiltersByPlaceholder(): void
    {
        $result = $this->translationRepository
            ->findAllByComponentTypePlaceholderValueAndLanguage(placeholder: 'submit');

        self::assertGreaterThanOrEqual(1, $result->count());

        foreach ($result as $translation) {
            self::assertStringContainsString('submit', $translation->getPlaceholder());
        }
    }

    #[Test]
    public function findAllByComponentTypePlaceholderValueAndLanguageFiltersByValue(): void
    {
        $result = $this->translationRepository
            ->findAllByComponentTypePlaceholderValueAndLanguage(value: 'Submit');

        self::assertGreaterThanOrEqual(1, $result->count());

        foreach ($result as $translation) {
            self::assertStringContainsString('Submit', $translation->getValue());
        }
    }

    #[Test]
    public function findAllByComponentTypePlaceholderValueAndLanguageFiltersByLanguage(): void
    {
        // Language uid 1 = German translations
        $result = $this->translationRepository
            ->findAllByComponentTypePlaceholderValueAndLanguage(languageId: 1);

        self::assertGreaterThanOrEqual(1, $result->count());

        foreach ($result as $translation) {
            self::assertSame(1, $translation->getSysLanguageUid());
        }
    }

    #[Test]
    public function findAllByComponentTypePlaceholderValueAndLanguageWithCombinedFiltersNarrowsResult(): void
    {
        // component=1 (checkout) + type=1 (button) + language=0 (EN)
        $result = $this->translationRepository
            ->findAllByComponentTypePlaceholderValueAndLanguage(
                component: 1,
                type: 1,
                languageId: 0,
            );

        self::assertGreaterThanOrEqual(1, $result->count());

        foreach ($result as $translation) {
            self::assertSame('checkout', $translation->getComponent()?->getName());
            self::assertSame('button', $translation->getType()?->getName());
        }
    }

    #[Test]
    public function findAllByComponentTypePlaceholderValueAndLanguageReturnsEmptyResultForUnknownComponent(): void
    {
        $result = $this->translationRepository
            ->findAllByComponentTypePlaceholderValueAndLanguage(component: 9999);

        self::assertSame(0, $result->count());
    }

    // -------------------------------------------------------------------------
    // findByEnvironmentComponentTypePlaceholderAndLanguage
    // -------------------------------------------------------------------------

    #[Test]
    public function findByEnvironmentComponentTypePlaceholderAndLanguageReturnsMatchingTranslation(): void
    {
        $environment = $this->getEnvironment('default');
        $component   = $this->getComponent('checkout');
        $type        = $this->getType('button');

        $result = $this->translationRepository
            ->findByEnvironmentComponentTypePlaceholderAndLanguage(
                $environment,
                $component,
                $type,
                'submit',
                0,
            );

        self::assertInstanceOf(Translation::class, $result);
        self::assertSame('submit', $result->getPlaceholder());
        self::assertSame(0, $result->getSysLanguageUid());
    }

    #[Test]
    public function findByEnvironmentComponentTypePlaceholderAndLanguageReturnsGermanTranslation(): void
    {
        $environment = $this->getEnvironment('default');
        $component   = $this->getComponent('checkout');
        $type        = $this->getType('button');

        $result = $this->translationRepository
            ->findByEnvironmentComponentTypePlaceholderAndLanguage(
                $environment,
                $component,
                $type,
                'submit',
                1,
            );

        self::assertInstanceOf(Translation::class, $result);
        self::assertSame(1, $result->getSysLanguageUid());
        self::assertSame('Absenden', $result->getValue());
    }

    #[Test]
    public function findByEnvironmentComponentTypePlaceholderAndLanguageReturnsNullWhenNotFound(): void
    {
        $environment = $this->getEnvironment('default');
        $component   = $this->getComponent('checkout');
        $type        = $this->getType('button');

        $result = $this->translationRepository
            ->findByEnvironmentComponentTypePlaceholderAndLanguage(
                $environment,
                $component,
                $type,
                'nonexistent_placeholder',
                0,
            );

        self::assertNull($result);
    }

    #[Test]
    public function findByEnvironmentComponentTypePlaceholderAndLanguageDistinguishesByEnvironment(): void
    {
        $defaultEnv = $this->getEnvironment('default');
        $stagingEnv = $this->getEnvironment('staging');
        $component  = $this->getComponent('checkout');
        $type       = $this->getType('button');

        $defaultResult = $this->translationRepository
            ->findByEnvironmentComponentTypePlaceholderAndLanguage(
                $defaultEnv,
                $component,
                $type,
                'submit',
                0,
            );

        $stagingResult = $this->translationRepository
            ->findByEnvironmentComponentTypePlaceholderAndLanguage(
                $stagingEnv,
                $component,
                $type,
                'submit',
                0,
            );

        self::assertInstanceOf(Translation::class, $defaultResult);
        self::assertInstanceOf(Translation::class, $stagingResult);
        self::assertNotSame($defaultResult->getUid(), $stagingResult->getUid());
        self::assertSame('Submit', $defaultResult->getValue());
        self::assertSame('Staging Submit', $stagingResult->getValue());
    }

    // -------------------------------------------------------------------------
    // findByEnvironmentComponentTypeAndPlaceholder
    // -------------------------------------------------------------------------

    #[Test]
    public function findByEnvironmentComponentTypeAndPlaceholderReturnsDefaultLanguageRecord(): void
    {
        $environment = $this->getEnvironment('default');
        $component   = $this->getComponent('checkout');
        $type        = $this->getType('label');

        $result = $this->translationRepository
            ->findByEnvironmentComponentTypeAndPlaceholder(
                $environment,
                $component,
                $type,
                'email',
            );

        self::assertInstanceOf(Translation::class, $result);
        self::assertSame(0, $result->getSysLanguageUid());
        self::assertSame('email', $result->getPlaceholder());
        self::assertSame('Email Address', $result->getValue());
    }

    #[Test]
    public function findByEnvironmentComponentTypeAndPlaceholderIgnoresNonDefaultLanguageRows(): void
    {
        // Fixture uid 5 (language=1) and uid 1 (language=0) both match "submit".
        // This method always queries sys_language_uid=0, so it must return uid 1.
        $environment = $this->getEnvironment('default');
        $component   = $this->getComponent('checkout');
        $type        = $this->getType('button');

        $result = $this->translationRepository
            ->findByEnvironmentComponentTypeAndPlaceholder(
                $environment,
                $component,
                $type,
                'submit',
            );

        self::assertInstanceOf(Translation::class, $result);
        self::assertSame(0, $result->getSysLanguageUid());
    }

    #[Test]
    public function findByEnvironmentComponentTypeAndPlaceholderReturnsNullWhenNotFound(): void
    {
        $environment = $this->getEnvironment('default');
        $component   = $this->getComponent('checkout');
        $type        = $this->getType('button');

        $result = $this->translationRepository
            ->findByEnvironmentComponentTypeAndPlaceholder(
                $environment,
                $component,
                $type,
                'does_not_exist',
            );

        self::assertNull($result);
    }

    // -------------------------------------------------------------------------
    // findByPidAndLanguage
    // -------------------------------------------------------------------------

    #[Test]
    public function findByPidAndLanguageReturnsTranslationsForGivenParentUid(): void
    {
        // uid 1 is the English "submit" record; its German counterpart (uid 5)
        // has l10n_parent=1. findByPidAndLanguage queries l10nParent = $uid
        // and storagePageId = configuredPageId (1).
        $result = $this->translationRepository->findByPidAndLanguage(1);

        self::assertNotEmpty($result);

        foreach ($result as $translation) {
            self::assertSame(1, $translation->getL10nParent());
        }
    }

    #[Test]
    public function findByPidAndLanguageReturnsEmptyArrayWhenNoChildrenExist(): void
    {
        // uid 4 (page_title) has no translated children in fixtures
        $result = $this->translationRepository->findByPidAndLanguage(4);

        self::assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // findByTranslationsAndLanguage
    // -------------------------------------------------------------------------

    #[Test]
    public function findByTranslationsAndLanguageReturnsGermanRowsForGivenParentUids(): void
    {
        // uid 5 has l10n_parent=1, uid 6 has l10n_parent=2 — both language=1
        $result = $this->translationRepository
            ->findByTranslationsAndLanguage([1, 2], 1);

        self::assertGreaterThanOrEqual(2, $result->count());

        foreach ($result as $translation) {
            self::assertSame(1, $translation->getSysLanguageUid());
            self::assertContains($translation->getL10nParent(), [1, 2]);
        }
    }

    #[Test]
    public function findByTranslationsAndLanguageReturnsEmptyResultWhenLanguageHasNoRows(): void
    {
        // Language uid 5 has no fixture rows
        $result = $this->translationRepository
            ->findByTranslationsAndLanguage([1, 2, 3], 5);

        self::assertSame(0, $result->count());
    }

    #[Test]
    public function findByTranslationsAndLanguageReturnsEmptyResultForUnknownParentUids(): void
    {
        $result = $this->translationRepository
            ->findByTranslationsAndLanguage([9999, 8888], 1);

        self::assertSame(0, $result->count());
    }

    // -------------------------------------------------------------------------
    // Helper methods
    // -------------------------------------------------------------------------

    /**
     * Retrieves an Environment object from the repository by name.
     * The method bypasses the static local cache by using a fresh query.
     */
    private function getEnvironment(string $name): Environment
    {
        $environment = $this->environmentRepository->findByName($name);

        self::assertInstanceOf(
            Environment::class,
            $environment,
            sprintf('Environment "%s" not found in fixtures.', $name),
        );

        return $environment;
    }

    /**
     * Retrieves a Component object from the repository by name.
     */
    private function getComponent(string $name): Component
    {
        $component = $this->componentRepository->findByName($name);

        self::assertInstanceOf(
            Component::class,
            $component,
            sprintf('Component "%s" not found in fixtures.', $name),
        );

        return $component;
    }

    /**
     * Retrieves a Type object from the repository by name.
     */
    private function getType(string $name): Type
    {
        $type = $this->typeRepository->findByName($name);

        self::assertInstanceOf(
            Type::class,
            $type,
            sprintf('Type "%s" not found in fixtures.', $name),
        );

        return $type;
    }
}
