<?php

namespace XmlBeans\Type;

use XmlBeans\Binding\XmlObjectBase;
use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlBoolean - corresponds to xs:boolean.
 */
class XmlBoolean extends XmlObjectBase
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_BOOLEAN);
    }

    /** @return bool */
    public function getValue()
    {
        $v = trim($this->getStringValue());
        return $v === 'true' || $v === '1';
    }

    /** @param bool $value */
    public function setValue($value)
    {
        $this->setStringValue($value ? 'true' : 'false');
    }
}
