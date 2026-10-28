<?php
session_start();
include __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection failed.");
}

$conn->set_charset("utf8mb4");

function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$customerId = trim($_GET['customer_id'] ?? '');

if ($customerId === '') {
    die("Invalid customer id.");
}

$sql = "
    SELECT 
        su.customer_id,
        su.created_at AS joining_date,
        su.sponsor_id,
        su.position,
        su.full_name,
        su.mobile,
        su.state,
        su.city,
        COALESCE(su.package_amount, 0) AS package_amount,
        su.status,

        mp.email,
        mp.address,
        mp.pan_number,
        mp.aadhar_number,
        mp.gst_number
    FROM signup_users su
    LEFT JOIN member_profiles mp 
        ON mp.customer_id = su.customer_id
    WHERE su.customer_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Query failed: " . $conn->error);
}

$stmt->bind_param("s", $customerId);
$stmt->execute();
$result = $stmt->get_result();
$member = $result->fetch_assoc();
$stmt->close();

if (!$member) {
    die("Member not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Member Profile</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-[#f3f4f6] font-sans">

<?php include __DIR__ . '/includes/sidebar.php'; ?>

<div class="ml-64 min-h-screen w-[calc(100%-16rem)]">
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <main class="p-5">

        <div class="bg-white rounded-xl shadow-sm border p-6">

            <h2 class="text-[22px] font-semibold text-[#143a66] mb-6">
                Member Profile - <?php echo e($member['customer_id']); ?>
            </h2>

            <div class="grid grid-cols-2 gap-5 text-[15px]">

                <div>
                    <label class="text-gray-500 font-semibold">Customer Id</label>
                    <div class="border rounded-lg px-3 py-2 mt-1"><?php echo e($member['customer_id']); ?></div>
                </div>

                <div>
                    <label class="text-gray-500 font-semibold">Joining Date</label>
                    <div class="border rounded-lg px-3 py-2 mt-1"><?php echo e($member['joining_date']); ?></div>
                </div>

                <div>
                    <label class="text-gray-500 font-semibold">Full Name</label>
                    <div class="border rounded-lg px-3 py-2 mt-1"><?php echo e($member['full_name']); ?></div>
                </div>

                <div>
                    <label class="text-gray-500 font-semibold">Mobile</label>
                    <div class="border rounded-lg px-3 py-2 mt-1"><?php echo e($member['mobile']); ?></div>
                </div>

                <div>
                    <label class="text-gray-500 font-semibold">Sponsor Id</label>
                    <div class="border rounded-lg px-3 py-2 mt-1"><?php echo e($member['sponsor_id'] ?: '-'); ?></div>
                </div>

                <div>
                    <label class="text-gray-500 font-semibold">Position</label>
                    <div class="border rounded-lg px-3 py-2 mt-1"><?php echo e($member['position'] ?: '-'); ?></div>
                </div>

                <div>
                    <label class="text-gray-500 font-semibold">State</label>
                    <div class="border rounded-lg px-3 py-2 mt-1"><?php echo e($member['state'] ?: '-'); ?></div>
                </div>

                <div>
                    <label class="text-gray-500 font-semibold">City</label>
                    <div class="border rounded-lg px-3 py-2 mt-1"><?php echo e($member['city'] ?: '-'); ?></div>
                </div>

                <div>
                    <label class="text-gray-500 font-semibold">Package</label>
                    <div class="border rounded-lg px-3 py-2 mt-1"><?php echo e($member['package_amount']); ?></div>
                </div>

                <div>
                    <label class="text-gray-500 font-semibold">Status</label>
                    <div class="border rounded-lg px-3 py-2 mt-1"><?php echo e($member['status'] ?: '-'); ?></div>
                </div>

                <div>
                    <label class="text-gray-500 font-semibold">Email</label>
                    <div class="border rounded-lg px-3 py-2 mt-1"><?php echo e($member['email'] ?: '-'); ?></div>
                </div>

                <div>
                    <label class="text-gray-500 font-semibold">PAN Number</label>
                    <div class="border rounded-lg px-3 py-2 mt-1"><?php echo e($member['pan_number'] ?: '-'); ?></div>
                </div>

                <div>
                    <label class="text-gray-500 font-semibold">Aadhar Number</label>
                    <div class="border rounded-lg px-3 py-2 mt-1"><?php echo e($member['aadhar_number'] ?: '-'); ?></div>
                </div>

                <div>
                    <label class="text-gray-500 font-semibold">GST Number</label>
                    <div class="border rounded-lg px-3 py-2 mt-1"><?php echo e($member['gst_number'] ?: '-'); ?></div>
                </div>

                <div class="col-span-2">
                    <label class="text-gray-500 font-semibold">Address</label>
                    <div class="border rounded-lg px-3 py-2 mt-1 min-h-[70px]">
                        <?php echo e($member['address'] ?: '-'); ?>
                    </div>
                </div>

            </div>

        </div>

    </main>
</div>

</body>
</html>