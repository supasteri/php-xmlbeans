<?php

namespace XmlBeans\Type;

use XmlBeans\Binding\XmlObjectBase;
use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlBase64Binary - corresponds to xs:base64Binary.
 */
class XmlBase64Binary extends XmlObjectBase
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_BASE_64_BINARY);
    }

    /** @return string Raw bytes */
    public function getValue()
    {
        return base64_decode(trim($this->getStringValue()));
    }

    /** @param string $rawBytes */
    public function setValue($rawBytes)
    {
        $this->setStringValue(base64_encode($rawBytes));
    }
}
