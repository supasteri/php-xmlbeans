<?php

namespace XmlBeans\Type;

use XmlBeans\Binding\XmlObjectBase;
use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlDecimal - corresponds to xs:decimal.
 */
class XmlDecimal extends XmlObjectBase
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_DECIMAL);
    }

    /** @return float */
    public function getValue()
    {
        return (float) trim($this->getStringValue());
    }

    /** @param float|string $value */
    public function setValue($value)
    {
        $this->setStringValue((string) $value);
    }
}
