<?php
/**
 * SchemaEnum Sample
 *
 * Demonstrates working with schema enumerations.
 * When a schema defines enumerated values, XMLBeans exposes them
 * as validated constraints on the type.
 *
 * Ported from: https://xmlbeans.apache.org/samples/SchemaEnum.html
 */

require_once __DIR__ . '/../vendor/autoload.php';

use XmlBeans\XmlBeans;
use XmlBeans\XmlOptions;

echo "=== PHP XMLBeans SchemaEnum Sample ===\n\n";

// --- 1. Compile a schema with enumerations ---
echo "--- 1. Schema with enumerations ---\n";

$xsd = '<?xml version="1.0"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"
           targetNamespace="http://example.com/colors"
           xmlns:tns="http://example.com/colors"
           elementFormDefault="qualified">

    <xs:simpleType name="colorType">
        <xs:restriction base="xs:string">
            <xs:enumeration value="red"/>
            <xs:enumeration value="green"/>
            <xs:enumeration value="blue"/>
            <xs:enumeration value="yellow"/>
            <xs:enumeration value="purple"/>
        </xs:restriction>
    </xs:simpleType>

    <xs:simpleType name="sizeType">
        <xs:restriction base="xs:string">
            <xs:enumeration value="small"/>
            <xs:enumeration value="medium"/>
            <xs:enumeration value="large"/>
            <xs:enumeration value="extra-large"/>
        </xs:restriction>
    </xs:simpleType>

    <xs:element name="product">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="name" type="xs:string"/>
                <xs:element name="color" type="tns:colorType"/>
                <xs:element name="size" type="tns:sizeType"/>
            </xs:sequence>
        </xs:complexType>
    </xs:element>

</xs:schema>';

$typeSystem = XmlBeans::compileXsd($xsd);

// --- 2. Inspect enumeration types ---
echo "\n--- 2. Inspecting enumeration types ---\n";

foreach ($typeSystem->getGlobalTypes() as $type) {
    if ($type->getName() === null) continue;

    $name = $type->getName()->getLocalPart();
    $facets = $type->getFacets();

    if (isset($facets['enumeration'])) {
        echo "\nType: " . $name . "\n";
        echo "  Base type: xs:" . ($type->getBaseType() ? $type->getBaseType()->getName()->getLocalPart() : 'unknown') . "\n";
        echo "  Allowed values:\n";
        foreach ($facets['enumeration'] as $i => $val) {
            echo "    [" . $i . "] " . $val . "\n";
        }
    }
}

// --- 3. Validate against enumerations ---
echo "\n--- 3. Validating enumerated values ---\n";

// Valid document
$validXml = '<product xmlns="http://example.com/colors">
    <name>Widget</name>
    <color>blue</color>
    <size>medium</size>
</product>';

$valid = XmlBeans::validate($validXml, $xsd);
echo "Valid enum values (blue, medium): " . ($valid ? "VALID" : "INVALID") . "\n";

// Invalid color
$invalidXml = '<product xmlns="http://example.com/colors">
    <name>Widget</name>
    <color>orange</color>
    <size>medium</size>
</product>';

$valid2 = XmlBeans::validate($invalidXml, $xsd);
echo "Invalid enum value (orange):      " . ($valid2 ? "VALID" : "INVALID") . "\n";

// Invalid size
$invalidXml2 = '<product xmlns="http://example.com/colors">
    <name>Widget</name>
    <color>red</color>
    <size>tiny</size>
</product>';

$valid3 = XmlBeans::validate($invalidXml2, $xsd);
echo "Invalid enum value (tiny):        " . ($valid3 ? "VALID" : "INVALID") . "\n";

// --- 4. Using enums with type wrappers ---
echo "\n--- 4. Reading enumerated values from XML ---\n";

$doc = XmlBeans::parse($validXml);

$colorNode = $doc->selectPath('//*[local-name()="color"]');
$sizeNode = $doc->selectPath('//*[local-name()="size"]');

if (count($colorNode) > 0) {
    $color = $colorNode[0]->getStringValue();
    echo "Color: " . $color . "\n";
    echo "  Is valid enum: " . (in_array($color, array('red', 'green', 'blue', 'yellow', 'purple')) ? 'yes' : 'no') . "\n";
}

if (count($sizeNode) > 0) {
    $size = $sizeNode[0]->getStringValue();
    echo "Size:  " . $size . "\n";
    echo "  Is valid enum: " . (in_array($size, array('small', 'medium', 'large', 'extra-large')) ? 'yes' : 'no') . "\n";
}

echo "\nDone.\n";
