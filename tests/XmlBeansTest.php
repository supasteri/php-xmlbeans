<?php

namespace XmlBeans\Tests;

use PHPUnit\Framework\TestCase;
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

class XmlBeansTest extends TestCase
{
    private static $fixtureDir;

    public static function setUpBeforeClass()
    {
        self::$fixtureDir = __DIR__ . '/fixtures';
    }

    // =============================================
    // QName Tests
    // =============================================

    public function testQNameBasic()
    {
        $qname = new QName('http://example.com', 'person', 'ex');
        $this->assertEquals('http://example.com', $qname->getNamespaceURI());
        $this->assertEquals('person', $qname->getLocalPart());
        $this->assertEquals('ex', $qname->getPrefix());
        $this->assertEquals('{http://example.com}person', (string) $qname);
    }

    public function testQNameNoNamespace()
    {
        $qname = new QName('', 'item');
        $this->assertEquals('item', (string) $qname);
    }

    public function testQNameFromClark()
    {
        $qname = QName::fromClark('{http://www.w3.org/2001/XMLSchema}string');
        $this->assertEquals('http://www.w3.org/2001/XMLSchema', $qname->getNamespaceURI());
        $this->assertEquals('string', $qname->getLocalPart());
    }

    public function testQNameEquals()
    {
        $a = new QName('http://example.com', 'name');
        $b = new QName('http://example.com', 'name', 'ex');
        $c = new QName('http://other.com', 'name');
        $this->assertTrue($a->equals($b)); // prefix doesn't matter
        $this->assertFalse($a->equals($c));
    }

    // =============================================
    // XmlOptions Tests
    // =============================================

    public function testXmlOptionsFluent()
    {
        $opts = new XmlOptions();
        $result = $opts->setSavePrettyPrint(true)
            ->setSavePrettyPrintIndent(4)
            ->setCharacterEncoding('UTF-8')
            ->setLoadStripComments(true);

        $this->assertSame($opts, $result);
        $this->assertTrue($opts->getSavePrettyPrint());
        $this->assertEquals(4, $opts->getSavePrettyPrintIndent());
        $this->assertEquals('UTF-8', $opts->getCharacterEncoding());
        $this->assertTrue($opts->getLoadStripComments());
    }

    public function testXmlOptionsErrorCollection()
    {
        $errors = [];
        $opts = new XmlOptions();
        $opts->setErrorCollection($errors);
        $errRef = &$opts->getValidationErrors();
        $errRef[] = new XmlError(XmlError::SEVERITY_ERROR, 'test');
        $this->assertCount(1, $errors);
    }

    // =============================================
    // Parsing Tests
    // =============================================

    public function testParseSimpleXml()
    {
        $xml = '<root><name>Alice</name><age>30</age></root>';
        $obj = XmlBeans::parse($xml);

        $this->assertInstanceOf(XmlObjectBase::class, $obj);
        $this->assertNotNull($obj->getDomNode());
    }

    public function testParseFile()
    {
        $obj = XmlBeans::parseFile(self::$fixtureDir . '/purchaseOrder.xml');
        $this->assertNotNull($obj);
        $this->assertContains('Alice Smith', $obj->getStringValue());
    }

    public function testParseInvalidXml()
    {
        $this->expectException(\RuntimeException::class);
        XmlBeans::parse('<broken><unclosed>');
    }

    public function testParsePrettyPrint()
    {
        $xml = '<root><a>1</a><b>2</b></root>';
        $obj = XmlBeans::parse($xml);
        $opts = (new XmlOptions())->setSavePrettyPrint(true);
        $output = $obj->xmlText($opts);
        $this->assertContains("\n", $output);
    }

    public function testParseStripComments()
    {
        $xml = '<root><!-- comment --><data>val</data></root>';
        $opts = (new XmlOptions())->setLoadStripComments(true);
        $obj = XmlBeans::parse($xml, null, $opts);
        $output = $obj->xmlText();
        $this->assertNotContains('comment', $output);
    }

    public function testXmlTextInner()
    {
        $xml = '<root><child>hello</child></root>';
        $obj = XmlBeans::parse($xml);
        $cursor = $obj->newCursor();
        $cursor->toFirstChild(); // root
        $inner = new XmlObjectBase($cursor->getDomNode());
        $opts = (new XmlOptions())->setSaveInner(true);
        $innerXml = $inner->xmlText($opts);
        $this->assertContains('hello', $innerXml);
    }

    // =============================================
    // Copy / Nil Tests
    // =============================================

