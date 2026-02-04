<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Unit\Task;

use Netresearch\NrTextdb\Task\ProcessMessengerQueueTaskAdditionalFieldProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use TYPO3\CMS\Scheduler\AdditionalFieldProviderInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case for ProcessMessengerQueueTaskAdditionalFieldProvider.
 */
#[CoversClass(ProcessMessengerQueueTaskAdditionalFieldProvider::class)]
final class ProcessMessengerQueueTaskAdditionalFieldProviderTest extends UnitTestCase
{
    private ProcessMessengerQueueTaskAdditionalFieldProvider $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = new ProcessMessengerQueueTaskAdditionalFieldProvider();
    }

    #[Test]
    public function providerCanBeInstantiated(): void
    {
        self::assertInstanceOf(ProcessMessengerQueueTaskAdditionalFieldProvider::class, $this->subject);
    }

    #[Test]
    public function providerImplementsAdditionalFieldProviderInterface(): void
    {
        $reflection = new ReflectionClass(ProcessMessengerQueueTaskAdditionalFieldProvider::class);

        self::assertTrue($reflection->implementsInterface(AdditionalFieldProviderInterface::class));
    }

    #[Test]
    public function providerHasGetAdditionalFieldsMethod(): void
    {
        $reflection = new ReflectionClass(ProcessMessengerQueueTaskAdditionalFieldProvider::class);

        self::assertTrue($reflection->hasMethod('getAdditionalFields'));

        $method = $reflection->getMethod('getAdditionalFields');
        self::assertSame(3, $method->getNumberOfRequiredParameters());
        self::assertTrue($method->hasReturnType());
        self::assertSame('array', (string) $method->getReturnType());
    }

    #[Test]
    public function providerHasValidateAdditionalFieldsMethod(): void
    {
        $reflection = new ReflectionClass(ProcessMessengerQueueTaskAdditionalFieldProvider::class);

        self::assertTrue($reflection->hasMethod('validateAdditionalFields'));

        $method = $reflection->getMethod('validateAdditionalFields');
        self::assertSame(2, $method->getNumberOfRequiredParameters());
        self::assertTrue($method->hasReturnType());
        self::assertSame('bool', (string) $method->getReturnType());
    }

    #[Test]
    public function providerHasSaveAdditionalFieldsMethod(): void
    {
        $reflection = new ReflectionClass(ProcessMessengerQueueTaskAdditionalFieldProvider::class);

        self::assertTrue($reflection->hasMethod('saveAdditionalFields'));

        $method = $reflection->getMethod('saveAdditionalFields');
        self::assertSame(2, $method->getNumberOfRequiredParameters());
        self::assertTrue($method->hasReturnType());
        self::assertSame('void', (string) $method->getReturnType());
    }
}
