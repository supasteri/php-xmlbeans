#!/usr/bin/env php
<?php
/**
 * Standalone test runner for PHP XMLBeans (no PHPUnit required).
 */

// PSR-4 autoloader
spl_autoload_register(function ($class) {
    $prefix = 'XmlBeans\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) require $file;
});

use XmlBeans\Binding\XmlObjectBase;
use XmlBeans\Binding\TypeMapper;
use XmlBeans\Common\QName;
use XmlBeans\Compiler\PhpCodeGenerator;
use XmlBeans\Compiler\SchemaCompiler;
use XmlBeans\Cursor\XmlCursor;
use XmlBeans\Schema\BuiltinTypeSystem;
use XmlBeans\SchemaParticle;
use XmlBeans\SchemaProperty;
use XmlBeans\SchemaType;
use XmlBeans\SchemaTypeSystem;
use XmlBeans\Type;
use XmlBeans\Validation\Validator;
use XmlBeans\XmlBeans;
use XmlBeans\XmlError;
use XmlBeans\XmlOptions;

$passed = 0;
$failed = 0;
$errors = [];
$fixtureDir = __DIR__ . '/fixtures';

function assert_true($val, $msg) { global $passed, $failed, $errors; if ($val) { $passed++; } else { $failed++; $errors[] = $msg; } }
function assert_false($val, $msg) { assert_true(!$val, $msg); }
function assert_eq($expected, $actual, $msg) { assert_true($expected === $actual, "$msg (expected " . var_export($expected, true) . ", got " . var_export($actual, true) . ")"); }
function assert_neq_null($val, $msg) { assert_true($val !== null, "$msg (got null)"); }
function assert_contains($needle, $haystack, $msg) { assert_true(strpos($haystack, $needle) !== false, "$msg ('$needle' not found)"); }
function assert_gt($a, $b, $msg) { assert_true($a > $b, "$msg ($a not > $b)"); }
function assert_gte($a, $b, $msg) { assert_true($a >= $b, "$msg ($a not >= $b)"); }
function assert_instance($class, $obj, $msg) { assert_true($obj instanceof $class, "$msg (not instance of $class)"); }
function assert_count($expected, $arr, $msg) { assert_eq($expected, count($arr), $msg); }

function run_test($name, $fn) {
    global $passed, $failed, $errors;
    $prevFailed = $failed;
    try {
        $fn();
        if ($failed === $prevFailed) {
            echo "  \033[32m✓\033[0m $name\n";
        } else {
            echo "  \033[31m✗\033[0m $name\n";
        }
    } catch (\Throwable $e) {
        $failed++;
        $errors[] = "$name: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine();
        echo "  \033[31m✗\033[0m $name: {$e->getMessage()}\n";
    }
}

echo "\n\033[1mPHP XMLBeans Test Suite\033[0m\n";
echo "PHP " . PHP_VERSION . "\n\n";

// ============================
echo "\033[1m[QName]\033[0m\n";
// ============================

run_test('QName basic', function() {
    $qn = new QName('http://example.com', 'person', 'ex');
    assert_eq('http://example.com', $qn->getNamespaceURI(), 'ns');
    assert_eq('person', $qn->getLocalPart(), 'local');
    assert_eq('ex', $qn->getPrefix(), 'prefix');
    assert_eq('{http://example.com}person', (string)$qn, 'toString');
});

run_test('QName fromClark', function() {
    $qn = QName::fromClark('{http://www.w3.org/2001/XMLSchema}string');
    assert_eq('string', $qn->getLocalPart(), 'local');
    assert_eq('http://www.w3.org/2001/XMLSchema', $qn->getNamespaceURI(), 'ns');
});

run_test('QName equals', function() {
    $a = new QName('http://x.com', 'n');
    $b = new QName('http://x.com', 'n', 'pfx');
    $c = new QName('http://y.com', 'n');
    assert_true($a->equals($b), 'same ns+local should be equal');
    assert_false($a->equals($c), 'different ns should not be equal');
});

// ============================
echo "\n\033[1m[XmlOptions]\033[0m\n";
// ============================

run_test('XmlOptions fluent API', function() {
    $opts = new XmlOptions();
    $ret = $opts->setSavePrettyPrint(true)->setSavePrettyPrintIndent(4)->setCharacterEncoding('UTF-8');
    assert_true($ret === $opts, 'fluent returns same instance');
    assert_true($opts->getSavePrettyPrint(), 'pretty print');
    assert_eq(4, $opts->getSavePrettyPrintIndent(), 'indent');
    assert_eq('UTF-8', $opts->getCharacterEncoding(), 'encoding');
});

// ============================
echo "\n\033[1m[Parsing]\033[0m\n";
// ============================

run_test('Parse simple XML', function() {
    $obj = XmlBeans::parse('<root><name>Alice</name></root>');
    assert_instance(XmlObjectBase::class, $obj, 'type');
    assert_neq_null($obj->getDomNode(), 'dom');
});

run_test('Parse file', function() use ($fixtureDir) {
    $obj = XmlBeans::parseFile($fixtureDir . '/purchaseOrder.xml');
    assert_neq_null($obj, 'parsed');
    assert_contains('Alice Smith', $obj->getStringValue(), 'content');
});

run_test('Parse invalid XML throws', function() {
    $threw = false;
    try { XmlBeans::parse('<broken><unclosed>'); } catch (\RuntimeException $e) { $threw = true; }
    assert_true($threw, 'should throw on invalid XML');
});

run_test('Pretty print output', function() {
    $obj = XmlBeans::parse('<root><a>1</a><b>2</b></root>');
    $opts = (new XmlOptions())->setSavePrettyPrint(true);
    assert_contains("\n", $obj->xmlText($opts), 'should have newlines');
});

run_test('Strip comments', function() {
    $obj = XmlBeans::parse('<root><!-- rem --><d>v</d></root>', null, (new XmlOptions())->setLoadStripComments());
    assert_false(strpos($obj->xmlText(), 'rem') !== false, 'comment should be stripped');
});

run_test('Save inner XML', function() {
    $obj = XmlBeans::parse('<root><child>hello</child></root>');
    $cursor = $obj->newCursor();
    $cursor->toFirstChild();
    $inner = new XmlObjectBase($cursor->getDomNode());
    $opts = (new XmlOptions())->setSaveInner(true);
    assert_contains('hello', $inner->xmlText($opts), 'inner text');
});

// ============================
echo "\n\033[1m[Copy / Nil]\033[0m\n";
// ============================

run_test('Copy is deep', function() {
    $obj = XmlBeans::parse('<root><n>Test</n></root>');
    $copy = $obj->copy();
    assert_contains('Test', $copy->xmlText(), 'copy has content');
    $obj->setStringValue('Modified');
    assert_contains('Test', $copy->xmlText(), 'copy unaffected');
});

