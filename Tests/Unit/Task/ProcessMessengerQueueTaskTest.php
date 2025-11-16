<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Unit\Task;

use Netresearch\NrTextdb\Task\ProcessMessengerQueueTask;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerAwareInterface;
use ReflectionClass;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Test case for ProcessMessengerQueueTask.
 */
#[CoversClass(ProcessMessengerQueueTask::class)]
final class ProcessMessengerQueueTaskTest extends UnitTestCase
{
    #[Test]
    public function taskExtendsAbstractTask(): void
    {
        $reflection = new ReflectionClass(ProcessMessengerQueueTask::class);

        self::assertTrue($reflection->isSubclassOf(AbstractTask::class));
    }

    #[Test]
    public function taskImplementsLoggerAwareInterface(): void
    {
        $reflection = new ReflectionClass(ProcessMessengerQueueTask::class);

        self::assertTrue($reflection->implementsInterface(LoggerAwareInterface::class));
    }

    #[Test]
    public function taskHasTimeLimitProperty(): void
    {
        $reflection = new ReflectionClass(ProcessMessengerQueueTask::class);

        self::assertTrue($reflection->hasProperty('timeLimit'));

        $property = $reflection->getProperty('timeLimit');
        self::assertTrue($property->isPublic());
        self::assertTrue($property->hasType());
        self::assertSame('int', (string) $property->getType());
    }

    #[Test]
    public function taskHasTransportProperty(): void
    {
        $reflection = new ReflectionClass(ProcessMessengerQueueTask::class);

        self::assertTrue($reflection->hasProperty('transport'));

        $property = $reflection->getProperty('transport');
        self::assertTrue($property->isPublic());
        self::assertTrue($property->hasType());
        self::assertSame('string', (string) $property->getType());
    }

    #[Test]
    public function taskHasGetAdditionalInformationMethod(): void
    {
        $reflection = new ReflectionClass(ProcessMessengerQueueTask::class);

        self::assertTrue($reflection->hasMethod('getAdditionalInformation'));

        $method = $reflection->getMethod('getAdditionalInformation');
        self::assertTrue($method->hasReturnType());
        self::assertSame('string', (string) $method->getReturnType());
    }

    #[Test]
    public function taskHasExecuteMethod(): void
    {
        $reflection = new ReflectionClass(ProcessMessengerQueueTask::class);

        self::assertTrue($reflection->hasMethod('execute'));

        $method = $reflection->getMethod('execute');
        self::assertTrue($method->hasReturnType());
        self::assertSame('bool', (string) $method->getReturnType());
    }
}
