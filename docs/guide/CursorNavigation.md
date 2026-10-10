# Cursor Navigation

*Adapted from the [Apache XMLBeans Cursor Navigation Guide](https://xmlbeans.apache.org/guide/CursorNavigation.html)*

## Overview

`XmlCursor` provides fine-grained, token-level access to XML documents. While `selectPath` and typed access work at the element level, cursors let you navigate every piece of an XML document — elements, attributes, text nodes, comments, and processing instructions.

Cursors are especially useful for:
- Building XML from scratch
- Handling mixed content
- Processing documents without a schema
- Fine-grained document editing

## Creating a Cursor

Call `newCursor()` on any `XmlObject`:

```php
use XmlBeans\XmlBeans;
use XmlBeans\Cursor\XmlCursor;

$doc = XmlBeans::parse('<root><child>text</child></root>');
$cursor = $doc->newCursor();
```

## Token Types

A cursor sees an XML document as a sequence of **tokens**. Each piece of information has a token type:

| Token Type | Constant | Description |
|---|---|---|
| STARTDOC | `TOKEN_STARTDOC` | Start of the XML document |
| ENDDOC | `TOKEN_ENDDOC` | End of the XML document |
| START | `TOKEN_START` | Start of an element |
| END | `TOKEN_END` | End of an element |
| TEXT | `TOKEN_TEXT` | Text content |
| ATTR | `TOKEN_ATTR` | An attribute |
| NAMESPACE | `TOKEN_NAMESPACE` | A namespace declaration |
| COMMENT | `TOKEN_COMMENT` | A comment |
| PROCINST | `TOKEN_PROCINST` | A processing instruction |
| NONE | `TOKEN_NONE` | No token (end of traversal) |

For example, `<root x="1">hello</root>` produces these tokens:

```
STARTDOC
  START <root>
    ATTR x="1"
    TEXT "hello"
  END
ENDDOC
```

### Checking Token Type

```php
$type = $cursor->currentTokenType();

// Compare with constants
if ($type === XmlCursor::TOKEN_START) { ... }

// Get human-readable name
$name = XmlCursor::tokenTypeName($type);  // "START"
```

## Movement Methods

### Element-Level Navigation

```php
$cursor->toFirstChild();     // Move to first child element
$cursor->toNextSibling();    // Move to next sibling element
$cursor->toParent();         // Move to parent element

// Move to a specific child by name
$cursor->toChild('customer');
$cursor->toChild('customer', 'http://example.com/ns');

// Move to document start
$cursor->toStartDoc();
```

### Token-Level Navigation

```php
$cursor->toNextToken();      // Move to next token of any type
```

This moves through every token — START, END, TEXT, ATTR, etc. — giving you complete control over traversal.

### Token-Level Traversal Example

```php
$xml = '<root><a>text1</a><b x="1">text2</b></root>';
$doc = XmlBeans::parse($xml);
$cursor = $doc->newCursor();

do {
    $type = $cursor->currentTokenType();
    $name = XmlCursor::tokenTypeName($type);

    if ($type === XmlCursor::TOKEN_START) {
        echo "$name <" . $cursor->getName()->getLocalPart() . ">\n";
    } elseif ($type === XmlCursor::TOKEN_ATTR) {
        echo "$name " . $cursor->getName()->getLocalPart()
             . '="' . $cursor->getTextValue() . "\"\n";
    } elseif ($type === XmlCursor::TOKEN_TEXT) {
        echo "$name \"" . $cursor->getTextValue() . "\"\n";
    } else {
        echo "$name\n";
    }
} while ($cursor->toNextToken() !== XmlCursor::TOKEN_NONE);
```

Output:
```
STARTDOC
START <root>
START <a>
TEXT "text1"
END
START <b>
ATTR x="1"
TEXT "text2"
END
END
ENDDOC
```

## Reading Values

```php
// Get the name of the current element or attribute
$qname = $cursor->getName();
$localName = $qname->getLocalPart();
$namespace = $qname->getNamespaceURI();

// Get text content
$text = $cursor->getTextValue();

// Get an attribute value by name
$value = $cursor->getAttributeText('id');
$value = $cursor->getAttributeText('id', 'http://example.com/ns');
```

## Writing Values

```php
// Set text content of current element
$cursor->setTextValue('new value');
```

## Building XML

### beginElement / insertChars

```php
$doc = XmlBeans::parse('<inventory/>');
$cursor = $doc->newCursor();
$cursor->toFirstChild(); // <inventory>

// Insert a new child element
$cursor->beginElement('item');
$cursor->insertAttributeWithValue('sku', 'WDG-001');
$cursor->insertChars('Premium Widget');
$cursor->toParent();

// Result: <inventory><item sku="WDG-001">Premium Widget</item></inventory>
```

`beginElement` creates a new element and positions the cursor between its START and END tokens. Attributes and text inserted at that point go into the new element. Call `toParent()` to move back out of the element.

### Building Multiple Elements

```php
$cursor->beginElement('item');
$cursor->insertAttributeWithValue('sku', 'A001');
$cursor->beginElement('name');
$cursor->insertChars('Widget A');
$cursor->toParent();
$cursor->toEndToken();
$cursor->beginElement('price');
$cursor->insertChars('29.99');
$cursor->toParent();
$cursor->toParent(); // close <item>
```

Use `toEndToken()` between sibling elements to position the cursor at the end of the current element, ready for the next `beginElement`.

## push() and pop()

Save and restore cursor positions with a stack:

```php
$cursor->push();                         // Save current position
$cursor->toChild('shipper', $ns);        // Navigate away
echo $cursor->getName()->getLocalPart(); // "shipper"
$cursor->pop();                          // Restore position
echo $cursor->getName()->getLocalPart(); // Back to original
```

Pushes and pops are last-in, first-out. Nested push/pop lets you remember multiple positions:

```php
$cursor->push();                  // Save position A
$cursor->toChild('customer', $ns);
$cursor->push();                  // Save position B
$cursor->toFirstChild();         // Move to name
$cursor->pop();                   // Back to position B (customer)
$cursor->pop();                   // Back to position A (root)
```

Using the stack is cheaper than creating extra cursors to mark positions.

## Bookmarks

Bookmarks let you name positions in the document for later navigation:

```php
// Set a bookmark
$cursor->setBookmark('my-position');

// Navigate elsewhere
$cursor->toChild('shipper', $ns);

// Jump back to the bookmarked position
$cursor->toBookmark('my-position');
```

See the [Bookmarks Guide](Bookmarks.md) for details.

## Removing XML

```php
$doc = XmlBeans::parse('<list><a>1</a><b>2</b><c>3</c></list>');
$cursor = $doc->newCursor();
$cursor->toFirstChild(); // <list>
$cursor->toChild('b');    // <b>
$cursor->removeXml();     // Remove <b> and its contents

// Result: <list><a>1</a><c>3</c></list>
```

## XPath from Cursors

Cursors can also run XPath queries:

```php
$cursor = $doc->newCursor();
$cursor->toFirstChild();

$results = $cursor->selectPath('descendant::*[local-name()="item"]');
foreach ($results as $result) {
    echo $result->getName()->getLocalPart() . "\n";
}

// Or select children directly
$children = $cursor->selectChildren('item', 'http://example.com/ns');
```

## Cloning Cursors

Use `clone` to create an independent copy at the same position:

```php
$subCursor = clone $cursor;
// subCursor can navigate independently
```

## See Also

- [XML Tokens](XmlTokens.md) — Detailed token type reference
- [Bookmarks](Bookmarks.md) — Annotating cursor positions
- [XPath/XQuery](XPathXQuery.md) — Query expressions