run_test('Nil handling', function() {
    $obj = XmlBeans::parse('<root xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><item xsi:nil="true"/></root>');
    $cursor = $obj->newCursor();
    $cursor->toFirstChild(); $cursor->toFirstChild();
    $item = new XmlObjectBase($cursor->getDomNode());
    assert_true($item->isNil(), 'should be nil');
    $item->setNil(false);
    assert_false($item->isNil(), 'should not be nil after unset');
});

// ============================
echo "\n\033[1m[XPath]\033[0m\n";
// ============================

run_test('selectPath', function() {
    $obj = XmlBeans::parse('<root><item>A</item><item>B</item><item>C</item></root>');
    $results = $obj->selectPath('//item');
    assert_count(3, $results, 'count');
    assert_eq('A', $results[0]->getStringValue(), 'first');
    assert_eq('C', $results[2]->getStringValue(), 'last');
});

run_test('selectAttribute', function() {
    $obj = XmlBeans::parse('<root lang="en"/>');
    $result = $obj->selectAttribute('/root/@lang');
    assert_neq_null($result, 'found');
    assert_eq('en', $result->getStringValue(), 'value');
});

// ============================
echo "\n\033[1m[XmlCursor]\033[0m\n";
// ============================

run_test('Cursor navigation', function() {
    $obj = XmlBeans::parse('<root><a>1</a><b>2</b><c>3</c></root>');
    $c = $obj->newCursor();
    assert_eq(XmlCursor::TOKEN_STARTDOC, $c->currentTokenType(), 'startdoc');
    assert_true($c->toFirstChild(), 'to root');
    assert_eq('root', $c->getName()->getLocalPart(), 'root name');
    assert_true($c->toFirstChild(), 'to a');
    assert_eq('a', $c->getName()->getLocalPart(), 'a name');
    assert_eq('1', $c->getTextValue(), 'a value');
    assert_true($c->toNextSibling(), 'to b');
    assert_eq('b', $c->getName()->getLocalPart(), 'b name');
    assert_true($c->toNextSibling(), 'to c');
    assert_eq('c', $c->getName()->getLocalPart(), 'c name');
    assert_false($c->toNextSibling(), 'no more siblings');
    assert_true($c->toParent(), 'back to parent');
    assert_eq('root', $c->getName()->getLocalPart(), 'parent is root');
});

run_test('Cursor toChild by name', function() {
    $obj = XmlBeans::parse('<root><first>1</first><second>2</second></root>');
    $c = $obj->newCursor();
    $c->toFirstChild();
    assert_true($c->toChild('second'), 'find second');
    assert_eq('2', $c->getTextValue(), 'value');
});

run_test('Cursor token type names', function() {
    assert_eq('STARTDOC', XmlCursor::tokenTypeName(XmlCursor::TOKEN_STARTDOC), 'startdoc');
    assert_eq('START', XmlCursor::tokenTypeName(XmlCursor::TOKEN_START), 'start');
    assert_eq('TEXT', XmlCursor::tokenTypeName(XmlCursor::TOKEN_TEXT), 'text');
});

run_test('Cursor modification', function() {
    $obj = XmlBeans::parse('<root><val>Old</val></root>');
    $c = $obj->newCursor();
    $c->toFirstChild(); $c->toChild('val');
    $c->setTextValue('New');
    assert_eq('New', $c->getTextValue(), 'modified');
    assert_contains('New', $obj->xmlText(), 'in doc');
});

run_test('Cursor attributes', function() {
    $obj = XmlBeans::parse('<root id="100" name="test"/>');
    $c = $obj->newCursor();
    $c->toFirstChild();
    assert_eq('100', $c->getAttributeText('id'), 'id attr');
    $c->setAttributeText('id', '200');
    assert_eq('200', $c->getAttributeText('id'), 'modified id');
});

run_test('Cursor bookmarks', function() {
    $obj = XmlBeans::parse('<root><a>1</a><b>2</b></root>');
    $c = $obj->newCursor();
    $c->toFirstChild(); $c->toFirstChild();
    $c->setBookmark('pos_a');
    $c->toNextSibling();
    assert_eq('b', $c->getName()->getLocalPart(), 'at b');
    $c->toBookmark('pos_a');
    assert_eq('a', $c->getName()->getLocalPart(), 'back at a');
});

run_test('Cursor insert element', function() {
    $obj = XmlBeans::parse('<root><a>1</a></root>');
    $c = $obj->newCursor();
    $c->toFirstChild();
    $c->beginElement('b');
    $c->insertChars('2');
    assert_contains('<b>', $obj->xmlText(), 'has b');
});

run_test('Cursor remove', function() {
    $obj = XmlBeans::parse('<root><a>1</a><b>2</b></root>');
    $c = $obj->newCursor();
    $c->toFirstChild(); $c->toChild('a');
    assert_true($c->removeXml(), 'removed');
    assert_false(strpos($obj->xmlText(), '<a>') !== false, 'a gone');
});

run_test('Cursor prevSibling / lastChild', function() {
    $obj = XmlBeans::parse('<root><a/><b/><c/></root>');
    $c = $obj->newCursor();
    $c->toFirstChild();
    assert_true($c->toLastChild(), 'to last');
    assert_eq('c', $c->getName()->getLocalPart(), 'c');
    assert_true($c->toPrevSibling(), 'to prev');
    assert_eq('b', $c->getName()->getLocalPart(), 'b');
});

run_test('Cursor startDoc/endDoc', function() {
    $obj = XmlBeans::parse('<root/>');
    $c = $obj->newCursor();
    $c->toEndDoc();
    assert_true($c->isAtEnd(), 'at end');
    $c->toStartDoc();
    assert_false($c->isAtEnd(), 'not at end');
    assert_eq(XmlCursor::TOKEN_STARTDOC, $c->currentTokenType(), 'startdoc');
});

// ============================
echo "\n\033[1m[Built-in Types]\033[0m\n";
// ============================

run_test('Built-in type system', function() {
    $ts = BuiltinTypeSystem::get();
    assert_instance(SchemaTypeSystem::class, $ts, 'type system');
    $st = BuiltinTypeSystem::findByLocalName('string');
    assert_neq_null($st, 'string type');
    assert_true($st->isSimpleType(), 'is simple');
    assert_eq(SchemaType::BTC_STRING, $st->getBuiltinTypeCode(), 'btc');
});

run_test('Built-in type hierarchy', function() {
    $intT = BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_INT);
    assert_neq_null($intT, 'int');
    $longT = $intT->getBaseType();
    assert_neq_null($longT, 'long');
    assert_eq(SchemaType::BTC_LONG, $longT->getBuiltinTypeCode(), 'long btc');
    $integerT = $longT->getBaseType();
    assert_eq(SchemaType::BTC_INTEGER, $integerT->getBuiltinTypeCode(), 'integer btc');
});

run_test('Built-in type facets', function() {
    $intT = BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_INT);
    assert_eq('-2147483648', $intT->getFacet('minInclusive'), 'int min');
    assert_eq('2147483647', $intT->getFacet('maxInclusive'), 'int max');
    $boolT = BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_BOOLEAN);
    assert_true(in_array('true', $boolT->getEnumerationValues()), 'bool enum');
});

