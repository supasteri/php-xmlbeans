<?php

namespace XmlBeans\Schema;

use XmlBeans\Common\QName;
use XmlBeans\SchemaType;
use XmlBeans\SchemaTypeSystem;

/**
 * Provides the built-in XML Schema types (xs:string, xs:int, xs:boolean, etc.).
 * This is a singleton that serves as the root type system.
 */
class BuiltinTypeSystem
{
    const XSD_NS = 'http://www.w3.org/2001/XMLSchema';

    /** @var SchemaTypeSystem|null */
    private static $instance;

    /** @var SchemaType[] keyed by BTC constant */
    private static $builtinTypes = [];

    /**
     * @return SchemaTypeSystem
     */
    public static function get()
    {
        if (self::$instance === null) {
            self::$instance = self::buildTypeSystem();
        }
        return self::$instance;
    }

    /**
     * Get a built-in type by its BTC constant.
     *
     * @param int $btc
     * @return SchemaType|null
     */
    public static function getBuiltinType($btc)
    {
        self::get(); // ensure initialized
        return isset(self::$builtinTypes[$btc]) ? self::$builtinTypes[$btc] : null;
    }

    /**
     * Look up a built-in type by xs:name.
     *
     * @param string $localName e.g. "string", "int", "boolean"
     * @return SchemaType|null
     */
    public static function findByLocalName($localName)
    {
        $ts = self::get();
        $qname = new QName(self::XSD_NS, $localName);
        return $ts->findType($qname);
    }

    /**
     * Build the entire built-in type system.
     *
     * @return SchemaTypeSystem
     */
    private static function buildTypeSystem()
    {
        $ts = new SchemaTypeSystem('builtin');
        $ts->setNamespace(self::XSD_NS);

        $typeDefs = self::getTypeDefinitions();

        // First pass: create all types
        foreach ($typeDefs as $btc => $def) {
            $type = new SchemaType(new QName(self::XSD_NS, $def['name']));
            $type->setBuiltinTypeCode($btc);
            $type->setIsSimpleType($def['simple']);

            if (isset($def['variety'])) {
                $type->setSimpleVariety($def['variety']);
            }

            if ($def['simple']) {
                $type->setContentType(SchemaType::SIMPLE_CONTENT);
            }

            self::$builtinTypes[$btc] = $type;
            $ts->addGlobalType(new QName(self::XSD_NS, $def['name']), $type);
        }

        // Second pass: wire base type hierarchy
        foreach ($typeDefs as $btc => $def) {
            if (isset($def['base']) && isset(self::$builtinTypes[$def['base']])) {
                self::$builtinTypes[$btc]->setBaseType(self::$builtinTypes[$def['base']]);
                self::$builtinTypes[$btc]->setDerivationType(SchemaType::DT_RESTRICTION);
            }
        }

        // Third pass: set facets
        self::applyFacets();

        return $ts;
    }