    public function testCopy()
    {
        $xml = '<root><name>Test</name></root>';
        $obj = XmlBeans::parse($xml);
        $copy = $obj->copy();
        $this->assertContains('Test', $copy->xmlText());
        // Modify original shouldn't affect copy
        $obj->setStringValue('Modified');
        $this->assertContains('Test', $copy->xmlText());
    }

    public function testNil()
    {
        $xml = '<root xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><item xsi:nil="true"/></root>';
        $obj = XmlBeans::parse($xml);
        $cursor = $obj->newCursor();
        $cursor->toFirstChild(); // root
        $cursor->toFirstChild(); // item
        $itemObj = new XmlObjectBase($cursor->getDomNode());
        $this->assertTrue($itemObj->isNil());

        $itemObj->setNil(false);
        $this->assertFalse($itemObj->isNil());
    }

    // =============================================
    // XPath / Select Tests
    // =============================================

    public function testSelectPath()
    {
        $xml = '<root><item>A</item><item>B</item><item>C</item></root>';
        $obj = XmlBeans::parse($xml);
        $results = $obj->selectPath('//item');
        $this->assertCount(3, $results);
        $this->assertEquals('A', $results[0]->getStringValue());
        $this->assertEquals('C', $results[2]->getStringValue());
    }

    public function testSelectAttribute()
    {
        $xml = '<root lang="en"><item id="1">First</item></root>';
        $obj = XmlBeans::parse($xml);
        $result = $obj->selectAttribute('/root/@lang');
        $this->assertNotNull($result);
        $this->assertEquals('en', $result->getStringValue());
    }

    // =============================================
    // XmlCursor Tests
    // =============================================

    public function testCursorNavigation()
    {
        $xml = '<root><a>1</a><b>2</b><c>3</c></root>';
        $obj = XmlBeans::parse($xml);
        $cursor = $obj->newCursor();

        // Start at document
        $this->assertEquals(XmlCursor::TOKEN_STARTDOC, $cursor->currentTokenType());

        // Move to root
        $this->assertTrue($cursor->toFirstChild());
        $this->assertEquals(XmlCursor::TOKEN_START, $cursor->currentTokenType());
        $this->assertEquals('root', $cursor->getName()->getLocalPart());

        // Move to first child
        $this->assertTrue($cursor->toFirstChild());
        $this->assertEquals('a', $cursor->getName()->getLocalPart());
        $this->assertEquals('1', $cursor->getTextValue());

        // Move to next sibling
        $this->assertTrue($cursor->toNextSibling());
        $this->assertEquals('b', $cursor->getName()->getLocalPart());
        $this->assertEquals('2', $cursor->getTextValue());

        $this->assertTrue($cursor->toNextSibling());
        $this->assertEquals('c', $cursor->getName()->getLocalPart());

        // No more siblings
        $this->assertFalse($cursor->toNextSibling());

        // Back to parent
        $this->assertTrue($cursor->toParent());
        $this->assertEquals('root', $cursor->getName()->getLocalPart());
    }

    public function testCursorToChild()
    {
        $xml = '<root><first>1</first><second>2</second></root>';
        $obj = XmlBeans::parse($xml);
        $cursor = $obj->newCursor();
        $cursor->toFirstChild(); // root

        $this->assertTrue($cursor->toChild('second'));
        $this->assertEquals('2', $cursor->getTextValue());
    }

    public function testCursorTokenTypes()
    {
        $this->assertEquals('STARTDOC', XmlCursor::tokenTypeName(XmlCursor::TOKEN_STARTDOC));
        $this->assertEquals('START', XmlCursor::tokenTypeName(XmlCursor::TOKEN_START));
        $this->assertEquals('END', XmlCursor::tokenTypeName(XmlCursor::TOKEN_END));
        $this->assertEquals('TEXT', XmlCursor::tokenTypeName(XmlCursor::TOKEN_TEXT));
    }

    public function testCursorModification()
    {
        $xml = '<root><name>Old</name></root>';
        $obj = XmlBeans::parse($xml);
        $cursor = $obj->newCursor();
        $cursor->toFirstChild(); // root
        $cursor->toChild('name');
        $cursor->setTextValue('New');
        $this->assertEquals('New', $cursor->getTextValue());
        $this->assertContains('New', $obj->xmlText());
    }

    public function testCursorAttributes()
    {
        $xml = '<root id="100" name="test"><child/></root>';
        $obj = XmlBeans::parse($xml);
        $cursor = $obj->newCursor();
        $cursor->toFirstChild(); // root

        $this->assertEquals('100', $cursor->getAttributeText('id'));
        $this->assertEquals('test', $cursor->getAttributeText('name'));

        $cursor->setAttributeText('id', '200');
        $this->assertEquals('200', $cursor->getAttributeText('id'));

        $this->assertTrue($cursor->toFirstAttribute());
    }

