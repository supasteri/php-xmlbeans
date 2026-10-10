# PHP XMLBeans

A PHP port of [Apache XMLBeans](https://xmlbeans.apache.org/) — an XML-Schema-to-PHP binding framework. Compiles XSD schemas into a type system and generates typed PHP classes with getter/setter accessors, validation, cursor-based navigation, and XPath support.

**Requirements:** PHP >= 7.2, ext-dom, ext-libxml, ext-simplexml, ext-bcmath

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

// Cursor-based navigation
$cursor = $obj->newCursor();
$cursor->toFirstChild(); // <order>
$cursor->toChild('qty');
echo $cursor->getTextValue(); // "5"
$cursor->setTextValue('10');

// Save
$obj->save('/tmp/order.xml');
```

### 2. Compile an XSD schema

```php
use XmlBeans\XmlBeans;

// Compile schema
$typeSystem = XmlBeans::compileXsd('purchase-order.xsd');

// Inspect the type system
$addressType = $typeSystem->findType(new \XmlBeans\Common\QName('http://example.com', 'USAddress'));
foreach ($addressType->getElementProperties() as $prop) {
    echo $prop->getName()->getLocalPart() . ': '
       . ($prop->isRequired() ? 'required' : 'optional') . "\n";
}
```

### 3. Generate typed PHP classes (scomp)

From the command line:

```bash
php bin/scomp -d src/Generated -n App\\Schema -v purchase-order.xsd
```

This generates classes like:

```php
// Generated: src/Generated/USAddress.php
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
    // ...
}
```

Or programmatically:

```php
use XmlBeans\Compiler\SchemaCompiler;
use XmlBeans\Compiler\PhpCodeGenerator;

$compiler = new SchemaCompiler();
$typeSystem = $compiler->compile('schema.xsd');

$generator = new PhpCodeGenerator($typeSystem);
$files = $generator->generate('./src/Generated', 'App\\Schema');
```

### 4. Validate XML

```php
use XmlBeans\XmlBeans;
use XmlBeans\XmlOptions;

// Quick validation against XSD
$valid = XmlBeans::validate($xmlString, $xsdString);

// Validation with error collection
$obj = XmlBeans::parse($xml);
$obj->setSchemaType($myType);

$errors = [];
$opts = (new XmlOptions())->setErrorCollection($errors);
if (!$obj->validate($opts)) {
    foreach ($errors as $err) {
        echo $err . "\n"; // "[element:5] error: Value 'xyz' not in enumeration"
    }
}

// Error listener callback
$opts->addErrorListener(function(\XmlBeans\XmlError $err) {
    log_validation_error($err->getMessage(), $err->getLine());
});
```

### 5. Use typed simple types

```php
use XmlBeans\Type;

$xml = '<price>19.99</price>';
$obj = XmlBeans::parse($xml);
$price = new Type\XmlDecimal($obj->getDomNode()->documentElement);
echo $price->getValue(); // float: 19.99

$date = new Type\XmlDate($dateNode);
$dt = $date->getValue(); // \DateTime instance

$bool = new Type\XmlBoolean($flagNode);
echo $bool->getValue(); // true/false
```

### 6. XmlCursor — fine-grained navigation

```php
$cursor = $obj->newCursor();

// Navigate
$cursor->toFirstChild();
$cursor->toNextSibling();
$cursor->toChild('address');
$cursor->toParent();
$cursor->toLastChild();

// Read/write
echo $cursor->getName()->getLocalPart();
echo $cursor->getTextValue();
$cursor->setTextValue('new value');
$cursor->getAttributeText('id');
$cursor->setAttributeText('id', '42');

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

## Running Tests

```bash
php tests/run_tests.php
```

## License

Apache-2.0 (matching the original Apache XMLBeans project)
