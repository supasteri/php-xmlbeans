<?php

namespace XmlBeans\Type;

use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlNonNegativeInteger - corresponds to xs:nonNegativeInteger.
 */
class XmlNonNegativeInteger extends XmlInteger
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_NON_NEGATIVE_INTEGER);
    }
}
