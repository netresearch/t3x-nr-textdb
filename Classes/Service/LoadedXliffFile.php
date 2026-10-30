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

/**
 * The messages of an XLIFF file as {@see ImportService::loadFile()} read them.
 *
 * @author  Netresearch DTT GmbH <typo3.dev@netresearch.de>
 * @license GPL-3.0-or-later
 *
 * @see    https://www.netresearch.de
 */
final readonly class LoadedXliffFile
{
    /**
     * @param array<array-key, mixed> $entries               The messages keyed "component|type|placeholder"
     * @param list<string>            $unapprovedKeys        Keys of the units the loader left out because they are not approved
     * @param list<string>            $partiallyApprovedKeys Keys of the plural units the loader kept without their forms that are not approved
     */
    public function __construct(
        public array $entries,
        public array $unapprovedKeys,
        public array $partiallyApprovedKeys,
    ) {}
}
