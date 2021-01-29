<?php
namespace Netresearch\NrTextdb\ViewHelpers;

use Netresearch\NrTextdb\Service\TranslationService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\Exception;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Fluid <a:textdb/> implementation
 * Provides a way to use aida_textdb from fluid.
 *
 * @category   Netresearch
 * @package    TextDB
 * @subpackage ViewHelper
 * @author     Thomas Schöne <thomas.schoene@netresearch.de>
 * @license    Netresearch
 */
class TextdbViewHelper extends AbstractViewHelper
{
    /**
     * Translation service instance
     *
     * @var TranslationService
     */
    protected $translationService;

    /**
     * Initializes arguments (attributes)
     *
     * @return void
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();

        $this->registerArgument(
            'placeholder',
            'string',
            'TextDB value',
            true
        );
        $this->registerArgument(
            'type',
            'string',
            'TextDB type',
            true,
            'P'
        );
        $this->registerArgument(
            'component',
            'string',
            'TextDb component',
            true
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
        return $this->getTranslationService()
            ->translate(
                $this->arguments['placeholder'],
                $this->arguments['type'],
                $this->arguments['component'],
                $this->arguments['environment']
            );
    }

    /**
     * Getter for translationService.
     *
     * @return TranslationService
     *
     * @throws Exception
     */
    public function getTranslationService(): TranslationService
    {
        if (!isset($this->translationService)) {
            $this->translationService = GeneralUtility::makeInstance(ObjectManager::class)
                ->get(TranslationService::class);
        }

        return $this->translationService;
    }
}
