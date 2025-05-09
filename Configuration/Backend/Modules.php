<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\NrTextdb\Controller\TranslationController;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

// Caution, variable name must not exist within \TYPO3\CMS\Core\Package\AbstractServiceProvider::configureBackendModules
$backendModulesConfiguration = [
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
        'inheritNavigationComponentFromMainModule' => false,

        // Extbase module configuration options
        'extensionName'                            => 'NrTextdb',
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

if (ExtensionManagementUtility::isLoaded('netresearch/nr-sync')) {
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
                'target' => \Netresearch\Sync\Controller\BaseSyncModuleController::class . '::indexAction',
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
