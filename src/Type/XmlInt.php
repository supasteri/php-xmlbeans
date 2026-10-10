<?php

namespace XmlBeans\Type;

use XmlBeans\Binding\XmlObjectBase;
use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlInt - corresponds to xs:int.
 */
class XmlInt extends XmlObjectBase
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_INT);
    }

    /** @return int */
    public function getValue()
    {
        return (int) trim($this->getStringValue());
    }

    /** @param int $value */
    public function setValue($value)
    {
        $this->setStringValue((string) (int) $value);
    }
}
