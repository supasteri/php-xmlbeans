<?php
/**
 * XmlTree Sample
 *
 * Demonstrates building a visual tree representation of an XML document
 * using XmlCursor's token-based traversal. Shows how cursor navigation
 * reveals the full token structure of a document.
 *
 * Ported from: https://xmlbeans.apache.org/samples/XmlTree.html
 */

require_once __DIR__ . '/../vendor/autoload.php';

use XmlBeans\XmlBeans;
use XmlBeans\Cursor\XmlCursor;

echo "=== PHP XMLBeans XmlTree Sample ===\n\n";

/**
 * Print a tree representation of an XML document using cursor traversal.
 *
 * @param XmlBeans\XmlObject $doc
 * @param bool               $showAttributes
 * @param bool               $showText
 */
function printXmlTree($doc, $showAttributes = true, $showText = true)
{
    $cursor = $doc->newCursor();
    $depth = 0;

    do {
        $type = $cursor->currentTokenType();
        $indent = str_repeat('  ', $depth);

        switch ($type) {
            case XmlCursor::TOKEN_STARTDOC:
                echo $indent . "[DOCUMENT]\n";
                $depth++;
                break;

            case XmlCursor::TOKEN_ENDDOC:
                $depth--;
                break;

            case XmlCursor::TOKEN_START:
                $name = $cursor->getName();
                $ns = $name->getNamespaceURI();
                $local = $name->getLocalPart();
                $display = $ns ? '{' . $ns . '}' . $local : $local;
                echo $indent . "<" . $display . ">\n";
                $depth++;
                break;

            case XmlCursor::TOKEN_END:
                $depth--;
                break;

            case XmlCursor::TOKEN_TEXT:
                if ($showText) {
                    $text = trim($cursor->getTextValue());
                    if ($text !== '') {
                        // Truncate long text
                        if (strlen($text) > 50) {
                            $text = substr($text, 0, 47) . '...';
                        }
                        echo $indent . "\"" . $text . "\"\n";
                    }
                }
                break;

            case XmlCursor::TOKEN_ATTR:
                if ($showAttributes) {
                    $attrName = $cursor->getName()->getLocalPart();
                    $attrVal = $cursor->getTextValue();
                    echo $indent . "@" . $attrName . "=\"" . $attrVal . "\"\n";
                }
                break;

            case XmlCursor::TOKEN_NAMESPACE:
                // Namespace declarations
                break;

            case XmlCursor::TOKEN_COMMENT:
                if ($showText) {
                    echo $indent . "<!-- comment -->\n";
                }
                break;

            case XmlCursor::TOKEN_PROCINST:
                if ($showText) {
                    echo $indent . "<? processing instruction ?>\n";
                }
                break;
        }
    } while ($cursor->toNextToken() !== XmlCursor::TOKEN_NONE);
}

/**
 * Count elements, attributes, and text nodes using cursor.
 *
 * @param XmlBeans\XmlObject $doc
 * @return array
 */
function countNodes($doc)
{
    $counts = array(
        'elements'   => 0,
        'attributes' => 0,
        'text'       => 0,
        'comments'   => 0,
        'depth'      => 0,
    );
    $currentDepth = 0;

    $cursor = $doc->newCursor();
    do {
        $type = $cursor->currentTokenType();

        if ($type === XmlCursor::TOKEN_START) {
            $counts['elements']++;
            $currentDepth++;
            if ($currentDepth > $counts['depth']) {
                $counts['depth'] = $currentDepth;
            }
        } elseif ($type === XmlCursor::TOKEN_END) {
            $currentDepth--;
        } elseif ($type === XmlCursor::TOKEN_ATTR) {
            $counts['attributes']++;
        } elseif ($type === XmlCursor::TOKEN_TEXT) {
            $text = trim($cursor->getTextValue());
            if ($text !== '') {
                $counts['text']++;
            }
        } elseif ($type === XmlCursor::TOKEN_COMMENT) {
            $counts['comments']++;
        }
    } while ($cursor->toNextToken() !== XmlCursor::TOKEN_NONE);

    return $counts;
}

