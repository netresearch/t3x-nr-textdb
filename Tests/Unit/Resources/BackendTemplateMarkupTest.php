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

namespace Netresearch\NrTextdb\Tests\Unit\Resources;

use DOMDocument;
use DOMElement;
use DOMXPath;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Pins the backend module templates to core button classes, accessible names
 * and headings. The Fluid source is read as HTML with the "f:" prefix
 * rewritten to "f-", so a Fluid tag appears as an element named e.g.
 * "f-form.select" whatever libxml does with prefixed names.
 */
#[CoversNothing]
final class BackendTemplateMarkupTest extends UnitTestCase
{
    private const TEMPLATE_DIR = __DIR__ . '/../../../Resources/Private/Templates/Translation/';

    #[Test]
    public function listSearchButtonIsTheNeutralCoreButton(): void
    {
        $xpath   = $this->load('List.html');
        $buttons = $xpath->query('//*[local-name()="f-form.button"][@type="submit"]');
        self::assertNotFalse($buttons);
        self::assertSame(1, $buttons->length);

        $button = $buttons->item(0);
        self::assertInstanceOf(DOMElement::class, $button);

        $classes = explode(' ', $button->getAttribute('class'));
        self::assertContains('btn-default', $classes);
        self::assertNotContains('btn-secondary', $classes);
    }

    #[Test]
    public function everyListFilterControlHasALabelPointingAtItsId(): void
    {
        $xpath    = $this->load('List.html');
        $controls = $xpath->query('//*[local-name()="f-form.select" or local-name()="f-form.textfield"]');
        self::assertNotFalse($controls);
        self::assertSame(4, $controls->length);

        foreach ($controls as $control) {
            self::assertInstanceOf(DOMElement::class, $control);

            $id = $control->getAttribute('id');
            self::assertNotSame('', $id, 'filter "' . $control->getAttribute('name') . '" has no id');

            $labels = $xpath->query('//label[@for="' . $id . '"]');
            self::assertNotFalse($labels);
            self::assertSame(1, $labels->length, 'no label points at #' . $id);
        }
    }

    #[Test]
    public function importViewHasOneHeadingOne(): void
    {
        $headings = $this->load('Import.html')->query('//h1');
        self::assertNotFalse($headings);
        self::assertSame(1, $headings->length);
    }

    #[Test]
    public function translatedViewHasOneHeadingOneOutsideTheInjectedFragment(): void
    {
        $xpath = $this->load('Translated.html');

        $headings = $xpath->query('//h1');
        self::assertNotFalse($headings);
        self::assertSame(1, $headings->length);

        // TextDbModule.js injects the children of .return into the list view,
        // which already has its own h1.
        $injected = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " return ")]//h1');
        self::assertNotFalse($injected);
        self::assertSame(0, $injected->length);
    }

    #[Test]
    public function translatedTableHasColumnHeadersAndLabelledTextareas(): void
    {
        $xpath = $this->load('Translated.html');

        $columnHeaders = $xpath->query('//table/thead/tr/th[@scope="col"]');
        self::assertNotFalse($columnHeaders);
        self::assertSame(2, $columnHeaders->length);

        $textareas = $xpath->query('//*[local-name()="f-form.textarea"]');
        self::assertNotFalse($textareas);
        self::assertSame(2, $textareas->length);

        foreach ($textareas as $textarea) {
            self::assertInstanceOf(DOMElement::class, $textarea);

            $labelledBy = $textarea->getAttribute('aria-labelledby');
            self::assertNotSame('', $labelledBy, 'textarea "' . $textarea->getAttribute('name') . '" has no accessible name');

            $rowHeader = $xpath->query('ancestor::tr[1]/th[@scope="row"][@id="' . $labelledBy . '"]', $textarea);
            self::assertNotFalse($rowHeader);
            self::assertSame(1, $rowHeader->length, 'aria-labelledby must point at the row header of the same row');
        }
    }

    private function load(string $template): DOMXPath
    {
        $contents = file_get_contents(self::TEMPLATE_DIR . $template);
        self::assertIsString($contents);

        $contents = str_replace(['<f:', '</f:'], ['<f-', '</f-'], $contents);

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($contents);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }
}
