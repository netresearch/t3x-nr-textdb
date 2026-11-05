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

use Netresearch\NrTextdb\Controller\TranslationController;
use Netresearch\Sync\Controller\BaseSyncModuleController;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

// Caution, variable name must not exist within \TYPO3\CMS\Core\Package\AbstractServiceProvider::configureBackendModules
$backendModulesConfiguration = [
    // Editors maintain the frontend texts here, so the module sits in the
    // core "Content" group, next to the other page-independent editor modules
    // such as Forms. The identifier and path stay unchanged: group
    // permissions and bookmarks refer to them.
    'netresearch_textdb' => [
        'parent'                                   => 'content',
        'position'                                 => [],
        'access'                                   => 'user',
        'iconIdentifier'                           => 'extension-netresearch-textdb',
        'path'                                     => '/module/netresearch/textdb',
        'labels'                                   => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_mod_textdb.xlf',
        'inheritNavigationComponentFromMainModule' => false,

        // Extbase module configuration options
        'extensionName'     => 'NrTextdb',
        'controllerActions' => [
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

if (ExtensionManagementUtility::isLoaded('nr_sync')) {
    $backendModulesConfiguration['netresearch_sync_textdb'] = [
        'parent'         => 'netresearch_sync',
        'access'         => 'user',
        'path'           => '/module/netresearch/sync/textdb',
        'iconIdentifier' => 'extension-netresearch-sync',
        'labels'         => [
            'title' => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_mod_sync.xlf:mod_textdb',
        ],
        'routes' => [
            '_default' => [
                'target' => BaseSyncModuleController::class . '::indexAction',
            ],
        ],
        'moduleData' => [
            'dumpFile' => 'nr-textdb.sql',
            'tables'   => [
                'tx_nrtextdb_domain_model_component',
                'tx_nrtextdb_domain_model_environment',
                'tx_nrtextdb_domain_model_translation',
                'tx_nrtextdb_domain_model_type',
            ],
        ],
    ];
}

return $backendModulesConfiguration;
