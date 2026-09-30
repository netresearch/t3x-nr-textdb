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

use InvalidArgumentException;

/**
 * Resolves a label key the way LocalizationUtility::translate() does.
 *
 * @internal seam for the TranslateViewHelper and its tests, not part of the extension's public API
 */
interface LabelTranslatorInterface
{
    /**
     * @throws InvalidArgumentException When the key cannot be mapped to a language file (1498144052)
     */
    public function translate(string $key, ?string $extensionName = null): ?string;
}
