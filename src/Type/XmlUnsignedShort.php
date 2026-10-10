<?php

namespace XmlBeans\Type;

use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlUnsignedShort - corresponds to xs:unsignedShort.
 */
class XmlUnsignedShort extends XmlUnsignedInt
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_UNSIGNED_SHORT);
    }
}
