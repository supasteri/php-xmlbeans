<?php
/**
 * BuiltinTypes Sample
 *
 * Demonstrates the 46 built-in XSD type wrappers provided by PHP XMLBeans.
 * Each XSD primitive and derived type maps to a PHP class that handles
 * parsing, validation, and conversion.
 *
 * Ported from: https://xmlbeans.apache.org/samples/BuiltInTypes.html
 */

require_once __DIR__ . '/../vendor/autoload.php';

use XmlBeans\XmlBeans;
use XmlBeans\SchemaType;
use XmlBeans\Schema\BuiltinTypeSystem;

echo "=== PHP XMLBeans Built-in Types Sample ===\n\n";

// --- 1. List all built-in types ---
echo "--- 1. All 46 built-in XSD types ---\n";

$ts = XmlBeans::getBuiltinTypeSystem();
$types = $ts->getGlobalTypes();

$typeNames = array();
foreach ($types as $type) {
    if ($type->getName() !== null) {
        $typeNames[] = $type->getName()->getLocalPart();
    }
}
sort($typeNames);

echo "Count: " . count($typeNames) . " types\n\n";
$col = 0;
foreach ($typeNames as $name) {
    echo sprintf("  %-22s", 'xs:' . $name);
    $col++;
    if ($col % 3 === 0) echo "\n";
}
if ($col % 3 !== 0) echo "\n";

// --- 2. Type hierarchy ---
echo "\n--- 2. Type hierarchy (inheritance) ---\n";

$hierarchyTypes = array('string', 'normalizedString', 'token', 'language', 'Name', 'NCName');
echo "\nString derivation chain:\n";
foreach ($hierarchyTypes as $name) {
    $type = XmlBeans::getBuiltinType($name);
    if ($type) {
        $base = $type->getBaseType();
        $baseName = $base ? $base->getName()->getLocalPart() : '(none)';
        echo sprintf("  xs:%-20s  extends xs:%s\n", $name, $baseName);
    }
}

$numTypes = array('decimal', 'integer', 'nonNegativeInteger', 'positiveInteger', 'long', 'int', 'short', 'byte');
echo "\nNumeric derivation chain:\n";
foreach ($numTypes as $name) {
    $type = XmlBeans::getBuiltinType($name);
    if ($type) {
        $base = $type->getBaseType();
        $baseName = $base ? $base->getName()->getLocalPart() : '(none)';
        echo sprintf("  xs:%-22s  extends xs:%s\n", $name, $baseName);
    }
}

// --- 3. Using types to parse and validate values ---
echo "\n--- 3. Parsing XML with typed values ---\n";

$xml = '<data xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
    <stringVal>Hello, World!</stringVal>
    <intVal>42</intVal>
    <doubleVal>3.14159</doubleVal>
    <boolVal>true</boolVal>
    <dateVal>2024-06-15</dateVal>
    <dateTimeVal>2024-06-15T10:30:00Z</dateTimeVal>
    <decimalVal>123.456</decimalVal>
    <hexBinVal>48656C6C6F</hexBinVal>
    <base64Val>SGVsbG8gV29ybGQ=</base64Val>
    <uriVal>https://example.com/path?q=test</uriVal>
    <durationVal>P1Y2M3DT4H5M6S</durationVal>
    <timeVal>14:30:00</timeVal>
</data>';

$doc = XmlBeans::parse($xml);

$typeMap = array(
    'stringVal'   => 'string',
    'intVal'      => 'int',
    'doubleVal'   => 'double',
    'boolVal'     => 'boolean',
    'dateVal'     => 'date',
    'dateTimeVal' => 'dateTime',
    'decimalVal'  => 'decimal',
    'hexBinVal'   => 'hexBinary',
    'base64Val'   => 'base64Binary',
    'uriVal'      => 'anyURI',
    'durationVal' => 'duration',
    'timeVal'     => 'time',
);

foreach ($typeMap as $elemName => $xsdType) {
    $nodes = $doc->selectPath('//*[local-name()="' . $elemName . '"]');
    if (count($nodes) > 0) {
        $rawValue = $nodes[0]->getStringValue();
        $type = XmlBeans::getBuiltinType($xsdType);
        echo sprintf("  %-14s (xs:%-12s): %s\n", $elemName, $xsdType, $rawValue);
    }
}

