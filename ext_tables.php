<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\NrTextdb\Controller\TranslationController;
use Netresearch\NrTextdb\Hooks\Sync;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') || exit('Access denied.');

call_user_func(static function (): void {
    // Sync
    if (ExtensionManagementUtility::isLoaded('nr_sync')) {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['nr_sync/mod1/index.php']['hookClass'][1_624_345_948]
            = Sync::class;
    }

    // Prevent adding the main module twice
    if (!isset($GLOBALS['TBE_MODULES']['netresearchModule'])) {
        ExtensionManagementUtility::addModule(
            'netresearchModule',
            '',
            'after:web',
            null,
            [
                'name'           => 'netresearchModule',
                'iconIdentifier' => 'extension-netresearch-module',
                'labels'         => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_mod.xlf',
            ]
        );
    }

    ExtensionUtility::registerModule(
        'NrTextdb',
        'netresearchModule',
        'textdb',
        '',
        [
            TranslationController::class => 'list, translated, translateRecord, import, export',
        ],
        [
            'access'                                   => 'user, group',
            'iconIdentifier'                           => 'extension-netresearch-textdb',
            'path'                                     => '/module/netresearch/textdb',
            'labels'                                   => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_mod_textdb.xlf',
            'inheritNavigationComponentFromMainModule' => false,
            'navigationComponentId'                    => '',
        ]
    );

    ExtensionManagementUtility::allowTableOnStandardPages('tx_nrtextdb_domain_model_environment');
    ExtensionManagementUtility::allowTableOnStandardPages('tx_nrtextdb_domain_model_component');
    ExtensionManagementUtility::allowTableOnStandardPages('tx_nrtextdb_domain_model_type');
    ExtensionManagementUtility::allowTableOnStandardPages('tx_nrtextdb_domain_model_translation');
});
