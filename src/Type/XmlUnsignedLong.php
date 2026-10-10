<?php

namespace XmlBeans\Type;

use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlUnsignedLong - corresponds to xs:unsignedLong.
 */
class XmlUnsignedLong extends XmlNonNegativeInteger
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_UNSIGNED_LONG);
    }
}
