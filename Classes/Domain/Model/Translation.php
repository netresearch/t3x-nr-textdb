<?php
namespace Netresearch\NrTextdb\Domain\Model;


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
     * value
     * 
     * @var \Netresearch\NrTextdb\Domain\Model\Value
     */
    protected $value = null;

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
     * Returns the value
     * 
     * @return \Netresearch\NrTextdb\Domain\Model\Value $value
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Sets the value
     * 
     * @param \Netresearch\NrTextdb\Domain\Model\Value $value
     * @return void
     */
    public function setValue(\Netresearch\NrTextdb\Domain\Model\Value $value)
    {
        $this->value = $value;
    }
}
