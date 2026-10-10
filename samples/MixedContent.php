<?php
/**
 * MixedContent Sample
 *
 * Demonstrates handling XML with mixed content (text interspersed with elements)
 * using XmlCursor. With typed access you can only get/set the full text value;
 * cursors let you navigate the individual text and element pieces.
 *
 * Ported from: https://xmlbeans.apache.org/samples/MixedContent.html
 */

require_once __DIR__ . '/../vendor/autoload.php';

use XmlBeans\XmlBeans;
use XmlBeans\Cursor\XmlCursor;

echo "=== PHP XMLBeans Mixed Content Sample ===\n\n";

$xml = file_get_contents(__DIR__ . '/data/mixed-content.xml');
$doc = XmlBeans::parse($xml);

// --- 1. Typed access — limited for mixed content ---
echo "--- 1. Typed access (limited for mixed content) ---\n";

$items = $doc->selectPath('//item');
echo "Found " . count($items) . " items\n\n";

foreach ($items as $i => $item) {
    // getStringValue() returns ALL text content concatenated, losing structure
    echo "Item " . ($i + 1) . " full text: " . trim($item->getStringValue()) . "\n";
    echo "  (Note: child element boundaries are lost)\n\n";
}

// --- 2. Cursor access — preserves mixed content structure ---
echo "--- 2. Cursor access (preserves structure) ---\n\n";

$cursor = $doc->newCursor();
$cursor->toFirstChild(); // <inventory>

$itemNum = 0;
// Walk through child elements
while ($cursor->toNextToken() !== XmlCursor::TOKEN_NONE) {
    if ($cursor->currentTokenType() === XmlCursor::TOKEN_START
        && $cursor->getName()->getLocalPart() === 'item') {

        $itemNum++;
        $sku = $cursor->getAttributeText('sku');
        echo "Item " . $itemNum . " (SKU: " . $sku . ") content breakdown:\n";

        // Walk tokens inside this element
        $depth = 1;
        while ($depth > 0 && $cursor->toNextToken() !== XmlCursor::TOKEN_NONE) {
            $type = $cursor->currentTokenType();

            if ($type === XmlCursor::TOKEN_START) {
                $depth++;
                $elemName = $cursor->getName()->getLocalPart();
                // Read the text inside this child element
                $innerCursor = clone $cursor;
                $innerCursor->toNextToken(); // move to text inside
                $innerText = '';
                if ($innerCursor->currentTokenType() === XmlCursor::TOKEN_TEXT) {
                    $innerText = $innerCursor->getTextValue();
                }
                echo "  [element] <" . $elemName . ">: " . $innerText . "\n";
            } elseif ($type === XmlCursor::TOKEN_TEXT) {
                $text = trim($cursor->getTextValue());
                if ($text !== '') {
                    echo "  [text]    \"" . $text . "\"\n";
                }
            } elseif ($type === XmlCursor::TOKEN_END) {
                $depth--;
            }
        }
        echo "\n";
    }
}

// --- 3. Extracting specific inline elements ---
echo "--- 3. Extract specific inline elements via XPath ---\n";

$colors = $doc->selectPath('//color');
echo "Colors found: ";
$colorNames = array();
foreach ($colors as $c) {
    $colorNames[] = $c->getStringValue();
}
echo implode(', ', $colorNames) . "\n";

$bold = $doc->selectPath('//b');
echo "Bold text: ";
$boldTexts = array();
foreach ($bold as $b) {
    $boldTexts[] = $b->getStringValue();
}
echo implode(', ', $boldTexts) . "\n";

echo "\nDone.\n";
