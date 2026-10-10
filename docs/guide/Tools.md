# Tools

*Adapted from the [Apache XMLBeans Tools Guide](https://xmlbeans.apache.org/guide/Tools.html)*

## Overview

PHP XMLBeans provides command-line tools for working with schemas and XML documents. The primary tool is `scomp`, the schema compiler.

## scomp — Schema Compiler

Compiles XSD schemas into PHP XMLBeans type systems.

```bash
php bin/scomp <schema.xsd> [options]
```

### Options

| Option | Description |
|---|---|
| `-o <dir>` | Output directory for generated files |
| `-n <name>` | Name for the type system |
| `-v` | Verbose output |

### Example

```bash
php bin/scomp purchase-order.xsd -o generated/ -n easypo
```

### Programmatic Compilation

```php
use XmlBeans\XmlBeans;

// Compile from file
$ts = XmlBeans::compileXsd('purchase-order.xsd');

// Compile from string
$ts = XmlBeans::compileXsd($xsdContent);

// Compile multiple files
$ts = XmlBeans::compileXsdFiles(['types.xsd', 'elements.xsd']);
```

## Validation

Validate XML instances against a schema:

```php
// Quick validation
$isValid = XmlBeans::validate($xml, $xsd);

// With error details
$errors = array();
$isValid = Validator::validateXmlAgainstXsd($xml, $xsd, $errors);
```

## PHP XMLBeans Equivalents to Java XMLBeans Tools

The original Apache XMLBeans provides several command-line tools. Here is how PHP XMLBeans covers the same functionality:

| Java Tool | PHP XMLBeans Equivalent |
|---|---|
| **scomp** (Schema Compiler) | `php bin/scomp` or `XmlBeans::compileXsd()` |
| **validate** (Instance Validator) | `XmlBeans::validate()` |
| **svalidate** (Streaming Validator) | `XmlBeans::validate()` (uses DOM internally) |
| **xpretty** (XML Pretty Printer) | `$doc->xmlText()` with formatting options |
| **xsd2inst** (Schema to Instance) | Not yet ported |
| **xsdtree** (Schema Type Hierarchy) | `SchemaTypeSystem::getGlobalTypes()` |
| **inst2xsd** (Instance to Schema) | Not yet ported |
| **dumpxsb** (XSB File Dumper) | N/A (PHP does not use XSB format) |
| **scopy** (Schema Copier) | N/A (use PHP file functions) |
| **sdownload** (Schema Downloader) | N/A (use PHP file functions) |
| **sfactor** (Schema Factoring) | Not yet ported |

## Programmatic Usage

Most functionality is accessed through the PHP API rather than command-line tools:

```php
use XmlBeans\XmlBeans;
use XmlBeans\Validation\Validator;

// Parse
$doc = XmlBeans::parse($xml);
$doc = XmlBeans::parseFile('document.xml');

// Compile
$ts = XmlBeans::compileXsd($xsd);

// Validate
$valid = XmlBeans::validate($xml, $xsd);

// Create
$doc = XmlBeans::newDocument('root', 'http://example.com');

// Query
$results = $doc->selectPath('//element');
$value = $doc->selectPathString('//name');

// Built-in types
$type = XmlBeans::getBuiltinType('string');
$ts = XmlBeans::getBuiltinTypeSystem();
```

## See Also

- [Getting Started](GettingStarted.md) — Introduction and setup
- [Schema Type System](SchemaTypeSystem.md) — Working with compiled schemas
