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
    final public const AUTO_CREATE_IDENTIFIER = 'auto-created-by-repository';

    /**
     * @var DateTime
     */
    protected DateTime $crdate;

    /**
     * @var DateTime
     */
    protected DateTime $tstamp;

    /**
     * @var int
     */
    protected int $sysLanguageUid = 0;

    /**
     * @var int
     */
    protected int $l10nParent = 0;

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
    protected int $sorting = 0;

    /**
     * The environment.
     *
     * @var Environment|null
     */
    protected ?Environment $environment = null;

    /**
     * The component.
     *
     * @var Component|null
     */
    protected ?Component $component = null;

    /**
     * The type.
     *
     * @var Type|null
     */
    protected ?Type $type = null;

    /**
     * The placeholder.
     *
     * @var string
     */
    #[Validate(['validator' => NotEmptyValidator::class])]
    protected string $placeholder = '';

    /**
     * The value.
     *
     * @var string
     */
    #[Validate(['validator' => NotEmptyValidator::class])]
    protected string $value = '';

    /**
     * @return DateTime
     */
    public function getCrdate(): DateTime
    {
        return $this->crdate;
    }

    /**
     * @param DateTime $crdate
     *
     * @return Translation
     */
    public function setCrdate(DateTime $crdate): Translation
    {
        $this->crdate = $crdate;
        return $this;
    }

    /**
     * @return DateTime
     */
    public function getTstamp(): DateTime
    {
        return $this->tstamp;
    }

    /**
     * @param DateTime $tstamp
     *
     * @return Translation
     */
    public function setTstamp(DateTime $tstamp): Translation
    {
        $this->tstamp = $tstamp;
        return $this;
    }

    /**
     * @return int
     */
    public function getSysLanguageUid(): int
    {
        return $this->_languageUid;
    }

    /**
     * @param int $sysLanguageUid
     *
     * @return Translation
     */
    public function setSysLanguageUid(int $sysLanguageUid): Translation
    {
        $this->_languageUid = $sysLanguageUid;
        return $this;
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
     *
     * @return Translation
     */
    public function setL10nParent(int $l10nParent): Translation
    {
        $this->l10nParent = $l10nParent;
        return $this;
    }

    /**
     * @return bool
     */
    public function isHidden(): bool
    {
        return $this->hidden;
    }

    /**
     * @param bool $hidden
     *
     * @return Translation
     */
    public function setHidden(bool $hidden): Translation
    {
        $this->hidden = $hidden;
        return $this;
    }

    /**
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->deleted;
    }

    /**
     * @param bool $deleted
     *
     * @return Translation
     */
    public function setDeleted(bool $deleted): Translation
    {
        $this->deleted = $deleted;
        return $this;
    }

    /**
     * @return int
     */
    public function getSorting(): int
    {
        return $this->sorting;
    }

    /**
     * @param int $sorting
     *
     * @return Translation
     */
    public function setSorting(int $sorting): Translation
    {
        $this->sorting = $sorting;
        return $this;
    }

    /**
     * @return null|Environment
     */
    public function getEnvironment(): ?Environment
    {
        return $this->environment;
    }

    /**
     * @param null|Environment $environment
     *
     * @return Translation
     */
    public function setEnvironment(?Environment $environment): Translation
    {
        $this->environment = $environment;
        return $this;
    }

    /**
     * @return null|Component
     */
    public function getComponent(): ?Component
    {
        return $this->component;
    }

    /**
     * @param null|Component $component
     *
     * @return Translation
     */
    public function setComponent(?Component $component): Translation
    {
        $this->component = $component;
        return $this;
    }

    /**
     * @return null|Type
     */
    public function getType(): ?Type
    {
        return $this->type;
    }

    /**
     * @param null|Type $type
     *
     * @return Translation
     */
    public function setType(?Type $type): Translation
    {
        $this->type = $type;
        return $this;
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
            return $this->getPlaceholder();
        }

        return $this->value;
    }

    /**
     * @param string $value
     *
     * @return Translation
     */
    public function setValue(string $value): Translation
    {
        $this->value = $value;
        return $this;
    }

    /**
     * Returns true if the entry was auto-created by the repository.
     *
     * @return bool
     */
    public function isAutoCreated(): bool
    {
        return $this->value === self::AUTO_CREATE_IDENTIFIER;
    }

    /**
     * @return int
     */
    public function getLocalizedUid(): int
    {
        return $this->_localizedUid;
     }
}