    /**
     * @return array
     */
    private static function getTypeDefinitions()
    {
        $S = true;  // simple
        $C = false; // complex
        $A = SchemaType::ATOMIC;

        return [
            SchemaType::BTC_ANY_TYPE => [
                'name' => 'anyType', 'simple' => $C,
            ],
            SchemaType::BTC_ANY_SIMPLE => [
                'name' => 'anySimpleType', 'simple' => $S, 'base' => SchemaType::BTC_ANY_TYPE,
                'variety' => SchemaType::ATOMIC,
            ],
            SchemaType::BTC_STRING => [
                'name' => 'string', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_BOOLEAN => [
                'name' => 'boolean', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_FLOAT => [
                'name' => 'float', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_DOUBLE => [
                'name' => 'double', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_DECIMAL => [
                'name' => 'decimal', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_DURATION => [
                'name' => 'duration', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_DATE_TIME => [
                'name' => 'dateTime', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_TIME => [
                'name' => 'time', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_DATE => [
                'name' => 'date', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_G_YEAR_MONTH => [
                'name' => 'gYearMonth', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_G_YEAR => [
                'name' => 'gYear', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_G_MONTH_DAY => [
                'name' => 'gMonthDay', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_G_DAY => [
                'name' => 'gDay', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_G_MONTH => [
                'name' => 'gMonth', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_HEX_BINARY => [
                'name' => 'hexBinary', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_BASE_64_BINARY => [
                'name' => 'base64Binary', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_ANY_URI => [
                'name' => 'anyURI', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_QNAME => [
                'name' => 'QName', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_NOTATION => [
                'name' => 'NOTATION', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => $A,
            ],
            SchemaType::BTC_NORMALIZED_STRING => [
                'name' => 'normalizedString', 'simple' => $S, 'base' => SchemaType::BTC_STRING,
                'variety' => $A,
            ],
            SchemaType::BTC_TOKEN => [
                'name' => 'token', 'simple' => $S, 'base' => SchemaType::BTC_NORMALIZED_STRING,
                'variety' => $A,
            ],
            SchemaType::BTC_LANGUAGE => [
                'name' => 'language', 'simple' => $S, 'base' => SchemaType::BTC_TOKEN,
                'variety' => $A,
            ],
            SchemaType::BTC_NMTOKEN => [
                'name' => 'NMTOKEN', 'simple' => $S, 'base' => SchemaType::BTC_TOKEN,
                'variety' => $A,
            ],
            SchemaType::BTC_NMTOKENS => [
                'name' => 'NMTOKENS', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => SchemaType::LIST,
            ],
            SchemaType::BTC_NAME => [
                'name' => 'Name', 'simple' => $S, 'base' => SchemaType::BTC_TOKEN,
                'variety' => $A,
            ],
            SchemaType::BTC_NCNAME => [
                'name' => 'NCName', 'simple' => $S, 'base' => SchemaType::BTC_NAME,
                'variety' => $A,
            ],
            SchemaType::BTC_ID => [
                'name' => 'ID', 'simple' => $S, 'base' => SchemaType::BTC_NCNAME,
                'variety' => $A,
            ],
            SchemaType::BTC_IDREF => [
                'name' => 'IDREF', 'simple' => $S, 'base' => SchemaType::BTC_NCNAME,
                'variety' => $A,
            ],
            SchemaType::BTC_IDREFS => [
                'name' => 'IDREFS', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => SchemaType::LIST,
            ],
            SchemaType::BTC_ENTITY => [
                'name' => 'ENTITY', 'simple' => $S, 'base' => SchemaType::BTC_NCNAME,
                'variety' => $A,
            ],
            SchemaType::BTC_ENTITIES => [
                'name' => 'ENTITIES', 'simple' => $S, 'base' => SchemaType::BTC_ANY_SIMPLE,
                'variety' => SchemaType::LIST,
            ],
            SchemaType::BTC_INTEGER => [
                'name' => 'integer', 'simple' => $S, 'base' => SchemaType::BTC_DECIMAL,
                'variety' => $A,
            ],
            SchemaType::BTC_NON_POSITIVE_INTEGER => [
                'name' => 'nonPositiveInteger', 'simple' => $S, 'base' => SchemaType::BTC_INTEGER,
                'variety' => $A,
            ],
            SchemaType::BTC_NEGATIVE_INTEGER => [
                'name' => 'negativeInteger', 'simple' => $S, 'base' => SchemaType::BTC_NON_POSITIVE_INTEGER,
                'variety' => $A,
            ],
            SchemaType::BTC_LONG => [
                'name' => 'long', 'simple' => $S, 'base' => SchemaType::BTC_INTEGER,
                'variety' => $A,
            ],
            SchemaType::BTC_INT => [
                'name' => 'int', 'simple' => $S, 'base' => SchemaType::BTC_LONG,
                'variety' => $A,
            ],
            SchemaType::BTC_SHORT => [
                'name' => 'short', 'simple' => $S, 'base' => SchemaType::BTC_INT,
                'variety' => $A,
            ],
            SchemaType::BTC_BYTE => [
                'name' => 'byte', 'simple' => $S, 'base' => SchemaType::BTC_SHORT,
                'variety' => $A,
            ],
            SchemaType::BTC_NON_NEGATIVE_INTEGER => [
                'name' => 'nonNegativeInteger', 'simple' => $S, 'base' => SchemaType::BTC_INTEGER,
                'variety' => $A,
            ],
            SchemaType::BTC_UNSIGNED_LONG => [
                'name' => 'unsignedLong', 'simple' => $S, 'base' => SchemaType::BTC_NON_NEGATIVE_INTEGER,
                'variety' => $A,
            ],
            SchemaType::BTC_UNSIGNED_INT => [
                'name' => 'unsignedInt', 'simple' => $S, 'base' => SchemaType::BTC_UNSIGNED_LONG,
                'variety' => $A,
            ],
            SchemaType::BTC_UNSIGNED_SHORT => [
                'name' => 'unsignedShort', 'simple' => $S, 'base' => SchemaType::BTC_UNSIGNED_INT,
                'variety' => $A,
            ],
            SchemaType::BTC_UNSIGNED_BYTE => [
                'name' => 'unsignedByte', 'simple' => $S, 'base' => SchemaType::BTC_UNSIGNED_SHORT,
                'variety' => $A,
            ],
            SchemaType::BTC_POSITIVE_INTEGER => [
                'name' => 'positiveInteger', 'simple' => $S, 'base' => SchemaType::BTC_NON_NEGATIVE_INTEGER,
                'variety' => $A,
            ],
        ];
    }

    /**
     * Apply numeric range and other facets to built-in types.
     */
    private static function applyFacets()
    {
        $bt = self::$builtinTypes;

        if (isset($bt[SchemaType::BTC_LONG])) {
            $bt[SchemaType::BTC_LONG]->setFacet('minInclusive', '-9223372036854775808');
            $bt[SchemaType::BTC_LONG]->setFacet('maxInclusive', '9223372036854775807');
        }
        if (isset($bt[SchemaType::BTC_INT])) {
            $bt[SchemaType::BTC_INT]->setFacet('minInclusive', '-2147483648');
            $bt[SchemaType::BTC_INT]->setFacet('maxInclusive', '2147483647');
        }
        if (isset($bt[SchemaType::BTC_SHORT])) {
            $bt[SchemaType::BTC_SHORT]->setFacet('minInclusive', '-32768');
            $bt[SchemaType::BTC_SHORT]->setFacet('maxInclusive', '32767');
        }
        if (isset($bt[SchemaType::BTC_BYTE])) {
            $bt[SchemaType::BTC_BYTE]->setFacet('minInclusive', '-128');
            $bt[SchemaType::BTC_BYTE]->setFacet('maxInclusive', '127');
        }
        if (isset($bt[SchemaType::BTC_UNSIGNED_LONG])) {
            $bt[SchemaType::BTC_UNSIGNED_LONG]->setFacet('minInclusive', '0');
            $bt[SchemaType::BTC_UNSIGNED_LONG]->setFacet('maxInclusive', '18446744073709551615');
        }
        if (isset($bt[SchemaType::BTC_UNSIGNED_INT])) {
            $bt[SchemaType::BTC_UNSIGNED_INT]->setFacet('minInclusive', '0');
            $bt[SchemaType::BTC_UNSIGNED_INT]->setFacet('maxInclusive', '4294967295');
        }
        if (isset($bt[SchemaType::BTC_UNSIGNED_SHORT])) {
            $bt[SchemaType::BTC_UNSIGNED_SHORT]->setFacet('minInclusive', '0');
            $bt[SchemaType::BTC_UNSIGNED_SHORT]->setFacet('maxInclusive', '65535');
        }
        if (isset($bt[SchemaType::BTC_UNSIGNED_BYTE])) {
            $bt[SchemaType::BTC_UNSIGNED_BYTE]->setFacet('minInclusive', '0');
            $bt[SchemaType::BTC_UNSIGNED_BYTE]->setFacet('maxInclusive', '255');
        }
        if (isset($bt[SchemaType::BTC_NON_NEGATIVE_INTEGER])) {
            $bt[SchemaType::BTC_NON_NEGATIVE_INTEGER]->setFacet('minInclusive', '0');
        }
        if (isset($bt[SchemaType::BTC_POSITIVE_INTEGER])) {
            $bt[SchemaType::BTC_POSITIVE_INTEGER]->setFacet('minInclusive', '1');
        }
        if (isset($bt[SchemaType::BTC_NON_POSITIVE_INTEGER])) {
            $bt[SchemaType::BTC_NON_POSITIVE_INTEGER]->setFacet('maxInclusive', '0');
        }
        if (isset($bt[SchemaType::BTC_NEGATIVE_INTEGER])) {
            $bt[SchemaType::BTC_NEGATIVE_INTEGER]->setFacet('maxInclusive', '-1');
        }
        if (isset($bt[SchemaType::BTC_INTEGER])) {
            $bt[SchemaType::BTC_INTEGER]->setFacet('fractionDigits', '0');
        }
        if (isset($bt[SchemaType::BTC_BOOLEAN])) {
            $bt[SchemaType::BTC_BOOLEAN]->setEnumerationValues(['true', 'false', '1', '0']);
        }
        if (isset($bt[SchemaType::BTC_LANGUAGE])) {
            $bt[SchemaType::BTC_LANGUAGE]->setPatternFacet('[a-zA-Z]{1,8}(-[a-zA-Z0-9]{1,8})*');
        }
    }
}
