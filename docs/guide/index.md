# PHP XMLBeans Documentation

*Ported from the [Apache XMLBeans Documentation](https://xmlbeans.apache.org/documentation/index.html)*

This documentation covers the PHP port of Apache XMLBeans, adapted for PHP 7.2+.

## Getting Started

- **[Getting Started](GettingStarted.md)** — Introduction to PHP XMLBeans: parsing, querying, cursors, validation, and building XML

## User Guide

| Guide | Description |
|---|---|
| [Schema Type System](SchemaTypeSystem.md) | Understanding the type system, type signatures, and schema introspection |
| [Generated Types](GeneratedTypes.md) | How schema types map to PHP XMLBeans objects |
| [Built-in Types](BuiltinTypes.md) | All 46 XSD built-in type mappings to PHP |
| [Validation](Validation.md) | Validating XML against schemas with error reporting |
| [Cursor Navigation](CursorNavigation.md) | Fine-grained XML traversal and editing with XmlCursor |
| [XPath / XQuery](XPathXQuery.md) | XPath queries, builder API, compiled expressions, custom functions |
| [XML Tokens](XmlTokens.md) | Token types used in cursor traversal |
| [Bookmarks](Bookmarks.md) | Annotating positions in XML for later navigation |
| [Handling xs:any](HandlingAny.md) | Working with wildcard content |
| [Tools](Tools.md) | Command-line tools and programmatic API |

## Samples

Working PHP code examples are in the [`samples/`](../../samples/) directory:

| Sample | Description |
|---|---|
| [GettingStarted](../../samples/GettingStarted.php) | End-to-end tutorial |
| [XPathQuery](../../samples/XPathQuery.php) | Comprehensive XPath usage |
| [CursorNavigation](../../samples/CursorNavigation.php) | Cursor-based navigation and editing |
| [Validation](../../samples/Validation.php) | Schema validation |
| [DateTime](../../samples/DateTime.php) | Date, time, and duration types |
| [SchemaEnum](../../samples/SchemaEnum.php) | Schema enumerations |
| [MixedContent](../../samples/MixedContent.php) | Mixed content handling |
| [OrderMatters](../../samples/OrderMatters.php) | Document order effects |
| [HandlingAny](../../samples/HandlingAny.php) | Wildcard content |
| [BuiltinTypes](../../samples/BuiltinTypes.php) | Built-in type wrappers |
| [XmlTree](../../samples/XmlTree.php) | XML tree visualization |

Run any sample from the project root:

```bash
php samples/GettingStarted.php
```

## API Reference

The core classes:

| Class | Description |
|---|---|
| `XmlBeans\XmlBeans` | Main factory — parse, compile, validate, create |
| `XmlBeans\XmlObject` | Base type for all XML objects |
| `XmlBeans\Cursor\XmlCursor` | Token-level cursor navigation |
| `XmlBeans\SchemaType` | Schema type representation |
| `XmlBeans\SchemaTypeSystem` | Container for a compiled schema |
| `XmlBeans\SchemaProperty` | Element/attribute declaration within a type |
| `XmlBeans\SchemaParticle` | Content model (sequence/choice/all) |
| `XmlBeans\Common\QName` | Qualified name (namespace + local name) |
| `XmlBeans\XPath\XPathEngine` | Advanced XPath with typed results |
| `XmlBeans\XPath\XPathBuilder` | Fluent XPath construction |
| `XmlBeans\XPath\XPathExpression` | Compiled XPath with variable binding |
| `XmlBeans\XPath\XPathNamespaceContext` | Namespace prefix management |
| `XmlBeans\XPath\XPathPredicate` | Fluent predicate builder |
| `XmlBeans\Validation\Validator` | XML validation against XSD |
| `XmlBeans\Compiler\SchemaCompiler` | Schema compilation |
| `XmlBeans\Schema\BuiltinTypeSystem` | Built-in type system (46 types) |
| `XmlBeans\XmlOptions` | Configuration options |
| `XmlBeans\XmlError` | Validation/parse error representation |

## License

PHP XMLBeans is licensed under the Apache License 2.0, the same license as the original Apache XMLBeans.
