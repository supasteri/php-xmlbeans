<?php

namespace XmlBeans;

use XmlBeans\Common\QName;

/**
 * Represents a Schema type definition (simple or complex).
 * Mirrors org.apache.xmlbeans.SchemaType.
 */
class SchemaType
{
    // Built-in type codes (matching XSD built-in types)
    const BTC_NOT_BUILTIN = 0;
    const BTC_ANY_TYPE = 1;
    const BTC_ANY_SIMPLE = 2;
    const BTC_STRING = 3;
    const BTC_BOOLEAN = 4;
    const BTC_FLOAT = 5;
    const BTC_DOUBLE = 6;
    const BTC_DECIMAL = 7;
    const BTC_DURATION = 8;
    const BTC_DATE_TIME = 9;
    const BTC_TIME = 10;
    const BTC_DATE = 11;
    const BTC_G_YEAR_MONTH = 12;
    const BTC_G_YEAR = 13;
    const BTC_G_MONTH_DAY = 14;
    const BTC_G_DAY = 15;
    const BTC_G_MONTH = 16;
    const BTC_HEX_BINARY = 17;
    const BTC_BASE_64_BINARY = 18;
    const BTC_ANY_URI = 19;
    const BTC_QNAME = 20;
    const BTC_NOTATION = 21;
    const BTC_NORMALIZED_STRING = 22;
    const BTC_TOKEN = 23;
    const BTC_LANGUAGE = 24;
    const BTC_NMTOKEN = 25;
    const BTC_NMTOKENS = 26;
    const BTC_NAME = 27;
    const BTC_NCNAME = 28;
    const BTC_ID = 29;
    const BTC_IDREF = 30;
    const BTC_IDREFS = 31;
    const BTC_ENTITY = 32;
    const BTC_ENTITIES = 33;
    const BTC_INTEGER = 34;
    const BTC_NON_POSITIVE_INTEGER = 35;
    const BTC_NEGATIVE_INTEGER = 36;
    const BTC_LONG = 37;
    const BTC_INT = 38;
    const BTC_SHORT = 39;
    const BTC_BYTE = 40;
    const BTC_NON_NEGATIVE_INTEGER = 41;
    const BTC_UNSIGNED_LONG = 42;
    const BTC_UNSIGNED_INT = 43;
    const BTC_UNSIGNED_SHORT = 44;
    const BTC_UNSIGNED_BYTE = 45;
    const BTC_POSITIVE_INTEGER = 46;

    // Content type
    const EMPTY_CONTENT = 0;
    const SIMPLE_CONTENT = 1;
    const ELEMENT_CONTENT = 2;
    const MIXED_CONTENT = 3;

    // Derivation types
    const DT_NOT_DERIVED = 0;
    const DT_RESTRICTION = 1;
    const DT_EXTENSION = 2;

    // Variety for simple types
    const NOT_SIMPLE = -1;
    const ATOMIC = 1;
    const LIST = 2;
    const UNION = 3;

    /** @var QName|null */
    private $name;

    /** @var int */
    private $builtinTypeCode = self::BTC_NOT_BUILTIN;

    /** @var SchemaType|null */
    private $baseType;

    /** @var int */
    private $contentType = self::ELEMENT_CONTENT;

    /** @var int */
    private $derivationType = self::DT_NOT_DERIVED;

    /** @var int */
    private $simpleVariety = self::NOT_SIMPLE;

    /** @var bool */
    private $isSimpleType = false;

    /** @var bool */
    private $isDocumentType = false;

    /** @var bool */
    private $isAttributeType = false;

    /** @var bool */
    private $isAbstract = false;

    /** @var bool */
    private $isFinal = false;

    /** @var SchemaProperty[] keyed by QName hash */
    private $elements = [];

    /** @var SchemaProperty[] keyed by QName hash */
    private $attributes = [];

    /** @var string|null The PHP class name for this type */
    private $phpClassName;

    /** @var SchemaParticle|null Content model */
    private $contentModel;

    /** @var array Facet restrictions */
    private $facets = [];

    /** @var array Enumeration values */
    private $enumerationValues = [];

    /** @var string|null Pattern facet */
    private $patternFacet;

    /** @var SchemaTypeSystem|null */
    private $typeSystem;

    /**
     * @param QName|null $name
     */
    public function __construct(QName $name = null)
    {
        $this->name = $name;
    }

    /** @return QName|null */
    public function getName()
    {
        return $this->name;
    }

    /** @param QName $name */
    public function setName(QName $name)
    {
        $this->name = $name;
    }

    /** @return int */
    public function getBuiltinTypeCode()
    {
        return $this->builtinTypeCode;
    }

    /** @param int $code */
    public function setBuiltinTypeCode($code)
    {
        $this->builtinTypeCode = (int) $code;
    }

    /** @return bool */
    public function isBuiltinType()
    {
        return $this->builtinTypeCode !== self::BTC_NOT_BUILTIN;
    }

    /** @return SchemaType|null */
    public function getBaseType()
    {
        return $this->baseType;
    }

    /** @param SchemaType $type */
    public function setBaseType(SchemaType $type)
    {
        $this->baseType = $type;
    }

