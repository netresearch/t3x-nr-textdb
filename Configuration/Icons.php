<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgSpriteIconProvider;

// The Netresearch module group is shared: on 13.4, nr_sync and
// universal_messenger 2.x register the same identifier, and the extension
// loaded last wins. nr_textdb and nr_sync ship ModuleGroup.svg with identical
// bytes. The module menu renders the icon inline, so its currentColor letter
// follows the backend colour scheme.
//
// Record icons are sprite icons, the way core registers its own: their default
// markup is <svg><use>, which inherits currentColor. An SvgIconProvider icon is
// rendered as <img>, where currentColor cannot reach the SVG.
return [
    'extension-netresearch-module' => [
        'provider' => SvgIconProvider::class,
        'source'   => 'EXT:nr_textdb/Resources/Public/Icons/ModuleGroup.svg',
    ],
    'extension-netresearch-textdb' => [
        'provider' => SvgIconProvider::class,
        'source'   => 'EXT:nr_textdb/Resources/Public/Icons/Module.svg',
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
