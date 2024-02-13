<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') || die('Access denied.');

call_user_func(static function () {
    // Sync
    if (ExtensionManagementUtility::isLoaded('nr_sync')) {
        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['nr_sync/mod1/index.php']['hookClass'][1_624_345_948]
            = \Netresearch\NrTextdb\Hooks\Sync::class;
    }

    ExtensionManagementUtility::allowTableOnStandardPages('tx_nrtextdb_domain_model_environment');
    ExtensionManagementUtility::allowTableOnStandardPages('tx_nrtextdb_domain_model_component');
    ExtensionManagementUtility::allowTableOnStandardPages('tx_nrtextdb_domain_model_type');
    ExtensionManagementUtility::allowTableOnStandardPages('tx_nrtextdb_domain_model_translation');
});
