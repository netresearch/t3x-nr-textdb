<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

// The Netresearch module group is shared: on 13.4, nr_sync and
// universal_messenger 2.x register the same identifier, and the extension
// loaded last wins. nr_textdb and nr_sync ship ModuleGroup.svg with identical
// bytes. The module menu renders the icon inline, so its currentColor letter
// follows the backend colour scheme.
return [
    'extension-netresearch-module' => [
        'provider' => SvgIconProvider::class,
        'source'   => 'EXT:nr_textdb/Resources/Public/Icons/ModuleGroup.svg',
    ],
    'extension-netresearch-textdb' => [
        'provider' => SvgIconProvider::class,
        'source'   => 'EXT:nr_textdb/Resources/Public/Icons/Module.svg',
    ],
];
