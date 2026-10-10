# Getting Started with PHP XMLBeans

*Adapted from the [Apache XMLBeans Getting Started Guide](https://xmlbeans.apache.org/guide/GettingStarted.html)*

## Introduction

PHP XMLBeans gives PHP developers object-style access to XML while keeping the native XML structure intact. An entire XML document is handled as one unit and stored in memory as XML, so document order and whitespace are preserved.

Unlike SimpleXML or DOMDocument which treat XML as a generic tree, XMLBeans understands your schema and provides validated, typed access to XML data.

## The Schema as the Starting Point

An XSD (XML Schema Definition) file defines the rules other XML documents must follow. An XML *instance* is a document that conforms to those rules.

You compile an XSD to create a type system that PHP XMLBeans uses to validate and provide structured access to XML data. The schema enforces ordering, value constraints, and type rules that plain PHP does not enforce without custom code.

### Purchase Order Example

Consider this purchase order XML:

```xml
<purchase-order xmlns="http://openuri.org/easypo">
    <customer>
        <name>Gladys Kravitz</name>
        <address>Anytown, PA</address>
    </customer>
    <date>2003-01-12T00:00:00</date>
    <line-item>
        <description>Burnham's Celestial Handbook, Vol 1</description>
        <per-unit-ounces>5</per-unit-ounces>
        <price>21.79</price>
        <quantity>2</quantity>
    </line-item>
    <line-item>
        <description>Burnham's Celestial Handbook, Vol 2</description>
        <per-unit-ounces>5</per-unit-ounces>
        <price>19.89</price>
        <quantity>2</quantity>
    </line-item>
    <shipper>
        <name>ZipShip</name>
        <per-ounce-rate>0.74</per-ounce-rate>
    </shipper>
</purchase-order>
```

And this schema (`easypo.xsd`):

```xml
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema"
           targetNamespace="http://openuri.org/easypo"
           xmlns:po="http://openuri.org/easypo"
           elementFormDefault="qualified">

    <xs:element name="purchase-order">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="customer" type="po:customer"/>
                <xs:element name="date" type="xs:dateTime"/>
                <xs:element name="line-item" type="po:line-item"
                            maxOccurs="unbounded"/>
                <xs:element name="shipper" type="po:shipper"/>
            </xs:sequence>
        </xs:complexType>
    </xs:element>

    <xs:complexType name="customer">
        <xs:sequence>
            <xs:element name="name" type="xs:string"/>
            <xs:element name="address" type="xs:string"/>
        </xs:sequence>
    </xs:complexType>

    <xs:complexType name="line-item">
        <xs:sequence>
            <xs:element name="description" type="xs:string"/>
            <xs:element name="per-unit-ounces" type="xs:decimal"/>
            <xs:element name="price" type="xs:decimal"/>
            <xs:element name="quantity" type="xs:int"/>
        </xs:sequence>
    </xs:complexType>

    <xs:complexType name="shipper">
        <xs:sequence>
            <xs:element name="name" type="xs:string"/>
            <xs:element name="per-ounce-rate" type="xs:decimal"/>
        </xs:sequence>
    </xs:complexType>
</xs:schema>
```

### Parsing and Querying

```php
use XmlBeans\XmlBeans;

// Parse the XML
$doc = XmlBeans::parse($xml);

// Query with XPath
$items = $doc->selectPath('//*[local-name()="line-item"]');
echo "Found " . count($items) . " line items\n";

foreach ($items as $item) {
    $desc = $item->selectPathString('*[local-name()="description"]');
    $price = $item->selectPathNumber('*[local-name()="price"]');
    $qty = (int) $item->selectPathNumber('*[local-name()="quantity"]');
    echo "  $desc: $qty x \$$price\n";
}

// Scalar accessors
$total = $doc->selectPathNumber('sum(//*[local-name()="price"])');
echo "Total: $" . number_format($total, 2) . "\n";
```

### Schema Compilation and Validation

```php
// Compile the schema
$typeSystem = XmlBeans::compileXsd($xsd);

// Validate
$isValid = XmlBeans::validate($xml, $xsd);
echo "Valid: " . ($isValid ? "YES" : "NO") . "\n";
```

### Creating New XML

```php
$doc = XmlBeans::newDocument('purchase-order', 'http://openuri.org/easypo');
$cursor = $doc->newCursor();
$cursor->toFirstChild();

$cursor->beginElement('customer');
$cursor->beginElement('name');
$cursor->insertChars('Jane Smith');
$cursor->toParent();
$cursor->toEndToken();
$cursor->beginElement('address');
$cursor->insertChars('123 Main St');
$cursor->toParent();
$cursor->toParent();
```

## XMLBeans Type Hierarchy

`XmlObject` is the base type for all XMLBeans types. Below it are:

- **Built-in types**: 46 types mirroring the XSD built-in types (`XmlString`, `XmlInt`, `XmlBoolean`, etc.)
- **User-defined types**: Types created by compiling a schema

All types can be introspected through the schema type system API.

## Built-In Type Support

PHP XMLBeans provides 46 type wrappers that mirror the 46 XML Schema built-in types. `XmlObject` corresponds to `xs:anyType`.

```php
$ts = XmlBeans::getBuiltinTypeSystem();
$stringType = XmlBeans::getBuiltinType('string');
$intType = XmlBeans::getBuiltinType('int');
```

## Using XPath

XPath is the primary query language in PHP XMLBeans. The library provides multiple ways to run queries:

```php
// On XmlObject
$results = $doc->selectPath('//line-item');

// Scalar accessors
$name = $doc->selectPathString('//customer/name');
$total = $doc->selectPathNumber('sum(//price)');
$exists = $doc->selectPathBoolean('//shipper');

// XPathEngine for advanced queries
$engine = XPathEngine::fromXml($xml);
$items = $engine->selectTyped('//line-item');

// XPathBuilder fluent API
$xpath = XPathBuilder::create()
    ->descendant('line-item')
    ->where(XPathBuilder::elem('price')->gt(20))
    ->build();
```

See the [XPath/XQuery Guide](XPathXQuery.md) for full details.

## Using XML Cursors

`XmlCursor` offers fine-grained navigation and manipulation. It is useful for building XML, handling mixed content, and processing documents without a schema.

```php
$cursor = $doc->newCursor();
$cursor->toFirstChild();  // move to root element
$cursor->toChild('customer', 'http://openuri.org/easypo');
$cursor->toFirstChild();  // move to <name>
echo $cursor->getTextValue();  // "Gladys Kravitz"
```

See the [Cursor Navigation Guide](CursorNavigation.md) for full details.

## Where to Go Next

- [Schema Type System](SchemaTypeSystem.md) — Understanding the type system
- [Built-in Types](BuiltinTypes.md) — All 46 XSD type mappings
- [Validation](Validation.md) — Validating XML against schemas
- [Cursor Navigation](CursorNavigation.md) — Fine-grained XML manipulation
- [XPath/XQuery](XPathXQuery.md) — Query expressions
- [XML Tokens](XmlTokens.md) — Token types in cursor traversal
- [Bookmarks](Bookmarks.md) — Annotating cursor positions
- [Handling xs:any](HandlingAny.md) — Working with wildcard content
- [Tools](Tools.md) — Command-line tools
