<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

call_user_func(static function () {
    // Add TypoScript automatically (to use it in backend modules)
    ExtensionManagementUtility::addTypoScript(
        'nr_textdb',
        'constants',
        '@import "EXT:nr_textdb/Configuration/TypoScript/constants.typoscript"'
    );

    ExtensionManagementUtility::addTypoScript(
        'nr_textdb',
        'setup',
        '@import "EXT:nr_textdb/Configuration/TypoScript/setup.typoscript"'
    );
});
