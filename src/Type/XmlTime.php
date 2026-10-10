<?php

namespace XmlBeans\Type;

use XmlBeans\Binding\XmlObjectBase;
use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlTime - corresponds to xs:time.
 */
class XmlTime extends XmlObjectBase
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_TIME);
    }

    /** @return string */
    public function getValue()
    {
        return trim($this->getStringValue());
    }

    /** @param string $value */
    public function setValue($value)
    {
        $this->setStringValue((string) $value);
    }
}
