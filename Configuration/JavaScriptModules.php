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

return [
    'dependencies' => [
        'core',
        'backend',
    ],
    'imports' => [
        '@netresearch/nr-textdb/' => 'EXT:nr_textdb/Resources/Public/JavaScript/',
    ],
];
