# XPath and XQuery

*Adapted from the [Apache XMLBeans XPath/XQuery Guide](https://xmlbeans.apache.org/guide/XPathXQuery.html)*

## Overview

PHP XMLBeans provides a comprehensive XPath subsystem for querying XML documents. The library offers multiple approaches ranging from simple one-line queries to fluent builders and compiled expressions.

## Query Methods

### XmlObject.selectPath (XPath)

The most common way to query XML. Returns an array of `XmlObject` instances:

```php
use XmlBeans\XmlBeans;

$doc = XmlBeans::parse($xml);

// Select elements
$items = $doc->selectPath('//*[local-name()="line-item"]');
echo count($items) . " items found\n";

// Results are live views into the source document
foreach ($items as $item) {
    echo $item->getStringValue() . "\n";
}
```

### Scalar Accessors

Convenient methods for extracting single values:

```php
// Get a string value
$name = $doc->selectPathString('//*[local-name()="customer"]/*[local-name()="name"]');

// Get a numeric value
$total = $doc->selectPathNumber('sum(//*[local-name()="price"])');

// Get a boolean
$hasShipper = $doc->selectPathBoolean('//*[local-name()="shipper"]');
```

### Nested Queries

Query within the context of a result:

```php
$items = $doc->selectPath('//*[local-name()="line-item"]');
foreach ($items as $item) {
    $desc = $item->selectPathString('*[local-name()="description"]');
    $price = $item->selectPathNumber('*[local-name()="price"]');
    echo "$desc: \$$price\n";
}
```

## XPathEngine

For more advanced queries with typed results, namespace management, and custom functions:

```php
use XmlBeans\XPath\XPathEngine;
use XmlBeans\XPath\XPathNamespaceContext;

$engine = XPathEngine::fromXml($xml);

// Register namespaces
$ctx = new XPathNamespaceContext();
$ctx->registerNamespace('po', 'http://openuri.org/easypo');
$engine->setNamespaceContext($ctx);

// Typed results
$items = $engine->selectTyped('//po:line-item');

// First result only
$first = $engine->selectFirst('//po:line-item');

// String array
$names = $engine->selectStrings('//po:line-item/po:description');

// Count and existence
$count = $engine->count('//po:line-item');
$exists = $engine->exists('//po:shipper');
```

## XPathBuilder — Fluent API

Build XPath expressions programmatically with a fluent interface:

```php
use XmlBeans\XPath\XPathBuilder;

// //line-item/description/text()
$xpath = XPathBuilder::create()
    ->descendant('line-item')
    ->child('description')
    ->text()
    ->build();

// //line-item[1]/price
$xpath = XPathBuilder::create()
    ->descendant('line-item')
    ->first()
    ->child('price')
    ->build();

// //line-item[price > 20]
$xpath = XPathBuilder::create()
    ->descendant('line-item')
    ->where(XPathBuilder::elem('price')->gt(20))
    ->build();

// //line-item[price > 15 and quantity >= 2]
$xpath = XPathBuilder::create()
    ->descendant('line-item')
    ->where(
        XPathBuilder::elem('price')->gt(15)
            ->andPred(XPathBuilder::elem('quantity')->gte(2))
    )
    ->build();

// //line-item[contains(description, 'Vol 1')]
$xpath = XPathBuilder::create()
    ->descendant('line-item')
    ->where(XPathBuilder::elem('description')->contains('Vol 1'))
    ->build();

// //customer/name | //shipper/name
$xpath = XPathBuilder::create()
    ->descendant('customer')
    ->child('name')
    ->union(
        XPathBuilder::create()
            ->descendant('shipper')
            ->child('name')
    )
    ->build();
```

### Namespace-Aware Queries

```php
// /po:purchase-order/po:line-item[last()]
$xpath = XPathBuilder::create()
    ->root()
    ->nsChild('po', 'purchase-order')
    ->nsChild('po', 'line-item')
    ->last()
    ->build();
```

### Axis Support

```php
// Following sibling
$xpath = XPathBuilder::create()
    ->descendant('line-item')
    ->first()
    ->followingSibling('line-item')
    ->build();
```

## Compiled XPathExpression

Compile XPath expressions with variable binding for reuse:

```php
use XmlBeans\XPath\XPathExpression;

$expr = XPathExpression::compile(
    '//*[local-name()="line-item"][*[local-name()="price"] > $minPrice]'
);

// Inspect the expression
echo $expr->getExpression() . "\n";
echo $expr->hasVariables() ? "Has variables\n" : "";
echo "Variables: " . implode(', ', $expr->getVariableNames()) . "\n";
echo $expr->isAbsolute() ? "Absolute path\n" : "";
echo $expr->hasPredicate() ? "Has predicates\n" : "";

// Bind variables and execute
$expr->bindVariable('minPrice', 20);
$normalized = $expr->getNormalizedExpression();
$results = $engine->selectTyped($normalized);

// Rebind with different value
$expr->clearVariables();
$expr->bindVariable('minPrice', 15);
$results2 = $engine->selectTyped($expr->getNormalizedExpression());

// Expressions are cached
echo XPathExpression::getCacheSize() . " cached expressions\n";
```

## Custom PHP Functions

Register PHP functions for use in XPath queries:

```php
$engine = XPathEngine::fromXml($xml);

$engine->registerFunction('discount', function ($price, $pct) {
    return round((float)$price * (1 - (float)$pct / 100), 2);
});

$engine->registerFunction('taxed', function ($price, $rate) {
    return round((float)$price * (1 + (float)$rate / 100), 2);
});

echo implode(', ', $engine->getRegisteredFunctions());
```

## XPathNamespaceContext

Manage namespace prefix-to-URI mappings:

```php
use XmlBeans\XPath\XPathNamespaceContext;

// Manual registration
$ctx = new XPathNamespaceContext();
$ctx->registerNamespace('po', 'http://openuri.org/easypo');
echo $ctx->getNamespaceUri('po');

// Auto-detect from a document
$dom = new DOMDocument();
$dom->loadXML($xml);
$autoCtx = new XPathNamespaceContext();
$autoCtx->loadFromDocument($dom);
foreach ($autoCtx->getAllNamespaces() as $prefix => $uri) {
    echo "$prefix => $uri\n";
}

// Pre-defined XML Schema namespaces
$schemaCtx = new XPathNamespaceContext();
$schemaCtx->registerSchemaNamespaces();
```

## Cursor-Based XPath

Run XPath from a cursor position:

```php
$cursor = $doc->newCursor();
$cursor->toFirstChild(); // position at root element

// XPath from cursor position
$results = $cursor->selectPath('descendant::*[local-name()="line-item"]');
foreach ($results as $result) {
    echo $result->getName()->getLocalPart() . "\n";
}

// Select children by name
$children = $cursor->selectChildren('line-item', 'http://openuri.org/easypo');
```

## Namespace Handling in Queries

XPath has no built-in syntax for binding namespace prefixes. PHP XMLBeans handles this through:

1. **Local-name queries**: `*[local-name()="element"]` — works without namespace setup
2. **XPathNamespaceContext**: Register prefixes, then use `po:element` syntax
3. **XPathEngine context**: Set a namespace context on the engine

For documents with namespaces, the local-name approach is simplest:

```php
$items = $doc->selectPath('//*[local-name()="line-item"]');
```

For repeated queries on namespaced documents, register the namespaces:

```php
$engine = XPathEngine::fromXml($xml);
$ctx = new XPathNamespaceContext();
$ctx->registerNamespace('po', 'http://openuri.org/easypo');
$engine->setNamespaceContext($ctx);

$items = $engine->selectTyped('//po:line-item');
```

## See Also

- [Getting Started](GettingStarted.md) — Introduction with XPath examples
- [Cursor Navigation](CursorNavigation.md) — Cursor-based queries
- [XPath/XQuery Sample](../samples/XPathQuery.php) — Comprehensive working example
