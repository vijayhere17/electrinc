<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config/db.php';

function getExtremeChain($conn, $startId, $side)
{
    $chain = [];

    $current = $startId;

    while ($current) {

        $query = $conn->query("
            SELECT customer_id
            FROM signup_users
            WHERE placement_id='$current'
            AND position='$side'
            ORDER BY id ASC
            LIMIT 1
        ");

        if (!$query || $query->num_rows == 0) {
            break;
        }

        $row = $query->fetch_assoc();

        $childId = $row['customer_id'];

        $chain[] = $childId;

        $current = $childId;
    }

    return $chain;
}

$rootId = $_GET['id'] ?? '';

if (!$rootId) {
    die('Pass ?id=USERID');
}

echo "<h2>Testing Pair Logic For: $rootId</h2>";

$leftChain = getExtremeChain($conn, $rootId, 'L');
$rightChain = getExtremeChain($conn, $rootId, 'R');

echo "<h3>LEFT CHAIN</h3>";
echo "<pre>";
print_r($leftChain);
echo "</pre>";

echo "<h3>RIGHT CHAIN</h3>";
echo "<pre>";
print_r($rightChain);
echo "</pre>";

$max = min(count($leftChain), count($rightChain));

$pairNo = 0;

for ($i = 0; $i < $max; $i++) {

    $leftNode = $leftChain[$i];
    $rightNode = $rightChain[$i];

    $checkActive = $conn->query("
        SELECT customer_id
        FROM signup_users
        WHERE customer_id IN ('$leftNode','$rightNode')
        AND registration_status='Active'
    ");

    echo "<hr>";

    echo "LEVEL: " . ($i + 1) . "<br>";
    echo "LEFT: $leftNode<br>";
    echo "RIGHT: $rightNode<br>";

    if ($checkActive && $checkActive->num_rows == 2) {

        $pairNo++;

        if ($pairNo % 3 != 0) {
            $income = 100;
        } else {
            $income = 0;
        }

        echo "<span style='color:green;font-weight:bold'>";
        echo "PAIR $pairNo CREATED | INCOME ₹$income";
        echo "</span>";

    } else {

        echo "<span style='color:red;font-weight:bold'>";
        echo "INACTIVE LEVEL → IGNORED";
        echo "</span>";
    }

    echo "<br>";
}