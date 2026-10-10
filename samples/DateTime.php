<?php
/**
 * DateTime Sample
 *
 * Demonstrates working with XML Schema date and time types:
 * xs:date, xs:dateTime, xs:time, xs:duration
 *
 * Ported from: https://xmlbeans.apache.org/samples/DateTime.html
 */

require_once __DIR__ . '/../vendor/autoload.php';

use XmlBeans\XmlBeans;
use XmlBeans\Type\XmlDate;
use XmlBeans\Type\XmlDateTime;
use XmlBeans\Type\XmlTime;
use XmlBeans\Type\XmlDuration;

echo "=== PHP XMLBeans DateTime Sample ===\n\n";

// --- 1. Parse XML with date/time values ---
$xml = file_get_contents(__DIR__ . '/data/datetime-sample.xml');
$doc = XmlBeans::parse($xml);

echo "--- Parsing date/time values from XML ---\n";

// Select date values using XPath
$dateNodes = $doc->selectPath('//*[local-name()="date-val"]');
if (count($dateNodes) > 0) {
    $dateObj = new XmlDate($dateNodes[0]->getDomNode());
    $dateVal = $dateObj->getValue();
    echo "xs:date value:     " . $dateVal->format('Y-m-d') . "\n";
    echo "  Year:            " . $dateVal->format('Y') . "\n";
    echo "  Month:           " . $dateVal->format('m') . "\n";
    echo "  Day:             " . $dateVal->format('d') . "\n";
}

$dtNodes = $doc->selectPath('//*[local-name()="datetime-val"]');
if (count($dtNodes) > 0) {
    $dtObj = new XmlDateTime($dtNodes[0]->getDomNode());
    $dtVal = $dtObj->getValue();
    echo "\nxs:dateTime value: " . $dtVal->format('Y-m-d\TH:i:sP') . "\n";
    echo "  Date part:       " . $dtVal->format('Y-m-d') . "\n";
    echo "  Time part:       " . $dtVal->format('H:i:s') . "\n";
    echo "  Timezone:        " . $dtVal->format('P') . "\n";
}

$timeNodes = $doc->selectPath('//*[local-name()="time-val"]');
if (count($timeNodes) > 0) {
    $timeObj = new XmlTime($timeNodes[0]->getDomNode());
    $timeVal = $timeObj->getValue();
    echo "\nxs:time value:     " . $timeVal->format('H:i:s') . "\n";
}

$durNodes = $doc->selectPath('//*[local-name()="duration-val"]');
if (count($durNodes) > 0) {
    $durObj = new XmlDuration($durNodes[0]->getDomNode());
    $durVal = $durObj->getStringValue();
    echo "\nxs:duration value: " . $durVal . "\n";
}

// --- 2. Create new date/time XML ---
echo "\n--- Creating XML with date/time values ---\n";

$now = new DateTime();
$newXml = '<timestamps>'
    . '<created>' . $now->format('Y-m-d\TH:i:s') . '</created>'
    . '<date-only>' . $now->format('Y-m-d') . '</date-only>'
    . '<time-only>' . $now->format('H:i:s') . '</time-only>'
    . '<expires>' . $now->modify('+30 days')->format('Y-m-d') . '</expires>'
    . '</timestamps>';

$newDoc = XmlBeans::parse($newXml);
echo "Generated XML:\n" . $newDoc->xmlText() . "\n";

// --- 3. Date arithmetic via PHP DateTime ---
echo "\n--- Date arithmetic ---\n";
$orderDate = new DateTime('2024-03-15');
$shipDate = clone $orderDate;
$shipDate->modify('+3 days');
$deliveryDate = clone $orderDate;
$deliveryDate->modify('+7 days');

echo "Order date:    " . $orderDate->format('Y-m-d (l)') . "\n";
echo "Ship date:     " . $shipDate->format('Y-m-d (l)') . "\n";
echo "Delivery date: " . $deliveryDate->format('Y-m-d (l)') . "\n";

echo "\nDone.\n";
