<?php

namespace XmlBeans\Type;

use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlNMTOKEN - corresponds to xs:NMTOKEN.
 */
class XmlNMTOKEN extends XmlToken
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_NMTOKEN);
    }
}
