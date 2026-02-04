<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

use Netresearch\NrTextdb\Queue\Message\ImportTranslationsMessage;
use Netresearch\NrTextdb\Task\ProcessMessengerQueueTask;
use Netresearch\NrTextdb\Task\ProcessMessengerQueueTaskAdditionalFieldProvider;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

call_user_func(static function (): void {
    // Add TypoScript automatically (to use it in backend modules)
    ExtensionManagementUtility::addTypoScript(
        'nr_textdb',
        'constants',
        '@import "EXT:nr_textdb/Configuration/TypoScript/constants.typoscript"'
    );

    ExtensionManagementUtility::addTypoScript(
        'nr_textdb',
        'setup',
        '@import "EXT:nr_textdb/Configuration/TypoScript/setup.typoscript"'
    );

    // Configure Symfony Messenger routing for async import queue
    // Route ImportTranslationsMessage to 'doctrine' transport (database-backed queue)
    // The #[AsMessageHandler] attribute on ImportTranslationsMessageHandler provides auto-registration
    /** @var array<string, array<string, array<string, array<string, string>>>> $GLOBALS */
    $existingRouting                                           = $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'] ?? [];
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['messenger']['routing'] = array_merge(
        is_array($existingRouting) ? $existingRouting : [],
        [
            ImportTranslationsMessage::class => 'doctrine',
        ]
    );

    // Register Scheduler task for processing Messenger queue
    // This allows automatic message processing via TYPO3 Scheduler instead of requiring systemd/supervisor
    if (ExtensionManagementUtility::isLoaded('scheduler')) {
        if (!isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'])) {
            $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'] = [];
        }

        $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][ProcessMessengerQueueTask::class] = [
            'extension'        => 'nr_textdb',
            'title'            => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_scheduler.xlf:task.processMessenger.title',
            'description'      => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_scheduler.xlf:task.processMessenger.description',
            'additionalFields' => ProcessMessengerQueueTaskAdditionalFieldProvider::class,
        ];
    }
});
