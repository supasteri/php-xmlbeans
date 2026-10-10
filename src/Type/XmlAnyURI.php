<?php

namespace XmlBeans\Type;

use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlAnyURI - corresponds to xs:anyURI.
 */
class XmlAnyURI extends XmlString
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_ANY_URI);
    }
}
