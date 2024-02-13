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
    'web_NrTextdbMod1' => [
        'parent'                                   => 'web',
        'position'                                 => [],
        'access'                                   => 'user',
        'iconIdentifier'                           => 'tx-textdb-module-web',
        'path'                                     => '/module/web/textdb',
        'labels'                                   => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_be.xlf',
        'extensionName'                            => 'NrTextdb',
        'inheritNavigationComponentFromMainModule' => false,
        'navigationComponentId'                    => '',
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
