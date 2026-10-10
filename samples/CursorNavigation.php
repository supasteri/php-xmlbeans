<?php
/**
 * Cursor Navigation Sample
 *
 * Demonstrates XmlCursor for fine-grained XML navigation and editing:
 * - Token-by-token traversal
 * - Moving between elements, attributes, text
 * - Reading and writing values
 * - Inserting and removing elements
 * - Bookmarks
 * - Push/pop cursor position
 *
 * Ported from: https://xmlbeans.apache.org/guide/CursorNavigation.html
 */

require_once __DIR__ . '/../vendor/autoload.php';

use XmlBeans\XmlBeans;
use XmlBeans\Cursor\XmlCursor;

echo "=== PHP XMLBeans Cursor Navigation Sample ===\n\n";

$xml = file_get_contents(__DIR__ . '/data/purchase-order.xml');
$doc = XmlBeans::parse($xml);

// --- 1. Basic navigation ---
echo "--- 1. Basic cursor navigation ---\n";

$cursor = $doc->newCursor();
echo "Token type at start: " . XmlCursor::tokenTypeName($cursor->currentTokenType()) . "\n";

$cursor->toFirstChild(); // move to document element
echo "Moved to first child: " . $cursor->getName()->getLocalPart() . "\n";
echo "Token type: " . XmlCursor::tokenTypeName($cursor->currentTokenType()) . "\n";

// Navigate to customer
$cursor->toChild('customer', 'http://openuri.org/easypo');
echo "\nAt customer element\n";

// Navigate to customer/name
$cursor->toFirstChild();
echo "First child of customer: " . $cursor->getName()->getLocalPart() . "\n";
echo "Text value: " . $cursor->getTextValue() . "\n";

// Move to sibling (address)
$cursor->toNextSibling();
echo "Next sibling: " . $cursor->getName()->getLocalPart() . "\n";
echo "Text value: " . $cursor->getTextValue() . "\n";

// --- 2. Token-level traversal ---
echo "\n--- 2. Token-level traversal ---\n";

$simpleXml = '<root><a>text1</a><b x="1">text2</b></root>';
$simpleDoc = XmlBeans::parse($simpleXml);
$tc = $simpleDoc->newCursor();

$tokenCount = 0;
do {
    $type = $tc->currentTokenType();
    $typeName = XmlCursor::tokenTypeName($type);
    $info = "  Token " . (++$tokenCount) . ": " . $typeName;

    if ($type === XmlCursor::TOKEN_START) {
        $info .= " <" . $tc->getName()->getLocalPart() . ">";
    } elseif ($type === XmlCursor::TOKEN_ATTR) {
        $info .= " " . $tc->getName()->getLocalPart() . "=\"" . $tc->getTextValue() . "\"";
    } elseif ($type === XmlCursor::TOKEN_TEXT) {
        $info .= " \"" . $tc->getTextValue() . "\"";
    }

    echo $info . "\n";
} while ($tc->toNextToken() !== XmlCursor::TOKEN_NONE);

// --- 3. Reading and writing values ---
echo "\n--- 3. Reading and writing values ---\n";

$doc2 = XmlBeans::parse($xml);
$c2 = $doc2->newCursor();
$c2->toFirstChild();

// Read an attribute (if present)
$c2->toChild('line-item', 'http://openuri.org/easypo');
echo "At: " . $c2->getName()->getLocalPart() . "\n";

// Navigate to price and modify it
if ($c2->toChild('price', 'http://openuri.org/easypo')) {
    echo "Original price: " . $c2->getTextValue() . "\n";
    $c2->setTextValue('25.99');
    echo "Modified price: " . $c2->getTextValue() . "\n";
}

// --- 4. Inserting elements ---
echo "\n--- 4. Inserting new elements ---\n";

$buildXml = '<inventory/>';
$buildDoc = XmlBeans::parse($buildXml);
$bc = $buildDoc->newCursor();
$bc->toFirstChild(); // <inventory>

// Insert child elements using beginElement
$bc->beginElement('item');
$bc->insertAttributeWithValue('sku', 'WDG-001');
$bc->insertChars('Premium Widget');
$bc->toParent();

echo "After insert:\n" . $buildDoc->xmlText() . "\n";

// --- 5. Bookmarks ---
echo "\n--- 5. Bookmarks ---\n";

$doc3 = XmlBeans::parse($xml);
$bk = $doc3->newCursor();
$bk->toFirstChild(); // root

// Set a bookmark at the root
$bk->setBookmark('root-position');
echo "Set bookmark at: " . $bk->getName()->getLocalPart() . "\n";

// Navigate deeper
$bk->toChild('line-item', 'http://openuri.org/easypo');
echo "Moved to: " . $bk->getName()->getLocalPart() . "\n";

// Jump back to bookmark
$bk->toBookmark('root-position');
echo "After toBookmark: " . $bk->getName()->getLocalPart() . "\n";

// --- 6. Push/pop ---
echo "\n--- 6. Push/pop cursor position ---\n";

$doc4 = XmlBeans::parse($xml);
$pp = $doc4->newCursor();
$pp->toFirstChild(); // root

// Push current position
$pp->push();
echo "Pushed at: " . $pp->getName()->getLocalPart() . "\n";

// Navigate somewhere else
$pp->toChild('shipper', 'http://openuri.org/easypo');
echo "Moved to: " . $pp->getName()->getLocalPart() . "\n";

// Pop back
$pp->pop();
echo "Popped back to: " . $pp->getName()->getLocalPart() . "\n";

// Nested push/pop
$pp->push(); // push root
$pp->toChild('customer', 'http://openuri.org/easypo');
$pp->push(); // push customer
$pp->toFirstChild(); // name
echo "\nNested - at: " . $pp->getName()->getLocalPart() . "\n";
$pp->pop(); // back to customer
echo "Pop 1 - at: " . $pp->getName()->getLocalPart() . "\n";
$pp->pop(); // back to root
echo "Pop 2 - at: " . $pp->getName()->getLocalPart() . "\n";

// --- 7. Removing XML ---
echo "\n--- 7. Removing XML ---\n";

$doc5 = XmlBeans::parse('<list><a>1</a><b>2</b><c>3</c></list>');
echo "Before: " . $doc5->xmlText() . "\n";

$rc = $doc5->newCursor();
$rc->toFirstChild(); // <list>
$rc->toChild('b');   // <b>
$rc->removeXml();

echo "After removing <b>: " . $doc5->xmlText() . "\n";

echo "\nDone.\n";
