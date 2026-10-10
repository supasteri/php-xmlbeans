# Generated Types

*Adapted from the [Apache XMLBeans Generated Types Guide](https://xmlbeans.apache.org/guide/GeneratedTypes.html)*

## Overview

Compiling a schema with PHP XMLBeans creates a type system that represents the schema's types, elements, and attributes as PHP objects. This guide explains how schema constructs map to the PHP XMLBeans API.

## Schema-to-PHP Mapping

### Global Elements

Global elements produce document-level types. For example, a global element `<purchase-order>` produces a type system entry accessible through `SchemaTypeSystem::getGlobalElements()`.

```php
$ts = XmlBeans::compileXsd($xsd);
foreach ($ts->getGlobalElements() as $elem) {
    echo "Element: " . $elem->getName()->getLocalPart() . "\n";
}
```

### Global Types

Named complex and simple types produce type entries:

```php
foreach ($ts->getGlobalTypes() as $type) {
    if ($type->getName() !== null) {
        echo "Type: " . $type->getName()->getLocalPart() . "\n";
    }
}
```

### Anonymous Types

Types defined inline (without a `name` attribute) are anonymous. They are identified by their containing context and have compound signatures:

```xml
<xs:element name="order">
    <xs:complexType>  <!-- anonymous type -->
        <xs:sequence>
            <xs:element name="item" type="xs:string"/>
        </xs:sequence>
    </xs:complexType>
</xs:element>
```

## Type Hierarchy

All generated types extend from `XmlObject`, the base of the XMLBeans type hierarchy:

```
XmlObject (xs:anyType)
├── XmlAnySimpleType (xs:anySimpleType)
│   ├── XmlString (xs:string)
│   ├── XmlInt (xs:int)
│   └── ... (46 built-in types)
└── [user-defined complex types]
```

Simple type restrictions extend the corresponding built-in type. For example, an `xs:string` restriction with enumeration values extends `XmlString`.

## Enumerations

Restricting `xs:string` with enumeration values creates a type with an allowed value set:

```xml
<xs:simpleType name="colorType">
    <xs:restriction base="xs:string">
        <xs:enumeration value="red"/>
        <xs:enumeration value="green"/>
        <xs:enumeration value="blue"/>
    </xs:restriction>
</xs:simpleType>
```

Access enumeration values through the type's facets:

```php
$ts = XmlBeans::compileXsd($xsd);
foreach ($ts->getGlobalTypes() as $type) {
    $facets = $type->getFacets();
    if (isset($facets['enumeration'])) {
        echo "Enum values: " . implode(', ', $facets['enumeration']) . "\n";
    }
}
```

## Properties

Elements and attributes within a complex type become properties:

```php
$type = $ts->getGlobalTypes()[0]; // a complex type
foreach ($type->getProperties() as $prop) {
    echo "Property: " . $prop->getName()->getLocalPart() . "\n";
    echo "  Type: " . $prop->getType()->getName()->getLocalPart() . "\n";
    echo "  Min occurs: " . $prop->getMinOccurs() . "\n";
    echo "  Max occurs: " . $prop->getMaxOccurs() . "\n";
}
```

## Content Model

Complex types have a content model (sequence, choice, or all):

```php
$particle = $type->getContentModel();
if ($particle !== null) {
    echo "Particle type: " . $particle->getParticleType() . "\n";
    foreach ($particle->getParticleChildren() as $child) {
        echo "  Child: " . $child->getName()->getLocalPart() . "\n";
    }
}
```

## Namespace Handling

Schema target namespaces are preserved in the type system:

```php
$ts = XmlBeans::compileXsd($xsd);
echo "Namespace: " . $ts->getNamespace() . "\n";

foreach ($ts->getGlobalElements() as $elem) {
    echo $elem->getName()->getNamespaceURI() . ":"
         . $elem->getName()->getLocalPart() . "\n";
}
```

## See Also

- [Schema Type System](SchemaTypeSystem.md) — Understanding the type system
- [Validation](Validation.md) — Validating against generated types
- [Built-in Types](BuiltinTypes.md) — The 46 base types
