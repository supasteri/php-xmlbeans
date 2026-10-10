<?php

namespace XmlBeans\Binding;

use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaType;
use XmlBeans\Type;

/**
 * Maps SchemaType instances to the appropriate PHP typed XmlObject class.
 */
class TypeMapper
{
    /** @var array BTC code => class name */
    private static $map = [
        SchemaType::BTC_STRING            => Type\XmlString::class,
        SchemaType::BTC_NORMALIZED_STRING  => Type\XmlNormalizedString::class,
        SchemaType::BTC_TOKEN             => Type\XmlToken::class,
        SchemaType::BTC_BOOLEAN           => Type\XmlBoolean::class,
        SchemaType::BTC_INT               => Type\XmlInt::class,
        SchemaType::BTC_INTEGER           => Type\XmlInteger::class,
        SchemaType::BTC_LONG              => Type\XmlLong::class,
        SchemaType::BTC_SHORT             => Type\XmlShort::class,
        SchemaType::BTC_BYTE              => Type\XmlByte::class,
        SchemaType::BTC_DECIMAL           => Type\XmlDecimal::class,
        SchemaType::BTC_FLOAT             => Type\XmlFloat::class,
        SchemaType::BTC_DOUBLE            => Type\XmlDouble::class,
        SchemaType::BTC_DATE              => Type\XmlDate::class,
        SchemaType::BTC_DATE_TIME         => Type\XmlDateTime::class,
        SchemaType::BTC_TIME              => Type\XmlTime::class,
        SchemaType::BTC_DURATION          => Type\XmlDuration::class,
        SchemaType::BTC_ANY_URI           => Type\XmlAnyURI::class,
        SchemaType::BTC_BASE_64_BINARY    => Type\XmlBase64Binary::class,
        SchemaType::BTC_HEX_BINARY        => Type\XmlHexBinary::class,
        SchemaType::BTC_QNAME             => Type\XmlQName::class,
        SchemaType::BTC_LANGUAGE          => Type\XmlLanguage::class,
        SchemaType::BTC_NCNAME            => Type\XmlNCName::class,
        SchemaType::BTC_NAME              => Type\XmlName::class,
        SchemaType::BTC_NMTOKEN           => Type\XmlNMTOKEN::class,
        SchemaType::BTC_ID                => Type\XmlID::class,
        SchemaType::BTC_IDREF             => Type\XmlIDREF::class,
        SchemaType::BTC_NON_NEGATIVE_INTEGER => Type\XmlNonNegativeInteger::class,
        SchemaType::BTC_POSITIVE_INTEGER  => Type\XmlPositiveInteger::class,
        SchemaType::BTC_UNSIGNED_INT      => Type\XmlUnsignedInt::class,
        SchemaType::BTC_UNSIGNED_LONG     => Type\XmlUnsignedLong::class,
        SchemaType::BTC_UNSIGNED_SHORT    => Type\XmlUnsignedShort::class,
        SchemaType::BTC_UNSIGNED_BYTE     => Type\XmlUnsignedByte::class,
    ];

    /**
     * Create a typed XmlObject for the given SchemaType.
     *
     * @param SchemaType   $type
     * @param \DOMNode|null $node
     * @return XmlObjectBase
     */
    public static function createTypedObject(SchemaType $type, \DOMNode $node = null)
    {
        $btc = $type->getBuiltinTypeCode();
        if ($btc !== SchemaType::BTC_NOT_BUILTIN && isset(self::$map[$btc])) {
            $class = self::$map[$btc];
            return new $class($node, $type);
        }

        // Check generated class
        $className = $type->getPhpClassName();
        if ($className && class_exists($className)) {
            return new $className($node, $type);
        }

        // Walk up base type chain
        $base = $type->getBaseType();
        if ($base) {
            return self::createTypedObject($base, $node);
        }

        return new XmlObjectBase($node, $type);
    }

    /**
     * Get the PHP class name for a built-in type code.
     *
     * @param int $btc
     * @return string|null
     */
    public static function getClassForBuiltin($btc)
    {
        return isset(self::$map[$btc]) ? self::$map[$btc] : null;
    }
}
