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

namespace Netresearch\NrTextdb\Tests\Functional\Backend;

use Netresearch\NrTextdb\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Module\ModuleProvider;

/**
 * The TextDB module sits in the core "Content" group, like the other
 * page-independent editor modules, instead of a top-level group of its own.
 */
#[CoversNothing]
final class ModuleRegistrationTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function textdbModuleIsASubmoduleOfTheContentGroup(): void
    {
        $module = $this->get(ModuleProvider::class)->getModule('netresearch_textdb');

        self::assertNotNull($module);
        self::assertSame('content', $module->getParentModule()?->getIdentifier());
        self::assertSame('/module/netresearch/textdb', $module->getPath());
    }

    #[Test]
    public function extensionRegistersNoTopLevelGroup(): void
    {
        self::assertFalse($this->get(ModuleProvider::class)->isModuleRegistered('netresearch_module'));
    }
}
