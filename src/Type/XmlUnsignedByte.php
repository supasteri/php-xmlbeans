<?php

namespace XmlBeans\Type;

use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlUnsignedByte - corresponds to xs:unsignedByte.
 */
class XmlUnsignedByte extends XmlUnsignedShort
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_UNSIGNED_BYTE);
    }
}
