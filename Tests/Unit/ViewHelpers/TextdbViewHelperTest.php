<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Unit\ViewHelpers;

use Netresearch\NrTextdb\Service\TranslationService;
use Netresearch\NrTextdb\ViewHelpers\TextdbViewHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

#[CoversClass(TextdbViewHelper::class)]
final class TextdbViewHelperTest extends UnitTestCase
{
    private TextdbViewHelper $subject;

    private TranslationService&MockObject $translationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translationService = $this->createMock(TranslationService::class);
        $this->subject            = new TextdbViewHelper($this->translationService);
        $this->subject->setRenderingContext($this->createMock(RenderingContextInterface::class));
    }

    #[Test]
    public function initializeArgumentsRegistersExpectedArguments(): void
    {
        $this->subject->initializeArguments();

        $arguments = $this->subject->prepareArguments();

        self::assertArrayHasKey('placeholder', $arguments);
        self::assertArrayHasKey('type', $arguments);
        self::assertArrayHasKey('component', $arguments);
        self::assertArrayHasKey('environment', $arguments);
    }

    #[Test]
    public function typeArgumentHasDefaultValueP(): void
    {
        $this->subject->initializeArguments();
        $arguments = $this->subject->prepareArguments();

        self::assertSame('P', $arguments['type']->getDefaultValue());
        self::assertFalse($arguments['type']->isRequired());
    }

    #[Test]
    public function environmentArgumentHasDefaultValueDefault(): void
    {
        $this->subject->initializeArguments();
        $arguments = $this->subject->prepareArguments();

        self::assertSame('default', $arguments['environment']->getDefaultValue());
        self::assertFalse($arguments['environment']->isRequired());
    }

    #[Test]
    public function renderCallsTranslationService(): void
    {
        $this->subject->initializeArguments();
        $this->subject->setArguments([
            'placeholder' => 'test.key',
            'type'        => 'label',
            'component'   => 'website',
            'environment' => 'default',
        ]);

        $this->translationService
            ->expects(self::once())
            ->method('translate')
            ->with('test.key', 'label', 'website', 'default')
            ->willReturn('Translated');

        $result = $this->subject->render();

        self::assertSame('Translated', $result);
    }

    #[Test]
    public function renderUsesDefaultEnvironment(): void
    {
        $this->subject->initializeArguments();
        $this->subject->setArguments([
            'placeholder' => 'key',
            'type'        => 'P',
            'component'   => 'comp',
            'environment' => 'default',
        ]);

        $this->translationService
            ->method('translate')
            ->with('key', 'P', 'comp', 'default')
            ->willReturn('Result');

        $result = $this->subject->render();

        self::assertSame('Result', $result);
    }
}