// ============================
echo "\n\033[1m[Simple Type Wrappers]\033[0m\n";
// ============================

run_test('XmlString', function() {
    $obj = XmlBeans::parse('<v>hello</v>');
    $t = new Type\XmlString($obj->getDomNode()->documentElement);
    assert_eq('hello', $t->getValue(), 'get');
    $t->setValue('world');
    assert_eq('world', $t->getValue(), 'set');
});

run_test('XmlBoolean', function() {
    $obj = XmlBeans::parse('<f>true</f>');
    $t = new Type\XmlBoolean($obj->getDomNode()->documentElement);
    assert_true($t->getValue(), 'true');
    $t->setValue(false);
    assert_false($t->getValue(), 'false');
});

run_test('XmlInt', function() {
    $obj = XmlBeans::parse('<n>42</n>');
    $t = new Type\XmlInt($obj->getDomNode()->documentElement);
    assert_eq(42, $t->getValue(), 'value');
    $t->setValue(99);
    assert_eq(99, $t->getValue(), 'set');
});

run_test('XmlFloat special values', function() {
    $obj = XmlBeans::parse('<v>INF</v>');
    $t = new Type\XmlFloat($obj->getDomNode()->documentElement);
    assert_true(is_infinite($t->getValue()), 'INF');
    $t->setValue(NAN);
    assert_eq('NaN', $t->getStringValue(), 'NaN string');
});

run_test('XmlDecimal', function() {
    $obj = XmlBeans::parse('<v>123.456</v>');
    $t = new Type\XmlDecimal($obj->getDomNode()->documentElement);
    assert_true(abs($t->getValue() - 123.456) < 0.001, 'value');
});

run_test('XmlDate', function() {
    $obj = XmlBeans::parse('<d>2024-03-15</d>');
    $t = new Type\XmlDate($obj->getDomNode()->documentElement);
    $v = $t->getValue();
    assert_instance(\DateTime::class, $v, 'DateTime');
    assert_eq('2024-03-15', $v->format('Y-m-d'), 'format');
});

run_test('XmlDateTime', function() {
    $obj = XmlBeans::parse('<ts>2024-03-15T10:30:00+00:00</ts>');
    $t = new Type\XmlDateTime($obj->getDomNode()->documentElement);
    assert_instance(\DateTime::class, $t->getValue(), 'DateTime');
});

run_test('XmlBase64Binary', function() {
    $raw = 'Hello!';
    $obj = XmlBeans::parse('<d>' . base64_encode($raw) . '</d>');
    $t = new Type\XmlBase64Binary($obj->getDomNode()->documentElement);
    assert_eq($raw, $t->getValue(), 'decode');
});

run_test('XmlHexBinary', function() {
    $raw = 'AB';
    $obj = XmlBeans::parse('<d>' . strtoupper(bin2hex($raw)) . '</d>');
    $t = new Type\XmlHexBinary($obj->getDomNode()->documentElement);
    assert_eq($raw, $t->getValue(), 'decode');
});

run_test('XmlToken whitespace collapse', function() {
    $obj = XmlBeans::parse('<t>  hello   world  </t>');
    $t = new Type\XmlToken($obj->getDomNode()->documentElement);
    assert_eq('hello world', $t->getValue(), 'collapsed');
});

run_test('TypeMapper creates correct class', function() {
    $intType = BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_INT);
    $obj = TypeMapper::createTypedObject($intType);
    assert_instance(Type\XmlInt::class, $obj, 'XmlInt');
});

// ============================
echo "\n\033[1m[Schema Compiler]\033[0m\n";
// ============================

run_test('Compile purchase order XSD', function() use ($fixtureDir) {
    $compiler = new SchemaCompiler();
    $ts = $compiler->compile($fixtureDir . '/purchaseOrder.xsd');
    assert_instance(SchemaTypeSystem::class, $ts, 'type system');
    assert_count(0, $compiler->getErrors(), 'no errors');

    $types = $ts->getGlobalTypes();
    assert_gte(count($types), 5, 'at least 5 global types');

    // USAddress
    $usa = $ts->findType(new QName('http://example.com/purchase', 'USAddress'));
    assert_neq_null($usa, 'USAddress found');
    assert_false($usa->isSimpleType(), 'not simple');
    $nameProp = $usa->getElementProperty(new QName('http://example.com/purchase', 'name'));
    assert_neq_null($nameProp, 'name prop');
    assert_true($nameProp->isRequired(), 'name required');

    // Country attribute
    $country = $usa->getAttributeProperty(new QName('', 'country'));
    assert_neq_null($country, 'country attr');
    assert_eq('US', $country->getFixedValue(), 'fixed US');

    // USState enum
    $uss = $ts->findType(new QName('http://example.com/purchase', 'USState'));
    assert_neq_null($uss, 'USState');
    assert_true($uss->isSimpleType(), 'simple');
    $enums = $uss->getEnumerationValues();
    assert_true(in_array('CA', $enums), 'CA enum');
    assert_true(in_array('NY', $enums), 'NY enum');

    // SKU pattern
    $sku = $ts->findType(new QName('http://example.com/purchase', 'SKU'));
    assert_neq_null($sku, 'SKU');
    assert_neq_null($sku->getPatternFacet(), 'pattern facet');

    // Quantity range
    $qty = $ts->findType(new QName('http://example.com/purchase', 'Quantity'));
    assert_neq_null($qty, 'Quantity');
    assert_eq('9999', $qty->getFacet('maxInclusive'), 'maxInclusive');

    // Document types
    $poDocs = $ts->findDocumentType(new QName('http://example.com/purchase', 'purchaseOrder'));
    assert_neq_null($poDocs, 'purchaseOrder doc');
    assert_true($poDocs->isDocumentType(), 'isDoc');
});

run_test('Compile XSD string', function() {
    $xsd = '<?xml version="1.0"?>
    <xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
        <xs:simpleType name="PhoneNumber">
            <xs:restriction base="xs:string">
                <xs:pattern value="\d{3}-\d{3}-\d{4}"/>
            </xs:restriction>
        </xs:simpleType>
        <xs:element name="phone" type="PhoneNumber"/>
    </xs:schema>';
    $ts = XmlBeans::compileXsdString($xsd);
    $pt = $ts->findType(new QName('', 'PhoneNumber'));
    assert_neq_null($pt, 'PhoneNumber');
    assert_true($pt->isSimpleType(), 'simple');
    assert_neq_null($pt->getPatternFacet(), 'pattern');
});

run_test('Compile complex content extension', function() use ($fixtureDir) {
    $compiler = new SchemaCompiler();
    $ts = $compiler->compile($fixtureDir . '/purchaseOrder.xsd');
    $uk = $ts->findType(new QName('http://example.com/purchase', 'UKAddress'));
    assert_neq_null($uk, 'UKAddress');
    assert_eq(SchemaType::DT_EXTENSION, $uk->getDerivationType(), 'extension');
    $base = $uk->getBaseType();
    assert_neq_null($base, 'base type');
    assert_eq('USAddress', $base->getName()->getLocalPart(), 'base is USAddress');
});

