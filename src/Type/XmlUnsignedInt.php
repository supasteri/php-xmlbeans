<?php

namespace XmlBeans\Type;

use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlUnsignedInt - corresponds to xs:unsignedInt.
 */
class XmlUnsignedInt extends XmlNonNegativeInteger
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_UNSIGNED_INT);
    }

    /** @return int */
    public function getValue()
    {
        return abs((int) trim($this->getStringValue()));
    }
}
