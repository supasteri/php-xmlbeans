<?php

namespace XmlBeans\Type;

use XmlBeans\Binding\XmlObjectBase;
use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlHexBinary - corresponds to xs:hexBinary.
 */
class XmlHexBinary extends XmlObjectBase
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_HEX_BINARY);
    }

    /** @return string Raw bytes */
    public function getValue()
    {
        return hex2bin(trim($this->getStringValue()));
    }

    /** @param string $rawBytes */
    public function setValue($rawBytes)
    {
        $this->setStringValue(strtoupper(bin2hex($rawBytes)));
    }
}