// --- 4. Simple vs complex types ---
echo "\n--- 4. Simple vs complex types ---\n";

$simpleTypes = array();
$complexTypes = array();

foreach ($types as $type) {
    if ($type->getName() === null) continue;
    if ($type->isSimpleType()) {
        $simpleTypes[] = $type->getName()->getLocalPart();
    } else {
        $complexTypes[] = $type->getName()->getLocalPart();
    }
}

sort($simpleTypes);
sort($complexTypes);

echo "Simple types (" . count($simpleTypes) . "): " . implode(', ', array_slice($simpleTypes, 0, 10)) . "...\n";
echo "Complex types (" . count($complexTypes) . "): " . implode(', ', $complexTypes) . "\n";

// --- 5. Type classification ---
echo "\n--- 5. Type classification by category ---\n";

$categories = array(
    'Primitive'   => array('string', 'boolean', 'decimal', 'float', 'double', 'duration',
                           'dateTime', 'time', 'date', 'hexBinary', 'base64Binary', 'anyURI', 'QName', 'NOTATION'),
    'Date/Time'   => array('dateTime', 'date', 'time', 'duration', 'gYear', 'gYearMonth', 'gMonth', 'gMonthDay', 'gDay'),
    'Numeric'     => array('decimal', 'float', 'double', 'integer', 'long', 'int', 'short', 'byte',
                           'nonNegativeInteger', 'nonPositiveInteger', 'positiveInteger', 'negativeInteger',
                           'unsignedLong', 'unsignedInt', 'unsignedShort', 'unsignedByte'),
    'String-derived' => array('normalizedString', 'token', 'language', 'Name', 'NCName',
                              'NMTOKEN', 'NMTOKENS', 'ID', 'IDREF', 'IDREFS', 'ENTITY', 'ENTITIES'),
);

foreach ($categories as $cat => $members) {
    echo "\n  " . $cat . ":\n";
    foreach ($members as $name) {
        $type = XmlBeans::getBuiltinType($name);
        if ($type) {
            echo "    xs:" . $name . "\n";
        }
    }
}

// --- 6. Schema validation using types ---
echo "\n--- 6. Schema validation with typed elements ---\n";

$xsd = '<?xml version="1.0"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="record">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="id" type="xs:positiveInteger"/>
                <xs:element name="name" type="xs:token"/>
                <xs:element name="score" type="xs:decimal"/>
                <xs:element name="active" type="xs:boolean"/>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>';

$validXml = '<record>
    <id>42</id>
    <name>Alice</name>
    <score>98.5</score>
    <active>true</active>
</record>';

$invalidXml = '<record>
    <id>-1</id>
    <name>Bob</name>
    <score>abc</score>
    <active>maybe</active>
</record>';

echo "Valid record:   " . (XmlBeans::validate($validXml, $xsd) ? "VALID" : "INVALID") . "\n";
echo "Invalid record: " . (XmlBeans::validate($invalidXml, $xsd) ? "VALID" : "INVALID") . "\n";

// --- 7. BTC (Built-in Type Code) constants ---
echo "\n--- 7. Built-in Type Code constants ---\n";

$btcExamples = array(
    array('ANY_TYPE',             SchemaType::BTC_ANY_TYPE),
    array('STRING',               SchemaType::BTC_STRING),
    array('BOOLEAN',              SchemaType::BTC_BOOLEAN),
    array('DECIMAL',              SchemaType::BTC_DECIMAL),
    array('INT',                  SchemaType::BTC_INT),
    array('DATE_TIME',            SchemaType::BTC_DATE_TIME),
    array('POSITIVE_INTEGER',     SchemaType::BTC_POSITIVE_INTEGER),
);

foreach ($btcExamples as $pair) {
    $constName = $pair[0];
    $btcVal = $pair[1];
    $type = BuiltinTypeSystem::getBuiltinType($btcVal);
    if ($type) {
        echo sprintf("  BTC_%-22s = %2d  =>  xs:%s\n", $constName, $btcVal, $type->getName()->getLocalPart());
    }
}

echo "\nDone.\n";
