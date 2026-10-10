<?php

namespace XmlBeans\Type;

use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlDouble - corresponds to xs:double.
 */
class XmlDouble extends XmlFloat
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_DOUBLE);
    }
}