run_test('Compile Item type with required attribute', function() use ($fixtureDir) {
    $compiler = new SchemaCompiler();
    $ts = $compiler->compile($fixtureDir . '/purchaseOrder.xsd');
    $item = $ts->findType(new QName('http://example.com/purchase', 'Item'));
    assert_neq_null($item, 'Item');
    $partNum = $item->getAttributeProperty(new QName('', 'partNum'));
    assert_neq_null($partNum, 'partNum');
    assert_true($partNum->isRequired(), 'required');
    // Optional element
    $comment = $item->getElementProperty(new QName('http://example.com/purchase', 'comment'));
    assert_neq_null($comment, 'comment');
    assert_false($comment->isRequired(), 'optional');
});

run_test('Compile unbounded array element', function() use ($fixtureDir) {
    $compiler = new SchemaCompiler();
    $ts = $compiler->compile($fixtureDir . '/purchaseOrder.xsd');
    $items = $ts->findType(new QName('http://example.com/purchase', 'Items'));
    assert_neq_null($items, 'Items');
    $itemProp = $items->getElementProperty(new QName('http://example.com/purchase', 'item'));
    assert_neq_null($itemProp, 'item prop');
    assert_true($itemProp->isArray(), 'is array');
    assert_eq(0, $itemProp->getMinOccurs(), 'minOccurs 0');
    assert_eq(-1, $itemProp->getMaxOccurs(), 'unbounded');
});

// ============================
echo "\n\033[1m[Code Generation]\033[0m\n";
// ============================

run_test('Generate PHP classes', function() use ($fixtureDir) {
    $compiler = new SchemaCompiler();
    $ts = $compiler->compile($fixtureDir . '/purchaseOrder.xsd');
    $outputDir = sys_get_temp_dir() . '/php-xmlbeans-test-' . uniqid();
    $gen = new PhpCodeGenerator($ts);
    $files = $gen->generate($outputDir, 'Test\\Generated');
    assert_gt(count($files), 0, 'files generated');

    // Check USAddress class
    $f = $outputDir . '/USAddress.php';
    assert_true(file_exists($f), 'USAddress.php exists');
    $content = file_get_contents($f);
    assert_contains('namespace Test\\Generated', $content, 'namespace');
    assert_contains('getName()', $content, 'getter');
    assert_contains('setName(', $content, 'setter');
    assert_contains('isSetName()', $content, 'isSet');

    // Check USState enum
    $f = $outputDir . '/USState.php';
    assert_true(file_exists($f), 'USState.php exists');
    $content = file_get_contents($f);
    assert_contains('VALUE_CA', $content, 'CA const');
    assert_contains('isValidValue', $content, 'validator');

    // Check Items array accessors
    $f = $outputDir . '/Items.php';
    assert_true(file_exists($f), 'Items.php exists');
    $content = file_get_contents($f);
    assert_contains('getItemArray()', $content, 'array getter');
    assert_contains('sizeOfItemArray()', $content, 'count');
    assert_contains('addNewItem(', $content, 'add');

    // Check document class
    $f = $outputDir . '/PurchaseOrderDocument.php';
    assert_true(file_exists($f), 'PurchaseOrderDocument.php');

    // Cleanup
    array_map('unlink', glob($outputDir . '/*.php'));
    @rmdir($outputDir);
});

// ============================
echo "\n\033[1m[Validation]\033[0m\n";
// ============================

run_test('Validate with XSD', function() {
    $xsd = '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
        <xs:element name="note"><xs:complexType><xs:sequence>
            <xs:element name="to" type="xs:string"/>
            <xs:element name="body" type="xs:string"/>
        </xs:sequence></xs:complexType></xs:element>
    </xs:schema>';
    assert_true(Validator::validateXmlAgainstXsd('<note><to>A</to><body>B</body></note>', $xsd), 'valid');
    assert_false(Validator::validateXmlAgainstXsd('<note><wrong>X</wrong></note>', $xsd), 'invalid');
});

run_test('Validate simple type facets', function() {
    $type = new SchemaType(new QName('', 'T'));
    $type->setIsSimpleType(true);
    $type->setContentType(SchemaType::SIMPLE_CONTENT);
    $type->setFacet('minLength', '3');
    $type->setFacet('maxLength', '10');
    $obj = XmlBeans::parse('<v>AB</v>');
    $obj->setSchemaType($type);
    $errors = [];
    $opts = (new XmlOptions())->setErrorCollection($errors);
    assert_false($obj->validate($opts), 'too short');
    assert_gt(count($errors), 0, 'has errors');
});

run_test('Validate enumeration', function() {
    $type = new SchemaType(new QName('', 'Color'));
    $type->setIsSimpleType(true);
    $type->setContentType(SchemaType::SIMPLE_CONTENT);
    $type->setEnumerationValues(['red', 'green', 'blue']);
    $obj = XmlBeans::parse('<c>purple</c>');
    $obj->setSchemaType($type);
    $errors = [];
    $opts = (new XmlOptions())->setErrorCollection($errors);
    assert_false($obj->validate($opts), 'invalid enum');
    assert_contains('enumeration', (string)$errors[0], 'error msg');
});

run_test('Validate pattern', function() {
    $type = new SchemaType(new QName('', 'Zip'));
    $type->setIsSimpleType(true);
    $type->setContentType(SchemaType::SIMPLE_CONTENT);
    $type->setPatternFacet('[0-9]{5}');
    $obj = XmlBeans::parse('<z>90210</z>');
    $obj->setSchemaType($type);
    assert_true($obj->validate(), 'valid zip');
    $obj2 = XmlBeans::parse('<z>ABCDE</z>');
    $obj2->setSchemaType($type);
    assert_false($obj2->validate(), 'invalid zip');
});

run_test('Validate numeric range', function() {
    $type = new SchemaType(new QName('', 'Age'));
    $type->setIsSimpleType(true);
    $type->setContentType(SchemaType::SIMPLE_CONTENT);
    $type->setFacet('minInclusive', '0');
    $type->setFacet('maxInclusive', '150');
    $obj = XmlBeans::parse('<a>200</a>');
    $obj->setSchemaType($type);
    assert_false($obj->validate(), 'over max');
});

run_test('Validate required attribute', function() {
    $type = new SchemaType(new QName('', 'E'));
    $type->setContentType(SchemaType::ELEMENT_CONTENT);
    $attr = new SchemaProperty(new QName('', 'id'), SchemaProperty::XML_ATTRIBUTE);
    $attr->setMinOccurs(1);
    $type->addAttributeProperty($attr);
    $obj = XmlBeans::parse('<elem/>');
    $obj->setSchemaType($type);
    $errors = [];
    $obj->validate((new XmlOptions())->setErrorCollection($errors));
    assert_gt(count($errors), 0, 'missing attr error');
    assert_contains('Required attribute', (string)$errors[0], 'error msg');
});

