<?php

namespace XmlBeans\Type;

use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlNCName - corresponds to xs:NCName.
 */
class XmlNCName extends XmlToken
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_NCNAME);
    }
}
