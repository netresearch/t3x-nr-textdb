<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Task;

use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\AdditionalFieldProviderInterface;
use TYPO3\CMS\Scheduler\Controller\SchedulerModuleController;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Provides additional fields for ProcessMessengerQueueTask in Scheduler backend.
 *
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class ProcessMessengerQueueTaskAdditionalFieldProvider implements AdditionalFieldProviderInterface
{
    /**
     * Render additional fields in the Scheduler backend.
     *
     * @param array<string, mixed>                        $taskInfo        Task information
     * @param AbstractTask|ProcessMessengerQueueTask|null $task            Task object when editing
     * @param SchedulerModuleController                   $schedulerModule Scheduler module
     *
     * @phpstan-param array $taskInfo
     *
     * @return array<string, array<string, string>> Additional fields HTML
     */
    public function getAdditionalFields(array &$taskInfo, $task, SchedulerModuleController $schedulerModule): array
    {
        $additionalFields = [];

        // Time limit field
        $fieldName  = 'tx_scheduler[timeLimit]';
        $fieldId    = 'task_timeLimit';
        $fieldValue = ($task instanceof ProcessMessengerQueueTask) ? $task->timeLimit : 120;

        $fieldHtml = '<input type="number" '
            . 'class="form-control" '
            . 'name="' . $fieldName . '" '
            . 'id="' . $fieldId . '" '
            . 'value="' . htmlspecialchars((string) $fieldValue) . '" '
            . 'min="30" '
            . 'max="600" '
            . 'step="30" />';

        $additionalFields[$fieldId] = [
            'code'     => $fieldHtml,
            'label'    => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_scheduler.xlf:task.processMessenger.timeLimit',
            'cshKey'   => '_MOD_system_txschedulerM1',
            'cshLabel' => $fieldId,
        ];

        // Transport field
        $fieldName  = 'tx_scheduler[transport]';
        $fieldId    = 'task_transport';
        $fieldValue = ($task instanceof ProcessMessengerQueueTask) ? $task->transport : 'doctrine';

        $fieldHtml = '<input type="text" '
            . 'class="form-control" '
            . 'name="' . $fieldName . '" '
            . 'id="' . $fieldId . '" '
            . 'value="' . htmlspecialchars($fieldValue) . '" />';

        $additionalFields[$fieldId] = [
            'code'     => $fieldHtml,
            'label'    => 'LLL:EXT:nr_textdb/Resources/Private/Language/locallang_scheduler.xlf:task.processMessenger.transport',
            'cshKey'   => '_MOD_system_txschedulerM1',
            'cshLabel' => $fieldId,
        ];

        return $additionalFields;
    }

    /**
     * Validate additional fields.
     *
     * @param array                     $submittedData   Submitted field values
     * @param SchedulerModuleController $schedulerModule Scheduler module
     *
     * @return bool TRUE if valid
     */
    public function validateAdditionalFields(array &$submittedData, SchedulerModuleController $schedulerModule): bool
    {
        $isValid = true;

        // Validate time limit
        $timeLimit = (int) (is_numeric($submittedData['timeLimit'] ?? 0) ? $submittedData['timeLimit'] : 0);
        if ($timeLimit < 30 || $timeLimit > 600) {
            $this->addMessage(
                'Time limit must be between 30 and 600 seconds',
                ContextualFeedbackSeverity::ERROR
            );
            $isValid = false;
        }

        // Validate transport name
        $transport = trim(is_string($submittedData['transport'] ?? '') ? $submittedData['transport'] : '');
        if ($transport === '') {
            $this->addMessage(
                'Transport name is required',
                ContextualFeedbackSeverity::ERROR
            );
            $isValid = false;
        }

        return $isValid;
    }

    /**
     * Save additional fields to task object.
     *
     * @param array                                  $submittedData Submitted field values
     * @param AbstractTask|ProcessMessengerQueueTask $task          Task object
     */
    public function saveAdditionalFields(array $submittedData, AbstractTask $task): void
    {
        if ($task instanceof ProcessMessengerQueueTask) {
            $task->timeLimit = (int) (is_numeric($submittedData['timeLimit'] ?? 120) ? $submittedData['timeLimit'] : 120);
            $task->transport = trim(is_string($submittedData['transport'] ?? 'doctrine') ? $submittedData['transport'] : 'doctrine');
        }
    }

    /**
     * Add flash message.
     */
    private function addMessage(string $message, ContextualFeedbackSeverity $severity): void
    {
        $flashMessage = GeneralUtility::makeInstance(
            FlashMessage::class,
            $message,
            '',
            $severity
        );

        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue        = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->addMessage($flashMessage);
    }
}
