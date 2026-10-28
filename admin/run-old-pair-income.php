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
            AND registration_status='Active'
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

function findLevelExtremePair($conn, $rootId, $matchedLeftIds = [], $matchedRightIds = [])
{
    $leftChain = getExtremeChain($conn, $rootId, 'L');
    $rightChain = getExtremeChain($conn, $rootId, 'R');

    $max = min(count($leftChain), count($rightChain));

    for ($i = 0; $i < $max; $i++) {

        $leftNode = $leftChain[$i];
        $rightNode = $rightChain[$i];

        if (
            !in_array($leftNode, $matchedLeftIds) &&
            !in_array($rightNode, $matchedRightIds)
        ) {

            return [
                'left' => $leftNode,
                'right' => $rightNode
            ];
        }
    }

    return null;
}

$parentsResult = $conn->query("
    SELECT DISTINCT placement_id
    FROM signup_users
    WHERE placement_id IS NOT NULL
");

if (!$parentsResult) {
    die($conn->error);
}

while ($parentRow = $parentsResult->fetch_assoc()) {

    $parent = $parentRow['placement_id'];

    echo "<hr>";
    echo "<b>Checking Parent:</b> $parent <br>";

    $statusResult = $conn->query("
        SELECT *
        FROM level_matching_status
        WHERE customer_id='$parent'
        LIMIT 1
    ");

    $status = ($statusResult && $statusResult->num_rows > 0)
        ? $statusResult->fetch_assoc()
        : null;

    if (!$status) {

        $insertStatus = $conn->query("
            INSERT INTO level_matching_status
            (
                customer_id,
                left_team,
                right_team,
                matched_pair,
                earned,
                matched_left_ids,
                matched_right_ids
            )
            VALUES
            (
                '$parent',
                0,
                0,
                0,
                0,
                '',
                ''
            )
        ");

        if (!$insertStatus) {
            die($conn->error);
        }

        $status = [
            'matched_pair' => 0,
            'matched_left_ids' => '',
            'matched_right_ids' => ''
        ];
    }

    $matchedLeftIds = !empty($status['matched_left_ids'])
        ? explode(',', $status['matched_left_ids'])
        : [];

    $matchedRightIds = !empty($status['matched_right_ids'])
        ? explode(',', $status['matched_right_ids'])
        : [];

    $loopSafety = 0;

    while (true) {

        $pair = findLevelExtremePair(
            $conn,
            $parent,
            $matchedLeftIds,
            $matchedRightIds
        );

        if (!$pair) {

            echo "No more pairs<br>";
            break;
        }

        $leftMemberId = $pair['left'];
        $rightMemberId = $pair['right'];

        $duplicate = $conn->query("
            SELECT id
            FROM level_matching_pairs
            WHERE customer_id='$parent'
            AND left_member_id='$leftMemberId'
            AND right_member_id='$rightMemberId'
            LIMIT 1
        ");

        if ($duplicate && $duplicate->num_rows > 0) {

            echo "Duplicate pair skipped<br>";
            break;
        }

        $prevPairs = (int)$status['matched_pair'];

        $newPairs = $prevPairs + 1;

        $income = 0;

        if ($newPairs % 3 != 0) {
            $income = 100;
        }

        $insertPair = $conn->query("
            INSERT INTO level_matching_pairs
            (
                customer_id,
                left_member_id,
                right_member_id,
                pair_no,
                amount,
                status,
                created_at
            )
            VALUES
            (
                '$parent',
                '$leftMemberId',
                '$rightMemberId',
                $newPairs,
                $income,
                'Credit',
                NOW()
            )
        ");

        if (!$insertPair) {
            die($conn->error);
        }

        $matchedLeftIds[] = $leftMemberId;
        $matchedRightIds[] = $rightMemberId;

        $updateStatus = $conn->query("
            UPDATE level_matching_status
            SET
                matched_pair=$newPairs,
                earned=earned+$income,
                matched_left_ids='" . implode(',', $matchedLeftIds) . "',
                matched_right_ids='" . implode(',', $matchedRightIds) . "'
            WHERE customer_id='$parent'
        ");

        if (!$updateStatus) {
            die($conn->error);
        }

        if ($income > 0) {

            $walletUpdate = $conn->query("
                UPDATE signup_users
                SET wallet_balance = wallet_balance + $income
                WHERE customer_id='$parent'
            ");

            if (!$walletUpdate) {
                die($conn->error);
            }

            $balResult = $conn->query("
                SELECT wallet_balance
                FROM signup_users
                WHERE customer_id='$parent'
                LIMIT 1
            ");

            $balance = 0;

            if ($balResult && $balResult->num_rows > 0) {
                $balance = $balResult->fetch_assoc()['wallet_balance'];
            }

            $reportInsert = $conn->query("
                INSERT INTO level_matching_income_report
                (
                    customer_id,
                    report_date,
                    description,
                    credit,
                    debit,
                    balance,
                    status,
                    created_at
                )
                VALUES
                (
                    '$parent',
                    NOW(),
                    'Level matching bonus',
                    $income,
                    0,
                    $balance,
                    'Credit',
                    NOW()
                )
            ");

            if (!$reportInsert) {
                die($conn->error);
            }
        }

        echo "
            Pair Created:
            LEFT = $leftMemberId |
            RIGHT = $rightMemberId |
            PAIR = $newPairs |
            INCOME = ₹$income
            <br>
        ";

        $status['matched_pair'] = $newPairs;

        $loopSafety++;

        if ($loopSafety > 100) {

            echo "Safety break triggered<br>";
            break;
        }
    }
}

echo "<h2>DONE</h2>";

?>