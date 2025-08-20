<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

$EM_CONF['nr_textdb'] = [
    'title'          => 'Netresearch TextDB',
    'description'    => 'Allows you to edit the translations in the back end',
    'category'       => 'module',
    'author'         => 'Thomas Schöne, Axel Seemann, Tobias Hein, Rico Sonntag',
    'author_email'   => 'thomas.schoene@netresearch.de, axel.seemann@netresearch.de, tobias.hein@netresearch.de, rico.sonntag@netresearch.de',
    'author_company' => 'Netresearch DTT GmbH',
    'state'          => 'stable',
    'version'        => '2.0.10',
    'constraints'    => [
        'depends' => [
            'typo3' => '11.5.0-11.5.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
