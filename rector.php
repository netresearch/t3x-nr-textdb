<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php80\Rector\FunctionLike\MixedTypeRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Set\ValueObject\LevelSetList;
use Ssch\TYPO3Rector\Rector\v9\v0\QueryLogicalOrAndLogicalAndToArrayParameterRector;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/Classes',
        __DIR__ . '/Configuration',
        __DIR__ . '/Resources',
        'ext_*',
    ]);

    $rectorConfig->skip([
        'ext_emconf.php',
        'ext_*.sql',
    ]);

    $rectorConfig->phpstanConfig('phpstan.neon');
    $rectorConfig->importNames();
    $rectorConfig->removeUnusedImports();
    $rectorConfig->disableParallel();

    // Define what rule sets will be applied
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_82,
        Typo3LevelSetList::UP_TO_TYPO3_12,

        Typo3SetList::UNDERSCORE_TO_NAMESPACE,
        Typo3SetList::DATABASE_TO_DBAL,
        Typo3SetList::EXTBASE_COMMAND_CONTROLLERS_TO_SYMFONY_COMMANDS,
        Typo3SetList::REGISTER_ICONS_TO_ICON,
    ]);

    // Skip some rules
    $rectorConfig->skip([
        ClassPropertyAssignToConstructorPromotionRector::class,
        MixedTypeRector::class,
        QueryLogicalOrAndLogicalAndToArrayParameterRector::class,
        ReadOnlyPropertyRector::class,
    ]);
};
