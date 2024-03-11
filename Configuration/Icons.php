<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'extension-netresearch-module' => [
        'provider' => SvgIconProvider::class,
        'source'   => 'EXT:nr_textdb/Resources/Public/Icons/Module.svg',
    ],
    'extension-netresearch-textdb' => [
        'provider' => SvgIconProvider::class,
        'source'   => 'EXT:nr_textdb/Resources/Public/Icons/Extension.svg',
    ],
];
