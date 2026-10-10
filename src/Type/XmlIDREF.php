<?php

namespace XmlBeans\Type;

use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlIDREF - corresponds to xs:IDREF.
 */
class XmlIDREF extends XmlToken
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_IDREF);
    }
}
