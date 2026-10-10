<?php
/**
 * HandlingAny Sample
 *
 * Demonstrates how to handle xs:any (wildcard) content in XML documents.
 * Wildcard content allows elements from other namespaces to appear in
 * schema-governed documents. Since these elements are not defined in the
 * schema, you must use cursor or XPath access rather than typed access.
 *
 * Ported from: https://xmlbeans.apache.org/samples/Any.html
 */

require_once __DIR__ . '/../vendor/autoload.php';

use XmlBeans\XmlBeans;
use XmlBeans\Cursor\XmlCursor;

echo "=== PHP XMLBeans Handling xs:any Sample ===\n\n";

// --- 1. Parse a document with wildcard content ---
echo "--- 1. Document with wildcard content ---\n";

$xml = file_get_contents(__DIR__ . '/data/wildcard.xml');
$doc = XmlBeans::parse($xml);

echo "Parsed document with content from multiple namespaces.\n";
echo "Full XML:\n" . $doc->xmlText() . "\n";

// --- 2. Accessing known elements via typed access ---
echo "\n--- 2. Typed access to known elements ---\n";

$nameNodes = $doc->selectPath('//*[local-name()="name"]');
if (count($nameNodes) > 0) {
    echo "Document name: " . $nameNodes[0]->getStringValue() . "\n";
}

// --- 3. Discovering wildcard content via cursor ---
echo "\n--- 3. Cursor access to wildcard content ---\n";

$cursor = $doc->newCursor();
$cursor->toFirstChild(); // <root>

echo "Walking all elements in document:\n";
while ($cursor->toNextToken() !== XmlCursor::TOKEN_NONE) {
    if ($cursor->currentTokenType() === XmlCursor::TOKEN_START) {
        $name = $cursor->getName();
        $ns = $name->getNamespaceURI();
        $local = $name->getLocalPart();

        if ($ns !== '' && $ns !== 'http://example.com/wildcard') {
            echo "  [wildcard] {" . $ns . "}" . $local;
        } else {
            echo "  [known]    " . $local;
        }

        // Check for attributes
        $attrText = $cursor->getAttributeText('type');
        if ($attrText !== null) {
            echo " (type=\"" . $attrText . "\")";
        }

        // If it's a leaf, show text content
        $subCursor = clone $cursor;
        $subCursor->toNextToken();
        if ($subCursor->currentTokenType() === XmlCursor::TOKEN_TEXT) {
            echo " = \"" . $subCursor->getTextValue() . "\"";
        }

        echo "\n";
    }
}

// --- 4. XPath access to wildcard elements ---
echo "\n--- 4. XPath access to extension elements ---\n";

// Find all elements from the extensions namespace
$extElements = $doc->selectPath('//*[namespace-uri()="http://example.com/extensions"]');
echo "Found " . count($extElements) . " extension elements:\n";
foreach ($extElements as $elem) {
    $cursor2 = $elem->newCursor();
    $name = $cursor2->getName()->getLocalPart();
    $text = $elem->getStringValue();
    echo "  ext:" . $name . " = \"" . trim($text) . "\"\n";
}

// --- 5. Extracting nested wildcard content ---
echo "\n--- 5. Extracting nested wildcard content ---\n";

$metadata = $doc->selectPath('//*[local-name()="metadata"]');
if (count($metadata) > 0) {
    echo "Metadata section found:\n";
    $children = $metadata[0]->selectPath('*');
    foreach ($children as $child) {
        $childCursor = $child->newCursor();
        $childName = $childCursor->getName()->getLocalPart();
        $childValue = trim($child->getStringValue());
        echo "  " . $childName . ": " . $childValue . "\n";
    }
}

// --- 6. Selectively extracting tags ---
echo "\n--- 6. Extracting specific wildcard elements ---\n";

$tags = $doc->selectPath('//*[local-name()="tag"]');
echo "Tags: ";
$tagValues = array();
foreach ($tags as $tag) {
    $tagValues[] = $tag->getStringValue();
}
echo implode(', ', $tagValues) . "\n";

// --- 7. Building a document with wildcard content ---
echo "\n--- 7. Building XML with wildcard content ---\n";

$buildDoc = XmlBeans::parse('<config xmlns="http://example.com/app"/>');
$bc = $buildDoc->newCursor();
$bc->toFirstChild(); // <config>

// Add known elements
$bc->beginElement('name');
$bc->insertChars('My Application');
$bc->toParent();

// Add wildcard extension content
$bc->toEndToken();
$bc->beginElement('plugin', 'http://example.com/plugins');
$bc->insertAttributeWithValue('id', 'analytics');
$bc->insertChars('Google Analytics');
$bc->toParent();

$bc->toEndToken();
$bc->beginElement('plugin', 'http://example.com/plugins');
$bc->insertAttributeWithValue('id', 'auth');
$bc->insertChars('OAuth2 Provider');
$bc->toParent();

echo "Built document:\n" . $buildDoc->xmlText() . "\n";

echo "\nDone.\n";