run_test('Validate occurrence constraints', function() {
    $type = new SchemaType(new QName('', 'P'));
    $type->setContentType(SchemaType::ELEMENT_CONTENT);
    $prop = new SchemaProperty(new QName('', 'child'), SchemaProperty::XML_ELEMENT);
    $prop->setMinOccurs(2);
    $prop->setMaxOccurs(3);
    $type->addElementProperty($prop);
    $obj = XmlBeans::parse('<parent><child>1</child></parent>');
    $obj->setSchemaType($type);
    assert_false($obj->validate(), 'too few children');
});

run_test('Validate empty content', function() {
    $type = new SchemaType(new QName('', 'E'));
    $type->setContentType(SchemaType::EMPTY_CONTENT);
    $obj = XmlBeans::parse('<empty>not empty</empty>');
    $obj->setSchemaType($type);
    assert_false($obj->validate(), 'should fail for non-empty');
});

run_test('Error listener callback', function() {
    $type = new SchemaType(new QName('', 'T'));
    $type->setIsSimpleType(true);
    $type->setContentType(SchemaType::SIMPLE_CONTENT);
    $type->setEnumerationValues(['a', 'b']);
    $obj = XmlBeans::parse('<v>c</v>');
    $obj->setSchemaType($type);
    $captured = [];
    $opts = (new XmlOptions())->addErrorListener(function($err) use (&$captured) { $captured[] = $err; });
    $obj->validate($opts);
    assert_gt(count($captured), 0, 'listener called');
});

// ============================
echo "\n\033[1m[XmlError]\033[0m\n";
// ============================

run_test('XmlError formatting', function() {
    $err = new XmlError(XmlError::SEVERITY_ERROR, 'Test error', 'test.xml', 10, 5);
    assert_eq('error', $err->getSeverityLabel(), 'severity');
    assert_eq('[test.xml:10:5] error: Test error', (string)$err, 'toString');
    $warn = new XmlError(XmlError::SEVERITY_WARNING, 'A warning');
    assert_eq('warning', $warn->getSeverityLabel(), 'warning');
});

// ============================
echo "\n\033[1m[SchemaType]\033[0m\n";
// ============================

run_test('SchemaType assignableFrom', function() {
    $parent = new SchemaType(new QName('', 'Parent'));
    $child = new SchemaType(new QName('', 'Child'));
    $child->setBaseType($parent);
    assert_true($parent->isAssignableFrom($child), 'parent assignable from child');
    assert_false($child->isAssignableFrom($parent), 'child not assignable from parent');
});

run_test('SchemaParticle', function() {
    $seq = new SchemaParticle(SchemaParticle::SEQUENCE);
    assert_eq(SchemaParticle::SEQUENCE, $seq->getParticleType(), 'type');
    $elem = new SchemaParticle(SchemaParticle::ELEMENT);
    $elem->setMaxOccurs(-1);
    assert_true($elem->isUnbounded(), 'unbounded');
    $seq->addChild($elem);
    assert_eq(1, $seq->countChildren(), 'child count');
});

// ============================
echo "\n\033[1m[Factory]\033[0m\n";
// ============================

run_test('XmlBeans::newDocument', function() {
    $doc = XmlBeans::newDocument('root');
    assert_contains('<root/>', $doc->xmlText(), 'root elem');
    $nsDoc = XmlBeans::newDocument('root', 'http://example.com');
    assert_contains('xmlns="http://example.com"', $nsDoc->xmlText(), 'ns');
});

run_test('XmlBeans::validate', function() {
    $xsd = '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
        <xs:element name="val" type="xs:int"/>
    </xs:schema>';
    assert_true(XmlBeans::validate('<val>42</val>', $xsd), 'valid');
    assert_false(XmlBeans::validate('<val>abc</val>', $xsd), 'invalid');
});

run_test('XmlBeans type system registry', function() {
    $ts = new SchemaTypeSystem('test-ts');
    XmlBeans::registerTypeSystem('test', $ts);
    assert_true(XmlBeans::getTypeSystem('test') === $ts, 'registered');
    assert_eq(null, XmlBeans::getTypeSystem('nonexistent'), 'not found');
});

run_test('XmlBeans::resolveType built-in', function() {
    $qn = new QName('http://www.w3.org/2001/XMLSchema', 'string');
    $found = XmlBeans::resolveType($qn);
    assert_neq_null($found, 'found');
    assert_eq(SchemaType::BTC_STRING, $found->getBuiltinTypeCode(), 'btc');
});

run_test('XmlBeans::getVersion', function() {
    assert_eq('1.0.0', XmlBeans::getVersion(), 'version');
});

// ============================
echo "\n\033[1m[End-to-End]\033[0m\n";
// ============================

run_test('Compile + Parse + Navigate', function() use ($fixtureDir) {
    $ts = XmlBeans::compileXsd($fixtureDir . '/purchaseOrder.xsd', 'po');
    $obj = XmlBeans::parseFile($fixtureDir . '/purchaseOrder.xml');
    $c = $obj->newCursor();
    $c->toFirstChild(); // purchaseOrder
    assert_eq('2024-03-15', $c->getAttributeText('orderDate'), 'orderDate');
    assert_true($c->toFirstChild(), 'to shipTo');
    assert_eq('shipTo', $c->getName()->getLocalPart(), 'shipTo');
    assert_true($c->toFirstChild(), 'to name');
    assert_eq('name', $c->getName()->getLocalPart(), 'name elem');
    assert_eq('Alice Smith', $c->getTextValue(), 'name value');

    $usAddr = $ts->findType(new QName('http://example.com/purchase', 'USAddress'));
    assert_neq_null($usAddr, 'USAddress in type system');
    $nameProp = $usAddr->getElementProperty(new QName('http://example.com/purchase', 'name'));
    assert_eq('getName', $nameProp->getGetterName(), 'getter name');
});

run_test('Save to file', function() {
    $obj = XmlBeans::parse('<data><item>test</item></data>');
    $tmp = sys_get_temp_dir() . '/xmlbeans-test-' . uniqid() . '.xml';
    $obj->save($tmp);
    assert_true(file_exists($tmp), 'file exists');
    assert_contains('<item>test</item>', file_get_contents($tmp), 'content');
    unlink($tmp);
});

// ============================
// XPath Subsystem Tests
// ============================
echo "\n\033[1m[XPath Subsystem]\033[0m\n";

use XmlBeans\XPath\XPathNamespaceContext;
use XmlBeans\XPath\XPathExpression;
use XmlBeans\XPath\XPathBuilder;
use XmlBeans\XPath\XPathPredicate;
use XmlBeans\XPath\XPathEngine;

