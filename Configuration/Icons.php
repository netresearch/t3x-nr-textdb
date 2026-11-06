<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 * SPDX-FileCopyrightText: Netresearch DTT GmbH
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgSpriteIconProvider;

// All icons are sprite icons, the way core registers its own. A sprite icon
// renders as <svg><use> in both the default markup (About module, submodule
// overview, record lists) and the inline markup (module menu), and <use>
// inherits currentColor, so the glyphs follow the light and dark backend
// scheme everywhere. An SvgIconProvider icon would render its default markup
// as <img>, where currentColor cannot reach the SVG. Do not add a "source"
// option: the sprite provider would then inline that file for the inline
// markup instead of the sprite.
//
// The accent is var(--nr-icon-accent) with the brand teal as fallback; no
// stylesheet sets that property yet.
return [
    'extension-netresearch-textdb' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite'   => 'EXT:nr_textdb/Resources/Public/Icons/Module.svg#extension-netresearch-textdb',
    ],
    'nr-textdb-record-component' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite'   => 'EXT:nr_textdb/Resources/Public/Icons/tx_nrtextdb_domain_model_component.svg#tx_nrtextdb_domain_model_component',
    ],
    'nr-textdb-record-environment' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite'   => 'EXT:nr_textdb/Resources/Public/Icons/tx_nrtextdb_domain_model_environment.svg#tx_nrtextdb_domain_model_environment',
    ],
    'nr-textdb-record-translation' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite'   => 'EXT:nr_textdb/Resources/Public/Icons/tx_nrtextdb_domain_model_translation.svg#tx_nrtextdb_domain_model_translation',
    ],
    'nr-textdb-record-type' => [
        'provider' => SvgSpriteIconProvider::class,
        'sprite'   => 'EXT:nr_textdb/Resources/Public/Icons/tx_nrtextdb_domain_model_type.svg#tx_nrtextdb_domain_model_type',
    ],
];
