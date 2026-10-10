<?php

namespace XmlBeans\Type;

use XmlBeans\Binding\XmlObjectBase;
use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlFloat - corresponds to xs:float.
 */
class XmlFloat extends XmlObjectBase
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_FLOAT);
    }

    /** @return float */
    public function getValue()
    {
        $v = trim($this->getStringValue());
        if ($v === 'INF') return INF;
        if ($v === '-INF') return -INF;
        if ($v === 'NaN') return NAN;
        return (float) $v;
    }

    /** @param float $value */
    public function setValue($value)
    {
        if (is_infinite($value)) {
            $this->setStringValue($value > 0 ? 'INF' : '-INF');
        } elseif (is_nan($value)) {
            $this->setStringValue('NaN');
        } else {
            $this->setStringValue((string) $value);
        }
    }
}
