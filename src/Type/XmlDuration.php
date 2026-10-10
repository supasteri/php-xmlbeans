<?php

namespace XmlBeans\Type;

use XmlBeans\Binding\XmlObjectBase;
use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlDuration - corresponds to xs:duration.
 */
class XmlDuration extends XmlObjectBase
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_DURATION);
    }

    /** @return \DateInterval|null */
    public function getValue()
    {
        $v = trim($this->getStringValue());
        if ($v === '') return null;
        try {
            return new \DateInterval($v);
        } catch (\Exception $e) {
            return null;
        }
    }

    /** @param \DateInterval|string $value */
    public function setValue($value)
    {
        if ($value instanceof \DateInterval) {
            $this->setStringValue($value->format('P%yY%mM%dDT%hH%iM%sS'));
        } else {
            $this->setStringValue((string) $value);
        }
    }
}