// --- 1. Simple XML tree ---
echo "--- 1. Simple XML tree ---\n";

$simpleXml = '<bookshelf>
    <book isbn="978-0-123456-78-9">
        <title>XML Processing in PHP</title>
        <author>Jane Smith</author>
        <year>2024</year>
    </book>
    <book isbn="978-0-987654-32-1">
        <title>Schema Design Patterns</title>
        <author>John Doe</author>
        <year>2023</year>
    </book>
</bookshelf>';

$doc1 = XmlBeans::parse($simpleXml);
printXmlTree($doc1);

$counts = countNodes($doc1);
echo "\nStatistics: " . $counts['elements'] . " elements, "
    . $counts['attributes'] . " attributes, "
    . $counts['text'] . " text nodes, "
    . "max depth " . $counts['depth'] . "\n";

// --- 2. Namespace-aware tree ---
echo "\n--- 2. Namespace-aware tree ---\n";

$nsXml = '<po:order xmlns:po="http://example.com/po" xmlns:addr="http://example.com/addr">
    <po:customer>
        <addr:name>Acme Corp</addr:name>
        <addr:street>123 Main St</addr:street>
    </po:customer>
    <po:items>
        <po:item sku="A001" qty="5">Widget</po:item>
    </po:items>
</po:order>';

$doc2 = XmlBeans::parse($nsXml);
printXmlTree($doc2);

// --- 3. Purchase order tree ---
echo "\n--- 3. Purchase order tree ---\n";

$poXml = file_get_contents(__DIR__ . '/data/purchase-order.xml');
$doc3 = XmlBeans::parse($poXml);
printXmlTree($doc3);

$counts3 = countNodes($doc3);
echo "\nStatistics: " . $counts3['elements'] . " elements, "
    . $counts3['attributes'] . " attributes, "
    . $counts3['text'] . " text nodes, "
    . "max depth " . $counts3['depth'] . "\n";

// --- 4. Token type summary ---
echo "\n--- 4. Token type distribution ---\n";

$tokenCounts = array();
$cursor4 = $doc3->newCursor();
do {
    $type = $cursor4->currentTokenType();
    $name = XmlCursor::tokenTypeName($type);
    if (!isset($tokenCounts[$name])) {
        $tokenCounts[$name] = 0;
    }
    $tokenCounts[$name]++;
} while ($cursor4->toNextToken() !== XmlCursor::TOKEN_NONE);

arsort($tokenCounts);
echo "\nToken distribution for purchase-order.xml:\n";
$totalTokens = array_sum($tokenCounts);
foreach ($tokenCounts as $name => $count) {
    $pct = round($count / $totalTokens * 100);
    echo sprintf("  %-12s %3d  (%2d%%)\n", $name, $count, $pct);
}
echo sprintf("  %-12s %3d\n", "TOTAL", $totalTokens);

// --- 5. Building XML programmatically and showing its tree ---
echo "\n--- 5. Programmatically built tree ---\n";

$built = XmlBeans::parse('<report/>');
$bc = $built->newCursor();
$bc->toFirstChild(); // <report>

$bc->insertAttributeWithValue('generated', '2024-06-15');

$bc->beginElement('summary');
$bc->insertChars('Q2 Financial Report');
$bc->toParent();

$bc->toEndToken();
$bc->beginElement('section');
$bc->insertAttributeWithValue('id', 'revenue');
$bc->beginElement('title');
$bc->insertChars('Revenue');
$bc->toParent();
$bc->toEndToken();
$bc->beginElement('value');
$bc->insertAttributeWithValue('currency', 'USD');
$bc->insertChars('1250000');
$bc->toParent();
$bc->toParent(); // close section

echo "Built document tree:\n";
printXmlTree($built);

echo "\nDone.\n";
