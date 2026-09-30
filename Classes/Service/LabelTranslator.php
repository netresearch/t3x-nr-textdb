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

namespace Netresearch\NrTextdb\Service;

use Override;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Injectable wrapper around the static LocalizationUtility::translate().
 *
 * The TranslateViewHelper asks it for the LLL fallback of a key. Being a
 * service behind an interface instead of a static call lets the functional
 * tests count how often the ViewHelper actually asks for a label.
 *
 * @internal seam for the TranslateViewHelper and its tests, not part of the extension's public API
 */
final readonly class LabelTranslator implements LabelTranslatorInterface
{
    #[Override]
    public function translate(string $key, ?string $extensionName = null): ?string
    {
        return LocalizationUtility::translate($key, $extensionName);
    }
}
