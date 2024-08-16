<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

return [
    'ctrl' => [
        'title'                    => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_translation',
        'descriptionColumn'        => 'value',
        'label'                    => 'value',
        'prependAtCopy'            => '',
        'hideAtCopy'               => false,
        'tstamp'                   => 'tstamp',
        'crdate'                   => 'crdate',
        'sortby'                   => 'sorting',
        'languageField'            => 'sys_language_uid',
        'transOrigPointerField'    => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'translationSource'        => 'l10n_source',
        'delete'                   => 'deleted',
        'enablecolumns'            => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'value',
        'iconfile'     => 'EXT:nr_textdb/Resources/Public/Icons/tx_nrtextdb_domain_model_translation.gif',
        'security'     => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'palettes' => [
        'paletteCore' => [
            'showitem' => 'environment, --linebreak--, component, --linebreak--, type, --linebreak--, placeholder, --linebreak--, value',
        ],
        'paletteHidden' => [
            'showitem' => 'hidden',
        ],
        'paletteLanguage' => [
            'showitem' => 'sys_language_uid;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:sys_language_uid_formlabel, l10n_parent, l10n_diffsource',
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => '
                    --palette--;;paletteCore,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language,
                    --palette--;;paletteLanguage,
                --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access,
                    --palette--;;paletteHidden,',
        ],
    ],
    'columns' => [
        'sys_language_uid' => [
            'displayCond' => 'REC:NEW:true',
            'exclude'     => true,
            'label'       => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config'      => [
                'type' => 'language',
            ],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'label'       => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config'      => [
                'type'     => 'group',
                'allowed'  => 'tx_nrtextdb_domain_model_translation',
                'size'     => 1,
                'maxitems' => 1,
                'minitems' => 0,
                'default'  => 0,
            ],
        ],
        'l10n_source' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type'    => 'passthrough',
                'default' => '',
            ],
        ],
        'hidden' => [
            'l10n_mode' => 'exclude',
            'exclude'   => true,
            'label'     => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.visible',
            'config'    => [
                'type'       => 'check',
                'renderType' => 'checkboxToggle',
                'items'      => [
                    [
                        'label'              => '',
                        'invertStateDisplay' => true,
                    ],
                ],
            ],
        ],
        'pid' => [
            'label'  => 'pid',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'crdate' => [
            'label'  => 'crdate',
            'config' => [
                'type' => 'datetime',
            ],
        ],
        'tstamp' => [
            'label'  => 'tstamp',
            'config' => [
                'type' => 'datetime',
            ],
        ],
        'sorting' => [
            'label'  => 'sorting',
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'environment' => [
            'l10n_mode' => 'exclude',
            'exclude'   => true,
            'label'     => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_translation.environment',
            'config'    => [
                'type'          => 'select',
                'renderType'    => 'selectSingle',
                'foreign_table' => 'tx_nrtextdb_domain_model_environment',
                'minitems'      => 0,
                'maxitems'      => 1,
            ],
        ],
        'component' => [
            'l10n_mode' => 'exclude',
            'exclude'   => true,
            'label'     => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_translation.component',
            'config'    => [
                'type'          => 'select',
                'renderType'    => 'selectSingle',
                'foreign_table' => 'tx_nrtextdb_domain_model_component',
                'minitems'      => 0,
                'maxitems'      => 1,
            ],
        ],
        'type' => [
            'l10n_mode' => 'exclude',
            'exclude'   => true,
            'label'     => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_translation.type',
            'config'    => [
                'type'          => 'select',
                'renderType'    => 'selectSingle',
                'foreign_table' => 'tx_nrtextdb_domain_model_type',
                'minitems'      => 0,
                'maxitems'      => 1,
            ],
        ],
        'placeholder' => [
            'exclude'   => true,
            'l10n_mode' => 'exclude',
            'label'     => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_translation.placeholder',
            'config'    => [
                'type'     => 'input',
                'size'     => 30,
                'eval'     => 'trim',
                'required' => true,
            ],
        ],
        'value' => [
            'exclude' => true,
            'label'   => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_translation.value',
            'config'  => [
                'type'     => 'input',
                'size'     => 30,
                'eval'     => 'trim',
                'required' => true,
            ],
        ],
    ],
];
