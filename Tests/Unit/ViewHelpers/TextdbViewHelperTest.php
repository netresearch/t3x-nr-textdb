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

namespace Netresearch\NrTextdb\Tests\Unit\ViewHelpers;

use Netresearch\NrTextdb\Service\TranslationService;
use Netresearch\NrTextdb\ViewHelpers\TextdbViewHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;

#[CoversClass(TextdbViewHelper::class)]
final class TextdbViewHelperTest extends UnitTestCase
{
    private TextdbViewHelper $subject;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subject = $this->createSubject(self::createStub(TranslationService::class));
    }

    private function createSubject(TranslationService $translationService): TextdbViewHelper
    {
        $subject = new TextdbViewHelper($translationService);
        $subject->setRenderingContext(self::createStub(RenderingContextInterface::class));

        return $subject;
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

    /**
     * @param array<string, string> $arguments
     * @param list<string>          $expectedCall
     */
    #[Test]
    #[DataProvider('renderArgumentsProvider')]
    public function renderPassesTheArgumentsToTheTranslationService(array $arguments, array $expectedCall): void
    {
        $translationService = $this->createMock(TranslationService::class);
        $translationService
            ->expects(self::once())
            ->method('translate')
            ->with(...$expectedCall)
            ->willReturn('Translated');

        $this->subject = $this->createSubject($translationService);
        $this->subject->initializeArguments();
        $this->subject->setArguments($arguments);

        self::assertSame('Translated', $this->subject->render());
    }

    /**
     * @return array<string, array{array<string, string>, list<string>}>
     */
    public static function renderArgumentsProvider(): array
    {
        return [
            'all arguments given' => [
                ['placeholder' => 'test.key', 'type' => 'label', 'component' => 'website', 'environment' => 'default'],
                ['test.key', 'label', 'website', 'default'],
            ],
            'type P, environment default' => [
                ['placeholder' => 'key', 'type' => 'P', 'component' => 'comp', 'environment' => 'default'],
                ['key', 'P', 'comp', 'default'],
            ],
        ];
    }
}
