# Schema Type System

*Adapted from the [Apache XMLBeans Schema Type System Guide](https://xmlbeans.apache.org/guide/IntroSchemaType.html)*

## Overview

Compiling a schema with PHP XMLBeans creates a **schema type system** — a runtime representation of the schema's types, elements, and attributes. The type system gives you a programmatic view of the schema for introspection and validation.

## Core Classes

### SchemaTypeSystem

The top-level container for a compiled schema. It holds all global types, global elements, and global attributes.

```php
use XmlBeans\XmlBeans;

$typeSystem = XmlBeans::compileXsd($xsd);

// Inspect the type system
$globalTypes = $typeSystem->getGlobalTypes();
$globalElements = $typeSystem->getGlobalElements();
```

### SchemaType

Represents a type definition — both named types (like `customerType`) and built-in types (like `xs:string`). Every type has:

- A `QName` (namespace + local name) for named types
- A base type it derives from
- Whether it is simple or complex
- Facets (restrictions) for simple types

```php
foreach ($typeSystem->getGlobalTypes() as $type) {
    $name = $type->getName()->getLocalPart();
    $base = $type->getBaseType();
    $isSimple = $type->isSimpleType();
    echo "$name extends " . $base->getName()->getLocalPart() . "\n";
}
```

### SchemaProperty

Represents an element or attribute declared within a type. Properties have a name, a type, and cardinality (minOccurs/maxOccurs).

### SchemaParticle

Represents the content model of a complex type — sequence, choice, or all groups, and their child particles.

## The Built-in Type System

PHP XMLBeans includes a pre-built type system for the 46 XSD built-in types:

```php
$builtins = XmlBeans::getBuiltinTypeSystem();
$stringType = XmlBeans::getBuiltinType('string');
$intType = XmlBeans::getBuiltinType('int');
```

The built-in types form a hierarchy. For example:

```
xs:anyType
  └─ xs:anySimpleType
       └─ xs:string
            └─ xs:normalizedString
                 └─ xs:token
                      └─ xs:language
                      └─ xs:Name
                           └─ xs:NCName
                                └─ xs:ID
                                └─ xs:IDREF
                                └─ xs:ENTITY
       └─ xs:decimal
            └─ xs:integer
                 └─ xs:nonPositiveInteger
                      └─ xs:negativeInteger
                 └─ xs:long
                      └─ xs:int
                           └─ xs:short
                                └─ xs:byte
                 └─ xs:nonNegativeInteger
                      └─ xs:unsignedLong
                           └─ xs:unsignedInt
                                └─ xs:unsignedShort
                                     └─ xs:unsignedByte
                      └─ xs:positiveInteger
```

## Type Signatures

Calling `toString()` on a `SchemaType` returns a signature string useful for debugging. These follow an XMLBeans convention (not a W3C standard).

### Signature Formats

| Kind | Format | Example |
|---|---|---|
| Global type | `T=<localname>@<namespace>` | `T=customerType@http://example.com` |
| Document type | `D=<element-name>@<namespace>` | `D=purchase-order@http://example.com` |
| Attribute type | `R=<attribute-name>@<namespace>` | `R=id@http://example.com` |

### Anonymous Type Signatures

Anonymous types (those without a name) build their signature from their containing context:

| Context | Format |
|---|---|
| Qualified element | `E=<name>\|<containing>` |
| Unqualified element | `U=<name>\|<containing>` |
| Qualified attribute | `Q=<name>\|<containing>` |
| Unqualified attribute | `A=<name>\|<containing>` |
| Union member | `M=#\|<union>` |
| Restriction base | `B=\|<base>` |
| List item | `I=\|<list>` |

Read signatures inside-out. For example:

```
I=|A=mylist|E=root|D=root@myNamespace
```

Means: the list item type → within the `mylist` attribute → within the `root` element → within the document type for `root` → in `myNamespace`.

## Inspecting a Compiled Schema

```php
$xsd = '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"
                   targetNamespace="http://example.com/po">
    <xs:complexType name="customerType">
        <xs:sequence>
            <xs:element name="name" type="xs:string"/>
            <xs:element name="address" type="xs:string"/>
        </xs:sequence>
    </xs:complexType>
    <xs:element name="customer" type="po:customerType"
                xmlns:po="http://example.com/po"/>
</xs:schema>';

$ts = XmlBeans::compileXsd($xsd);

// List global types
foreach ($ts->getGlobalTypes() as $type) {
    if ($type->getName() !== null) {
        echo "Type: " . $type->getName()->getLocalPart() . "\n";
        echo "  Simple: " . ($type->isSimpleType() ? 'yes' : 'no') . "\n";

        $base = $type->getBaseType();
        if ($base && $base->getName()) {
            echo "  Base: " . $base->getName()->getLocalPart() . "\n";
        }

        // Inspect properties
        foreach ($type->getProperties() as $prop) {
            echo "  Property: " . $prop->getName()->getLocalPart() . "\n";
        }
    }
}

// List global elements
foreach ($ts->getGlobalElements() as $elem) {
    echo "Element: " . $elem->getName()->getLocalPart() . "\n";
}
```

## Resolving Types

You can look up types across all loaded type systems:

```php
use XmlBeans\Common\QName;

$qname = new QName('http://www.w3.org/2001/XMLSchema', 'string');
$type = XmlBeans::resolveType($qname);
```

## Built-in Type Code Constants

Each built-in type has a numeric constant (`BTC_*`) on `SchemaType`:

```php
use XmlBeans\SchemaType;

SchemaType::BTC_STRING;          // 3
SchemaType::BTC_BOOLEAN;         // 4
SchemaType::BTC_INT;             // 38
SchemaType::BTC_DATE_TIME;       // 9
SchemaType::BTC_POSITIVE_INTEGER; // 46
```

These are useful for efficient type comparisons in switch statements.
