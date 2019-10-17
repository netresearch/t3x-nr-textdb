<?php
namespace Netresearch\NrTextdb\ViewHelpers;

use TYPO3\CMS\Core\Utility\GeneralUtility;

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
class TextdbViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractTagBasedViewHelper
{

    /**
     * Translation service instance
     *
     * @var \Netresearch\NrTextdb\Service\TranslationService
     */
    protected $translationService;


    /**
     * Initializes arguments (attributes)
     *
     * @return void
     */
    public function initializeArguments()
    {
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
            true
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
     */
    public function render()
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
     * Getter for translationService
     *
     * @return \Netresearch\NrTextdb\Service\TranslationService
     */
    public function getTranslationService()
    {
        if (!isset($this->translationService)) {
            $this->translationService = GeneralUtility::makeInstance(
                'TYPO3\CMS\Extbase\Object\ObjectManager'
            )->get('Netresearch\NrTextdb\Service\TranslationService');
        }

        return $this->translationService;
    }
}
