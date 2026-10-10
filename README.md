# PHP XMLBeans

A PHP port of [Apache XMLBeans](https://xmlbeans.apache.org/) — an XML-Schema-to-PHP binding framework. Compiles XSD schemas into a type system and generates typed PHP classes with getter/setter accessors, validation, cursor-based navigation, and XPath support.

**Requirements:** PHP >= 7.2, ext-dom, ext-libxml, ext-simplexml, ext-bcmath

## The Problem

XML is everywhere in enterprise systems — SOAP services, EDI, configuration, data interchange — and working with it in PHP typically means one of two painful paths:

1. **SimpleXML / DOMDocument** — You get raw nodes with no type safety. An element that should be an integer comes back as a string. A missing required element is discovered at runtime, deep in business logic, not at parse time. Refactoring an XSD breaks nothing visibly until production.

2. **Manual mapping classes** — You hand-write dozens of PHP classes with `fromXml()` / `toXml()` methods, maintaining them by hand every time the schema changes. This is tedious, error-prone, and falls out of sync.

XMLBeans eliminates both problems.

## What XMLBeans Does

XMLBeans compiles XML Schema (XSD) definitions into a strongly-typed PHP object model. The schema *is* the source of truth, and the generated code enforces it.

Given a schema like:

```xml
<xs:complexType name="PurchaseOrder">
  <xs:sequence>
    <xs:element name="customer" type="xs:string"/>
    <xs:element name="total" type="xs:decimal"/>
    <xs:element name="date" type="xs:date"/>
    <xs:element name="items" type="tns:ItemList"/>
  </xs:sequence>
</xs:complexType>
```

You get PHP classes where `$order->getTotal()` returns a proper decimal, `$order->getDate()` returns a date, and validation happens automatically against all 46 XSD built-in types and their facets (patterns, enumerations, ranges, lengths).

## Installation

```bash
composer require xmlbeans/php-xmlbeans
```

Or add to `composer.json`:

```json
{
    "require": {
        "xmlbeans/php-xmlbeans": "^1.0"
    }
}
```

## Quick Start

### 1. Parse and navigate XML

```php
use XmlBeans\XmlBeans;

$obj = XmlBeans::parse('<order><item>Widget</item><qty>5</qty></order>');

// XPath selection
$items = $obj->selectPath('//item');
echo $items[0]->getStringValue(); // "Widget"

// Scalar accessors
echo $obj->selectPathString('//item');       // "Widget"
echo $obj->selectPathNumber('//qty');         // 5
echo $obj->selectPathBoolean('//item');       // true

// Cursor-based navigation
$cursor = $obj->newCursor();
$cursor->toFirstChild(); // <order>
$cursor->toChild('qty');
echo $cursor->getTextValue(); // "5"
$cursor->setTextValue('10');
```

### 2. Compile an XSD schema

```php
$typeSystem = XmlBeans::compileXsd('purchase-order.xsd');

// Inspect the type system
$addressType = $typeSystem->findType(
    new \XmlBeans\Common\QName('http://example.com', 'USAddress')
);
foreach ($addressType->getElementProperties() as $prop) {
    echo $prop->getName()->getLocalPart() . ': '
       . ($prop->isRequired() ? 'required' : 'optional') . "\n";
}
```

### 3. Generate typed PHP classes (scomp)

```bash
php bin/scomp -d src/Generated -n App\\Schema -v purchase-order.xsd
```

This generates classes like:

```php
namespace App\Schema;

class USAddress extends XmlObjectBase
{
    public function getName() { ... }
    public function setName($value) { ... }
    public function getStreet() { ... }
    public function setStreet($value) { ... }
    public function getCity() { ... }
    public function isSetCity() { ... }
    public function unsetCity() { ... }
}
```

### 4. Validate XML

```php
// Quick validation against XSD
$valid = XmlBeans::validate($xmlString, $xsdString);

// Validation with error collection
$errors = [];
$opts = (new XmlOptions())->setErrorCollection($errors);
if (!$obj->validate($opts)) {
    foreach ($errors as $err) {
        echo $err . "\n";
    }
}

// Error listener callback
$opts->addErrorListener(function(\XmlBeans\XmlError $err) {
    log_validation_error($err->getMessage(), $err->getLine());
});
```

### 5. XmlCursor — fine-grained navigation

```php
$cursor = $obj->newCursor();

// Navigate
$cursor->toFirstChild();
$cursor->toNextSibling();
$cursor->toChild('address');
$cursor->toParent();

// Read/write
echo $cursor->getName()->getLocalPart();
echo $cursor->getTextValue();
$cursor->setTextValue('new value');
$cursor->getAttributeText('id');

// Modify structure
$cursor->beginElement('newChild');
$cursor->insertChars('text content');
$cursor->removeXml();

// Bookmarks
$cursor->setBookmark('my-position');
$cursor->toNextSibling();
$cursor->toBookmark('my-position'); // jump back

// Token-level traversal
while ($cursor->toNextToken() !== \XmlBeans\Cursor\XmlCursor::TOKEN_NONE) {
    echo XmlCursor::tokenTypeName($cursor->currentTokenType()) . "\n";
}
```

## XPath — A Complete Subsystem

XPath isn't bolted on as an afterthought. It's a complete subsystem with five dedicated classes, integrated throughout the library.

### Features

- **Typed results** — XPath queries return `XmlObject` instances, not raw DOM nodes
- **Fluent expression builder** — Construct XPath with axis methods, predicates, and operators
- **Fluent predicate builder** — Comparison, logical, and string function predicates with proper escaping
- **Compiled & cached expressions** — Parse once, execute many; LRU cache with configurable size
- **Variable binding** — Bind PHP values with type-aware escaping
- **Namespace-aware** — Auto-detection from documents, manual registration, well-known namespace sets
- **Custom PHP functions** — Register any callable and invoke it from XPath
- **Scalar accessors** — `selectString()`, `selectNumber()`, `selectBoolean()`, `selectStrings()`
- **Convenience methods** — `count()`, `exists()`, `selectFirst()`, `selectChildren()`
- **Cursor integration** — Execute XPath from any cursor position
- **Expression analysis** — `isAbsolute()`, `hasPredicate()`, `hasVariables()`, `getVariableNames()`
- **Union support** — Combine paths with the `|` operator

### XPath Builder — Fluent API

```php
use XmlBeans\XPath\XPathBuilder;

// //item[@price > 100]
$xpath = XPathBuilder::create()
    ->descendant('item')
    ->where(XPathBuilder::attr('price')->gt(100))
    ->build();

// //item[price > 50 and category = 'tools']/name
$xpath = XPathBuilder::create()
    ->descendant('item')
    ->where(
        XPathBuilder::elem('price')->gt(50)
            ->andPred(XPathBuilder::elem('category')->eq('tools'))
    )
    ->child('name')
    ->build();

// Union: //name | //price
$xpath = XPathBuilder::create()
    ->descendant('name')
    ->union(XPathBuilder::create()->descendant('price'))
    ->build();

// Namespace-prefixed: /soap:Envelope/soap:Body/*
$xpath = XPathBuilder::create()
    ->root()
    ->nsChild('soap', 'Envelope')
    ->nsChild('soap', 'Body')
    ->any()
    ->build();
```

### Compiled Expressions with Variables

```php
use XmlBeans\XPath\XPathExpression;

$expr = XPathExpression::compile('//item[price > $minPrice and @category = $cat]');
$expr->bindVariable('minPrice', 50);
$expr->bindVariable('cat', 'electronics');

$results = $engine->selectTyped($expr->getNormalizedExpression());

// Rebind and reuse
$expr->clearVariables();
$expr->bindVariable('minPrice', 200);
```

### XPath Engine

```php
use XmlBeans\XPath\XPathEngine;

$engine = XPathEngine::fromXml($xml);

$items = $engine->selectTyped('//item');
$first = $engine->selectFirst('//item');
$names = $engine->selectStrings('//item/name');
$count = $engine->count('//item');
$exists = $engine->exists('//shipper');
```

### Custom PHP Functions

```php
$engine->registerFunction('discount', function ($price, $pct) {
    return (float) $price * (1 - (float) $pct / 100);
});
```

### Namespace Management

```php
use XmlBeans\XPath\XPathNamespaceContext;

$ctx = new XPathNamespaceContext();
$ctx->registerNamespace('po', 'http://openuri.org/easypo');
$ctx->loadFromDocument($domDocument);           // Auto-detect
$ctx->registerSchemaNamespaces();                // xs, xsi
$engine->setNamespaceContext($ctx);
```

## Architecture

| Component | Description | XMLBeans Equivalent |
|---|---|---|
| `XmlObject` | Base interface for all XML beans | `org.apache.xmlbeans.XmlObject` |
| `XmlObjectBase` | DOM-backed implementation | `XmlObjectBase` |
| `XmlCursor` | Cursor-based navigation | `XmlCursor` |
| `SchemaType` | Type definition (simple/complex) | `SchemaType` |
| `SchemaProperty` | Element/attribute declaration | `SchemaProperty` |
| `SchemaParticle` | Content model particle | `SchemaParticle` |
| `SchemaTypeSystem` | Compiled type registry | `SchemaTypeSystem` |
| `BuiltinTypeSystem` | 46 XSD built-in types | Built-in type system |
| `SchemaCompiler` | XSD → type system | `SchemaTypeLoader` |
| `PhpCodeGenerator` | Type system → PHP classes | scomp codegen |
| `Validator` | Schema validation | Validation subsystem |
| `TypeMapper` | Type → PHP class resolver | Type binding |
| `XPathEngine` | Advanced XPath with typed results | XPath subsystem |
| `XPathBuilder` | Fluent XPath construction | N/A (PHP XMLBeans exclusive) |
| `XPathExpression` | Compiled/cached XPath with variables | N/A (PHP XMLBeans exclusive) |
| `XPathPredicate` | Fluent predicate builder | N/A (PHP XMLBeans exclusive) |
| `XPathNamespaceContext` | Namespace prefix management | N/A (PHP XMLBeans exclusive) |

### Built-in Simple Types

All 46 XSD built-in types are supported with typed wrappers:

`XmlString`, `XmlNormalizedString`, `XmlToken`, `XmlBoolean`, `XmlInt`, `XmlLong`, `XmlShort`, `XmlByte`, `XmlInteger`, `XmlDecimal`, `XmlFloat`, `XmlDouble`, `XmlDate`, `XmlDateTime`, `XmlTime`, `XmlDuration`, `XmlAnyURI`, `XmlBase64Binary`, `XmlHexBinary`, `XmlQName`, `XmlLanguage`, `XmlNCName`, `XmlName`, `XmlNMTOKEN`, `XmlID`, `XmlIDREF`, `XmlNonNegativeInteger`, `XmlPositiveInteger`, `XmlUnsignedInt`, `XmlUnsignedLong`, `XmlUnsignedShort`, `XmlUnsignedByte`

### Validation Facets Supported

- `pattern` — regex validation
- `enumeration` — allowed value set
- `minLength` / `maxLength` / `length` — string length
- `minInclusive` / `maxInclusive` / `minExclusive` / `maxExclusive` — numeric range
- `totalDigits` / `fractionDigits` — precision
- Occurrence constraints (`minOccurs`/`maxOccurs`)
- Required attributes
- Fixed values
- Empty content check

### Schema Features Supported

- Global types (simple and complex)
- Global elements and attributes
- `xs:restriction` with all facets
- `xs:extension` (simple and complex content)
- Content models: `xs:sequence`, `xs:choice`, `xs:all`
- `xs:any` wildcards
- `xs:import` and `xs:include`
- `nillable` elements (`xsi:nil`)
- Default and fixed values
- Document types for global elements

## Comparison

| Capability | DOMDocument | SimpleXML | XMLBeans |
|---|---|---|---|
| Type-safe access | No | No | Yes |
| Schema compilation | No | No | Yes |
| Code generation | No | No | Yes |
| Facet validation | No | No | Yes |
| XPath + typed results | No | No | Yes |
| Cursor navigation | No | No | Yes |
| Namespace management | Manual | Partial | Automatic |
| Content model awareness | No | No | Yes |
| PHP 7.2 compatible | Yes | Yes | Yes |

## Who Needs This

- **SOAP/WSDL integrations** — Parse and generate compliant messages with confidence
- **EDI/B2B data exchange** — Enforce partner schemas at the code level
- **Configuration systems** — Validate complex config files against their XSD
- **Document processing** — Transform, validate, and route XML documents in workflows
- **Legacy system integration** — Bridge XML-heavy enterprise systems with modern PHP applications

## Samples

Working code examples are in the [`samples/`](samples/) directory:

| Sample | Description |
|---|---|
| [GettingStarted](samples/GettingStarted.php) | End-to-end tutorial: parse, query, compile, validate |
| [XPathQuery](samples/XPathQuery.php) | Comprehensive XPath: builder, engine, expressions, functions |
| [CursorNavigation](samples/CursorNavigation.php) | Cursor navigation, editing, bookmarks, push/pop |
| [Validation](samples/Validation.php) | Schema validation with error handling |
| [DateTime](samples/DateTime.php) | XSD date, time, dateTime, and duration types |
| [SchemaEnum](samples/SchemaEnum.php) | Schema enumerations |
| [MixedContent](samples/MixedContent.php) | Mixed content handling with cursors |
| [OrderMatters](samples/OrderMatters.php) | Document order vs typed access |
| [HandlingAny](samples/HandlingAny.php) | xs:any wildcard content |
| [BuiltinTypes](samples/BuiltinTypes.php) | The 46 built-in type wrappers |
| [XmlTree](samples/XmlTree.php) | XML tree visualization |

Run any sample:

```bash
php samples/GettingStarted.php
```

## Documentation

Full documentation is in [`docs/guide/`](docs/guide/):

- [Getting Started](docs/guide/GettingStarted.md) — Introduction and tutorial
- [Schema Type System](docs/guide/SchemaTypeSystem.md) — Type system architecture
- [Generated Types](docs/guide/GeneratedTypes.md) — Schema-to-PHP mapping
- [Built-in Types](docs/guide/BuiltinTypes.md) — All 46 XSD type mappings
- [Validation](docs/guide/Validation.md) — Validation and error handling
- [Cursor Navigation](docs/guide/CursorNavigation.md) — XmlCursor usage
- [XPath/XQuery](docs/guide/XPathXQuery.md) — XPath query system
- [XML Tokens](docs/guide/XmlTokens.md) — Token types reference
- [Bookmarks](docs/guide/Bookmarks.md) — Position bookmarks
- [Handling xs:any](docs/guide/HandlingAny.md) — Wildcard content
- [Tools](docs/guide/Tools.md) — Command-line tools

## Running Tests

```bash
php tests/run_tests.php
```

## The Original

Apache XMLBeans was created for Java and became an Apache Top-Level Project. It powered enterprise Java XML processing for over a decade. This PHP port brings the same architecture — schema compilation, typed binding, cursor navigation, XPath integration — to the PHP ecosystem, where nothing comparable exists.

## License

Apache-2.0 (matching the original Apache XMLBeans project)
