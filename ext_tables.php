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

defined('TYPO3') || die('Access denied.');

call_user_func(static function () {
    // Sync
    if (ExtensionManagementUtility::isLoaded('nr_sync')) {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['nr_sync/mod1/index.php']['hookClass'][1_624_345_948]
            = Sync::class;
    }

    ExtensionUtility::registerModule(
        'NrTextdb',
        'web',
        'textdb',
        '',
        [
            TranslationController::class => 'list, translated, translateRecord, import, export',
        ],
        [
            'access'                                   => 'user,group',
            'icon'                                     => 'EXT:nr_textdb/Resources/Public/Icons/user_mod_textdb.svg',
            'labels'                                   => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_textdb.xlf',
            'navigationComponentId'                    => '',
            'inheritNavigationComponentFromMainModule' => false,
        ]
    );

    ExtensionManagementUtility::addLLrefForTCAdescr(
        'tx_nrtextdb_domain_model_environment',
        'EXT:nr_textdb/Resources/Private/Language/locallang_csh_tx_nrtextdb_domain_model_environment.xlf'
    );

    ExtensionManagementUtility::addLLrefForTCAdescr(
        'tx_nrtextdb_domain_model_component',
        'EXT:nr_textdb/Resources/Private/Language/locallang_csh_tx_nrtextdb_domain_model_component.xlf'
    );

    ExtensionManagementUtility::addLLrefForTCAdescr(
        'tx_nrtextdb_domain_model_type',
        'EXT:nr_textdb/Resources/Private/Language/locallang_csh_tx_nrtextdb_domain_model_type.xlf'
    );

    ExtensionManagementUtility::addLLrefForTCAdescr(
        'tx_nrtextdb_domain_model_translation',
        'EXT:nr_textdb/Resources/Private/Language/locallang_csh_tx_nrtextdb_domain_model_translation.xlf'
    );

    ExtensionManagementUtility::allowTableOnStandardPages('tx_nrtextdb_domain_model_environment');
    ExtensionManagementUtility::allowTableOnStandardPages('tx_nrtextdb_domain_model_component');
    ExtensionManagementUtility::allowTableOnStandardPages('tx_nrtextdb_domain_model_type');
    ExtensionManagementUtility::allowTableOnStandardPages('tx_nrtextdb_domain_model_translation');
});
