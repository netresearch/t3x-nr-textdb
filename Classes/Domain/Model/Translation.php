<?php
namespace Netresearch\NrTextdb\Domain\Model;

use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use TYPO3\CMS\Extbase\Annotation\Validate;

/***
 *
 * This file is part of the "Netresearch TextDB" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2019 Thomas Schöne <thomas.schoene@netresearch.de>, Netresearch
 *
 ***/
/**
 * Translation
 */
class Translation extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    /**
     * environment
     *
     * @var \Netresearch\NrTextdb\Domain\Model\Environment
     */
    protected $environment = null;

    /**
     * component
     *
     * @var \Netresearch\NrTextdb\Domain\Model\Component
     */
    protected $component = null;

    /**
     * type
     *
     * @var \Netresearch\NrTextdb\Domain\Model\Type
     */
    protected $type = null;

    /**
     * Placeholder
     *
     * @var string
     * @Validate("TYPO3\CMS\Extbase\Validation\Validator\NotEmptyValidator")
     */
    protected $placeholder;

    /**
     * value
     *
     * @var string
     * @Validate("TYPO3\CMS\Extbase\Validation\Validator\NotEmptyValidator")
     */
    protected $value = '';

    /**
     * @var boolean
     */
    protected $hidden;

    /**
     * @var int
     */
    protected $l10nParent;

    /**
     * __construct
     */
    public function __construct()
    {

        //Do not remove the next line: It would break the functionality
        $this->initStorageObjects();
    }

    /**
     * Initializes all ObjectStorage properties
     * Do not modify this method!
     * It will be rewritten on each save in the extension builder
     * You may modify the constructor of this class instead
     *
     * @return void
     */
    protected function initStorageObjects()
    {
    }

    /**
     * Returns the environment
     *
     * @return \Netresearch\NrTextdb\Domain\Model\Environment $environment
     */
    public function getEnvironment()
    {
        return $this->environment;
    }

    /**
     * Sets the environment
     *
     * @param \Netresearch\NrTextdb\Domain\Model\Environment $environment
     * @return void
     */
    public function setEnvironment(\Netresearch\NrTextdb\Domain\Model\Environment $environment)
    {
        $this->environment = $environment;
    }

    /**
     * Returns the component
     *
     * @return \Netresearch\NrTextdb\Domain\Model\Component $component
     */
    public function getComponent()
    {
        return $this->component;
    }

    /**
     * Sets the component
     *
     * @param \Netresearch\NrTextdb\Domain\Model\Component $component
     * @return void
     */
    public function setComponent(\Netresearch\NrTextdb\Domain\Model\Component $component)
    {
        $this->component = $component;
    }

    /**
     * Returns the type
     *
     * @return \Netresearch\NrTextdb\Domain\Model\Type $type
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * Sets the type
     *
     * @param \Netresearch\NrTextdb\Domain\Model\Type $type
     * @return void
     */
    public function setType(\Netresearch\NrTextdb\Domain\Model\Type $type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }

    /**
     * @param string $placeholder
     *
     * @return Translation
     */
    public function setPlaceholder(string $placeholder): Translation
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    /**
     * Returns the value
     *
     * @return string $value
     */
    public function getValue()
    {
        if ($this->isAutoCreated()) {
            return $this->placeholder;
        }

        return $this->value;
    }

    /**
     * Sets the value
     *
     * @param string $value
     * @return void
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * @return boolean $hidden
     */
    public function getHidden()
    {
        return $this->hidden;
    }

    /**
     * Return the language uid
     *
     * @return int
     */
    public function getLanguageUid()
    {
        return $this->_languageUid;
    }

    /**
     * Set the language UID
     *
     * @param int $languageUid
     *
     * @return void
     */
    public function setLanguageUid($languageUid)
    {
        $this->_languageUid = $languageUid;
    }

    /**
     * @param int $localizedUid
     */
    public function setLocalizedUid($localizedUid)
    {
        $this->_localizedUid = $localizedUid;
    }

    /**
     * @return int
     */
    public function getLocalizedUid()
    {
        return $this->_localizedUid;
    }

    /**
     * @return int
     */
    public function getL10nParent(): int
    {
        return $this->l10nParent;
    }

    /**
     * @param int $l10nParent
     */
    public function setL10nParent(int $l10nParent): void
    {
        $this->l10nParent = $l10nParent;
    }

    /**
     * Returns true if the entry was auto-created by the repository
     * @return bool
     */
    public function isAutoCreated(): bool
    {
        return $this->value === TranslationRepository::AUTO_CREATE_IDENTIFIER;
    }
}
