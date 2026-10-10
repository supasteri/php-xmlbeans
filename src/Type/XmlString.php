<?php

namespace XmlBeans\Type;

use XmlBeans\Binding\XmlObjectBase;
use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlString - corresponds to xs:string.
 */
class XmlString extends XmlObjectBase
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_STRING);
    }

    /** @return string */
    public function getValue()
    {
        return $this->getStringValue();
    }

    /** @param string $value */
    public function setValue($value)
    {
        $this->setStringValue((string) $value);
    }
}
