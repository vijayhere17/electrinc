<?php

include '../config/db.php';
include 'process-activation.php';

generateReward(
    $conn,
    'NA9661382',
    1
);

echo "DONE";