<?php
/**
 * XPath/XQuery Sample
 *
 * Demonstrates the full XPath subsystem:
 * - Raw XPath queries on XmlObject
 * - XPathEngine with typed results, scalar accessors
 * - XPathBuilder fluent API
 * - XPathPredicate fluent filtering
 * - XPathExpression compilation and variable binding
 * - XPathNamespaceContext management
 * - Custom PHP functions in XPath
 * - Cursor-based XPath
 *
 * Ported from: https://xmlbeans.apache.org/samples/XQueryXPath.html
 */

require_once __DIR__ . '/../vendor/autoload.php';

use XmlBeans\XmlBeans;
use XmlBeans\XPath\XPathEngine;
use XmlBeans\XPath\XPathBuilder;
use XmlBeans\XPath\XPathExpression;
use XmlBeans\XPath\XPathNamespaceContext;

echo "=== PHP XMLBeans XPath/XQuery Sample ===\n\n";

$xml = file_get_contents(__DIR__ . '/data/purchase-order.xml');
$doc = XmlBeans::parse($xml);

// --- 1. Basic XPath on XmlObject ---
echo "--- 1. selectPath on XmlObject ---\n";

$items = $doc->selectPath('//*[local-name()="line-item"]');
echo "Found " . count($items) . " line items:\n";
foreach ($items as $item) {
    $desc = $item->selectPath('*[local-name()="description"]');
    $price = $item->selectPath('*[local-name()="price"]');
    if (count($desc) > 0 && count($price) > 0) {
        echo "  - " . $desc[0]->getStringValue() . ": $" . $price[0]->getStringValue() . "\n";
    }
}

// Scalar accessors
$customerName = $doc->selectPathString('//*[local-name()="customer"]/*[local-name()="name"]');
echo "\nCustomer: " . $customerName . "\n";

$totalPrice = $doc->selectPathNumber('sum(//*[local-name()="price"])');
echo "Total price: $" . number_format($totalPrice, 2) . "\n";

$hasShipper = $doc->selectPathBoolean('//*[local-name()="shipper"]');
echo "Has shipper: " . ($hasShipper ? "yes" : "no") . "\n";

// --- 2. XPathEngine ---
echo "\n--- 2. XPathEngine with typed results ---\n";

$engine = XPathEngine::fromXml($xml);

// Register namespaces for the document
$ctx = new XPathNamespaceContext();
$ctx->registerNamespace('po', 'http://openuri.org/easypo');
$engine->setNamespaceContext($ctx);

$typedItems = $engine->selectTyped('//po:line-item');
echo "Typed results: " . count($typedItems) . " XmlObject instances\n";

$firstItem = $engine->selectFirst('//po:line-item');
if ($firstItem !== null) {
    echo "First item XML:\n  " . substr($firstItem->xmlText(), 0, 80) . "...\n";
}

// Scalar accessors on engine
$names = $engine->selectStrings('//po:line-item/po:description');
echo "\nAll descriptions:\n";
foreach ($names as $name) {
    echo "  - " . $name . "\n";
}

$count = $engine->count('//po:line-item');
echo "\nItem count: " . $count . "\n";

$exists = $engine->exists('//po:shipper');
echo "Shipper exists: " . ($exists ? "yes" : "no") . "\n";

// --- 3. XPathBuilder fluent API ---
echo "\n--- 3. XPathBuilder fluent API ---\n";

// Build: //line-item/description/text()
$xpath1 = XPathBuilder::create()
    ->descendant('line-item')
    ->child('description')
    ->text()
    ->build();
echo "Builder 1: " . $xpath1 . "\n";

// Build: //line-item[1]/price
$xpath2 = XPathBuilder::create()
    ->descendant('line-item')
    ->first()
    ->child('price')
    ->build();
echo "Builder 2: " . $xpath2 . "\n";

// Build with predicate: //line-item[price > 20]
$xpath3 = XPathBuilder::create()
    ->descendant('line-item')
    ->where(XPathBuilder::elem('price')->gt(20))
    ->build();
echo "Builder 3: " . $xpath3 . "\n";

// Build with complex predicate: //line-item[price > 15 and quantity >= 2]
$xpath4 = XPathBuilder::create()
    ->descendant('line-item')
    ->where(
        XPathBuilder::elem('price')->gt(15)
            ->andPred(XPathBuilder::elem('quantity')->gte(2))
    )
    ->build();
echo "Builder 4: " . $xpath4 . "\n";

// Build with string predicate: //line-item[contains(description, 'Vol 1')]
$xpath5 = XPathBuilder::create()
    ->descendant('line-item')
    ->where(XPathBuilder::elem('description')->contains('Vol 1'))
    ->build();
echo "Builder 5: " . $xpath5 . "\n";

