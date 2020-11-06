<?php
namespace Netresearch\NrTextdb\Command;

use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Package\Exception\UnknownPackageException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Extensionmanager\Utility\ListUtility;


class ImportCommand extends Command
{
    /**
     * @var \TYPO3\CMS\Extensionmanager\Utility\ListUtility
     */
    protected $listUtility;

    /**
     * @var string path for the language file within an extension.
     */
    protected $languagePath = 'Resources/Private/Language/';

    /**
     * @var string extension with the language file
     */
    protected $extension;

    /**
     * @var \Netresearch\NrTextdb\Domain\Repository\TranslationRepository
     */
    private $translationRepository;

    /**
     * @var ComponentRepository
     */
    private $componentRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

    /**
     * @var EnvironmentRepository
     */
    private $environmentRepository;

    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var array IDS
     */
    private $ids = [];

    /**
     * Configures the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setDescription('Imports textdb records from language files');
        $this->setHelp('If you want to add textdb records to your extension. Create a file languagecode.textdb_import.xlf');
        $this->addArgument('extensionKey', InputArgument::REQUIRED, 'Extension with language file');
        $this->addOption('override', 'o');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|void
     * @throws UnknownPackageException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->initializeCommand();

        $this->extension = $this->listUtility->getExtension($input->getArgument('extensionKey'));
        $languageFiles = glob($this->extension->getPackagePath() . $this->languagePath . '*textdb_import.xlf');
        $xliffParser = $this->getXliffParser();
        $languageFileRecords = [];
        foreach($languageFiles as $file) {
            $languageKey = $this->getLanguageKeyFromFile($file);
            $sysLanguageUid = $this->getLanguageId($languageKey);
            $languageFileRecords[$sysLanguageUid] = $xliffParser->getParsedData($file, $languageKey);
        }
        ksort($languageFileRecords);
        $this->addLanguageRecords($languageFileRecords, (bool) $input->getOption('override'));
    }

    /**
     * Returns the sys_language_uid for a language code
     *
     * @param string $languageCode Language Code
     *
     * @return int
     */
    private function getLanguageId(string $languageCode): int
    {
        if ($languageCode === 'default') {
            $languageCode = 'en';
        }
        foreach ($this->getAllLanguages() as $localLanguage) {
            if ($languageCode === $localLanguage->getTwoLetterIsoCode()) {
                return $localLanguage->getLanguageId();
            }
        }

        return 0;
    }

    /**
     * Add the language records.
     *
     * @param array $languageFileRecords
     * @param bool  $override
     */
    private function addLanguageRecords($languageFileRecords, $override = false)
    {
        foreach ($languageFileRecords as $languageId => $fileRecords) {
            foreach ($fileRecords as $language => $records) {
                foreach ($records as $key => $record) {
                    try {
                        $persistenceManager = $this->objectManager->get(PersistenceManager::class);
                        $this->translationRepository->injectPersistenceManager($persistenceManager);

                        if ($override) {
                            $translation = $this->translationRepository->findEntry(
                                $this->getComponentFromKey($key),
                                'default',
                                $this->getTypeFromKey($key),
                                $this->getPlaceholderFromKey($key),
                                $languageId
                            );

                            $translation->setValue($record[0]['target']);
                            $this->translationRepository->update($translation);
                            $persistenceManager->persistAll();

                        } else {
                            $this->translationRepository->createTranslation(
                                $this->getComponentFromKey($key),
                                'default',
                                $this->getTypeFromKey($key),
                                $this->getPlaceholderFromKey($key),
                                $languageId,
                                $record[0]['target']
                            );
                        }
                    } catch (\Exception $exception) {
                    }
                }
            }
        }
    }

    /**
     * @param $placeholder
     *
     * @return int
     */
    private function getParentId($placeholder): int
    {
        if (isset($this->ids[$placeholder])) {
            return $this->ids[$placeholder];
        }

        return 0;
    }

    /**
     * Get the component from key
     *
     * @param $key
     *
     * @return mixed
     */
    protected function getComponentFromKey($key)
    {
        $parts = explode('|', $key);
        return $parts[0];
    }

    /**
     * Get the type from key
     *
     * @param $key
     *
     * @return mixed
     */
    protected function getTypeFromKey($key)
    {
        $parts = explode('|', $key);
        return $parts[1];
    }

    /**
     * Get the placeholder from key
     *
     * @param $key
     *
     * @return mixed
     */
    protected function getPlaceholderFromKey($key)
    {
        $parts = explode('|', $key);
        return $parts[2];
    }

    /**
     * Get All languages, configured for mfag.
     *
     * @return array|\TYPO3\CMS\Core\Site\Entity\SiteLanguage[]
     */
    protected function getAllLanguages()
    {
        /** @var  SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(
            'TYPO3\CMS\Extbase\Object\ObjectManager'
        )->get(SiteFinder::class);

        $sites = $siteFinder->getAllSites();

        return $sites['mfag']->getAllLanguages();
    }

    /**
     * Extract the language key from the file. Use default, if there is not a language key.
     *
     * @param string $file
     * @return string
     */
    private function getLanguageKeyFromFile($file)
    {
        $fileParts = explode('.', basename($file));

        if (count($fileParts) < 3) {
            return 'default';
        }
        return $fileParts[0];
    }

    /**
     * Get the xliff parser.
     *
     * @return \TYPO3\CMS\Core\Localization\Parser\XliffParser
     */
    private function getXliffParser()
    {
        return $this->objectManager->get('TYPO3\CMS\Core\Localization\Parser\XliffParser');
    }


    /**
     * Get the xliff parser.
     *
     * @return \Netresearch\NrTextdb\Domain\Repository\TranslationRepository
     */
    private function getTranslationRepository()
    {
        return $this->objectManager->get('Netresearch\NrTextdb\Domain\Repository\TranslationRepository');
    }

    /**
     * Initializesz all needed repos
     *
     * @return void
     */
    private function initializeCommand(): void
    {
        Bootstrap::initializeBackendUser();
        $this->objectManager         = GeneralUtility::makeInstance(ObjectManager::class);
        $this->typeRepository        = $this->objectManager->get(TypeRepository::class);
        $this->componentRepository   = $this->objectManager->get(ComponentRepository::class);
        $this->environmentRepository = $this->objectManager->get(EnvironmentRepository::class);
        $this->translationRepository = $this->getTranslationRepository();
        $this->listUtility           = $this->objectManager->get(ListUtility::class);
    }
}
