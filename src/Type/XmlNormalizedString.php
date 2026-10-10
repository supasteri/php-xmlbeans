<?php

namespace XmlBeans\Type;

use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlNormalizedString - corresponds to xs:normalizedString.
 */
class XmlNormalizedString extends XmlString
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_NORMALIZED_STRING);
    }

    public function getValue()
    {
        $val = parent::getValue();
        return str_replace(["\t", "\n", "\r"], ' ', $val);
    }
}
