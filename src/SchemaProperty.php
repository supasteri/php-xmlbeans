<?php

namespace XmlBeans;

use XmlBeans\Common\QName;

/**
 * Represents an element or attribute property within a SchemaType.
 * Mirrors org.apache.xmlbeans.SchemaProperty.
 */
class SchemaProperty
{
    const XML_ELEMENT = 1;
    const XML_ATTRIBUTE = 2;

    // Use constants
    const NEVER = 0;
    const VARIABLE = 1;
    const ALWAYS = 2;
    const CONSISTENTLY = 3;

    /** @var QName */
    private $name;

    /** @var int */
    private $propertyType;

    /** @var SchemaType|null */
    private $type;

    /** @var string|null */
    private $typeName;

    /** @var int */
    private $minOccurs = 0;

    /** @var int -1 = unbounded */
    private $maxOccurs = 1;

    /** @var bool */
    private $nillable = false;

    /** @var string|null */
    private $defaultValue;

    /** @var string|null */
    private $fixedValue;

    /** @var string|null PHP getter method name */
    private $getterName;

    /** @var string|null PHP setter method name */
    private $setterName;

    /** @var string|null PHP property/field name */
    private $phpName;

    /**
     * @param QName $name
     * @param int   $propertyType (XML_ELEMENT or XML_ATTRIBUTE)
     */
    public function __construct(QName $name, $propertyType = self::XML_ELEMENT)
    {
        $this->name = $name;
        $this->propertyType = $propertyType;
    }

    /** @return QName */
    public function getName()
    {
        return $this->name;
    }

    /** @return int */
    public function getPropertyType()
    {
        return $this->propertyType;
    }

    /** @return bool */
    public function isElement()
    {
        return $this->propertyType === self::XML_ELEMENT;
    }

    /** @return bool */
    public function isAttribute()
    {
        return $this->propertyType === self::XML_ATTRIBUTE;
    }

    /** @return SchemaType|null */
    public function getType()
    {
        return $this->type;
    }

    /** @param SchemaType $type */
    public function setType(SchemaType $type)
    {
        $this->type = $type;
    }

    /** @return string|null */
    public function getTypeName()
    {
        return $this->typeName;
    }

    /** @param string $name */
    public function setTypeName($name)
    {
        $this->typeName = $name;
    }

    /** @return int */
    public function getMinOccurs()
    {
        return $this->minOccurs;
    }

    /** @param int $min */
    public function setMinOccurs($min)
    {
        $this->minOccurs = (int) $min;
    }

    /** @return int -1 means unbounded */
    public function getMaxOccurs()
    {
        return $this->maxOccurs;
    }

    /** @param int $max Use -1 for unbounded */
    public function setMaxOccurs($max)
    {
        $this->maxOccurs = (int) $max;
    }

    /** @return bool */
    public function isRequired()
    {
        return $this->minOccurs > 0;
    }

    /** @return bool */
    public function isArray()
    {
        return $this->maxOccurs === -1 || $this->maxOccurs > 1;
    }

    /** @return bool */
    public function isNillable()
    {
        return $this->nillable;
    }

    /** @param bool $flag */
    public function setNillable($flag)
    {
        $this->nillable = (bool) $flag;
    }

    /** @return string|null */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    /** @param string $value */
    public function setDefaultValue($value)
    {
        $this->defaultValue = $value;
    }

    /** @return string|null */
    public function getFixedValue()
    {
        return $this->fixedValue;
    }

    /** @param string $value */
    public function setFixedValue($value)
    {
        $this->fixedValue = $value;
    }

    /** @return string|null */
    public function getGetterName()
    {
        return $this->getterName;
    }

    /** @param string $name */
    public function setGetterName($name)
    {
        $this->getterName = $name;
    }

    /** @return string|null */
    public function getSetterName()
    {
        return $this->setterName;
    }

    /** @param string $name */
    public function setSetterName($name)
    {
        $this->setterName = $name;
    }

    /** @return string|null */
    public function getPhpName()
    {
        return $this->phpName;
    }

    /** @param string $name */
    public function setPhpName($name)
    {
        $this->phpName = $name;
    }
}
