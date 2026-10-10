# Built-in Types

*Adapted from the [Apache XMLBeans Built-in Types Guide](https://xmlbeans.apache.org/guide/BuiltinTypes.html)*

## Overview

PHP XMLBeans provides 46 type wrappers that mirror the 46 XML Schema built-in types. Each type handles parsing, validation, and conversion between XSD values and PHP values.

## Type Mapping Table

| # | XSD Type | PHP XMLBeans Class | PHP Type |
|---|---|---|---|
| 1 | xs:anyType | XmlObject | XmlObject |
| 2 | xs:anySimpleType | XmlAnySimpleType | string |
| 3 | xs:anyURI | XmlAnyURI | string |
| 4 | xs:base64Binary | XmlBase64Binary | string |
| 5 | xs:boolean | XmlBoolean | bool |
| 6 | xs:byte | XmlByte | int |
| 7 | xs:date | XmlDate | string |
| 8 | xs:dateTime | XmlDateTime | string |
| 9 | xs:decimal | XmlDecimal | string (arbitrary precision) |
| 10 | xs:double | XmlDouble | float |
| 11 | xs:duration | XmlDuration | string |
| 12 | xs:ENTITIES | XmlENTITIES | string |
| 13 | xs:ENTITY | XmlENTITY | string |
| 14 | xs:float | XmlFloat | float |
| 15 | xs:gDay | XmlGDay | string |
| 16 | xs:gMonth | XmlGMonth | string |
| 17 | xs:gMonthDay | XmlGMonthDay | string |
| 18 | xs:gYear | XmlGYear | string |
| 19 | xs:gYearMonth | XmlGYearMonth | string |
| 20 | xs:hexBinary | XmlHexBinary | string |
| 21 | xs:ID | XmlID | string |
| 22 | xs:IDREF | XmlIDREF | string |
| 23 | xs:IDREFS | XmlIDREFS | string |
| 24 | xs:int | XmlInt | int |
| 25 | xs:integer | XmlInteger | string (arbitrary precision) |
| 26 | xs:language | XmlLanguage | string |
| 27 | xs:long | XmlLong | int |
| 28 | xs:Name | XmlName | string |
| 29 | xs:NCName | XmlNCName | string |
| 30 | xs:negativeInteger | XmlNegativeInteger | string |
| 31 | xs:NMTOKEN | XmlNMTOKEN | string |
| 32 | xs:NMTOKENS | XmlNMTOKENS | string |
| 33 | xs:nonNegativeInteger | XmlNonNegativeInteger | string |
| 34 | xs:nonPositiveInteger | XmlNonPositiveInteger | string |
| 35 | xs:normalizedString | XmlNormalizedString | string |
| 36 | xs:NOTATION | XmlNOTATION | string |
| 37 | xs:positiveInteger | XmlPositiveInteger | string |
| 38 | xs:QName | XmlQName | string |
| 39 | xs:short | XmlShort | int |
| 40 | xs:string | XmlString | string |
| 41 | xs:time | XmlTime | string |
| 42 | xs:token | XmlToken | string |
| 43 | xs:unsignedByte | XmlUnsignedByte | int |
| 44 | xs:unsignedInt | XmlUnsignedInt | int |
| 45 | xs:unsignedLong | XmlUnsignedLong | string |
| 46 | xs:unsignedShort | XmlUnsignedShort | int |

Note: PHP has no fixed-width integer types, so `xs:long` and `xs:int` both map to PHP `int`. Arbitrary-precision types (`xs:decimal`, `xs:integer`) map to `string` to preserve precision.

## Type Hierarchy

The built-in types form an inheritance hierarchy:

```
xs:anyType
├── xs:anySimpleType
│   ├── xs:string
│   │   └── xs:normalizedString
│   │       └── xs:token
│   │           ├── xs:language
│   │           ├── xs:NMTOKEN
│   │           ├── xs:Name
│   │           │   └── xs:NCName
│   │           │       ├── xs:ID
│   │           │       ├── xs:IDREF
│   │           │       └── xs:ENTITY
│   │           └── xs:NMTOKENS (list)
│   ├── xs:boolean
│   ├── xs:float
│   ├── xs:double
│   ├── xs:decimal
│   │   └── xs:integer
│   │       ├── xs:nonPositiveInteger
│   │       │   └── xs:negativeInteger
│   │       ├── xs:long
│   │       │   └── xs:int
│   │       │       └── xs:short
│   │       │           └── xs:byte
│   │       └── xs:nonNegativeInteger
│   │           ├── xs:unsignedLong
│   │           │   └── xs:unsignedInt
│   │           │       └── xs:unsignedShort
│   │           │           └── xs:unsignedByte
│   │           └── xs:positiveInteger
│   ├── xs:duration
│   ├── xs:dateTime
│   ├── xs:date
│   ├── xs:time
│   ├── xs:gYear
│   ├── xs:gYearMonth
│   ├── xs:gMonth
│   ├── xs:gMonthDay
│   ├── xs:gDay
│   ├── xs:hexBinary
│   ├── xs:base64Binary
│   ├── xs:anyURI
│   ├── xs:QName
│   └── xs:NOTATION
```

## Using Built-in Types

### Looking Up Types

```php
use XmlBeans\XmlBeans;
use XmlBeans\SchemaType;
use XmlBeans\Schema\BuiltinTypeSystem;

// By name
$stringType = XmlBeans::getBuiltinType('string');
$intType = XmlBeans::getBuiltinType('int');

// By BTC constant
$type = BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_STRING);

// Get the entire type system
$ts = XmlBeans::getBuiltinTypeSystem();
$allTypes = $ts->getGlobalTypes();
```

### Inspecting Types

```php
$type = XmlBeans::getBuiltinType('int');

echo $type->getName()->getLocalPart();    // "int"
echo $type->isSimpleType() ? "simple" : "complex";  // "simple"

$base = $type->getBaseType();
echo $base->getName()->getLocalPart();    // "long"

echo $type->isBuiltinType() ? "built-in" : "user";  // "built-in"
echo $type->getBuiltinTypeCode();         // 38 (SchemaType::BTC_INT)
```

### Type Categories

**Primitive types**: `string`, `boolean`, `decimal`, `float`, `double`, `duration`, `dateTime`, `time`, `date`, `hexBinary`, `base64Binary`, `anyURI`, `QName`, `NOTATION`

**Date/time types**: `dateTime`, `date`, `time`, `duration`, `gYear`, `gYearMonth`, `gMonth`, `gMonthDay`, `gDay`

**Numeric types**: `decimal`, `float`, `double`, `integer`, `long`, `int`, `short`, `byte`, `nonNegativeInteger`, `nonPositiveInteger`, `positiveInteger`, `negativeInteger`, `unsignedLong`, `unsignedInt`, `unsignedShort`, `unsignedByte`

**String-derived types**: `normalizedString`, `token`, `language`, `Name`, `NCName`, `NMTOKEN`, `NMTOKENS`, `ID`, `IDREF`, `IDREFS`, `ENTITY`, `ENTITIES`

## Built-in Type Code Constants

Each type has a numeric constant on `SchemaType`:

```php
SchemaType::BTC_NOT_BUILTIN          // 0
SchemaType::BTC_ANY_TYPE             // 1
SchemaType::BTC_ANY_SIMPLE           // 2
SchemaType::BTC_STRING               // 3
SchemaType::BTC_BOOLEAN              // 4
SchemaType::BTC_FLOAT                // 5
SchemaType::BTC_DOUBLE               // 6
SchemaType::BTC_DECIMAL              // 7
SchemaType::BTC_INT                  // 38
SchemaType::BTC_INTEGER              // 34
SchemaType::BTC_POSITIVE_INTEGER     // 46
// ... and 36 more
```

## See Also

- [Schema Type System](SchemaTypeSystem.md) — Type system architecture
- [Validation](Validation.md) — Type validation in action
- [Built-in Types Sample](../samples/BuiltinTypes.php) — Working example