$xpathXml = '<catalog xmlns:bk="http://example.com/books">
  <bk:book id="1" price="29.99">
    <bk:title>PHP Basics</bk:title>
    <bk:author>Jane Doe</bk:author>
  </bk:book>
  <bk:book id="2" price="39.99">
    <bk:title>Advanced PHP</bk:title>
    <bk:author>John Smith</bk:author>
  </bk:book>
  <bk:book id="3" price="19.99">
    <bk:title>XML Mastery</bk:title>
    <bk:author>Jane Doe</bk:author>
  </bk:book>
</catalog>';

// --- XPathNamespaceContext ---
run_test('XPathNamespaceContext: register and lookup', function() {
    $ctx = new XPathNamespaceContext();
    $ctx->registerNamespace('bk', 'http://example.com/books');
    assert_eq('http://example.com/books', $ctx->getNamespaceUri('bk'), 'lookup by prefix');
    assert_eq('bk', $ctx->getPrefix('http://example.com/books'), 'lookup by URI');
    assert_true($ctx->hasPrefix('bk'), 'hasPrefix');
    assert_true($ctx->hasPrefix('xml'), 'xml prefix always present');
});

run_test('XPathNamespaceContext: register multiple', function() {
    $ctx = new XPathNamespaceContext();
    $ctx->registerNamespaces(array(
        'bk' => 'http://example.com/books',
        'ns' => 'http://example.com/ns'
    ));
    assert_eq('http://example.com/books', $ctx->getNamespaceUri('bk'), 'first ns');
    assert_eq('http://example.com/ns', $ctx->getNamespaceUri('ns'), 'second ns');
});

run_test('XPathNamespaceContext: unregister', function() {
    $ctx = new XPathNamespaceContext();
    $ctx->registerNamespace('bk', 'http://example.com/books');
    $ctx->unregisterNamespace('bk');
    assert_eq(null, $ctx->getNamespaceUri('bk'), 'removed');
    // Cannot unregister xml
    $ctx->unregisterNamespace('xml');
    assert_true($ctx->hasPrefix('xml'), 'xml cannot be unregistered');
});

run_test('XPathNamespaceContext: default namespace', function() {
    $ctx = new XPathNamespaceContext();
    $ctx->setDefaultNamespace('http://example.com/default');
    assert_eq('http://example.com/default', $ctx->getDefaultNamespace(), 'default ns');
});

run_test('XPathNamespaceContext: load from document', function() use ($xpathXml) {
    $doc = new DOMDocument();
    $doc->loadXML($xpathXml);
    $ctx = new XPathNamespaceContext();
    $ctx->loadFromDocument($doc);
    assert_eq('http://example.com/books', $ctx->getNamespaceUri('bk'), 'auto-detected bk prefix');
});

run_test('XPathNamespaceContext: schema namespaces', function() {
    $ctx = new XPathNamespaceContext();
    $ctx->registerSchemaNamespaces();
    assert_eq(XPathNamespaceContext::NS_XSD, $ctx->getNamespaceUri('xs'), 'xs');
    assert_eq(XPathNamespaceContext::NS_XSI, $ctx->getNamespaceUri('xsi'), 'xsi');
});

run_test('XPathNamespaceContext: merge and copy', function() {
    $ctx1 = new XPathNamespaceContext();
    $ctx1->registerNamespace('a', 'http://example.com/a');
    $ctx2 = new XPathNamespaceContext();
    $ctx2->registerNamespace('b', 'http://example.com/b');
    $ctx2->registerNamespace('a', 'http://example.com/a-different');

    $ctx1->merge($ctx2);
    assert_eq('http://example.com/a', $ctx1->getNamespaceUri('a'), 'existing not overwritten');
    assert_eq('http://example.com/b', $ctx1->getNamespaceUri('b'), 'new merged in');

    $copy = $ctx1->copy();
    assert_eq('http://example.com/a', $copy->getNamespaceUri('a'), 'copy works');
});

run_test('XPathNamespaceContext: clear', function() {
    $ctx = new XPathNamespaceContext();
    $ctx->registerNamespace('bk', 'http://example.com/books');
    $ctx->clear();
    assert_eq(null, $ctx->getNamespaceUri('bk'), 'cleared');
    assert_true($ctx->hasPrefix('xml'), 'xml survives clear');
});

run_test('XPathNamespaceContext: apply to DOMXPath', function() use ($xpathXml) {
    $doc = new DOMDocument();
    $doc->loadXML($xpathXml);
    $ctx = new XPathNamespaceContext();
    $ctx->registerNamespace('bk', 'http://example.com/books');
    $xpath = new DOMXPath($doc);
    $ctx->applyTo($xpath);
    $result = $xpath->query('//bk:book');
    assert_eq(3, $result->length, 'query with applied context');
});

// --- XPathExpression ---
run_test('XPathExpression: compile and cache', function() {
    XPathExpression::clearCache();
    $expr1 = XPathExpression::compile('//book');
    $expr2 = XPathExpression::compile('//book');
    assert_eq('//book', $expr1->getExpression(), 'expression preserved');
    assert_eq(1, XPathExpression::getCacheSize(), 'cached');
});

run_test('XPathExpression: variable binding', function() {
    $expr = new XPathExpression('//book[@id=$bookId]');
    $expr->bindVariable('bookId', '1');
    $normalized = $expr->getNormalizedExpression();
    assert_contains("'1'", $normalized, 'variable substituted');
});

run_test('XPathExpression: variable names extraction', function() {
    $expr = new XPathExpression('//book[@id=$id and @price > $minPrice]');
    $names = $expr->getVariableNames();
    assert_true(in_array('id', $names), 'found id');
    assert_true(in_array('minPrice', $names), 'found minPrice');
});

run_test('XPathExpression: boolean variable', function() {
    $expr = new XPathExpression('//item[$available]');
    $expr->bindVariable('available', true);
    assert_contains('true()', $expr->getNormalizedExpression(), 'bool true');
});

run_test('XPathExpression: numeric variable', function() {
    $expr = new XPathExpression('//item[@price > $min]');
    $expr->bindVariable('min', 25);
    assert_contains('25', $expr->getNormalizedExpression(), 'numeric');
});

run_test('XPathExpression: isAbsolute and hasPredicate', function() {
    $abs = new XPathExpression('/root/child');
    assert_true($abs->isAbsolute(), 'absolute');
    assert_true(!$abs->hasPredicate(), 'no predicate');

    $rel = new XPathExpression('child[@id=1]');
    assert_true(!$rel->isAbsolute(), 'relative');
    assert_true($rel->hasPredicate(), 'has predicate');
});

run_test('XPathExpression: copy independence', function() {
    $expr = XPathExpression::compile('//test');
    $copy = $expr->copy();
    $copy->bindVariable('x', 'hello');
    assert_true(empty($expr->getVariables()), 'original unmodified');
    assert_eq(1, count($copy->getVariables()), 'copy has variable');
});

// --- XPathBuilder ---
run_test('XPathBuilder: basic child path', function() {
    $path = XPathBuilder::create()->root()->child('catalog')->child('book')->build();
    assert_eq('/catalog/book', $path, 'basic child path');
});

