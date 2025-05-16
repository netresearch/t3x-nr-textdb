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
        'title'         => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_environment',
        'label'         => 'name',
        'tstamp'        => 'tstamp',
        'crdate'        => 'crdate',
        'delete'        => 'deleted',
        'enablecolumns' => [
            'disabled'  => 'hidden',
            'starttime' => 'starttime',
            'endtime'   => 'endtime',
        ],
        'searchFields' => 'name',
        'iconfile'     => 'EXT:nr_textdb/Resources/Public/Icons/tx_nrtextdb_domain_model_environment.svg',
        'security'     => [
            'ignorePageTypeRestriction' => true,
        ],
    ],
    'types' => [
        '1' => [
            'showitem' => 'name, --div--;LLL:EXT:frontend/Resources/Private/Language/locallang_ttc.xlf:tabs.access, hidden, starttime, endtime',
        ],
    ],
    'columns' => [
        'name' => [
            'exclude' => true,
            'label'   => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_db.xlf:tx_nrtextdb_domain_model_environment.name',
            'config'  => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'translation' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
    ],
];
