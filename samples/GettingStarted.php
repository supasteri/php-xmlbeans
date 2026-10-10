<?php
/**
 * GettingStarted Sample
 *
 * An end-to-end tutorial that walks through the core features of PHP XMLBeans:
 *  1. Parsing XML
 *  2. Querying with XPath
 *  3. Modifying with cursors
 *  4. Compiling a schema
 *  5. Validating documents
 *  6. Building XML from scratch
 *
 * This is the "Hello World" of PHP XMLBeans.
 *
 * Ported from: https://xmlbeans.apache.org/samples/GettingStarted.html
 */

require_once __DIR__ . '/../vendor/autoload.php';

use XmlBeans\XmlBeans;
use XmlBeans\Cursor\XmlCursor;
use XmlBeans\XPath\XPathEngine;
use XmlBeans\XPath\XPathBuilder;

echo "=== PHP XMLBeans Getting Started ===\n\n";

// ============================================================
// STEP 1: Parse XML
// ============================================================
echo "--- Step 1: Parsing XML ---\n";

$xml = '<catalog>
    <book id="bk101">
        <author>Gambardella, Matthew</author>
        <title>XML Developer\'s Guide</title>
        <genre>Computer</genre>
        <price>44.95</price>
        <publish_date>2000-10-01</publish_date>
    </book>
    <book id="bk102">
        <author>Ralls, Kim</author>
        <title>Midnight Rain</title>
        <genre>Fantasy</genre>
        <price>5.95</price>
        <publish_date>2000-12-16</publish_date>
    </book>
    <book id="bk103">
        <author>Corets, Eva</author>
        <title>Maeve Ascendant</title>
        <genre>Fantasy</genre>
        <price>5.95</price>
        <publish_date>2000-11-17</publish_date>
    </book>
</catalog>';

// Parse from string
$doc = XmlBeans::parse($xml);
echo "Parsed catalog with " . count($doc->selectPath('//book')) . " books.\n";

// You can also parse from a file:
//   $doc = XmlBeans::parseFile('catalog.xml');

// ============================================================
// STEP 2: Query with XPath
// ============================================================
echo "\n--- Step 2: Querying with XPath ---\n";

// Select nodes
$books = $doc->selectPath('//book');
echo "\nAll books:\n";
foreach ($books as $book) {
    $title = $book->selectPathString('*[local-name()="title"]');
    $author = $book->selectPathString('*[local-name()="author"]');
    $price = $book->selectPathNumber('*[local-name()="price"]');
    echo sprintf("  %-25s by %-25s  $%.2f\n", $title, $author, $price);
}

// Scalar accessors
$totalPrice = $doc->selectPathNumber('sum(//price)');
echo "\nTotal price: $" . number_format($totalPrice, 2) . "\n";

$bookCount = $doc->selectPathNumber('count(//book)');
echo "Book count: " . (int)$bookCount . "\n";

// XPath Builder — fluent API
$xpath = XPathBuilder::create()
    ->descendant('book')
    ->where(XPathBuilder::elem('genre')->eq('Fantasy'))
    ->child('title')
    ->build();
echo "\nBuilder query: " . $xpath . "\n";

$fantasyTitles = $doc->selectPath($xpath);
echo "Fantasy books: ";
$names = array();
foreach ($fantasyTitles as $ft) {
    $names[] = $ft->getStringValue();
}
echo implode(', ', $names) . "\n";

// ============================================================
// STEP 3: Navigate and modify with cursors
// ============================================================
echo "\n--- Step 3: Cursor navigation and editing ---\n";

$cursor = $doc->newCursor();
$cursor->toFirstChild(); // <catalog>
$cursor->toChild('book'); // first <book>

echo "At: " . $cursor->getName()->getLocalPart() . "\n";
echo "  id attribute: " . $cursor->getAttributeText('id') . "\n";

// Modify a value: change the first book's price
if ($cursor->toChild('price')) {
    echo "  Original price: " . $cursor->getTextValue() . "\n";
    $cursor->setTextValue('39.95');
    echo "  Updated price:  " . $cursor->getTextValue() . "\n";
    $cursor->toParent(); // back to <book>
}

// Use push/pop to remember position
$cursor->toParent(); // back to <catalog>
$cursor->push();

// Navigate to last book
$cursor->toChild('book');   // first book
$cursor->toNextSibling();   // second book
$cursor->toNextSibling();   // third book
echo "\nLast book: " . $cursor->getAttributeText('id') . "\n";

