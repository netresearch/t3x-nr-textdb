<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Service;

use InvalidArgumentException;

/**
 * Resolves a label key the way LocalizationUtility::translate() does.
 */
interface LabelTranslatorInterface
{
    /**
     * @throws InvalidArgumentException When the key cannot be mapped to a language file (1498144052)
     */
    public function translate(string $key, ?string $extensionName = null): ?string;
}
