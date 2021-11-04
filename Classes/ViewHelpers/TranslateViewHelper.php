<?php
namespace Netresearch\NrTextdb\ViewHelpers;

use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\Exception;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Fluid <a:translate/> implementation
 * Provides a way import LLL Keys from f:translate to textdb
 *
 * @category   Netresearch
 * @package    TextDB
 * @subpackage ViewHelper
 * @author     Tobias Hein <tobias.hein@netresearch.de>
 * @license    Netresearch
 */
class TranslateViewHelper extends AbstractViewHelper
{
    /**
     * Translation service instance
     *
     * @var translationRepository
     */
    protected $translationRepository;

    /**
     * Component which can be used for migration step
     *
     * @var null
     */
    public static $component = null;

    /**
     * English UID to import.
     *
     * @var int
     */
    CONST LANGUAGE_UID_EN = 1;

    /**
     * Initializes arguments (attributes)
     *
     * @return void
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument(
            'key',
            'string',
            'key file',
            true
        );
        $this->registerArgument(
            'extensionName',
            'string',
            'extensionName',
            false
        );
        $this->registerArgument(
            'environment',
            'string',
            'TextDB environment',
            false,
            'default'
        );
    }

    /**
     * Render translated string
     *
     * @return string The translated key or tag body if key doesn't exist
     *
     * @throws Exception
     */
    public function render(): string
    {
        if (empty(static::$component)) {
            throw new \Exception(
                'Please set a component in your controler via TranslateViewHelper::component="my-component".'
            );
        }

        $placeholder = $this->arguments['key'];
        $extension   = $this->arguments['extensionName'] ?: null;
        $environment = $this->arguments['environment'];

        $translationRequested = LocalizationUtility::translate(
            $placeholder,
            $extension
        );

        $translationOriginal = LocalizationUtility::translate(
            $placeholder,
            $extension,
            [],
            self::LANGUAGE_UID_EN
        );

        $placeholderParts = explode(':', $placeholder);
        if (count($placeholderParts) > 1) {
            $placeholder = $placeholderParts[3];
        }


        if ($this->hasTextDbEntry($placeholder)) {
             return (string) $translationRequested;
        }

        try {
            $this->getTranslationRepository()
                ->createTranslation(
                    static::$component,
                    $environment,
                    'label',
                    $placeholder,
                    $this->getLanguageUid(),
                    (string) $translationRequested
                );

            $this->getTranslationRepository()
                ->createTranslation(
                    static::$component,
                    $environment,
                    'label',
                    $placeholder,
                    self::LANGUAGE_UID_EN,
                    (string) $translationOriginal
                );
        } catch (\Exception $e) {}

        return (string) $translationRequested;
    }

    /**
     * Getter for translationRepository.
     *
     * @return translationRepository
     *
     * @throws Exception
     */
    public function getTranslationRepository(): translationRepository
    {
        if (!isset($this->translationRepository)) {
            $this->translationRepository = GeneralUtility::makeInstance(ObjectManager::class)
                ->get(TranslationRepository::class);
        }

        return $this->translationRepository;
    }


    /**
     * Returns the current language uid.
     *
     * @return mixed
     */
    private function getLanguageUid()
    {
        $languageAspect = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Context\Context::class)->getAspect('language');
        return $languageAspect->getId();
    }

    /**
     * Returns true, if a textdb translation exisits
     *
     * @param $placeholder
     * @return bool
     *
     * @throws Exception
     */
    private function hasTextDbEntry($placeholder) : bool
    {
        $textdbTranslation = $this->getTranslationRepository()->findEntry(
            static::$component,
            $this->arguments['arguments'],
            'label',
            $placeholder,
            $this->getLanguageUid(),
            false
        );

        return !empty($textdbTranslation);
    }
}
