# Validation

*Adapted from the [Apache XMLBeans Validation Guide](https://xmlbeans.apache.org/guide/Validation.html)*

## Overview

PHP XMLBeans provides schema validation to ensure XML documents conform to their XSD definitions. Validation checks structural constraints, type restrictions, enumerations, facets, and cardinality rules.

## When Validation Happens

Validation is **on-request only** — it does not happen automatically during parsing. Parsing performs only a shallow structural check. You must explicitly call `validate()` to check conformance.

## Validation Methods

### Simple Validation

The quickest way to validate XML against a schema:

```php
use XmlBeans\XmlBeans;

$xml = '<product>
    <name>Widget</name>
    <price>29.99</price>
</product>';

$xsd = '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="product">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="name" type="xs:string"/>
                <xs:element name="price" type="xs:decimal"/>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>';

$isValid = XmlBeans::validate($xml, $xsd);
echo $isValid ? "VALID" : "INVALID";
```

### Validation with Error Collection

To get detailed error messages, use the `Validator` class with an error listener:

```php
use XmlBeans\Validation\Validator;

$errors = array();
$isValid = Validator::validateXmlAgainstXsd($xml, $xsd, $errors);

if (!$isValid) {
    foreach ($errors as $error) {
        echo "Error: " . $error . "\n";
    }
}
```

### Validation with Error Listener Callback

For processing errors as they occur:

```php
$listener = function ($error) {
    echo "Validation error: " . $error . "\n";
};

$isValid = Validator::validateXmlAgainstXsd($xml, $xsd, $errors, $listener);
```

## What Gets Validated

Schema validation checks:

- **Structure**: Required elements are present, unexpected elements are rejected
- **Order**: Elements appear in the order defined by `xs:sequence`
- **Cardinality**: `minOccurs` and `maxOccurs` constraints are satisfied
- **Types**: Values match their declared types (e.g., `xs:int` must be a number)
- **Facets**: Restrictions like `minLength`, `maxLength`, `pattern`, `minInclusive`, `maxInclusive`
- **Enumerations**: Values are from the allowed set
- **Attributes**: Required attributes are present, values match their types

## Validation Examples

### Type Validation

```php
$xsd = '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="data">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="count" type="xs:positiveInteger"/>
                <xs:element name="ratio" type="xs:decimal"/>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>';

// Valid
$valid = XmlBeans::validate('<data><count>42</count><ratio>3.14</ratio></data>', $xsd);
// true

// Invalid: -1 is not a positive integer
$invalid = XmlBeans::validate('<data><count>-1</count><ratio>3.14</ratio></data>', $xsd);
// false

// Invalid: "abc" is not a decimal
$invalid2 = XmlBeans::validate('<data><count>1</count><ratio>abc</ratio></data>', $xsd);
// false
```

### Enumeration Validation

```php
$xsd = '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:simpleType name="colorType">
        <xs:restriction base="xs:string">
            <xs:enumeration value="red"/>
            <xs:enumeration value="green"/>
            <xs:enumeration value="blue"/>
        </xs:restriction>
    </xs:simpleType>
    <xs:element name="color" type="colorType"/>
</xs:schema>';

XmlBeans::validate('<color>red</color>', $xsd);    // true
XmlBeans::validate('<color>orange</color>', $xsd); // false
```

### Cardinality Validation

```php
$xsd = '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="list">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="item" type="xs:string"
                            minOccurs="1" maxOccurs="3"/>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>';

// Valid: 2 items (within 1-3 range)
XmlBeans::validate('<list><item>a</item><item>b</item></list>', $xsd);
// true

// Invalid: 0 items (below minOccurs=1)
XmlBeans::validate('<list/>', $xsd);
// false
```

## Schema Compilation Validation

When compiling a schema, the schema itself is validated:

```php
try {
    $ts = XmlBeans::compileXsd($invalidXsd);
} catch (\Exception $e) {
    echo "Schema error: " . $e->getMessage();
}
```

## Modify and Revalidate

A common pattern is to parse XML, modify it, and validate the result:

```php
// Parse and modify
$doc = XmlBeans::parse($xml);
$cursor = $doc->newCursor();
$cursor->toFirstChild();
$cursor->toChild('price');
$cursor->setTextValue('not-a-number'); // intentionally bad

// Revalidate the modified document
$modifiedXml = $doc->xmlText();
$isValid = XmlBeans::validate($modifiedXml, $xsd);
// false — "not-a-number" doesn't match xs:decimal
```

## See Also

- [Getting Started](GettingStarted.md) — Introduction with validation examples
- [Schema Type System](SchemaTypeSystem.md) — Understanding compiled schemas
- [Built-in Types](BuiltinTypes.md) — Type constraints and facets
