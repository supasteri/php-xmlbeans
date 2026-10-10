<?php

namespace XmlBeans\Type;

use XmlBeans\Binding\XmlObjectBase;
use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlDate - corresponds to xs:date.
 */
class XmlDate extends XmlObjectBase
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_DATE);
    }

    /** @return \DateTime|null */
    public function getValue()
    {
        $v = trim($this->getStringValue());
        if ($v === '') return null;
        try {
            return new \DateTime($v);
        } catch (\Exception $e) {
            return null;
        }
    }

    /** @param \DateTime|string $value */
    public function setValue($value)
    {
        if ($value instanceof \DateTime) {
            $this->setStringValue($value->format('Y-m-d'));
        } else {
            $this->setStringValue((string) $value);
        }
    }
}
