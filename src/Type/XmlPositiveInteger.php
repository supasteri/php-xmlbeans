<?php

namespace XmlBeans\Type;

use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlPositiveInteger - corresponds to xs:positiveInteger.
 */
class XmlPositiveInteger extends XmlNonNegativeInteger
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_POSITIVE_INTEGER);
    }
}