run_test('XPathBuilder: descendant', function() {
    $path = XPathBuilder::create()->descendant('book')->build();
    assert_eq('//book', $path, 'descendant');
});

run_test('XPathBuilder: with predicate', function() {
    $path = XPathBuilder::create()
        ->descendant('book')
        ->where(XPathBuilder::attr('id')->eq('1'))
        ->build();
    assert_contains("@id = '1'", $path, 'predicate in path');
});

run_test('XPathBuilder: positional', function() {
    $path = XPathBuilder::create()->descendant('book')->first()->build();
    assert_eq('//book[1]', $path, 'first');

    $path2 = XPathBuilder::create()->descendant('book')->last()->build();
    assert_eq('//book[last()]', $path2, 'last');
});

run_test('XPathBuilder: attribute axis', function() {
    $path = XPathBuilder::create()->descendant('book')->attribute('price')->build();
    assert_contains('@price', $path, 'attribute axis');
});

run_test('XPathBuilder: text node', function() {
    $path = XPathBuilder::create()->descendant('title')->text()->build();
    assert_contains('text()', $path, 'text()');
});

run_test('XPathBuilder: namespace-prefixed', function() {
    $path = XPathBuilder::create()->descendant('item')->nsChild('bk', 'title')->build();
    assert_contains('bk:title', $path, 'ns child');
});

run_test('XPathBuilder: union', function() {
    $path = XPathBuilder::create()
        ->descendant('title')
        ->union('//author')
        ->build();
    assert_contains(' | //author', $path, 'union');
});

run_test('XPathBuilder: compile to XPathExpression', function() {
    $expr = XPathBuilder::create()->descendant('book')->compile();
    assert_true($expr instanceof XPathExpression, 'compiled to XPathExpression');
    assert_eq('//book', $expr->getExpression(), 'expression correct');
});

// --- XPathPredicate ---
run_test('XPathPredicate: comparisons', function() {
    assert_eq("@id = '1'", XPathBuilder::attr('id')->eq('1')->toXPath(), 'eq');
    assert_eq("@id != '2'", XPathBuilder::attr('id')->ne('2')->toXPath(), 'ne');
    assert_eq('@price > 20', XPathBuilder::attr('price')->gt(20)->toXPath(), 'gt');
    assert_eq('@price >= 20', XPathBuilder::attr('price')->gte(20)->toXPath(), 'gte');
    assert_eq('@price < 50', XPathBuilder::attr('price')->lt(50)->toXPath(), 'lt');
    assert_eq('@price <= 50', XPathBuilder::attr('price')->lte(50)->toXPath(), 'lte');
});

run_test('XPathPredicate: logical operators', function() {
    $pred = XPathBuilder::attr('price')->gt(10)->andPred(XPathBuilder::attr('price')->lt(50));
    assert_contains('and', $pred->toXPath(), 'and');

    $pred2 = XPathBuilder::attr('type')->eq('fiction')->orPred(XPathBuilder::attr('type')->eq('sci-fi'));
    assert_contains('or', $pred2->toXPath(), 'or');
});

run_test('XPathPredicate: string functions', function() {
    $pred = XPathBuilder::attr('name')->contains('PHP');
    assert_contains("contains(@name, 'PHP')", $pred->toXPath(), 'contains');

    $pred2 = XPathBuilder::attr('name')->startsWith('Adv');
    assert_contains("starts-with(@name, 'Adv')", $pred2->toXPath(), 'starts-with');
});

run_test('XPathPredicate: normalizeSpace and stringLength', function() {
    $pred = XPathBuilder::elem('title')->normalizeSpace();
    assert_contains('normalize-space(title)', $pred->toXPath(), 'normalize-space');

    $pred2 = XPathBuilder::elem('title')->stringLength();
    assert_contains('string-length(title)', $pred2->toXPath(), 'string-length');
});

// --- XPathEngine ---
run_test('XPathEngine: basic query', function() use ($xpathXml) {
    $engine = XPathEngine::fromXml($xpathXml);
    $result = $engine->query('//bk:book', null);
    assert_eq(3, $result->length, 'found 3 books');
});

run_test('XPathEngine: selectTyped', function() use ($xpathXml) {
    $engine = XPathEngine::fromXml($xpathXml);
    $books = $engine->selectTyped('//bk:book');
    assert_eq(3, count($books), '3 typed results');
    assert_true($books[0] instanceof XmlObjectBase, 'typed as XmlObjectBase');
});

run_test('XPathEngine: selectFirst', function() use ($xpathXml) {
    $engine = XPathEngine::fromXml($xpathXml);
    $first = $engine->selectFirst('//bk:title');
    assert_neq_null($first, 'found first');
    assert_eq('PHP Basics', $first->getStringValue(), 'correct value');
});

run_test('XPathEngine: selectString', function() use ($xpathXml) {
    $engine = XPathEngine::fromXml($xpathXml);
    $title = $engine->selectString('//bk:title[1]');
    assert_eq('PHP Basics', $title, 'string value');
});

run_test('XPathEngine: selectNumber', function() use ($xpathXml) {
    $engine = XPathEngine::fromXml($xpathXml);
    $count = $engine->selectNumber('count(//bk:book)');
    assert_eq(3.0, $count, 'count as number');
});

run_test('XPathEngine: selectBoolean', function() use ($xpathXml) {
    $engine = XPathEngine::fromXml($xpathXml);
    assert_true($engine->selectBoolean('//bk:book[@id="1"]'), 'exists');
    assert_true(!$engine->selectBoolean('//bk:book[@id="99"]'), 'not exists');
});

run_test('XPathEngine: selectStrings', function() use ($xpathXml) {
    $engine = XPathEngine::fromXml($xpathXml);
    $titles = $engine->selectStrings('//bk:title');
    assert_eq(3, count($titles), '3 titles');
    assert_eq('PHP Basics', $titles[0], 'first title');
    assert_eq('Advanced PHP', $titles[1], 'second title');
    assert_eq('XML Mastery', $titles[2], 'third title');
});

run_test('XPathEngine: count and exists', function() use ($xpathXml) {
    $engine = XPathEngine::fromXml($xpathXml);
    assert_eq(3, $engine->count('//bk:book'), 'count');
    assert_true($engine->exists('//bk:book'), 'exists');
    assert_true(!$engine->exists('//bk:nonexistent'), 'not exists');
});

run_test('XPathEngine: selectChildren', function() use ($xpathXml) {
    $engine = XPathEngine::fromXml($xpathXml);
    $doc = $engine->getDocument();
    $books = $engine->selectChildren($doc->documentElement, 'book', 'http://example.com/books');
    assert_eq(3, count($books), '3 book children');
});

run_test('XPathEngine: selectElements', function() use ($xpathXml) {
    $engine = XPathEngine::fromXml($xpathXml);
    $doc = $engine->getDocument();
    $titles = $engine->selectElements($doc->documentElement, 'title', 'http://example.com/books');
    assert_eq(3, count($titles), '3 title elements');
});

