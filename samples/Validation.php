<?php
/**
 * Validation Sample
 *
 * Demonstrates validating XML instances against a schema using XMLBeans.
 * Shows: validate(), error collection, error listener callbacks,
 * and validating modified documents.
 *
 * Ported from: https://xmlbeans.apache.org/samples/Validation.html
 */

require_once __DIR__ . '/../vendor/autoload.php';

use XmlBeans\XmlBeans;
use XmlBeans\XmlOptions;
use XmlBeans\XmlError;

echo "=== PHP XMLBeans Validation Sample ===\n\n";

$xsd = file_get_contents(__DIR__ . '/data/todolist.xsd');
$xml = file_get_contents(__DIR__ . '/data/todolist.xml');

// --- 1. Basic validation ---
echo "--- 1. Basic validation (valid document) ---\n";
$valid = XmlBeans::validate($xml, $xsd);
echo "Document is " . ($valid ? "VALID" : "INVALID") . "\n";

// --- 2. Validate with error collection ---
echo "\n--- 2. Validation with error collection (invalid document) ---\n";

$invalidXml = '<todolist xmlns="http://example.com/todolist">
    <item priority="urgent">
        <name>Test item</name>
        <description>This has an invalid priority value</description>
        <due-date>not-a-date</due-date>
        <status>unknown-status</status>
    </item>
</todolist>';

$errors = array();
$opts = (new XmlOptions())->setErrorCollection($errors);

$doc = XmlBeans::parse($invalidXml);
$typeSystem = XmlBeans::compileXsd($xsd);
$rootType = null;
foreach ($typeSystem->getGlobalTypes() as $t) {
    if ($t->getName() !== null && $t->getName()->getLocalPart() === 'todoItem') {
        $rootType = $t;
        break;
    }
}

if ($rootType !== null) {
    $doc->setSchemaType($rootType);
}

$valid = $doc->validate($opts);
echo "Document is " . ($valid ? "VALID" : "INVALID") . "\n";

if (!empty($errors)) {
    echo "Errors found: " . count($errors) . "\n";
    foreach ($errors as $err) {
        if ($err instanceof XmlError) {
            echo "  [" . $err->getSeverityName() . "] " . $err->getMessage();
            if ($err->getLine() > 0) {
                echo " (line " . $err->getLine() . ")";
            }
            echo "\n";
        } else {
            echo "  " . $err . "\n";
        }
    }
}

// --- 3. Validate with error listener callback ---
echo "\n--- 3. Validation with error listener callback ---\n";

$errorCount = 0;
$opts2 = (new XmlOptions())->addErrorListener(function (XmlError $err) use (&$errorCount) {
    $errorCount++;
    echo "  LISTENER: " . $err->getMessage() . "\n";
});

$doc2 = XmlBeans::parse($invalidXml);
$doc2->validate($opts2);
echo "Listener caught " . $errorCount . " error(s)\n";

// --- 4. Validate a valid document, then break it ---
echo "\n--- 4. Modify and re-validate ---\n";

$doc3 = XmlBeans::parse($xml);
echo "Original document valid: " . (XmlBeans::validate($xml, $xsd) ? "yes" : "no") . "\n";

// Use cursor to modify — change a valid status to invalid
$cursor = $doc3->newCursor();
$cursor->toFirstChild(); // into todolist
$cursor->toChild('item'); // first item
if ($cursor->toChild('status')) {
    $originalStatus = $cursor->getTextValue();
    $cursor->setTextValue('INVALID_STATUS');
    echo "Changed status from '" . $originalStatus . "' to 'INVALID_STATUS'\n";
}

$modifiedXml = $doc3->xmlText();
echo "Modified document valid: " . (XmlBeans::validate($modifiedXml, $xsd) ? "yes" : "no") . "\n";

// --- 5. Schema compilation validation ---
echo "\n--- 5. Schema compilation validates the schema itself ---\n";

$badSchema = '<?xml version="1.0"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
    <xs:element name="test" type="xs:nonExistentType"/>
</xs:schema>';

try {
    $ts = XmlBeans::compileXsd($badSchema);
    echo "Schema compiled (may have unresolved types)\n";
} catch (\Exception $e) {
    echo "Schema compilation error: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