    /** @return int */
    public function getContentType()
    {
        return $this->contentType;
    }

    /** @param int $type */
    public function setContentType($type)
    {
        $this->contentType = (int) $type;
    }

    /** @return int */
    public function getDerivationType()
    {
        return $this->derivationType;
    }

    /** @param int $type */
    public function setDerivationType($type)
    {
        $this->derivationType = (int) $type;
    }

    /** @return int */
    public function getSimpleVariety()
    {
        return $this->simpleVariety;
    }

    /** @param int $variety */
    public function setSimpleVariety($variety)
    {
        $this->simpleVariety = (int) $variety;
    }

    /** @return bool */
    public function isSimpleType()
    {
        return $this->isSimpleType;
    }

    /** @param bool $flag */
    public function setIsSimpleType($flag)
    {
        $this->isSimpleType = (bool) $flag;
    }

    /** @return bool */
    public function isDocumentType()
    {
        return $this->isDocumentType;
    }

    /** @param bool $flag */
    public function setIsDocumentType($flag)
    {
        $this->isDocumentType = (bool) $flag;
    }

    /** @return bool */
    public function isAttributeType()
    {
        return $this->isAttributeType;
    }

    /** @param bool $flag */
    public function setIsAttributeType($flag)
    {
        $this->isAttributeType = (bool) $flag;
    }

    /** @return bool */
    public function isAbstract()
    {
        return $this->isAbstract;
    }

    /** @param bool $flag */
    public function setIsAbstract($flag)
    {
        $this->isAbstract = (bool) $flag;
    }

    /**
     * Get all element properties.
     *
     * @return SchemaProperty[]
     */
    public function getElementProperties()
    {
        return array_values($this->elements);
    }

    /**
     * Get element property by name.
     *
     * @param QName $name
     * @return SchemaProperty|null
     */
    public function getElementProperty(QName $name)
    {
        $key = $name->hashKey();
        return isset($this->elements[$key]) ? $this->elements[$key] : null;
    }

    /**
     * @param SchemaProperty $prop
     */
    public function addElementProperty(SchemaProperty $prop)
    {
        $this->elements[$prop->getName()->hashKey()] = $prop;
    }

    /**
     * Get all attribute properties.
     *
     * @return SchemaProperty[]
     */
    public function getAttributeProperties()
    {
        return array_values($this->attributes);
    }

    /**
     * Get attribute property by name.
     *
     * @param QName $name
     * @return SchemaProperty|null
     */
    public function getAttributeProperty(QName $name)
    {
        $key = $name->hashKey();
        return isset($this->attributes[$key]) ? $this->attributes[$key] : null;
    }

    /**
     * @param SchemaProperty $prop
     */
    public function addAttributeProperty(SchemaProperty $prop)
    {
        $this->attributes[$prop->getName()->hashKey()] = $prop;
    }

    /** @return string|null */
    public function getPhpClassName()
    {
        return $this->phpClassName;
    }

    /** @param string $className */
    public function setPhpClassName($className)
    {
        $this->phpClassName = $className;
    }

    /** @return SchemaParticle|null */
    public function getContentModel()
    {
        return $this->contentModel;
    }

    /** @param SchemaParticle $model */
    public function setContentModel(SchemaParticle $model)
    {
        $this->contentModel = $model;
    }

    /**
     * @param string $facetName (e.g., 'minInclusive', 'maxLength')
     * @param mixed  $value
     */
    public function setFacet($facetName, $value)
    {
        $this->facets[$facetName] = $value;
    }

    /**
     * @param string $facetName
     * @return mixed|null
     */
    public function getFacet($facetName)
    {
        return isset($this->facets[$facetName]) ? $this->facets[$facetName] : null;
    }

    /** @return array */
    public function getFacets()
    {
        return $this->facets;
    }

    /** @return array */
    public function getEnumerationValues()
    {
        return $this->enumerationValues;
    }

    /** @param array $values */
    public function setEnumerationValues(array $values)
    {
        $this->enumerationValues = $values;
    }

    /** @return string|null */
    public function getPatternFacet()
    {
        return $this->patternFacet;
    }

    /** @param string $pattern */
    public function setPatternFacet($pattern)
    {
        $this->patternFacet = $pattern;
    }

    /**
     * Tests if this type is assignable from the given type.
     *
     * @param SchemaType $subType
     * @return bool
     */
    public function isAssignableFrom(SchemaType $subType)
    {
        $cursor = $subType;
        while ($cursor !== null) {
            if ($cursor === $this) {
                return true;
            }
            if ($cursor->name !== null && $this->name !== null && $cursor->name->equals($this->name)) {
                return true;
            }
            $cursor = $cursor->getBaseType();
        }
        return false;
    }

    /** @return SchemaTypeSystem|null */
    public function getTypeSystem()
    {
        return $this->typeSystem;
    }

    /** @param SchemaTypeSystem $ts */
    public function setTypeSystem(SchemaTypeSystem $ts)
    {
        $this->typeSystem = $ts;
    }

    /** @return string */
    public function __toString()
    {
        return $this->name ? (string) $this->name : '(anonymous)';
    }
}
