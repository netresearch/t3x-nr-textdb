<?php

/**
 * This file is part of the package netresearch/nr-textdb.
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
    'imports'      => [
        '@vendor/nr-textdb/' => 'EXT:nr_textdb/Resources/Public/JavaScript/',
    ],
];
