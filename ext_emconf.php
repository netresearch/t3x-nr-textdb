<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

$EM_CONF['nr_textdb'] = [
    'title'          => 'Netresearch - TextDB',
    'description'    => 'Allows you to edit the translations in the back end',
    'category'       => 'module',
    'author'         => 'Thomas Schöne, Axel Seemann, Tobias Hein, Rico Sonntag',
    'author_email'   => 'thomas.schoene@netresearch.de, axel.seemann@netresearch.de, tobias.hein@netresearch.de, rico.sonntag@netresearch.de',
    'author_company' => 'Netresearch DTT GmbH',
    'state'          => 'stable',
    'version'        => '2.0.9',
    'constraints'    => [
        'depends' => [
            'typo3' => '12.4.0-12.99.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