// Build with union: //customer/name | //shipper/name
$xpath6 = XPathBuilder::create()
    ->descendant('customer')
    ->child('name')
    ->union(
        XPathBuilder::create()
            ->descendant('shipper')
            ->child('name')
    )
    ->build();
echo "Builder 6: " . $xpath6 . "\n";

// Build with namespace prefix
$xpath7 = XPathBuilder::create()
    ->root()
    ->nsChild('po', 'purchase-order')
    ->nsChild('po', 'line-item')
    ->last()
    ->build();
echo "Builder 7: " . $xpath7 . "\n";

// Build with axes
$xpath8 = XPathBuilder::create()
    ->descendant('line-item')
    ->first()
    ->followingSibling('line-item')
    ->build();
echo "Builder 8: " . $xpath8 . "\n";

// --- 4. Compiled expressions with variable binding ---
echo "\n--- 4. Compiled XPathExpression with variables ---\n";

$expr = XPathExpression::compile('//*[local-name()="line-item"][*[local-name()="price"] > $minPrice]');
echo "Expression: " . $expr->getExpression() . "\n";
echo "Has variables: " . ($expr->hasVariables() ? "yes" : "no") . "\n";
echo "Variable names: " . implode(', ', $expr->getVariableNames()) . "\n";
echo "Is absolute: " . ($expr->isAbsolute() ? "yes" : "no") . "\n";
echo "Has predicate: " . ($expr->hasPredicate() ? "yes" : "no") . "\n";

// Bind and execute
$expr->bindVariable('minPrice', 20);
echo "\nWith minPrice=20:\n";
$normalized = $expr->getNormalizedExpression();
echo "  Normalized: " . $normalized . "\n";

$results = $engine->selectTyped($normalized);
echo "  Matches: " . count($results) . "\n";

// Rebind
$expr->clearVariables();
$expr->bindVariable('minPrice', 15);
echo "\nWith minPrice=15:\n";
$results2 = $engine->selectTyped($expr->getNormalizedExpression());
echo "  Matches: " . count($results2) . "\n";

// Cache info
echo "\nExpression cache size: " . XPathExpression::getCacheSize() . "\n";

// --- 5. Custom PHP functions ---
echo "\n--- 5. Custom PHP functions in XPath ---\n";

$engine2 = XPathEngine::fromXml('<items>
    <item><name>Widget A</name><price>29.99</price></item>
    <item><name>Widget B</name><price>49.50</price></item>
    <item><name>Widget C</name><price>15.00</price></item>
</items>');

$engine2->registerFunction('discount', function ($price, $pct) {
    return round((float)$price * (1 - (float)$pct / 100), 2);
});

$engine2->registerFunction('taxed', function ($price, $rate) {
    return round((float)$price * (1 + (float)$rate / 100), 2);
});

// Note: Custom PHP functions require the php: namespace prefix in XPath
echo "Registered functions: " . implode(', ', $engine2->getRegisteredFunctions()) . "\n";

// --- 6. Namespace context ---
echo "\n--- 6. XPathNamespaceContext ---\n";

$nsCtx = new XPathNamespaceContext();
$nsCtx->registerNamespace('po', 'http://openuri.org/easypo');
echo "Registered: po => " . $nsCtx->getNamespaceUri('po') . "\n";

// Auto-detect from document
$dom = new DOMDocument();
$dom->loadXML($xml);
$autoCtx = new XPathNamespaceContext();
$autoCtx->loadFromDocument($dom);
echo "Auto-detected namespaces:\n";
foreach ($autoCtx->getAllNamespaces() as $prefix => $uri) {
    echo "  " . $prefix . " => " . $uri . "\n";
}

// Well-known sets
$schemaCtx = new XPathNamespaceContext();
$schemaCtx->registerSchemaNamespaces();
echo "\nSchema namespaces:\n";
foreach ($schemaCtx->getAllNamespaces() as $prefix => $uri) {
    echo "  " . $prefix . " => " . $uri . "\n";
}

// --- 7. Cursor-based XPath ---
echo "\n--- 7. Cursor-based XPath ---\n";

$cursor = $doc->newCursor();
$cursor->toFirstChild(); // into root element

// Select from cursor position
$cursorResults = $cursor->selectPath('descendant::*[local-name()="line-item"]');
echo "Cursor XPath found " . count($cursorResults) . " items\n";
foreach ($cursorResults as $cr) {
    echo "  Cursor at: " . $cr->getName()->getLocalPart() . "\n";
}

// selectChildren from cursor
$children = $cursor->selectChildren('line-item', 'http://openuri.org/easypo');
echo "\nselectChildren found " . count($children) . " line-item children\n";

echo "\nDone.\n";
