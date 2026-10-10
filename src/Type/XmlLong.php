<?php

namespace XmlBeans\Type;

use XmlBeans\Binding\XmlObjectBase;
use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlLong - corresponds to xs:long.
 */
class XmlLong extends XmlObjectBase
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_LONG);
    }

    /** @return int */
    public function getValue()
    {
        return (int) trim($this->getStringValue());
    }

    /** @param int $value */
    public function setValue($value)
    {
        $this->setStringValue((string) $value);
    }
}
