<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

return [
    'ctrl' => [
        'title'                    => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_translation',
        'label'                    => 'value',
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
        'searchFields' => 'value,placeholder',
        'iconfile'     => 'EXT:nr_textdb/Resources/Public/Icons/tx_nrtextdb_domain_model_translation.svg',
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
            'showitem' => 'sys_language_uid, l10n_parent, l10n_diffsource',
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
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'items'      => [
                    ['label' => '', 'value' => 0],
                ],
                'foreign_table'       => 'tx_nrtextdb_domain_model_translation',
                'foreign_table_where' => 'AND {#tx_nrtextdb_domain_model_translation}.{#pid}=###CURRENT_PID### AND {#tx_nrtextdb_domain_model_translation}.{#sys_language_uid} IN (-1,0)',
                'default'             => 0,
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
                'default'       => 0,
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
                'default'       => 0,
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
                'default'       => 0,
            ],
        ],
        'placeholder' => [
            'exclude'   => true,
            'l10n_mode' => 'exclude',
            'label'     => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_translation.placeholder',
            'config'    => [
                'type'     => 'input',
                'size'     => 30,
                'required' => true,
            ],
        ],
        'value' => [
            'exclude' => true,
            'label'   => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_translation.value',
            'config'  => [
                'type'           => 'text',
                'cols'           => 30,
                'rows'           => 3,
                'required'       => true,
                'enableRichtext' => true,
            ],
        ],
    ],
];
