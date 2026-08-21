<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Privatization\Rector\Property\PrivatizeFinalClassPropertyRector;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;

$configure = require_once __DIR__ . '/../.Build/vendor/netresearch/typo3-ci-workflows/config/rector/rector.php';

return static function (RectorConfig $rectorConfig) use ($configure): void {
    // Shared org base config: paths, code-quality sets, rule skips,
    // and the package's ergebnis-free phpstan-rector.neon.
    $configure($rectorConfig, __DIR__ . '/..');

    $rectorConfig->disableParallel();

    $rectorConfig->sets([
        Typo3LevelSetList::UP_TO_TYPO3_14,
    ]);

    $rectorConfig->skip([
        __DIR__ . '/../ext_*.sql',

        // Extbase domain models are final, but their mapped properties MUST stay
        // `protected`: the DataMapper assigns them via
        // AbstractDomainObject::_setProperty() from the parent class scope, which
        // fails with "Cannot access private property" on private declarations.
        PrivatizeFinalClassPropertyRector::class => [
            __DIR__ . '/../Classes/Domain/Model',
        ],
    ]);
};