    public function testCursorBookmarks()
    {
        $xml = '<root><a>1</a><b>2</b></root>';
        $obj = XmlBeans::parse($xml);
        $cursor = $obj->newCursor();
        $cursor->toFirstChild(); // root
        $cursor->toFirstChild(); // a
        $cursor->setBookmark('position_a');

        $cursor->toNextSibling(); // b
        $this->assertEquals('b', $cursor->getName()->getLocalPart());

        $this->assertTrue($cursor->toBookmark('position_a'));
        $this->assertEquals('a', $cursor->getName()->getLocalPart());
    }

    public function testCursorInsert()
    {
        $xml = '<root><a>1</a></root>';
        $obj = XmlBeans::parse($xml);
        $cursor = $obj->newCursor();
        $cursor->toFirstChild(); // root
        $cursor->beginElement('b');
        $cursor->insertChars('2');

        $output = $obj->xmlText();
        $this->assertContains('<b>', $output);
        $this->assertContains('2', $output);
    }

    public function testCursorRemove()
    {
        $xml = '<root><a>1</a><b>2</b></root>';
        $obj = XmlBeans::parse($xml);
        $cursor = $obj->newCursor();
        $cursor->toFirstChild(); // root
        $cursor->toChild('a');
        $this->assertTrue($cursor->removeXml());
        $this->assertNotContains('<a>', $obj->xmlText());
    }

    public function testCursorPrevSibling()
    {
        $xml = '<root><a/><b/><c/></root>';
        $obj = XmlBeans::parse($xml);
        $cursor = $obj->newCursor();
        $cursor->toFirstChild();
        $cursor->toLastChild();
        $this->assertEquals('c', $cursor->getName()->getLocalPart());
        $this->assertTrue($cursor->toPrevSibling());
        $this->assertEquals('b', $cursor->getName()->getLocalPart());
    }

    public function testCursorStartEndDoc()
    {
        $xml = '<root/>';
        $obj = XmlBeans::parse($xml);
        $cursor = $obj->newCursor();
        $cursor->toEndDoc();
        $this->assertTrue($cursor->isAtEnd());
        $cursor->toStartDoc();
        $this->assertFalse($cursor->isAtEnd());
        $this->assertEquals(XmlCursor::TOKEN_STARTDOC, $cursor->currentTokenType());
    }

    // =============================================
    // Built-in Type System Tests
    // =============================================

    public function testBuiltinTypeSystem()
    {
        $ts = BuiltinTypeSystem::get();
        $this->assertInstanceOf(SchemaTypeSystem::class, $ts);

        $stringType = BuiltinTypeSystem::findByLocalName('string');
        $this->assertNotNull($stringType);
        $this->assertTrue($stringType->isSimpleType());
        $this->assertEquals(SchemaType::BTC_STRING, $stringType->getBuiltinTypeCode());
    }

    public function testBuiltinTypeHierarchy()
    {
        $intType = BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_INT);
        $this->assertNotNull($intType);

        $longType = $intType->getBaseType();
        $this->assertNotNull($longType);
        $this->assertEquals(SchemaType::BTC_LONG, $longType->getBuiltinTypeCode());

