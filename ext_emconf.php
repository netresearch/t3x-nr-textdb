<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

$EM_CONF[$_EXTKEY] = [
    'title'          => 'Netresearch - TextDB',
    'description'    => 'Auto-creating TYPO3 translation database - use ViewHelpers, editors translate in backend, instant updates - by Netresearch',
    'category'       => 'module',
    'author'         => 'Thomas Schöne, Axel Seemann, Tobias Hein, Rico Sonntag',
    'author_email'   => 'thomas.schoene@netresearch.de, axel.seemann@netresearch.de, tobias.hein@netresearch.de, rico.sonntag@netresearch.de',
    'author_company' => 'Netresearch DTT GmbH',
    'state'          => 'stable',
    'version'        => '4.0.2',
    'constraints'    => [
        'depends' => [
            'typo3' => '14.3.0-14.99.99',
        ],
        'conflicts' => [
        ],
        'suggests' => [
        ],
    ],
];
