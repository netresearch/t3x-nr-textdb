<?php
return [
    'ctrl' => [
        'title' => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_translation',
        'label' => 'value',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'sortby' => 'sorting',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'searchFields' => 'value',
        'iconfile' => 'EXT:nr_textdb/Resources/Public/Icons/tx_nrtextdb_domain_model_translation.gif'
    ],
    'interface' => [
        'showRecordFieldList' => 'sys_language_uid, l10n_parent, l10n_diffsource, hidden, environment, component, type, placeholder, value',
    ],
    'types' => [
        '1' => ['showitem' => 'sys_language_uid, l10n_parent, l10n_diffsource, hidden, environment, component, type, placeholder, value'],
    ],
    'columns' => [
        'sys_language_uid' => [
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.language',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'special' => 'languages',
                'items' => [
                    [
                        'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.allLanguages',
                        -1,
                        'flags-multiple'
                    ]
                ],
                'default' => 0,
            ],
        ],
        'l10n_parent' => [
            'displayCond' => 'FIELD:sys_language_uid:>:0',
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.l18n_parent',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'default' => 0,
                'items' => [
                    ['', 0],
                ],
                'foreign_table' => 'tx_nrtextdb_domain_model_translation',
                'foreign_table_where' => 'AND {#tx_nrtextdb_domain_model_translation}.{#pid}=###CURRENT_PID### AND {#tx_nrtextdb_domain_model_translation}.{#sys_language_uid} IN (-1,0)',
            ],
        ],
        'l10n_diffsource' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
        'hidden' => [
            'l10n_mode' => 'exclude',
            'exclude' => true,
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.visible',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [
                    [
                        0 => '',
                        1 => '',
                        'invertStateDisplay' => true
                    ]
                ],
            ],
        ],
        'environment' => [
            'l10n_mode' => 'exclude',
            'exclude' => true,
            'label' => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_translation.environment',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_nrtextdb_domain_model_environment',
                'minitems' => 0,
                'maxitems' => 1,
            ],
        ],
        'component' => [
            'l10n_mode' => 'exclude',
            'exclude' => true,
            'label' => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_translation.component',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_nrtextdb_domain_model_component',
                'minitems' => 0,
                'maxitems' => 1,
            ],
        ],
        'type' => [
            'l10n_mode' => 'exclude',
            'exclude' => true,
            'label' => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_translation.type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_nrtextdb_domain_model_type',
                'minitems' => 0,
                'maxitems' => 1,
            ],
        ],
        'placeholder' => [
            'exclude' => true,
            'l10n_mode' => 'exclude',
            'label' => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_translation.placeholder',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,required'
            ],
        ],
        'value' => [
            'exclude' => true,
            'label' => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_translation.value',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,required'
            ],
        ],

    ],
];
