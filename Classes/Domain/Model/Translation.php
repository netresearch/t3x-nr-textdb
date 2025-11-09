<?php

/**
 * This file is part of the package netresearch/nr-textdb.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Netresearch\NrTextdb\Domain\Model;

use DateTime;
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
    final public const string AUTO_CREATE_IDENTIFIER = 'auto-created-by-repository';

    protected DateTime $crdate;

    protected DateTime $tstamp;

    protected int $sysLanguageUid = 0;

    protected int $l10nParent = 0;

    protected bool $hidden = false;

    protected bool $deleted = false;

    protected int $sorting = 0;

    /**
     * The environment.
     */
    protected ?Environment $environment = null;

    /**
     * The component.
     */
    protected ?Component $component = null;

    /**
     * The type.
     */
    protected ?Type $type = null;

    /**
     * The placeholder.
     */
    #[Validate(['validator' => NotEmptyValidator::class])]
    protected string $placeholder = '';

    /**
     * The value.
     */
    #[Validate(['validator' => NotEmptyValidator::class])]
    protected string $value = '';

    public function getCrdate(): DateTime
    {
        return $this->crdate;
    }

    public function setCrdate(DateTime $crdate): Translation
    {
        $this->crdate = $crdate;

        return $this;
    }

    public function getTstamp(): DateTime
    {
        return $this->tstamp;
    }

    public function setTstamp(DateTime $tstamp): Translation
    {
        $this->tstamp = $tstamp;

        return $this;
    }

    public function getSysLanguageUid(): int
    {
        return $this->_languageUid;
    }

    /**
     * @param int<-1, max> $sysLanguageUid
     */
    public function setSysLanguageUid(int $sysLanguageUid): Translation
    {
        $this->_languageUid = $sysLanguageUid;

        return $this;
    }

    public function getL10nParent(): int
    {
        return $this->l10nParent;
    }

    public function setL10nParent(int $l10nParent): Translation
    {
        $this->l10nParent = $l10nParent;

        return $this;
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): Translation
    {
        $this->hidden = $hidden;

        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    public function setDeleted(bool $deleted): Translation
    {
        $this->deleted = $deleted;

        return $this;
    }

    public function getSorting(): int
    {
        return $this->sorting;
    }

    public function setSorting(int $sorting): Translation
    {
        $this->sorting = $sorting;

        return $this;
    }

    public function getEnvironment(): ?Environment
    {
        return $this->environment;
    }

    public function setEnvironment(?Environment $environment): Translation
    {
        $this->environment = $environment;

        return $this;
    }

    public function getComponent(): ?Component
    {
        return $this->component;
    }

    public function setComponent(?Component $component): Translation
    {
        $this->component = $component;

        return $this;
    }

    public function getType(): ?Type
    {
        return $this->type;
    }

    public function setType(?Type $type): Translation
    {
        $this->type = $type;

        return $this;
    }

    public function getPlaceholder(): string
    {
        return $this->placeholder;
    }

    public function setPlaceholder(string $placeholder): Translation
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    /**
     * Returns the value.
     */
    public function getValue(): string
    {
        if ($this->isAutoCreated()) {
            return $this->getPlaceholder();
        }

        return $this->value;
    }

    public function setValue(string $value): Translation
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Returns true if the entry was auto-created by the repository.
     */
    public function isAutoCreated(): bool
    {
        return $this->value === self::AUTO_CREATE_IDENTIFIER;
    }

    /**
     * @return int<0, max>
     */
    public function getLocalizedUid(): int
    {
        return $this->_localizedUid;
    }
}
