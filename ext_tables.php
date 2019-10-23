<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function()
    {

        if (TYPO3_MODE === 'BE') {

            \TYPO3\CMS\Extbase\Utility\ExtensionUtility::registerModule(
                'Netresearch.NrTextdb',
                'web', // Make module a submodule of 'web'
                'textdb', // Submodule key
                '', // Position
                [
                    'Translation' => 'list, translated, translateRecord, import',

                ],
                [
                    'access' => 'user,group',
                    'icon'   => 'EXT:nr_textdb/Resources/Public/Icons/user_mod_textdb.svg',
                    'labels' => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_textdb.xlf',
                    'navigationComponentId' => '',
                    'inheritNavigationComponentFromMainModule' => false
                ]
            );

        }

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile('nr_textdb', 'Configuration/TypoScript', 'Netresearch TextDB');

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_nrtextdb_domain_model_environment', 'EXT:nr_textdb/Resources/Private/Language/locallang_csh_tx_nrtextdb_domain_model_environment.xlf');
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_nrtextdb_domain_model_environment');

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_nrtextdb_domain_model_component', 'EXT:nr_textdb/Resources/Private/Language/locallang_csh_tx_nrtextdb_domain_model_component.xlf');
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_nrtextdb_domain_model_component');

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_nrtextdb_domain_model_type', 'EXT:nr_textdb/Resources/Private/Language/locallang_csh_tx_nrtextdb_domain_model_type.xlf');
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_nrtextdb_domain_model_type');

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_nrtextdb_domain_model_translation', 'EXT:nr_textdb/Resources/Private/Language/locallang_csh_tx_nrtextdb_domain_model_translation.xlf');
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_nrtextdb_domain_model_translation');

    }
);
