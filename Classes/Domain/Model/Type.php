<?php
namespace Netresearch\NrTextdb\Domain\Model;

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
 * Type
 */
class Type extends \TYPO3\CMS\Extbase\DomainObject\AbstractValueObject
{

    /**
     * name
     *
     * @var string
     *
     * @Validate("TYPO3\CMS\Extbase\Validation\Validator\NotEmptyValidator")
     */
    protected $name = '';

    /**
     * Returns the name
     *
     * @return string name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the name
     *
     * @param string $name
     * @return void
     */
    public function setName($name)
    {
        $this->name = $name;
    }
}
