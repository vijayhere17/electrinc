<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);


function generateReward($conn, $customerId, $clubLevel)
{
    $check = $conn->query("
        SELECT id
        FROM reward_achievers
        WHERE customer_id='$customerId'
        AND club_no='$clubLevel'
        LIMIT 1
    ");

    if($check && $check->num_rows > 0){
        return;
    }

    $reward = $conn->query("
        SELECT reward_name,reward_budget
        FROM reward_master
        WHERE club_no='$clubLevel'
        LIMIT 1
    ");

    if(!$reward || $reward->num_rows == 0){
        return;
    }

    $row = $reward->fetch_assoc();

    $rewardName   = $row['reward_name'];
    $rewardBudget = $row['reward_budget'];

    $conn->query("
        INSERT INTO reward_achievers
        (
            customer_id,
            club_no,
            reward_name,
            reward_budget,
            achieved_date,
            status
        )
        VALUES
        (
            '$customerId',
            '$clubLevel',
            '$rewardName',
            '$rewardBudget',
            CURDATE(),
            'Pending'
        )
    ");
}

if (!function_exists('getExtremeChain')) {
    
    
  function processClubPair($conn, $clubLevel = 1)
{
    $root = $conn->query("
        SELECT customer_id
        FROM club_members
        WHERE club_level='$clubLevel'
        ORDER BY id ASC
        LIMIT 1
    ");

    if (!$root || $root->num_rows == 0) {
        return;
    }

    $rootId = $root->fetch_assoc()['customer_id'];

    $leftChain = [];

    $current = $rootId;

    while (true) {

        $child = $conn->query("
            SELECT customer_id
            FROM club_members
            WHERE parent_id='$current'
            AND club_level='$clubLevel'
            AND position='L'
            ORDER BY id ASC
            LIMIT 1
        ");

        if (!$child || $child->num_rows == 0) {
            break;
        }

        $row = $child->fetch_assoc();

        $leftChain[] = $row['customer_id'];

        $current = $row['customer_id'];
    }

    $rightChain = [];

    $current = $rootId;

    while (true) {

        $child = $conn->query("
            SELECT customer_id
            FROM club_members
            WHERE parent_id='$current'
            AND club_level='$clubLevel'
            AND position='R'
            ORDER BY id ASC
            LIMIT 1
        ");

        if (!$child || $child->num_rows == 0) {
            break;
        }

        $row = $child->fetch_assoc();

        $rightChain[] = $row['customer_id'];

        $current = $row['customer_id'];
    }

    $maxPairs = min(count($leftChain), count($rightChain));

    if ($maxPairs <= 0) {
        return;
    }

    $status = $conn->query("
        SELECT matched_pairs
        FROM club_members
        WHERE customer_id='$rootId'
        AND club_level='$clubLevel'
        LIMIT 1
    ");

    $currentPairs = ($status && $status->num_rows)
        ? (int)$status->fetch_assoc()['matched_pairs']
        : 0;

    $clubBonus = [
        1  => 300,
        2  => 800,
        3  => 2000,
        4  => 5000,
        5  => 12000,
        6  => 30000,
        7  => 75000,
        8  => 185000,
        9  => 450000,
        10 => 1100000,
        11 => 2500000,
        12 => 6000000,
        13 => 15000000,
        14 => 37000000,
        15 => 90000000
    ];

    $incomePerPair = $clubBonus[$clubLevel] ?? 0;

    for ($i = $currentPairs; $i < $maxPairs; $i++) {

        $leftId = $leftChain[$i];
        $rightId = $rightChain[$i];

        $dup = $conn->query("
            SELECT id
            FROM club_bonus_income_report
            WHERE customer_id='$rootId'
            AND club_no='$clubLevel'
            AND left_id='$leftId'
            AND right_id='$rightId'
            LIMIT 1
        ");

        if ($dup && $dup->num_rows > 0) {
            continue;
        }

        $newPairNo = $i + 1;

        $deductionPairs = [3,6,9,12,15];

        if (in_array($newPairNo, $deductionPairs)) {
            
            
            $pairIncome = 0;
            $pairStatus = 'Skipped';
        } else {
            $pairIncome = $incomePerPair;
            $pairStatus = 'Credit';
        }

        $conn->query("
            UPDATE club_members
            SET
                matched_pairs = matched_pairs + 1,
                earned = earned + $pairIncome
            WHERE customer_id='$rootId'
            AND club_level='$clubLevel'
        ");

        if ($pairIncome > 0) {

            $conn->query("
                UPDATE signup_users
                SET wallet_balance = wallet_balance + $pairIncome
                WHERE customer_id='$rootId'
            ");
        }

        $bal = $conn->query("
            SELECT wallet_balance
            FROM signup_users
            WHERE customer_id='$rootId'
        ");

        $walletBalance = 0;

        if ($bal && $bal->num_rows > 0) {
            $walletBalance = $bal->fetch_assoc()['wallet_balance'];
        }

        $conn->query("
            INSERT INTO club_bonus_income_report
            (
                customer_id,
                report_date,
                club_no,
                left_id,
                right_id,
                club_income,
                description,
                credit,
                debit,
                balance,
                status,
                created_at
            )
            VALUES
            (
                '$rootId',
                CURDATE(),
                $clubLevel,
                '$leftId',
                '$rightId',
                $pairIncome,
                'Club Bonus Pair',
                $pairIncome,
                0,
                '$walletBalance',
                '$pairStatus',
                NOW()
            )
        ");

     if ($newPairNo == 15) {

    generateReward(
        $conn,
        $rootId,
        $clubLevel
    );

    payClubReferralBonus(
        $conn,
        $rootId,
        $clubLevel
    );

    $nextClub = $clubLevel + 1;

    $exists = $conn->query("
        SELECT id
        FROM club_members
        WHERE customer_id='$rootId'
        AND club_level='$nextClub'
        LIMIT 1
    ");

    if (!$exists || $exists->num_rows == 0) {

        enterClubMember(
            $conn,
            $rootId,
            $nextClub
        );
    }
}
    }
}

function payClubReferralBonus($conn, $customerId, $clubLevel)
{
    
   
    $dup = $conn->query("
        SELECT id
        FROM referral_bonus_status
        WHERE customer_id='$customerId'
        AND club_no='$clubLevel'
        LIMIT 1
    ");

    if($dup && $dup->num_rows > 0){
        return;
    }

    $plan = $conn->query("
        SELECT *
        FROM referral_bonus_master
        WHERE club_no='$clubLevel'
        LIMIT 1
    ");

    if(!$plan || $plan->num_rows == 0){
        return;
    }

    $bonus = $plan->fetch_assoc();

    $directBonus  = $bonus['referral_bonus'];
    $senior1Bonus = $bonus['senior1_bonus'];
    $senior2Bonus = $bonus['senior2_bonus'];

    $user = $conn->query("
        SELECT referral_id
        FROM signup_users
        WHERE customer_id='$customerId'
        LIMIT 1
    ");

    if(!$user || $user->num_rows == 0){
        return;
    }

    $directId = $user->fetch_assoc()['referral_id'];

    $senior1Id = '';
    $senior2Id = '';

    if(!empty($directId)){

        $conn->query("
            UPDATE signup_users
            SET wallet_balance = wallet_balance + $directBonus
            WHERE customer_id='$directId'
        ");

        $d = $conn->query("
            SELECT wallet_balance
            FROM signup_users
            WHERE customer_id='$directId'
        ");

        $bal = ($d && $d->num_rows)
            ? $d->fetch_assoc()['wallet_balance']
            : 0;

        $conn->query("
            INSERT INTO referral_bonus_income_report
            (
                customer_id,
                report_date,
                description,
                credit,
                debit,
                balance,
                status,
                club_no,
                income_type,
                created_at
            )
            VALUES
            (
                '$directId',
                CURDATE(),
                'Club Referral Bonus From $customerId',
                $directBonus,
                0,
                $bal,
                'Credit',
                $clubLevel,
                'Direct Referral',
                NOW()
            )
        ");

        $s1 = $conn->query("
            SELECT referral_id
            FROM signup_users
            WHERE customer_id='$directId'
            LIMIT 1
        ");

        if($s1 && $s1->num_rows){
            $senior1Id = $s1->fetch_assoc()['referral_id'];
        }
    }

    if(!empty($senior1Id)){

        $conn->query("
            UPDATE signup_users
            SET wallet_balance = wallet_balance + $senior1Bonus
            WHERE customer_id='$senior1Id'
        ");

        $d = $conn->query("
            SELECT wallet_balance
            FROM signup_users
            WHERE customer_id='$senior1Id'
        ");

        $bal = ($d && $d->num_rows)
            ? $d->fetch_assoc()['wallet_balance']
            : 0;

        $conn->query("
            INSERT INTO referral_bonus_income_report
            (
                customer_id,
                report_date,
                description,
                credit,
                debit,
                balance,
                status,
                club_no,
                income_type,
                created_at
            )
            VALUES
            (
                '$senior1Id',
                CURDATE(),
                'Club Senior1 Bonus From $customerId',
                $senior1Bonus,
                0,
                $bal,
                'Credit',
                $clubLevel,
                'Senior 1',
                NOW()
            )
        ");

        $s2 = $conn->query("
            SELECT referral_id
            FROM signup_users
            WHERE customer_id='$senior1Id'
            LIMIT 1
        ");

        if($s2 && $s2->num_rows){
            $senior2Id = $s2->fetch_assoc()['referral_id'];
        }
    }

    if(!empty($senior2Id)){

        $conn->query("
            UPDATE signup_users
            SET wallet_balance = wallet_balance + $senior2Bonus
            WHERE customer_id='$senior2Id'
        ");

        $d = $conn->query("
            SELECT wallet_balance
            FROM signup_users
            WHERE customer_id='$senior2Id'
        ");

        $bal = ($d && $d->num_rows)
            ? $d->fetch_assoc()['wallet_balance']
            : 0;

        $conn->query("
            INSERT INTO referral_bonus_income_report
            (
                customer_id,
                report_date,
                description,
                credit,
                debit,
                balance,
                status,
                club_no,
                income_type,
                created_at
            )
            VALUES
            (
                '$senior2Id',
                CURDATE(),
                'Club Senior2 Bonus From $customerId',
                $senior2Bonus,
                0,
                $bal,
                'Credit',
                $clubLevel,
                'Senior 2',
                NOW()
            )
        ");
    }

    $conn->query("
        INSERT INTO referral_bonus_status
        (
            customer_id,
            bonus_date,
            transaction_id,
            club_no,
            club_level,
            referral_bonus,
            senior1_bonus,
            senior2_bonus,
            earned,
            status,
            created_at
        )
        VALUES
        (
            '$customerId',
            CURDATE(),
            CONCAT('CLUB-',NOW()),
            '$clubLevel',
            '$clubLevel',
            '$directBonus',
            '$senior1Bonus',
            '$senior2Bonus',
            '".($directBonus+$senior1Bonus+$senior2Bonus)."',
            'Paid',
            NOW()
        )
    ");
}


function enterClubMember($conn, $customerId, $clubLevel = 1)
{
    $check = $conn->query("
        SELECT id
        FROM club_members
        WHERE customer_id='$customerId'
        AND club_level='$clubLevel'
        LIMIT 1
    ");

    if ($check && $check->num_rows > 0) {
        return;
    }

    $root = $conn->query("
        SELECT customer_id
        FROM club_members
        WHERE club_level='$clubLevel'
        ORDER BY id ASC
        LIMIT 1
    ");

    if (!$root || $root->num_rows == 0) {

        $conn->query("
    INSERT INTO club_members
    (
        customer_id,
        club_level,
        parent_id,
        position
    )
    VALUES
    (
        '$customerId',
        '$clubLevel',
        NULL,
        NULL
    )
");

processClubPair($conn, $clubLevel);

return;
    }

    $rootId = $root->fetch_assoc()['customer_id'];

    $side = 'L';

    $current = $rootId;

    while (true) {

        $child = $conn->query("
            SELECT customer_id
            FROM club_members
            WHERE parent_id='$current'
            AND club_level='$clubLevel'
            AND position='$side'
            ORDER BY id ASC
            LIMIT 1
        ");

        if (!$child || $child->num_rows == 0) {

            $conn->query("
    INSERT INTO club_members
    (
        customer_id,
        club_level,
        parent_id,
        position
    )
    VALUES
    (
        '$customerId',
        '$clubLevel',
        '$current',
        '$side'
    )
");

processClubPair($conn, $clubLevel);

return;
        }

        $current = $child->fetch_assoc()['customer_id'];

        $side = ($side == 'L') ? 'R' : 'L';
    }
}

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

}

if (!function_exists('findLevelExtremePair')) {

function findLevelExtremePair($conn, $rootId, $matchedLeftIds = [], $matchedRightIds = [])
{
    $leftChain = getExtremeChain($conn, $rootId, 'L');
    $rightChain = getExtremeChain($conn, $rootId, 'R');

    $leftNode = null;

    foreach ($leftChain as $id) {

        if (in_array($id, $matchedLeftIds)) {
            continue;
        }

        $q = $conn->query("
            SELECT customer_id
            FROM signup_users
            WHERE customer_id='$id'
            AND registration_status='Active'
            LIMIT 1
        ");

        if ($q && $q->num_rows > 0) {
            $leftNode = $id;
            break;
        }
    }

    $rightNode = null;

    foreach ($rightChain as $id) {

        if (in_array($id, $matchedRightIds)) {
            continue;
        }

        $q = $conn->query("
            SELECT customer_id
            FROM signup_users
            WHERE customer_id='$id'
            AND registration_status='Active'
            LIMIT 1
        ");

        if ($q && $q->num_rows > 0) {
            $rightNode = $id;
            break;
        }
    }

    $pairs = [];

    if ($leftNode && $rightNode) {
        $pairs[] = [
            'left'  => $leftNode,
            'right' => $rightNode
        ];
    }

    return $pairs;
}

}



if (isset($customer_id) && !empty($customer_id)) {

   

    $current = $customer_id;

   

$current = $customer_id;

$processedParents = [];

while ($current) {
    
    

    $userResult = $conn->query("
        SELECT customer_id, placement_id, position
        FROM signup_users
        WHERE customer_id='$current'
    ");

    if (!$userResult || $userResult->num_rows == 0) {
        break;
    }

    $user = $userResult->fetch_assoc();

    $parent = $user['placement_id'];

    if (!$parent) {
        break;
    }

    if (in_array($parent, $processedParents)) {
        break;
    }

    $processedParents[] = $parent;

    $side = $user['position'];

    $statusResult = $conn->query("
        SELECT *
        FROM level_matching_status
        WHERE customer_id='$parent'
    ");

    $status = ($statusResult && $statusResult->num_rows > 0)
        ? $statusResult->fetch_assoc()
        : null;

    if (!$status) {

        $conn->query("
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

        $status = [
            'left_team' => 0,
            'right_team' => 0,
            'matched_pair' => 0,
            'matched_left_ids' => '',
            'matched_right_ids' => ''
        ];
    }

    $left = (int)$status['left_team'];
    $right = (int)$status['right_team'];

    $matchedLeftIds = !empty($status['matched_left_ids'])
        ? explode(',', $status['matched_left_ids'])
        : [];

    $matchedRightIds = !empty($status['matched_right_ids'])
        ? explode(',', $status['matched_right_ids'])
        : [];

    if ($side == 'L') {
        $left++;
    } else {
        $right++;
    }

    $prevPairs = (int)$status['matched_pair'];

    $newPairs = $prevPairs;
    $income = 0;

$pairs = findLevelExtremePair(
    $conn,
    $parent,
    $matchedLeftIds,
    $matchedRightIds
);

error_log("Parent: ".$parent);
error_log("Pairs: ".json_encode($pairs));


$totalIncome = 0;

if (!empty($pairs)) {

    foreach ($pairs as $pair) {

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

        if (!$duplicate || $duplicate->num_rows == 0) {
            
            $parentCheck = $conn->query("
    SELECT registration_status
    FROM signup_users
    WHERE customer_id='$parent'
    LIMIT 1
");

$parentStatus = ($parentCheck && $parentCheck->num_rows > 0)
    ? $parentCheck->fetch_assoc()['registration_status']
    : 'Inactive';

if ($parentStatus != 'Active') {
    continue;
}

            $matchedLeftIds[] = $leftMemberId;
            $matchedRightIds[] = $rightMemberId;

            $newPairs++;

$deductionPairs = [3, 6, 9, 12, 15];

if (in_array($newPairs, $deductionPairs)) {
    $income = 0;

    if ($newPairs == 15) {
        enterClubMember($conn, $parent, 1);
    }

} else {
    $income = 100;
}

            $totalIncome += $income;

            if ($income > 0) {
    $pairStatus = 'Credit';
} else {
    $pairStatus = 'Skipped';
}

            $pairNoToSave = $newPairs;

            $conn->query("
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
                    $pairNoToSave,
                    $income,
                    '$pairStatus',
                    NOW()
                )
            ");
        }
    }
}

$conn->query("
    UPDATE level_matching_status
    SET
        left_team=$left,
        right_team=$right,
        matched_pair=$newPairs,
        earned=earned+$totalIncome,
        matched_left_ids='" . implode(',', $matchedLeftIds) . "',
        matched_right_ids='" . implode(',', $matchedRightIds) . "'
    WHERE customer_id='$parent'
");

if ($newPairs > $prevPairs) {

    if ($totalIncome > 0) {

        $conn->query("
            UPDATE signup_users
            SET wallet_balance = wallet_balance + $totalIncome
            WHERE customer_id='$parent'
        ");
    }

    $balResult = $conn->query("
        SELECT wallet_balance
        FROM signup_users
        WHERE customer_id='$parent'
    ");

    $balance = ($balResult && $balResult->num_rows > 0)
        ? $balResult->fetch_assoc()['wallet_balance']
        : 0;

    $conn->query("
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
            $totalIncome,
            0,
            $balance,
            'Credit',
            NOW()
        )
    ");
}

    $current = $parent;
}}
?>