$cursor->pop(); // back to <catalog>
echo "Popped back to: " . $cursor->getName()->getLocalPart() . "\n";

// ============================================================
// STEP 4: Build XML from scratch
// ============================================================
echo "\n--- Step 4: Building XML from scratch ---\n";

$newDoc = XmlBeans::parse('<catalog/>');
$bc = $newDoc->newCursor();
$bc->toFirstChild(); // <catalog>

// Add a book element
$bc->beginElement('book');
$bc->insertAttributeWithValue('id', 'bk201');

$bc->beginElement('author');
$bc->insertChars('Doe, John');
$bc->toParent();

$bc->toEndToken();
$bc->beginElement('title');
$bc->insertChars('PHP XMLBeans in Action');
$bc->toParent();

$bc->toEndToken();
$bc->beginElement('price');
$bc->insertChars('29.99');
$bc->toParent();

$bc->toParent(); // close <book>

echo "Built XML:\n" . $newDoc->xmlText() . "\n";

// ============================================================
// STEP 5: Compile a schema and validate
// ============================================================
echo "\n--- Step 5: Schema compilation and validation ---\n";

$xsd = '<?xml version="1.0"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="catalog">
        <xs:complexType>
            <xs:sequence>
                <xs:element name="book" maxOccurs="unbounded">
                    <xs:complexType>
                        <xs:sequence>
                            <xs:element name="author" type="xs:string"/>
                            <xs:element name="title" type="xs:string"/>
                            <xs:element name="genre" type="xs:string" minOccurs="0"/>
                            <xs:element name="price" type="xs:decimal"/>
                            <xs:element name="publish_date" type="xs:date" minOccurs="0"/>
                        </xs:sequence>
                        <xs:attribute name="id" type="xs:ID" use="required"/>
                    </xs:complexType>
                </xs:element>
            </xs:sequence>
        </xs:complexType>
    </xs:element>
</xs:schema>';

// Compile the schema
$typeSystem = XmlBeans::compileXsd($xsd);
echo "Schema compiled: " . count($typeSystem->getGlobalTypes()) . " type(s), "
    . count($typeSystem->getGlobalElements()) . " element(s)\n";

// Inspect types
foreach ($typeSystem->getGlobalElements() as $elem) {
    echo "  Global element: " . $elem->getName()->getLocalPart() . "\n";
}

// Validate a good document
$valid = XmlBeans::validate($xml, $xsd);
echo "\nOriginal catalog valid: " . ($valid ? "YES" : "NO") . "\n";

// Validate a bad document
$badXml = '<catalog>
    <book>
        <title>Missing Author and ID</title>
        <price>9.99</price>
    </book>
</catalog>';
$valid2 = XmlBeans::validate($badXml, $xsd);
echo "Bad catalog valid:      " . ($valid2 ? "YES" : "NO") . "\n";

// ============================================================
// STEP 6: Built-in types
// ============================================================
echo "\n--- Step 6: Built-in XSD types ---\n";

$ts = XmlBeans::getBuiltinTypeSystem();
$allTypes = $ts->getGlobalTypes();

$primitives = array('string', 'boolean', 'decimal', 'float', 'double',
                    'date', 'dateTime', 'time', 'duration', 'integer');
echo "Some built-in types:\n";
foreach ($primitives as $name) {
    $type = XmlBeans::getBuiltinType($name);
    if ($type) {
        $base = $type->getBaseType();
        $baseName = $base ? $base->getName()->getLocalPart() : '-';
        echo sprintf("  xs:%-12s (simple: %s, base: xs:%s)\n",
            $name,
            $type->isSimpleType() ? 'yes' : 'no',
            $baseName
        );
    }
}

// ============================================================
// Summary
// ============================================================
echo "\n--- Summary ---\n";
echo "PHP XMLBeans provides:\n";
echo "  - XmlBeans::parse()       Parse XML from strings or files\n";
echo "  - selectPath()            XPath queries with typed results\n";
echo "  - XmlCursor               Token-level navigation and editing\n";
echo "  - XPathBuilder            Fluent XPath construction\n";
echo "  - compileXsd()            Schema compilation\n";
echo "  - validate()              XML validation against schemas\n";
echo "  - Built-in type system    46 XSD types with PHP mappings\n";

echo "\nDone.\n";
