# XML Tokens

*Adapted from the [Apache XMLBeans XmlTokens Guide](https://xmlbeans.apache.org/guide/XmlTokens.html)*

## Overview

When navigating XML with an `XmlCursor`, the document is represented as a sequence of **tokens**. Each piece of information in the XML — elements, attributes, text, comments — is a token with a specific type.

## Token Types

| Token Type | Constant | Description |
|---|---|---|
| **STARTDOC** | `XmlCursor::TOKEN_STARTDOC` | Marks the beginning of the XML document. Always the first token. |
| **ENDDOC** | `XmlCursor::TOKEN_ENDDOC` | Marks the end of the XML document. Always the last token. |
| **START** | `XmlCursor::TOKEN_START` | Marks the start of an element. The document element is a START token, not STARTDOC. |
| **END** | `XmlCursor::TOKEN_END` | Marks the end of an element. Carries no name or value. |
| **TEXT** | `XmlCursor::TOKEN_TEXT` | Represents text content within an element. |
| **ATTR** | `XmlCursor::TOKEN_ATTR` | Represents an attribute. Only appears immediately after a START or STARTDOC token. |
| **NAMESPACE** | `XmlCursor::TOKEN_NAMESPACE` | Represents an `xmlns` namespace declaration. Only appears after START or STARTDOC. |
| **COMMENT** | `XmlCursor::TOKEN_COMMENT` | Represents an XML comment (`<!-- ... -->`). |
| **PROCINST** | `XmlCursor::TOKEN_PROCINST` | Represents a processing instruction (`<?target data?>`). |
| **NONE** | `XmlCursor::TOKEN_NONE` | No token — returned when `toNextToken()` has no more tokens to visit. |

## Token Layout

For this XML:

```xml
<order id="123">
    <item>Widget</item>
    <item>Gadget</item>
</order>
```

The token sequence is:

```
STARTDOC
  START <order>
    ATTR id="123"
    TEXT "\n    "
    START <item>
      TEXT "Widget"
    END
    TEXT "\n    "
    START <item>
      TEXT "Gadget"
    END
    TEXT "\n"
  END
ENDDOC
```

Key observations:
- STARTDOC and ENDDOC bracket the entire document
- Each element has a matching START and END pair
- Attributes and namespaces appear immediately after START
- Whitespace between elements appears as TEXT tokens

## Checking Token Types

```php
use XmlBeans\Cursor\XmlCursor;

$cursor = $doc->newCursor();

// Get the current token type
$type = $cursor->currentTokenType();

// Compare with constants
if ($type === XmlCursor::TOKEN_START) {
    echo "At element: " . $cursor->getName()->getLocalPart() . "\n";
}

// Get human-readable name
echo XmlCursor::tokenTypeName($type);  // e.g., "START"
```

## Token Traversal Pattern

A common pattern is to walk all tokens with a `do-while` loop:

```php
$cursor = $doc->newCursor();

do {
    $type = $cursor->currentTokenType();

    switch ($type) {
        case XmlCursor::TOKEN_STARTDOC:
            echo "Document start\n";
            break;
        case XmlCursor::TOKEN_START:
            echo "Element: " . $cursor->getName()->getLocalPart() . "\n";
            break;
        case XmlCursor::TOKEN_ATTR:
            echo "Attribute: " . $cursor->getName()->getLocalPart()
                 . " = " . $cursor->getTextValue() . "\n";
            break;
        case XmlCursor::TOKEN_TEXT:
            $text = trim($cursor->getTextValue());
            if ($text !== '') {
                echo "Text: " . $text . "\n";
            }
            break;
        case XmlCursor::TOKEN_END:
            echo "End element\n";
            break;
        case XmlCursor::TOKEN_ENDDOC:
            echo "Document end\n";
            break;
    }
} while ($cursor->toNextToken() !== XmlCursor::TOKEN_NONE);
```

## Token Scope

A cursor's scope is the entire document, regardless of where it was created. Calling `toNextToken()` repeatedly will traverse from the current position all the way to ENDDOC.

## Attributes and Namespaces

ATTR and NAMESPACE tokens can only appear immediately after a START or STARTDOC token. They are not interleaved with child elements or text:

```xml
<person xmlns:addr="http://example.com" name="Alice">
    <addr:city>Portland</addr:city>
</person>
```

Token order:
```
START <person>
  NAMESPACE xmlns:addr="http://example.com"
  ATTR name="Alice"
  START <addr:city>
    TEXT "Portland"
  END
END
```

## See Also

- [Cursor Navigation](CursorNavigation.md) — Full cursor usage guide
- [Bookmarks](Bookmarks.md) — Annotating positions in the token stream
