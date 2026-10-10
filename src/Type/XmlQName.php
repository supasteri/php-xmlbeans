<?php

namespace XmlBeans\Type;

use XmlBeans\Binding\XmlObjectBase;
use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;

/**
 * XmlQName - corresponds to xs:QName.
 */
class XmlQName extends XmlObjectBase
{
    public function schemaType()
    {
        return BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_QNAME);
    }

    /** @return \XmlBeans\Common\QName */
    public function getValue()
    {
        $v = trim($this->getStringValue());
        if (strpos($v, ':') !== false) {
            list($prefix, $local) = explode(':', $v, 2);
            $ns = '';
            // Try to resolve prefix from DOM context
            if ($this->domNode instanceof \DOMElement) {
                $ns = $this->domNode->lookupNamespaceUri($prefix) ?: '';
            }
            return new \XmlBeans\Common\QName($ns, $local, $prefix);
        }
        return new \XmlBeans\Common\QName('', $v);
    }

    /** @param \XmlBeans\Common\QName|string $value */
    public function setValue($value)
    {
        if ($value instanceof \XmlBeans\Common\QName) {
            $prefix = $value->getPrefix();
            $str = $prefix ? ($prefix . ':' . $value->getLocalPart()) : $value->getLocalPart();
            $this->setStringValue($str);
        } else {
            $this->setStringValue((string) $value);
        }
    }
}
