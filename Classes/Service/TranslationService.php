<?php
namespace Netresearch\NrTextdb\Service;

use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\DebuggerUtility;


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
class TranslationService
{
    /**
     * @var TranslationRepository
     */
    private $translationRepository = null;

    /**
     * @var SiteFinder
     */
    private $siteFinder;

    /**
     * Translation constructor.
     *
     * @param TranslationRepository $translationRepository
     * @param SiteFinder            $siteFinder
     */
    public function __construct(TranslationRepository $translationRepository, SiteFinder $siteFinder)
    {
        $this->translationRepository = $translationRepository;
        $this->siteFinder            = $siteFinder;
    }

    /**
     * Translate method
     *
     * @param string $placeholder The translation key
     * @param string $type        List of parameters for translation string
     * @param string $component   Flag if to escape html
     * @param string $environment Which type of translation is done
     *
     * @return string
     */
    public function translate(
        $placeholder,
        $type,
        $component,
        $environment
    ) {
        if (empty($placeholder)) {
            return  $placeholder;
        }
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
     * Get All languages, configured for mfag.
     *
     * @return SiteLanguage[]
     */
    public function getAllLanguages()
    {
        $sites = $this->siteFinder->getAllSites();

        return reset($sites)->getAllLanguages();
    }

    /**
     * Get the current language.
     *
     * @return string
     */
    protected function getCurrentLanguage()
    {
        $languageAspect = GeneralUtility::makeInstance(Context::class)->getAspect('language');
        return $languageAspect->getId();
    }


}