        $integerType = $longType->getBaseType();
        $this->assertNotNull($integerType);
        $this->assertEquals(SchemaType::BTC_INTEGER, $integerType->getBuiltinTypeCode());
    }

    public function testBuiltinTypeFacets()
    {
        $intType = BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_INT);
        $this->assertEquals('-2147483648', $intType->getFacet('minInclusive'));
        $this->assertEquals('2147483647', $intType->getFacet('maxInclusive'));

        $boolType = BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_BOOLEAN);
        $this->assertContains('true', $boolType->getEnumerationValues());
    }

    // =============================================
    // Simple Type Wrapper Tests
    // =============================================

    public function testXmlString()
    {
        $xml = '<value>hello world</value>';
        $obj = XmlBeans::parse($xml);
        $typed = new Type\XmlString($obj->getDomNode()->documentElement);
        $this->assertEquals('hello world', $typed->getValue());

        $typed->setValue('new value');
        $this->assertEquals('new value', $typed->getValue());
    }

    public function testXmlBoolean()
    {
        $xml = '<flag>true</flag>';
        $obj = XmlBeans::parse($xml);
        $typed = new Type\XmlBoolean($obj->getDomNode()->documentElement);
        $this->assertTrue($typed->getValue());

        $typed->setValue(false);
        $this->assertFalse($typed->getValue());
        $this->assertEquals('false', $typed->getStringValue());
    }

    public function testXmlInt()
    {
        $xml = '<count>42</count>';
        $obj = XmlBeans::parse($xml);
        $typed = new Type\XmlInt($obj->getDomNode()->documentElement);
        $this->assertSame(42, $typed->getValue());

        $typed->setValue(99);
        $this->assertSame(99, $typed->getValue());
    }

    public function testXmlFloat()
    {
        $xml = '<price>19.99</price>';
        $obj = XmlBeans::parse($xml);
        $typed = new Type\XmlFloat($obj->getDomNode()->documentElement);
        $this->assertEqualsWithDelta(19.99, $typed->getValue(), 0.001);

        // Test special values
        $xml2 = '<val>INF</val>';
        $obj2 = XmlBeans::parse($xml2);
        $typed2 = new Type\XmlFloat($obj2->getDomNode()->documentElement);
        $this->assertTrue(is_infinite($typed2->getValue()));
    }

    public function testXmlDecimal()
    {
        $xml = '<amount>123.456</amount>';
        $obj = XmlBeans::parse($xml);
        $typed = new Type\XmlDecimal($obj->getDomNode()->documentElement);
        $this->assertEqualsWithDelta(123.456, $typed->getValue(), 0.001);
    }

    public function testXmlDate()
    {
        $xml = '<date>2024-03-15</date>';
        $obj = XmlBeans::parse($xml);
        $typed = new Type\XmlDate($obj->getDomNode()->documentElement);
        $val = $typed->getValue();
        $this->assertInstanceOf(\DateTime::class, $val);
        $this->assertEquals('2024-03-15', $val->format('Y-m-d'));
    }

    public function testXmlDateTime()
    {
        $xml = '<ts>2024-03-15T10:30:00+00:00</ts>';
        $obj = XmlBeans::parse($xml);
        $typed = new Type\XmlDateTime($obj->getDomNode()->documentElement);
        $val = $typed->getValue();
        $this->assertInstanceOf(\DateTime::class, $val);
    }

    public function testXmlBase64Binary()
    {
        $raw = 'Hello, World!';
        $b64 = base64_encode($raw);
        $xml = "<data>{$b64}</data>";
        $obj = XmlBeans::parse($xml);
        $typed = new Type\XmlBase64Binary($obj->getDomNode()->documentElement);
        $this->assertEquals($raw, $typed->getValue());
    }

    public function testXmlHexBinary()
    {
        $raw = 'AB';
        $hex = strtoupper(bin2hex($raw));
        $xml = "<data>{$hex}</data>";
        $obj = XmlBeans::parse($xml);
        $typed = new Type\XmlHexBinary($obj->getDomNode()->documentElement);
        $this->assertEquals($raw, $typed->getValue());
    }

    public function testXmlToken()
    {
        $xml = '<tok>  hello   world  </tok>';
        $obj = XmlBeans::parse($xml);
        $typed = new Type\XmlToken($obj->getDomNode()->documentElement);
        $this->assertEquals('hello world', $typed->getValue());
    }

    public function testXmlNormalizedString()
    {
        $xml = "<ns>hello\tworld\nnewline</ns>";
        $obj = XmlBeans::parse($xml);
        $typed = new Type\XmlNormalizedString($obj->getDomNode()->documentElement);
        $val = $typed->getValue();
        $this->assertNotContains("\t", $val);
    }

    // =============================================
    // TypeMapper Tests
    // =============================================

    public function testTypeMapperBuiltin()
    {
        $intType = BuiltinTypeSystem::getBuiltinType(SchemaType::BTC_INT);
        $obj = TypeMapper::createTypedObject($intType);
        $this->assertInstanceOf(Type\XmlInt::class, $obj);
    }

    public function testTypeMapperFallback()
    {
        $customType = new SchemaType(new QName('http://test.com', 'Custom'));
        $obj = TypeMapper::createTypedObject($customType);
        $this->assertInstanceOf(XmlObjectBase::class, $obj);
    }

    // =============================================
    // Schema Compiler Tests
    // =============================================

    public function testCompilePurchaseOrder()
    {
        $compiler = new SchemaCompiler();
        $ts = $compiler->compile(self::$fixtureDir . '/purchaseOrder.xsd');

        $this->assertInstanceOf(SchemaTypeSystem::class, $ts);
        $this->assertEmpty($compiler->getErrors(), 'Compilation should have no errors');

        // Check global types
        $types = $ts->getGlobalTypes();
        $this->assertGreaterThanOrEqual(5, count($types)); // USState, SKU, Quantity, USAddress, Item, Items, PurchaseOrderType

        // Find USAddress
        $usAddress = $ts->findType(new QName('http://example.com/purchase', 'USAddress'));
        $this->assertNotNull($usAddress, 'USAddress type should be found');
        $this->assertFalse($usAddress->isSimpleType());

        // Check element properties
        $nameProp = $usAddress->getElementProperty(new QName('http://example.com/purchase', 'name'));
        $this->assertNotNull($nameProp, 'USAddress should have "name" element');
        $this->assertTrue($nameProp->isRequired());

        $streetProp = $usAddress->getElementProperty(new QName('http://example.com/purchase', 'street'));
        $this->assertNotNull($streetProp);

        // Check attribute
        $countryAttr = $usAddress->getAttributeProperty(new QName('', 'country'));
        $this->assertNotNull($countryAttr, 'USAddress should have "country" attribute');
        $this->assertEquals('US', $countryAttr->getFixedValue());

        // Check simple type with enumeration
        $usState = $ts->findType(new QName('http://example.com/purchase', 'USState'));
        $this->assertNotNull($usState);
        $this->assertTrue($usState->isSimpleType());
        $enums = $usState->getEnumerationValues();
        $this->assertContains('CA', $enums);
        $this->assertContains('NY', $enums);

        // Check simple type with pattern
        $sku = $ts->findType(new QName('http://example.com/purchase', 'SKU'));
        $this->assertNotNull($sku);
        $this->assertNotNull($sku->getPatternFacet());

        // Check Quantity type with range facet
        $qty = $ts->findType(new QName('http://example.com/purchase', 'Quantity'));
        $this->assertNotNull($qty);
        $this->assertEquals('9999', $qty->getFacet('maxInclusive'));

        // Check document types
        $docTypes = $ts->getDocumentTypes();
        $this->assertGreaterThanOrEqual(1, count($docTypes));

        $poDocs = $ts->findDocumentType(new QName('http://example.com/purchase', 'purchaseOrder'));
        $this->assertNotNull($poDocs);
        $this->assertTrue($poDocs->isDocumentType());
    }

    public function testCompileXsdString()
    {
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
        $phoneType = $ts->findType(new QName('', 'PhoneNumber'));
        $this->assertNotNull($phoneType);
        $this->assertTrue($phoneType->isSimpleType());
        $this->assertNotNull($phoneType->getPatternFacet());
    }

    public function testCompileComplexContentExtension()
    {
        $compiler = new SchemaCompiler();
        $ts = $compiler->compile(self::$fixtureDir . '/purchaseOrder.xsd');

        $ukAddress = $ts->findType(new QName('http://example.com/purchase', 'UKAddress'));
        $this->assertNotNull($ukAddress);
        $this->assertEquals(SchemaType::DT_EXTENSION, $ukAddress->getDerivationType());

        $baseType = $ukAddress->getBaseType();
        $this->assertNotNull($baseType);
        $this->assertEquals('USAddress', $baseType->getName()->getLocalPart());
    }

    public function testCompileItemType()
    {
        $compiler = new SchemaCompiler();
        $ts = $compiler->compile(self::$fixtureDir . '/purchaseOrder.xsd');

        $item = $ts->findType(new QName('http://example.com/purchase', 'Item'));
        $this->assertNotNull($item);

        // Check partNum required attribute
        $partNum = $item->getAttributeProperty(new QName('', 'partNum'));
        $this->assertNotNull($partNum);
        $this->assertTrue($partNum->isRequired());

        // Check optional comment element
        $comment = $item->getElementProperty(new QName('http://example.com/purchase', 'comment'));
        $this->assertNotNull($comment);
        $this->assertFalse($comment->isRequired());
        $this->assertEquals(0, $comment->getMinOccurs());
    }

    public function testCompileArrayElement()
    {
        $compiler = new SchemaCompiler();
        $ts = $compiler->compile(self::$fixtureDir . '/purchaseOrder.xsd');

        $items = $ts->findType(new QName('http://example.com/purchase', 'Items'));
        $this->assertNotNull($items);

        $itemProp = $items->getElementProperty(new QName('http://example.com/purchase', 'item'));
        $this->assertNotNull($itemProp);
        $this->assertTrue($itemProp->isArray());
        $this->assertEquals(0, $itemProp->getMinOccurs());
        $this->assertEquals(-1, $itemProp->getMaxOccurs());
    }

    // =============================================
    // Code Generation Tests
    // =============================================

    public function testCodeGeneration()
    {
        $compiler = new SchemaCompiler();
        $ts = $compiler->compile(self::$fixtureDir . '/purchaseOrder.xsd');

        $outputDir = sys_get_temp_dir() . '/php-xmlbeans-test-' . uniqid();
        $generator = new PhpCodeGenerator($ts);
        $files = $generator->generate($outputDir, 'Test\\Generated');

        $this->assertGreaterThan(0, count($files));

        // Check files were created
        foreach ($files as $file) {
            $this->assertFileExists($file);
            $content = file_get_contents($file);
            $this->assertContains('namespace Test\\Generated', $content);
            $this->assertContains('@generated', $content);
        }

        // Check USAddress class has expected methods
        $addressFile = $outputDir . '/USAddress.php';
        $this->assertFileExists($addressFile);
        $content = file_get_contents($addressFile);
        $this->assertContains('getName()', $content);
        $this->assertContains('setName(', $content);
        $this->assertContains('getStreet()', $content);
        $this->assertContains('getCity()', $content);
        $this->assertContains('getState()', $content);
        $this->assertContains('isSetName()', $content);

        // Check USState enum type
        $stateFile = $outputDir . '/USState.php';
        $this->assertFileExists($stateFile);
        $content = file_get_contents($stateFile);
        $this->assertContains('VALUE_CA', $content);
        $this->assertContains('VALUE_NY', $content);
        $this->assertContains('isValidValue', $content);

        // Check Items class has array methods
        $itemsFile = $outputDir . '/Items.php';
        $this->assertFileExists($itemsFile);
        $content = file_get_contents($itemsFile);
        $this->assertContains('getItemArray()', $content);
        $this->assertContains('sizeOfItemArray()', $content);
        $this->assertContains('addNewItem(', $content);

        // Check document class
        $poDocFile = $outputDir . '/PurchaseOrderDocument.php';
        $this->assertFileExists($poDocFile);
        $content = file_get_contents($poDocFile);
        $this->assertContains('Document', $content);
        $this->assertContains('parse(', $content);

        // Cleanup
        array_map('unlink', glob($outputDir . '/*.php'));
        rmdir($outputDir);
    }

    // =============================================
    // Validation Tests
    // =============================================

    public function testValidateWithXsd()
    {
        $xsd = '<?xml version="1.0"?>
        <xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
            <xs:element name="note">
                <xs:complexType>
                    <xs:sequence>
                        <xs:element name="to" type="xs:string"/>
                        <xs:element name="body" type="xs:string"/>
                    </xs:sequence>
                </xs:complexType>
            </xs:element>
        </xs:schema>';

        $validXml = '<note><to>Alice</to><body>Hello</body></note>';
        $this->assertTrue(Validator::validateXmlAgainstXsd($validXml, $xsd));

        $invalidXml = '<note><wrong>Fail</wrong></note>';
        $this->assertFalse(Validator::validateXmlAgainstXsd($invalidXml, $xsd));
    }

    public function testValidateSimpleTypeFacets()
    {
        $type = new SchemaType(new QName('', 'TestType'));
        $type->setIsSimpleType(true);
        $type->setContentType(SchemaType::SIMPLE_CONTENT);
        $type->setFacet('minLength', '3');
        $type->setFacet('maxLength', '10');

        $xml = '<val>AB</val>'; // Too short
        $obj = XmlBeans::parse($xml);
        $obj->setSchemaType($type);

        $errors = [];
        $opts = (new XmlOptions())->setErrorCollection($errors);
        $result = $obj->validate($opts);

        $this->assertFalse($result);
        $this->assertGreaterThan(0, count($errors));
    }

    public function testValidateEnumeration()
    {
        $type = new SchemaType(new QName('', 'Color'));
        $type->setIsSimpleType(true);
        $type->setContentType(SchemaType::SIMPLE_CONTENT);
        $type->setEnumerationValues(['red', 'green', 'blue']);

        $xml = '<color>purple</color>';
        $obj = XmlBeans::parse($xml);
        $obj->setSchemaType($type);

        $errors = [];
        $opts = (new XmlOptions())->setErrorCollection($errors);
        $result = $obj->validate($opts);

        $this->assertFalse($result);
        $this->assertGreaterThan(0, count($errors));
        $this->assertContains('enumeration', (string) $errors[0]);
    }

    public function testValidatePattern()
    {
        $type = new SchemaType(new QName('', 'ZipCode'));
        $type->setIsSimpleType(true);
        $type->setContentType(SchemaType::SIMPLE_CONTENT);
        $type->setPatternFacet('[0-9]{5}');

        // Valid
        $xml = '<zip>90210</zip>';
        $obj = XmlBeans::parse($xml);
        $obj->setSchemaType($type);
        $this->assertTrue($obj->validate());

        // Invalid
        $xml2 = '<zip>ABCDE</zip>';
        $obj2 = XmlBeans::parse($xml2);
        $obj2->setSchemaType($type);
        $this->assertFalse($obj2->validate());
    }

    public function testValidateNumericRange()
    {
        $type = new SchemaType(new QName('', 'Age'));
        $type->setIsSimpleType(true);
        $type->setContentType(SchemaType::SIMPLE_CONTENT);
        $type->setFacet('minInclusive', '0');
        $type->setFacet('maxInclusive', '150');

        $xml = '<age>200</age>';
        $obj = XmlBeans::parse($xml);
        $obj->setSchemaType($type);

        $errors = [];
        $opts = (new XmlOptions())->setErrorCollection($errors);
        $this->assertFalse($obj->validate($opts));
        $this->assertGreaterThan(0, count($errors));
    }

    public function testValidateRequiredAttribute()
    {
        $type = new SchemaType(new QName('', 'TestElem'));
        $type->setContentType(SchemaType::ELEMENT_CONTENT);

        $attrProp = new SchemaProperty(new QName('', 'id'), SchemaProperty::XML_ATTRIBUTE);
        $attrProp->setMinOccurs(1); // required
        $type->addAttributeProperty($attrProp);

        $xml = '<elem/>';  // missing required attribute
        $obj = XmlBeans::parse($xml);
        $obj->setSchemaType($type);

        $errors = [];
        $opts = (new XmlOptions())->setErrorCollection($errors);
        $this->assertFalse($obj->validate($opts));
        $this->assertContains('Required attribute', (string) $errors[0]);
    }

    public function testValidateOccurrenceConstraints()
    {
        $type = new SchemaType(new QName('', 'Parent'));
        $type->setContentType(SchemaType::ELEMENT_CONTENT);

        $childProp = new SchemaProperty(new QName('', 'child'), SchemaProperty::XML_ELEMENT);
        $childProp->setMinOccurs(2);
        $childProp->setMaxOccurs(3);
        $type->addElementProperty($childProp);

        // Too few
        $xml = '<parent><child>1</child></parent>';
        $obj = XmlBeans::parse($xml);
        $obj->setSchemaType($type);
        $errors = [];
        $opts = (new XmlOptions())->setErrorCollection($errors);
        $this->assertFalse($obj->validate($opts));
    }

    public function testValidateEmptyContent()
    {
        $type = new SchemaType(new QName('', 'Empty'));
        $type->setContentType(SchemaType::EMPTY_CONTENT);

        $xml = '<empty>should be empty</empty>';
        $obj = XmlBeans::parse($xml);
        $obj->setSchemaType($type);

        $errors = [];
        $opts = (new XmlOptions())->setErrorCollection($errors);
        $this->assertFalse($obj->validate($opts));
    }

    public function testValidateErrorListener()
    {
        $type = new SchemaType(new QName('', 'TestType'));
        $type->setIsSimpleType(true);
        $type->setContentType(SchemaType::SIMPLE_CONTENT);
        $type->setEnumerationValues(['a', 'b']);

        $xml = '<val>c</val>';
        $obj = XmlBeans::parse($xml);
        $obj->setSchemaType($type);

        $captured = [];
        $opts = (new XmlOptions())->addErrorListener(function (XmlError $err) use (&$captured) {
            $captured[] = $err;
        });

        $obj->validate($opts);
        $this->assertGreaterThan(0, count($captured));
    }

    // =============================================
    // XmlError Tests
    // =============================================

    public function testXmlError()
    {
        $err = new XmlError(XmlError::SEVERITY_ERROR, 'Test error', 'test.xml', 10, 5);
        $this->assertEquals('error', $err->getSeverityLabel());
        $this->assertEquals('Test error', $err->getMessage());
        $this->assertEquals(10, $err->getLine());
        $this->assertEquals('[test.xml:10:5] error: Test error', (string) $err);

        $warn = new XmlError(XmlError::SEVERITY_WARNING, 'A warning');
        $this->assertEquals('warning', $warn->getSeverityLabel());
    }

    // =============================================
    // SchemaType Tests
    // =============================================

    public function testSchemaTypeAssignableFrom()
    {
        $parent = new SchemaType(new QName('', 'Parent'));
        $child = new SchemaType(new QName('', 'Child'));
        $child->setBaseType($parent);

        $this->assertTrue($parent->isAssignableFrom($child));
        $this->assertFalse($child->isAssignableFrom($parent));
    }

    public function testSchemaTypeToString()
    {
        $named = new SchemaType(new QName('http://test.com', 'MyType'));
        $this->assertContains('MyType', (string) $named);

        $anon = new SchemaType();
        $this->assertEquals('(anonymous)', (string) $anon);
    }

    // =============================================
    // SchemaParticle Tests
    // =============================================

    public function testSchemaParticle()
    {
        $seq = new SchemaParticle(SchemaParticle::SEQUENCE);
        $this->assertEquals(SchemaParticle::SEQUENCE, $seq->getParticleType());

        $elem = new SchemaParticle(SchemaParticle::ELEMENT);
        $elem->setName(new QName('', 'test'));
        $elem->setMaxOccurs(-1);
        $this->assertTrue($elem->isUnbounded());

        $seq->addChild($elem);
        $this->assertEquals(1, $seq->countChildren());
    }

    // =============================================
    // XmlBeans Factory Tests
    // =============================================

    public function testNewDocument()
    {
        $doc = XmlBeans::newDocument('root');
        $this->assertContains('<root/>', $doc->xmlText());

        $nsDoc = XmlBeans::newDocument('root', 'http://example.com');
        $this->assertContains('xmlns="http://example.com"', $nsDoc->xmlText());
    }

    public function testFactoryValidate()
    {
        $xsd = '<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
            <xs:element name="val" type="xs:int"/>
        </xs:schema>';

        $this->assertTrue(XmlBeans::validate('<val>42</val>', $xsd));
        $this->assertFalse(XmlBeans::validate('<val>not-int</val>', $xsd));
    }

    public function testNewOptions()
    {
        $opts = XmlBeans::newOptions();
        $this->assertInstanceOf(XmlOptions::class, $opts);
    }

    public function testGetVersion()
    {
        $this->assertEquals('1.0.0', XmlBeans::getVersion());
    }

    public function testTypeSystemRegistry()
    {
        $ts = new SchemaTypeSystem('test-ts');
        XmlBeans::registerTypeSystem('test', $ts);
        $this->assertSame($ts, XmlBeans::getTypeSystem('test'));
        $this->assertNull(XmlBeans::getTypeSystem('nonexistent'));
    }

    public function testResolveType()
    {
        // Built-in
        $qname = new QName('http://www.w3.org/2001/XMLSchema', 'string');
        $found = XmlBeans::resolveType($qname);
        $this->assertNotNull($found);
        $this->assertEquals(SchemaType::BTC_STRING, $found->getBuiltinTypeCode());
    }

    public function testGetBuiltinType()
    {
        $stringType = XmlBeans::getBuiltinType('string');
        $this->assertNotNull($stringType);
        $this->assertTrue($stringType->isBuiltinType());

        $this->assertNull(XmlBeans::getBuiltinType('nonexistentType'));
    }

    // =============================================
    // XmlObjectBase Accessor Tests
    // =============================================

    public function testChildTextAccessors()
    {
        $xml = '<person><name>Alice</name><age>30</age></person>';
        $obj = XmlBeans::parse($xml);

        $cursor = $obj->newCursor();
        $cursor->toFirstChild();
        $personObj = new XmlObjectBase($cursor->getDomNode());

        $this->assertEquals('Alice', $personObj->getStringValue());
    }

    public function testSaveToFile()
    {
        $xml = '<data><item>test</item></data>';
        $obj = XmlBeans::parse($xml);
        $tmpFile = sys_get_temp_dir() . '/xmlbeans-test-' . uniqid() . '.xml';

        $obj->save($tmpFile);
        $this->assertFileExists($tmpFile);
        $content = file_get_contents($tmpFile);
        $this->assertContains('<item>test</item>', $content);
        unlink($tmpFile);
    }

    // =============================================
    // End-to-End: Compile + Parse + Navigate
    // =============================================

    public function testEndToEnd()
    {
        // Compile schema
        $ts = XmlBeans::compileXsd(self::$fixtureDir . '/purchaseOrder.xsd', 'po');

        // Parse document
        $obj = XmlBeans::parseFile(self::$fixtureDir . '/purchaseOrder.xml');

        // Navigate with cursor
        $cursor = $obj->newCursor();
        $cursor->toFirstChild(); // purchaseOrder root

        // Read orderDate attribute
        $orderDate = $cursor->getAttributeText('orderDate');
        $this->assertEquals('2024-03-15', $orderDate);

        // Navigate to shipTo
        $this->assertTrue($cursor->toFirstChild());
        $this->assertEquals('shipTo', $cursor->getName()->getLocalPart());

        // Navigate into shipTo to find name
        $this->assertTrue($cursor->toFirstChild());
        $this->assertEquals('name', $cursor->getName()->getLocalPart());
        $this->assertEquals('Alice Smith', $cursor->getTextValue());

        // Verify schema has matching type info
        $usAddressType = $ts->findType(new QName('http://example.com/purchase', 'USAddress'));
        $this->assertNotNull($usAddressType);
        $nameProp = $usAddressType->getElementProperty(new QName('http://example.com/purchase', 'name'));
        $this->assertNotNull($nameProp);
        $this->assertEquals('getName', $nameProp->getGetterName());
    }
}
