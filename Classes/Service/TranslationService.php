<?php
namespace Netresearch\NrTextdb\Service;

use Netresearch\NrTextdb\Domain\Repository\ComponentRepository;
use Netresearch\NrTextdb\Domain\Repository\EnvironmentRepository;
use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use Netresearch\NrTextdb\Domain\Repository\TypeRepository;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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
     * @var EnvironmentRepository
     */
    private $environmentRepository;

    /**
     * @var ComponentRepository
     */
    private $componentRepository;

    /**
     * @var TypeRepository
     */
    private $typeRepository;

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
    public function __construct(
        EnvironmentRepository $environmentRepository,
        ComponentRepository $componentRepository,
        TypeRepository $typeRepository,
        TranslationRepository $translationRepository,
        SiteFinder $siteFinder
    ) {
        $this->environmentRepository = $environmentRepository;
        $this->componentRepository   = $componentRepository;
        $this->typeRepository        = $typeRepository;
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

        $environment = $this->environmentRepository->findByName($environment);
        $component   = $this->componentRepository->findByName($component);
        $type        = $this->typeRepository->findByName($type);

        if (!$environment || !$component || !$type) {
            return "$placeholder";
        }

        $translation = $this->translationRepository->find(
            $environment,
            $component,
            $type,
            $placeholder,
            $this->getCurrentLanguage()
        );

        if (empty($translation)) {
            return $placeholder;
        }
        return $translation->getValue() ?? $translation->getPlaceholder();
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