run_test('XPathEngine: with XPathExpression', function() use ($xpathXml) {
    $engine = XPathEngine::fromXml($xpathXml);
    $ctx = new XPathNamespaceContext();
    $ctx->registerNamespace('bk', 'http://example.com/books');
    $expr = XPathExpression::compile('//bk:book[@id=$bookId]', $ctx);
    $expr->bindVariable('bookId', '2');
    $books = $engine->selectTyped($expr);
    assert_eq(1, count($books), 'found by variable');
});

run_test('XPathEngine: with XPathBuilder', function() use ($xpathXml) {
    $engine = XPathEngine::fromXml($xpathXml);
    $ctx = new XPathNamespaceContext();
    $ctx->registerNamespace('bk', 'http://example.com/books');
    $expr = XPathBuilder::create()
        ->descendant('bk:book')
        ->where(XPathBuilder::attr('price')->gt(25))
        ->withNamespaces($ctx)
        ->compile();
    $books = $engine->selectTyped($expr);
    assert_eq(2, count($books), 'expensive books');
});

// --- XmlObjectBase XPath integration ---
run_test('XmlObjectBase: selectChildren', function() use ($xpathXml) {
    $obj = XmlObjectBase::parse($xpathXml);
    $books = $obj->selectChildren('book', 'http://example.com/books');
    assert_eq(3, count($books), 'selectChildren found books');
});

run_test('XmlObjectBase: selectElements', function() use ($xpathXml) {
    $obj = XmlObjectBase::parse($xpathXml);
    $titles = $obj->selectElements('title', 'http://example.com/books');
    assert_eq(3, count($titles), 'selectElements found titles');
});

run_test('XmlObjectBase: selectPathTyped', function() use ($xpathXml) {
    $obj = XmlObjectBase::parse($xpathXml);
    $ctx = new XPathNamespaceContext();
    $ctx->registerNamespace('bk', 'http://example.com/books');
    $books = $obj->selectPathTyped('//bk:book', $ctx);
    assert_eq(3, count($books), 'selectPathTyped');
});

run_test('XmlObjectBase: selectPathString', function() use ($xpathXml) {
    $obj = XmlObjectBase::parse($xpathXml);
    $title = $obj->selectPathString('count(//*)');
    assert_true(is_string($title), 'returns string');
});

run_test('XmlObjectBase: selectPathNumber', function() use ($xpathXml) {
    $obj = XmlObjectBase::parse($xpathXml);
    $count = $obj->selectPathNumber('count(//*)');
    assert_true($count > 0, 'returns number');
});

run_test('XmlObjectBase: selectPathBoolean', function() use ($xpathXml) {
    $obj = XmlObjectBase::parse($xpathXml);
    assert_true($obj->selectPathBoolean('//*'), 'returns boolean');
});

run_test('XmlObjectBase: createXPathEngine', function() use ($xpathXml) {
    $obj = XmlObjectBase::parse($xpathXml);
    $engine = $obj->createXPathEngine();
    assert_true($engine instanceof XPathEngine, 'returns engine');
});

// --- XmlCursor XPath integration ---
run_test('XmlCursor: selectPath', function() use ($xpathXml) {
    $obj = XmlObjectBase::parse($xpathXml);
    $cursor = $obj->newCursor();
    $cursor->toFirstChild(); // move to catalog
    $cursors = $cursor->selectPath('./*[local-name()="book"]');
    assert_eq(3, count($cursors), 'found 3 book cursors');
    assert_true($cursors[0] instanceof XmlCursor, 'returns cursor instances');
});

run_test('XmlCursor: selectChildren', function() use ($xpathXml) {
    $obj = XmlObjectBase::parse($xpathXml);
    $cursor = $obj->newCursor();
    $cursor->toFirstChild(); // catalog
    $children = $cursor->selectChildren('book', 'http://example.com/books');
    assert_eq(3, count($children), 'selectChildren on cursor');
});

run_test('XmlCursor: selectElements', function() use ($xpathXml) {
    $obj = XmlObjectBase::parse($xpathXml);
    $cursor = $obj->newCursor();
    $cursor->toFirstChild(); // catalog
    $elements = $cursor->selectElements('title', 'http://example.com/books');
    assert_eq(3, count($elements), 'selectElements on cursor');
});

// --- XPath with simple (non-namespaced) XML ---
$simpleXml = '<store>
  <product id="1" category="electronics" price="599">
    <name>Laptop</name>
    <brand>TechCo</brand>
  </product>
  <product id="2" category="books" price="29">
    <name>PHP Guide</name>
    <brand>BookCo</brand>
  </product>
  <product id="3" category="electronics" price="199">
    <name>Tablet</name>
    <brand>TechCo</brand>
  </product>
</store>';

run_test('XPathEngine: simple XML query', function() use ($simpleXml) {
    $engine = XPathEngine::fromXml($simpleXml);
    $products = $engine->selectTyped('//product');
    assert_eq(3, count($products), '3 products');
});

run_test('XPathEngine: attribute filter', function() use ($simpleXml) {
    $engine = XPathEngine::fromXml($simpleXml);
    $electronics = $engine->selectTyped('//product[@category="electronics"]');
    assert_eq(2, count($electronics), '2 electronics');
});

run_test('XPathEngine: numeric comparison', function() use ($simpleXml) {
    $engine = XPathEngine::fromXml($simpleXml);
    $expensive = $engine->selectTyped('//product[@price > 100]');
    assert_eq(2, count($expensive), '2 expensive products');
});

run_test('XPathEngine: text content query', function() use ($simpleXml) {
    $engine = XPathEngine::fromXml($simpleXml);
    $name = $engine->selectString('//product[@id="2"]/name');
    assert_eq('PHP Guide', $name, 'found by id');
});

run_test('XPathBuilder: complex query', function() use ($simpleXml) {
    $engine = XPathEngine::fromXml($simpleXml);
    $path = XPathBuilder::create()
        ->descendant('product')
        ->where(
            XPathBuilder::attr('category')->eq('electronics')
                ->andPred(XPathBuilder::attr('price')->lt(500))
        )
        ->child('name')
        ->build();
    $names = $engine->selectStrings($path);
    assert_eq(1, count($names), 'one cheap electronic');
    assert_eq('Tablet', $names[0], 'correct product');
});

// ============================
echo "\n\n\033[1m═══════════════════════════\033[0m\n";
if ($failed === 0) {
    echo "\033[32m  All $passed tests passed! ✓\033[0m\n";
} else {
    echo "\033[31m  $failed FAILED\033[0m, \033[32m$passed passed\033[0m\n";
    echo "\n\033[31mFailures:\033[0m\n";
    foreach ($errors as $e) {
        echo "  • $e\n";
    }
}
echo "\033[1m═══════════════════════════\033[0m\n\n";

exit($failed > 0 ? 1 : 0);
