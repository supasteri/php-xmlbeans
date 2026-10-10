# Handling xs:any

*Adapted from the [Apache XMLBeans Handling Any Guide](https://xmlbeans.apache.org/guide/HandlingAny.html)*

## Overview

`xs:any` (wildcard) allows elements from other namespaces to appear in schema-governed documents. Since wildcard elements are not defined in the schema, there is no typed access — no `getAny()` or `setAny()` method. Instead, you use cursor or XPath access.

## Approaches

PHP XMLBeans provides four approaches for handling wildcard content:

### 1. XmlCursor

Use cursor-based navigation to discover and build wildcard content:

```php
use XmlBeans\XmlBeans;
use XmlBeans\Cursor\XmlCursor;

$doc = XmlBeans::parse($xml);
$cursor = $doc->newCursor();
$cursor->toFirstChild(); // root element

// Walk all tokens to find wildcard content
while ($cursor->toNextToken() !== XmlCursor::TOKEN_NONE) {
    if ($cursor->currentTokenType() === XmlCursor::TOKEN_START) {
        $name = $cursor->getName();
        $ns = $name->getNamespaceURI();

        if ($ns === 'http://example.com/extensions') {
            // This is wildcard content from the extensions namespace
            echo "Extension element: " . $name->getLocalPart() . "\n";
        }
    }
}
```

#### Building Wildcard Content

```php
$doc = XmlBeans::parse('<config xmlns="http://example.com/app"/>');
$cursor = $doc->newCursor();
$cursor->toFirstChild();

// Add wildcard extension content
$cursor->toEndToken();
$cursor->beginElement('plugin', 'http://example.com/plugins');
$cursor->insertAttributeWithValue('id', 'analytics');
$cursor->insertChars('Google Analytics');
$cursor->toParent();
```

### 2. selectPath (XPath)

Use XPath to locate wildcard elements by namespace or name:

```php
// Find all elements from the extensions namespace
$extElements = $doc->selectPath(
    '//*[namespace-uri()="http://example.com/extensions"]'
);

foreach ($extElements as $elem) {
    echo $elem->getStringValue() . "\n";
}

// Find specific wildcard elements by local name
$metadata = $doc->selectPath('//*[local-name()="metadata"]');
```

### 3. selectChildren

Retrieve child elements by name, including wildcard children:

```php
$cursor = $doc->newCursor();
$cursor->toFirstChild();

$children = $cursor->selectChildren(
    'plugin',
    'http://example.com/plugins'
);

foreach ($children as $child) {
    echo $child->getName()->getLocalPart() . "\n";
}
```

### 4. DOM Access

Access the underlying DOM for fine-grained manipulation:

```php
$doc = XmlBeans::parse($xml);
// Use DOMDocument methods through the parsed document
```

## Example: Discovering Wildcard Content

A common pattern is to walk the document and classify elements as known (defined in the schema) or wildcard (from other namespaces):

```php
$doc = XmlBeans::parse($xml);
$cursor = $doc->newCursor();
$cursor->toFirstChild();

$knownNs = 'http://example.com/myapp';

while ($cursor->toNextToken() !== XmlCursor::TOKEN_NONE) {
    if ($cursor->currentTokenType() === XmlCursor::TOKEN_START) {
        $name = $cursor->getName();
        $ns = $name->getNamespaceURI();

        if ($ns !== '' && $ns !== $knownNs) {
            // Wildcard element
            echo "[wildcard] {" . $ns . "}" . $name->getLocalPart();
        } else {
            // Known element
            echo "[known]    " . $name->getLocalPart();
        }

        // Show text content if it's a leaf
        $sub = clone $cursor;
        $sub->toNextToken();
        if ($sub->currentTokenType() === XmlCursor::TOKEN_TEXT) {
            echo " = \"" . $sub->getTextValue() . "\"";
        }
        echo "\n";
    }
}
```

## See Also

- [Cursor Navigation](CursorNavigation.md) — Full cursor usage guide
- [XPath/XQuery](XPathXQuery.md) — Querying wildcard content with XPath
- [Handling Any Sample](../samples/HandlingAny.php) — Working example
