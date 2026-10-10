<?php

namespace XmlBeans\Type;

use XmlBeans\Binding\XmlObjectBase;
use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlInteger - corresponds to xs:integer (arbitrary precision).
 */
class XmlInteger extends XmlObjectBase
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_INTEGER);
    }

    /** @return string Numeric string for arbitrary precision */
    public function getValue()
    {
        return trim($this->getStringValue());
    }

    /** @param int|string $value */
    public function setValue($value)
    {
        $this->setStringValue((string) $value);
    }
}
