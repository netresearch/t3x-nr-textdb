<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\NrTextdb\Controller\TranslationController;

return [
    'netresearch_module' => [
        'labels'         => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_mod.xlf',
        'iconIdentifier' => 'extension-netresearch-module',
        'position'       => [
            'after' => 'web',
        ],
    ],
    'netresearch_textdb' => [
        'parent'                                   => 'netresearch_module',
        'position'                                 => [],
        'access'                                   => 'user',
        'iconIdentifier'                           => 'extension-netresearch-textdb',
        'path'                                     => '/module/netresearch/textdb',
        'labels'                                   => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_mod_textdb.xlf',
        'extensionName'                            => 'NrTextdb',
        'inheritNavigationComponentFromMainModule' => false,
        'navigationComponent'                      => '',
        'controllerActions'                        => [
            TranslationController::class => [
                'list',
                'translated',
                'translateRecord',
                'import',
                'export',
            ],
        ],
    ],
];
