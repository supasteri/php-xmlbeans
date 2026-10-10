# Bookmarks

*Adapted from the [Apache XMLBeans Bookmarks Guide](https://xmlbeans.apache.org/guide/Bookmarks.html)*

## Overview

Bookmarks let you mark positions in an XML document for later navigation. They are not written into the XML itself — they are cursor-level annotations that persist as long as the document exists.

## Setting Bookmarks

Position a cursor where you want to place a bookmark, then call `setBookmark()` with a name:

```php
use XmlBeans\XmlBeans;

$doc = XmlBeans::parse($xml);
$cursor = $doc->newCursor();
$cursor->toFirstChild(); // root element

// Set a named bookmark
$cursor->setBookmark('root-position');
echo "Bookmarked at: " . $cursor->getName()->getLocalPart() . "\n";
```

## Navigating to Bookmarks

Use `toBookmark()` to jump to a previously set bookmark:

```php
// Navigate somewhere else
$cursor->toChild('customer', $ns);
$cursor->toFirstChild();
echo "Now at: " . $cursor->getName()->getLocalPart() . "\n";

// Jump back to the bookmarked position
$cursor->toBookmark('root-position');
echo "Back at: " . $cursor->getName()->getLocalPart() . "\n";
```

## Bookmarks vs Push/Pop

Both bookmarks and push/pop save cursor positions, but they work differently:

| Feature | Bookmarks | Push/Pop |
|---|---|---|
| Named positions | Yes — any name | No — stack only |
| Jump order | Any order | LIFO (last in, first out) |
| Persistence | Until document is freed | Until popped or cursor freed |
| Multiple cursors | Can be shared via name | Per-cursor stack |

Use bookmarks when you need to jump to positions by name or out of order. Use push/pop for simple nested save/restore patterns.

## Example: Bookmarking Multiple Positions

```php
$doc = XmlBeans::parse('<catalog>
    <book id="1"><title>Book A</title></book>
    <book id="2"><title>Book B</title></book>
    <book id="3"><title>Book C</title></book>
</catalog>');

$cursor = $doc->newCursor();
$cursor->toFirstChild(); // <catalog>

// Bookmark the catalog
$cursor->setBookmark('catalog');

// Navigate and bookmark each book
$cursor->toChild('book');
$cursor->setBookmark('book-1');

$cursor->toNextSibling();
$cursor->setBookmark('book-2');

$cursor->toNextSibling();
$cursor->setBookmark('book-3');

// Now jump to any book directly
$cursor->toBookmark('book-2');
echo "At book: " . $cursor->getAttributeText('id') . "\n"; // "2"

$cursor->toBookmark('book-1');
echo "At book: " . $cursor->getAttributeText('id') . "\n"; // "1"

$cursor->toBookmark('catalog');
echo "At: " . $cursor->getName()->getLocalPart() . "\n"; // "catalog"
```

## See Also

- [Cursor Navigation](CursorNavigation.md) — Full cursor usage including push/pop
- [XML Tokens](XmlTokens.md) — Token types in cursor traversal
