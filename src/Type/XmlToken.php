<?php

namespace XmlBeans\Type;

use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlToken - corresponds to xs:token.
 */
class XmlToken extends XmlNormalizedString
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_TOKEN);
    }

    public function getValue()
    {
        return trim(preg_replace('/\s+/', ' ', parent::getValue()));
    }
}
