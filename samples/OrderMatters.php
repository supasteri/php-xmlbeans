<?php
/**
 * OrderMatters Sample
 *
 * Demonstrates how document order affects processing when using
 * typed access vs cursor-based access.
 *
 * With typed access, deposits and withdrawals are accessed separately
 * (deposits first, then withdrawals), giving a positive running balance.
 *
 * With cursor access, transactions are processed in document order
 * (interleaved), which can produce a negative balance mid-stream.
 *
 * Ported from: https://xmlbeans.apache.org/samples/OrderMatters.html
 */

require_once __DIR__ . '/../vendor/autoload.php';

use XmlBeans\XmlBeans;
use XmlBeans\Cursor\XmlCursor;

echo "=== PHP XMLBeans OrderMatters Sample ===\n\n";

$xml = file_get_contents(__DIR__ . '/data/statement.xml');
$doc = XmlBeans::parse($xml);

// --- Approach 1: Typed access (deposits first, then withdrawals) ---
echo "--- Approach 1: Typed access (grouped by type) ---\n";

$balance = 0.0;

// Process all deposits first
$deposits = $doc->selectPath('//*[local-name()="deposit"]');
echo "\nProcessing all deposits first:\n";
foreach ($deposits as $dep) {
    $amount = (float) $dep->selectPathString('*[local-name()="amount"]');
    $desc = $dep->selectPathString('*[local-name()="description"]');
    $balance += $amount;
    echo sprintf("  + %-25s $%8.2f  (balance: $%8.2f)\n", $desc, $amount, $balance);
}

// Then all withdrawals
$withdrawals = $doc->selectPath('//*[local-name()="withdrawal"]');
echo "\nThen all withdrawals:\n";
foreach ($withdrawals as $wd) {
    $amount = (float) $wd->selectPathString('*[local-name()="amount"]');
    $desc = $wd->selectPathString('*[local-name()="description"]');
    $balance -= $amount;
    echo sprintf("  - %-25s $%8.2f  (balance: $%8.2f)\n", $desc, $amount, $balance);
}

echo "\nFinal balance (typed): $" . number_format($balance, 2) . "\n";
echo "Balance never went negative!\n";

// --- Approach 2: Cursor access (document order) ---
echo "\n--- Approach 2: Cursor access (document order) ---\n";

$balance2 = 0.0;
$wentNegative = false;

$cursor = $doc->newCursor();
$cursor->toFirstChild(); // <statement>

echo "\nProcessing in document order:\n";
while ($cursor->toNextToken() !== XmlCursor::TOKEN_NONE) {
    if ($cursor->currentTokenType() !== XmlCursor::TOKEN_START) {
        continue;
    }

    $localName = $cursor->getName()->getLocalPart();

    if ($localName === 'deposit' || $localName === 'withdrawal') {
        // Extract amount and description using a sub-cursor
        $subCursor = clone $cursor;
        $amount = 0.0;
        $desc = '';

        // Walk children of this transaction
        $depth = 1;
        while ($depth > 0 && $subCursor->toNextToken() !== XmlCursor::TOKEN_NONE) {
            if ($subCursor->currentTokenType() === XmlCursor::TOKEN_START) {
                $depth++;
                $childName = $subCursor->getName()->getLocalPart();
                $subCursor->toNextToken(); // to text
                if ($subCursor->currentTokenType() === XmlCursor::TOKEN_TEXT) {
                    if ($childName === 'amount') {
                        $amount = (float) $subCursor->getTextValue();
                    } elseif ($childName === 'description') {
                        $desc = $subCursor->getTextValue();
                    }
                }
            } elseif ($subCursor->currentTokenType() === XmlCursor::TOKEN_END) {
                $depth--;
            }
        }

        if ($localName === 'deposit') {
            $balance2 += $amount;
            echo sprintf("  + %-25s $%8.2f  (balance: $%8.2f)\n", $desc, $amount, $balance2);
        } else {
            $balance2 -= $amount;
            echo sprintf("  - %-25s $%8.2f  (balance: $%8.2f)\n", $desc, $amount, $balance2);
            if ($balance2 < 0) {
                $wentNegative = true;
            }
        }
    }
}

echo "\nFinal balance (cursor): $" . number_format($balance2, 2) . "\n";
if ($wentNegative) {
    echo "WARNING: Balance went negative during processing!\n";
} else {
    echo "Balance never went negative.\n";
}

echo "\n--- Conclusion ---\n";
echo "Same data, same final balance, but document order matters\n";
echo "for intermediate states (overdraft detection, running totals, etc).\n";

echo "\nDone.\n";
