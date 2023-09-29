<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Domain\Model;

use Netresearch\NrTextdb\Domain\Repository\TranslationRepository;
use TYPO3\CMS\Extbase\Annotation\Validate;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Translation
 *
 * @author  Thomas Schöne <thomas.schoene@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class Translation extends AbstractEntity
{
    /**
     * environment
     *
     * @var null|Environment
     */
    protected ?Environment $environment = null;

    /**
     * component
     *
     * @var null|Component
     */
    protected ?Component $component = null;

    /**
     * type
     *
     * @var null|Type
     */
    protected ?Type $type = null;

    /**
     * Placeholder
     *
     * @var string
     *
     * @Validate("TYPO3\CMS\Extbase\Validation\Validator\NotEmptyValidator")
     */
    protected string $placeholder = '';

    /**
     * value
     *
     * @var string
     * @Validate("TYPO3\CMS\Extbase\Validation\Validator\NotEmptyValidator")
     */
    protected string $value = '';

    /**
     * @var bool
     */
    protected bool $hidden = false;

    /**
     * @var int
     */
    protected int $l10nParent = 0;

    /**
     * @var int
     */
    protected int $sysLanguageUid = 0;

    /**
     * Returns the environment
     *
     * @return null|Environment
     */
    public function getEnvironment(): ?Environment
    {
        return $this->environment;
    }

    /**
     * Sets the environment
     *
     * @param null|Environment $environment
     *
     * @return void
     */
    public function setEnvironment(?Environment $environment): void
    {
        $this->environment = $environment;
    }

    /**
     * Returns the component
     *
     * @return null|Component
     */
    public function getComponent(): ?Component
    {
        return $this->component;
    }

    /**
     * Sets the component
     *
     * @param null|Component $component
     *
     * @return void
     */
    public function setComponent(?Component $component): void
    {
        $this->component = $component;
    }

    /**
     * Returns the type
     *
     * @return null|Type
     */
    public function getType(): ?Type
    {
        return $this->type;
    }

    /**
     * Sets the type
     *
     * @param null|Type $type
     *
     * @return void
     */
    public function setType(?Type $type): void
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
     * @return string
     */
    public function getValue(): string
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
     *
     * @return void
     */
    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    /**
     * @return bool
     */
    public function getHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * Return the language uid
     *
     * @return int
     */
    public function getLanguageUid(): int
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
    public function setLanguageUid(int $languageUid): void
    {
        $this->_languageUid = $languageUid;
    }

    /**
     * @param int $localizedUid
     */
    public function setLocalizedUid(int $localizedUid): void
    {
        $this->_localizedUid = $localizedUid;
    }

    /**
     * @return int
     */
    public function getLocalizedUid(): int
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
     * @return int
     */
    public function getSysLanguageUid(): int
    {
        return $this->sysLanguageUid;
    }

    /**
     * @param int $sysLanguageUid
     *
     * @return Translation
     */
    public function setSysLanguageUid(int $sysLanguageUid): Translation
    {
        $this->sysLanguageUid = $sysLanguageUid;
        return $this;
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
