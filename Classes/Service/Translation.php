<?php
namespace Netresearch\NrTextdb\Service;

/**
 * The translation service
 *
 * @category   Netresearch
 * @package    TextDb
 * @subpackage Service
 * @author     Thomas Schöne <thomas.schoene@netresearch.de>
 * @license    Netresearch
 * @link       http://www.netresearch.de/
 */
class Translation
{
    /**
     * @var \Netresearch\NrTextdb\Domain\Repository\TranslationRepository
     * @inject
     */
    private $translationRepository = null;

    /**
     * Translate method
     *
     * @param string       $key         The translation key
     * @param array|null   $arguments   List of parameters for translation string
     * @param boolean|null $htmlEscape  Flag if to escape html
     * @param string|null  $type        Which type of translation is done
     * @param boolean      $bStripPTags Flag if p tags should be stripped
     *
     * @return string
     */
    public function translate(
        $placeholder,
        $type,
        $component,
        $environment
    ) {
        $this->translationRepository
            ->setUseLanguageFilter()
            ->setLanguageUid(
                $this->getCurrentLanguage()
            );

        $translation = $this->translationRepository->findEntry(
            $component,
            $environment,
            $type,
            $placeholder,
            $this->getCurrentLanguage()
        );

        return $translation->getValue();
    }

    /**
     * Get the current language.
     *
     * @return string
     */
    protected function getCurrentLanguage()
    {
        $languageAspect = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Context\Context::class)->getAspect('language');
        return $languageAspect->getId();
    }


}
