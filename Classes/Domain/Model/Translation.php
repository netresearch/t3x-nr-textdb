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
use TYPO3\CMS\Extbase\Validation\Validator\NotEmptyValidator;

/**
 * Translation.
 *
 * @author  Thomas Schöne <thomas.schoene@netresearch.de>
 * @author  Rico Sonntag <rico.sonntag@netresearch.de>
 * @license Netresearch https://www.netresearch.de
 * @link    https://www.netresearch.de
 */
class Translation extends AbstractEntity
{
    /**
     * environment.
     *
     * @var Environment|null
     */
    protected ?Environment $environment = null;

    /**
     * component.
     *
     * @var Component|null
     */
    protected ?Component $component = null;

    /**
     * type.
     *
     * @var Type|null
     */
    protected ?Type $type = null;

    /**
     * Placeholder.
     *
     * @var string
     */
    #[Validate(['validator' => NotEmptyValidator::class])]
    protected string $placeholder = '';

    /**
     * value.
     *
     * @var string
     */
    #[Validate(['validator' => NotEmptyValidator::class])]
    protected string $value = '';

    /**
     * @var bool
     */
    protected bool $hidden = false;

    /**
     * @var bool
     */
    protected bool $deleted = false;

    /**
     * @var int
     */
    protected int $l10nParent = 0;

    /**
     * @var int
     */
    protected int $sysLanguageUid = 0;

    /**
     * Returns the environment.
     *
     * @return Environment|null
     */
    public function getEnvironment(): ?Environment
    {
        return $this->environment;
    }

    /**
     * Sets the environment.
     *
     * @param Environment|null $environment
     *
     * @return void
     */
    public function setEnvironment(?Environment $environment): void
    {
        $this->environment = $environment;
    }

    /**
     * Returns the component.
     *
     * @return Component|null
     */
    public function getComponent(): ?Component
    {
        return $this->component;
    }

    /**
     * Sets the component.
     *
     * @param Component|null $component
     *
     * @return void
     */
    public function setComponent(?Component $component): void
    {
        $this->component = $component;
    }

    /**
     * Returns the type.
     *
     * @return Type|null
     */
    public function getType(): ?Type
    {
        return $this->type;
    }

    /**
     * Sets the type.
     *
     * @param Type|null $type
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
     * Returns the value.
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
     * Sets the value.
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
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * Return the language uid.
     *
     * @return int
     */
    public function getLanguageUid(): int
    {
        return $this->_languageUid;
    }

    /**
     * Set the language UID.
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
     * Returns true if the entry was auto-created by the repository.
     *
     * @return bool
     */
    public function isAutoCreated(): bool
    {
        return $this->value === TranslationRepository::AUTO_CREATE_IDENTIFIER;
    }
}
