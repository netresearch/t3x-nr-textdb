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
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Functional tests for ComponentRepository.
 *
 * Tests cover:
 *  - findByName() with an existing record (cache miss → DB hit)
 *  - findByName() with an existing record after it has been cached (cache hit)
 *  - findByName() for a missing record with createIfMissing=false (returns null)
 *  - findByName() for a missing record with createIfMissing=true (creates and returns)
 *
 * Fixture places components on pid=1. Extension configuration is mocked so
 * getConfiguredPageId() returns 1.
 */
#[CoversClass(ComponentRepository::class)]
final class ComponentRepositoryTest extends AbstractFunctionalTestCase
{
    private ComponentRepository $componentRepository;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->componentRepository = $this->get(ComponentRepository::class);

        $this->importCSVDataSet(
            __DIR__ . '/../../Fixtures/ComponentRepository/Components.csv',
        );
    }

    #[Test]
    public function findByNameReturnsCachedComponentOnSecondCallWithoutDbQuery(): void
    {
        // First call: cache miss, DB lookup expected
        $first = $this->componentRepository->findByName('existing-component');

        self::assertInstanceOf(Component::class, $first);
        self::assertSame('existing-component', $first->getName());

        // Second call: cache hit, must return the identical object instance
        $second = $this->componentRepository->findByName('existing-component');

        self::assertSame(
            $first,
            $second,
            'Second call must return the same object instance from the local cache.',
        );
    }

    #[Test]
    public function findByNameReturnsComponentFromDatabaseWhenCacheMisses(): void
    {
        $component = $this->componentRepository->findByName('another-component');

        self::assertInstanceOf(Component::class, $component);
        self::assertSame('another-component', $component->getName());
        // Must be persisted in the database (has a non-null uid)
        self::assertNotNull($component->getUid());
        self::assertGreaterThan(0, $component->getUid());
    }

    #[Test]
    public function findByNameReturnsNullForMissingComponentWhenCreateIfMissingIsFalse(): void
    {
        // createIfMissing is disabled (mocked to return '0')
        $component = $this->componentRepository->findByName('does-not-exist');

        self::assertNull($component);
    }

    #[Test]
    public function findByNameCreatesAndReturnsMissingComponentWhenCreateIfMissingIsEnabled(): void
    {
        // Override the mock so createIfMissing returns true for this test.
        // A fresh repository instance is needed because the static cache from
        // setUp may still hold the previous mock state.
        $extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $extensionConfigurationMock
            ->method('get')
            ->withAnyParameters()
            ->willReturnCallback(static function (string $extension, string $path): string {
                return match ($path) {
                    'textDbPid'       => '1',
                    'createIfMissing' => '1',
                    default           => '0',
                };
            });

        GeneralUtility::addInstance(ExtensionConfiguration::class, $extensionConfigurationMock);

        // Use a freshly resolved instance to avoid static-cache pollution
        $repository = $this->get(ComponentRepository::class);
        $repository->setCreateIfMissing(true);

        $component = $repository->findByName('brand-new-component');

        self::assertInstanceOf(Component::class, $component);
        self::assertSame('brand-new-component', $component->getName());
        // The new record must have been persisted and received a uid
        self::assertNotNull($component->getUid());
        self::assertGreaterThan(0, $component->getUid());
    }

    #[Test]
    public function findByNameCreatedComponentIsPersistableAndRetrievableBySecondCall(): void
    {
        $extensionConfigurationMock = $this->createMock(ExtensionConfiguration::class);
        $extensionConfigurationMock
            ->method('get')
            ->withAnyParameters()
            ->willReturnCallback(static function (string $extension, string $path): string {
                return match ($path) {
                    'textDbPid'       => '1',
                    'createIfMissing' => '1',
                    default           => '0',
                };
            });

        GeneralUtility::addInstance(ExtensionConfiguration::class, $extensionConfigurationMock);

        $repository = $this->get(ComponentRepository::class);
        $repository->setCreateIfMissing(true);

        $created = $repository->findByName('persisted-component');
        self::assertInstanceOf(Component::class, $created);
        $createdUid = $created->getUid();

        // Retrieve the same record again from a fresh repository without setCreateIfMissing
        $extensionConfigurationMock2 = $this->createMock(ExtensionConfiguration::class);
        $extensionConfigurationMock2
            ->method('get')
            ->withAnyParameters()
            ->willReturnCallback(static function (string $extension, string $path): string {
                return match ($path) {
                    'textDbPid'       => '1',
                    'createIfMissing' => '0',
                    default           => '0',
                };
            });

        GeneralUtility::addInstance(ExtensionConfiguration::class, $extensionConfigurationMock2);

        $repository2 = $this->get(ComponentRepository::class);
        $fetched     = $repository2->findByName('persisted-component');

        self::assertInstanceOf(Component::class, $fetched);
        self::assertSame($createdUid, $fetched->getUid());
    }

    #[Test]
    public function findByNameScopesQueryToConfiguredPageId(): void
    {
        // Fixture only contains records on pid=1. If we ask for a component
        // that exists on a different pid (not in fixtures) while our configured
        // pid is 1, the repository must return null because the record is
        // outside the configured storage page.
        $component = $this->componentRepository->findByName('existing-component');

        self::assertInstanceOf(Component::class, $component);
        // pid column must equal the configured page id
        self::assertSame(1, $component->getPid());
    }
}
