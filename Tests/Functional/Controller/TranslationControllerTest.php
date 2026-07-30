<?php

/*
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Tests\Functional\Controller;

use Netresearch\NrTextdb\Controller\TranslationController;
use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use Netresearch\NrTextdb\Service\ImportService;
use Netresearch\NrTextdb\Service\TranslationService;
use Netresearch\NrTextdb\Tests\Functional\AbstractFunctionalTestCase;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use TYPO3\CMS\Backend\Template\Components\ComponentFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Regression tests for the backend module's save path.
 *
 * Reproduces the three defects reported in issue #100 "Backend module: Save
 * creates orphaned duplicate row (environment/component/type = 0), further
 * saves fail with 503":
 *
 *   1. The "untranslated" column submits one `new[<language>]` value per
 *      language, and the action inserted a record for each of them without
 *      checking whether that language already had one — the second save then
 *      collided with the unique key and surfaced as a raw 503.
 *   2. Empty textareas were submitted as empty values and stored as records.
 *   3. persistAll() ran unguarded, so any persistence error reached the editor
 *      as an exception page instead of a flash message.
 *
 * Fixtures (Fixtures/TranslationRepository/*.csv), all on pid 1:
 *   uid 1  language 0  environment 1 / component 1 / type 1 / "submit"    = "Submit"
 *   uid 5  language 1  l10n_parent 1                          "submit"    = "Absenden"
 *   uid 2  language 0  environment 1 / component 1 / type 2 / "email"     = "Email Address"
 *
 * @see https://github.com/netresearch/t3x-nr-textdb/issues/100
 */
#[CoversClass(TranslationController::class)]
final class TranslationControllerTest extends AbstractFunctionalTestCase
{
    private TranslationController $controller;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->setExtensionConfiguration(textDbPid: '1', createIfMissing: '0');

        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.com/typo3/module/netresearch/textdb', 'POST'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $this->importFixture('Pages.csv');
        $this->importFixture('BackendUser.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/TranslationRepository/Environments.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/TranslationRepository/Components.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/TranslationRepository/Types.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/TranslationRepository/Translations.csv');

        // Flash messages of the backend module are session bound, so a backend
        // user has to be present for the queue to accept them.
        $this->setUpBackendUser(1);

        $this->controller = $this->get(TranslationController::class);
    }

    #[Test]
    public function translateRecordUpdatesTheDefaultLanguageRecordSubmittedAsNew(): void
    {
        // The dialog renders a new[0] textarea whenever the default-language row
        // is not part of its "translated" list. Before the fix this inserted a
        // second language-0 row for the same placeholder.
        $this->controller->translateRecordAction(1, [0 => 'Send it']);

        self::assertSame(
            1,
            $this->countRows(placeholder: 'submit', languageUid: 0),
            'Submitting new[0] for an existing default record must not insert a second row.',
        );
        self::assertSame('Send it', $this->fetchValue(1));
    }

    #[Test]
    public function translateRecordUpdatesAnExistingLocalizedRecordSubmittedAsNew(): void
    {
        $this->controller->translateRecordAction(1, [1 => 'Abschicken']);

        self::assertSame(
            1,
            $this->countRows(placeholder: 'submit', languageUid: 1),
            'Submitting new[1] for an existing German record must not insert a second row.',
        );
        self::assertSame('Abschicken', $this->fetchValue(5));
    }

    #[Test]
    public function translateRecordSavedTwiceDoesNotFail(): void
    {
        // The reported reproducer: the first save silently created a duplicate,
        // the second one aborted with "Duplicate entry … for key 'translation'".
        $this->controller->translateRecordAction(1, [0 => 'First']);
        $this->controller->translateRecordAction(1, [0 => 'Second']);

        self::assertSame(1, $this->countRows(placeholder: 'submit', languageUid: 0));
        self::assertSame('Second', $this->fetchValue(1));
        self::assertSame([], $this->flashMessages(), 'A successful save must not queue an error message.');
    }

    #[Test]
    public function translateRecordSkipsEmptySubmittedValues(): void
    {
        // Language 2 has no record at all; an untouched textarea must not create one.
        $this->controller->translateRecordAction(1, [2 => '   ']);

        self::assertSame(0, $this->countRows(placeholder: 'submit', languageUid: 2));
    }

    #[Test]
    public function translateRecordCreatesRecordForALanguageThatHasNone(): void
    {
        $this->controller->translateRecordAction(1, [2 => 'Envoyer']);

        self::assertSame(1, $this->countRows(placeholder: 'submit', languageUid: 2));
    }

    #[Test]
    public function translateRecordUpdatesLocalizedRecordAddressedByUid(): void
    {
        // update[<uid>] carries the localized uid. Repository::findByUid() is
        // language aware and returned null for the German row in a backend
        // context, so the edit was silently dropped.
        $this->controller->translateRecordAction(1, [], [5 => 'Absenden!']);

        self::assertSame('Absenden!', $this->fetchValue(5));
    }

    #[Test]
    public function translateRecordSurfacesAPersistenceFailureAsFlashMessage(): void
    {
        $persistenceManager = $this->createMock(PersistenceManagerInterface::class);
        $persistenceManager
            ->method('persistAll')
            ->willThrowException(new RuntimeException('Duplicate entry'));

        $controller = new TranslationController(
            $this->get(ModuleTemplateFactory::class),
            $this->get(ExtensionConfiguration::class),
            $this->get(IconFactory::class),
            $this->get(EnvironmentRepository::class),
            $this->get(TranslationRepository::class),
            $this->get(TranslationService::class),
            $persistenceManager,
            $this->get(ComponentRepository::class),
            $this->get(TypeRepository::class),
            $this->get(ImportService::class),
            $this->get(FlashMessageService::class),
            $this->get(ComponentFactory::class),
        );

        $controller->translateRecordAction(1, [0 => 'Send it']);

        $messages = $this->flashMessages();

        self::assertCount(1, $messages);
        self::assertStringContainsString('Duplicate entry', $messages[0]);
    }

    /**
     * Counts non-deleted rows for a placeholder in one language.
     */
    private function countRows(string $placeholder, int $languageUid): int
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation');

        // Scoped exactly like the unique key: the fixtures deliberately contain
        // the same placeholder on another pid (uid 8) and in another
        // environment (uid 11), which must not be touched.
        return (int) $connection->count(
            'uid',
            'tx_nrtextdb_domain_model_translation',
            [
                'placeholder'      => $placeholder,
                'sys_language_uid' => $languageUid,
                'pid'              => 1,
                'environment'      => 1,
                'component'        => 1,
                'type'             => 1,
                'deleted'          => 0,
            ],
        );
    }

    private function fetchValue(int $uid): string
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('tx_nrtextdb_domain_model_translation');

        $value = $connection->select(
            ['value'],
            'tx_nrtextdb_domain_model_translation',
            ['uid' => $uid],
        )->fetchAssociative();

        self::assertIsArray($value, 'Translation record ' . $uid . ' is gone.');

        return (string) $value['value'];
    }

    /**
     * Returns the texts of all queued error flash messages.
     *
     * @return string[]
     */
    private function flashMessages(): array
    {
        $messages = $this->get(FlashMessageService::class)
            ->getMessageQueueByIdentifier()
            ->getAllMessagesAndFlush();

        $texts = [];

        foreach ($messages as $message) {
            if ($message->getSeverity() === ContextualFeedbackSeverity::ERROR) {
                $texts[] = $message->getMessage();
            }
        }

        return $texts;
    }
}
