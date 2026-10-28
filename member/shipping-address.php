<?php
include __DIR__ . '/config/db.php';
session_start();

if (!isset($_SESSION['customer_id'])) {
    header("Location: login.php");
    exit();
}

$customerId   = $_SESSION['customer_id'];
$memberName   = "Swapnil Solanki";
$managerName  = "Swapnil Solanki";
$managerRole  = "MLM Manager";
$profileImage = "https://i.pravatar.cc/80?img=12";
$navTitle     = "My Profile";
$navIcon      = "panel-left";

$message = '';
$error = '';

function getShippingDetails(mysqli $conn, string $customerId): ?array
{
    $sql = "SELECT * FROM shipping_details WHERE customer_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $customerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function createShippingRowIfNotExists(mysqli $conn, string $customerId): void
{
    $existing = getShippingDetails($conn, $customerId);

    if ($existing) {
        return;
    }

    $emptyName = '';
    $sql = "INSERT INTO shipping_details (customer_id, name) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ss", $customerId, $emptyName);
        $stmt->execute();
        $stmt->close();
    }
}

createShippingRowIfNotExists($conn, $customerId);
$shipping = getShippingDetails($conn, $customerId);

if (!$shipping) {
    die("Shipping details not found.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name          = trim($_POST['name'] ?? '');
    $house_number  = trim($_POST['house_number'] ?? '');
    $street_name   = trim($_POST['street_name'] ?? '');
    $landmark      = trim($_POST['landmark'] ?? '');
    $state_name    = trim($_POST['state_name'] ?? '');
    $district_name = trim($_POST['district_name'] ?? '');
    $city_name     = trim($_POST['city_name'] ?? '');
    $pincode       = trim($_POST['pincode'] ?? '');
    $email         = trim($_POST['email'] ?? '');
    $mobile_no     = trim($_POST['mobile_no'] ?? '');

    if ($name === '') {
        $error = "Name is required.";
    } else {
        $sql = "UPDATE shipping_details SET
                    name = ?,
                    house_number = ?,
                    street_name = ?,
                    landmark = ?,
                    state_name = ?,
                    district_name = ?,
                    city_name = ?,
                    pincode = ?,
                    email = ?,
                    mobile_no = ?
                WHERE customer_id = ?";

        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $error = "Prepare failed: " . $conn->error;
        } else {
            $stmt->bind_param(
                "sssssssssss",
                $name,
                $house_number,
                $street_name,
                $landmark,
                $state_name,
                $district_name,
                $city_name,
                $pincode,
                $email,
                $mobile_no,
                $customerId
            );

            if ($stmt->execute()) {
                $message = "Shipping details updated successfully.";
            } else {
                $error = "Update failed: " . $stmt->error;
            }

            $stmt->close();
        }
    }

    $shipping = getShippingDetails($conn, $customerId);
}

$districtOptions = ['Ahmedabad', 'Surat', 'Vadodara', 'Rajkot', 'Bharuch', 'Navsari', 'Valsad'];
$cityOptions = ['Ankleshwar', 'Surat', 'Vadodara', 'Rajkot', 'Bharuch', 'Navsari', 'Valsad'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shipping Details</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-[#f5f5f5] font-sans overflow-x-hidden">

    <?php include __DIR__ . '/includes/sidebar.php'; ?>

  <div class="ml-64 min-h-screen">
            <?php include __DIR__ . '/includes/navbar.php'; ?>  

        <div class="px-4 sm:px-6 py-5">
            <?php if ($message): ?>
                <div class="mb-4 rounded-lg border border-green-300 bg-green-100 px-4 py-3 text-sm text-green-700">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="mb-4 rounded-lg border border-red-300 bg-red-100 px-4 py-3 text-sm text-red-700">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-12">
                <section>
                    <div class="mb-7 flex items-center gap-4">
                        <div class="flex h-[40px] w-[40px] shrink-0 items-center justify-center rounded-[10px] bg-[#f4eee3]">
                            <i data-lucide="badge-check" class="h-[18px] w-[18px] text-[#d09a3d]"></i>
                        </div>
                        <h2 class="whitespace-nowrap text-[20px] font-normal text-[#2d2a25]" style="font-family: Georgia, 'Times New Roman', serif;">
                            SHIPPING ADDRESS
                        </h2>
                        <div class="h-[2px] w-full bg-[#c99638]"></div>
                    </div>

                    <div class="grid grid-cols-1 gap-x-4 gap-y-5 md:grid-cols-2">
                        <div class="md:col-span-2">
                            <label class="mb-2 flex items-center gap-2 text-[12px] font-bold uppercase text-[#9c9c9c]">
                                <i data-lucide="user-round" class="h-[18px] w-[18px] text-[#d0932f]"></i>
                                <span>NAME <span class="text-red-500">*</span></span>
                            </label>
                            <input
                                type="text"
                                name="name"
                                value="<?php echo htmlspecialchars($shipping['name'] ?? ''); ?>"
                                placeholder="Enter Your Name"
                                class="h-[44px] w-full rounded-[12px] border border-[#d5d8dd] bg-[#eef0f3] px-5 text-[14px] font-semibold text-[#3d3d3d] outline-none"
                            >
                        </div>

                        <div>
                            <label class="mb-2 flex items-center gap-2 text-[12px] font-bold uppercase text-[#9c9c9c]">
                                <i data-lucide="house" class="h-[18px] w-[18px] text-[#d0932f]"></i>
                                <span>HOUSE NUMBER <span class="text-red-500">*</span></span>
                            </label>
                            <input
                                type="text"
                                name="house_number"
                                value="<?php echo htmlspecialchars($shipping['house_number'] ?? ''); ?>"
                                placeholder="Enter Your House No."
                                class="h-[44px] w-full rounded-[12px] border border-[#d5d8dd] bg-[#eef0f3] px-5 text-[14px] font-semibold text-[#3d3d3d] outline-none"
                            >
                        </div>

                        <div>
                            <label class="mb-2 flex items-center gap-2 text-[12px] font-bold uppercase text-[#9c9c9c]">
                                <i data-lucide="map-pin" class="h-[18px] w-[18px] text-[#d0932f]"></i>
                                <span>STREET NAME <span class="text-red-500">*</span></span>
                            </label>
                            <input
                                type="text"
                                name="street_name"
                                value="<?php echo htmlspecialchars($shipping['street_name'] ?? ''); ?>"
                                placeholder="Enter Your Street Name"
                                class="h-[44px] w-full rounded-[12px] border border-[#d5d8dd] bg-[#eef0f3] px-5 text-[14px] font-semibold text-[#3d3d3d] outline-none"
                            >
                        </div>

                        <div>
                            <label class="mb-2 flex items-center gap-2 text-[12px] font-bold uppercase text-[#9c9c9c]">
                                <i data-lucide="send" class="h-[18px] w-[18px] text-[#d0932f]"></i>
                                <span>LANDMARK <span class="text-red-500">*</span></span>
                            </label>
                            <input
                                type="text"
                                name="landmark"
                                value="<?php echo htmlspecialchars($shipping['landmark'] ?? ''); ?>"
                                placeholder="Enter landmark Details"
                                class="h-[44px] w-full rounded-[12px] border border-[#d5d8dd] bg-[#eef0f3] px-5 text-[14px] font-semibold text-[#3d3d3d] outline-none"
                            >
                        </div>

                        <div>
                            <label class="mb-2 flex items-center gap-2 text-[12px] font-bold uppercase text-[#9c9c9c]">
                                <i data-lucide="map-pin" class="h-[18px] w-[18px] text-[#d0932f]"></i>
                                <span>STATE <span class="text-red-500">*</span></span>
                            </label>
                            <input
                                type="text"
                                name="state_name"
                                value="<?php echo htmlspecialchars($shipping['state_name'] ?? ''); ?>"
                                placeholder="Enter Your Street Name"
                                class="h-[44px] w-full rounded-[12px] border border-[#d5d8dd] bg-[#eef0f3] px-5 text-[14px] font-semibold text-[#3d3d3d] outline-none"
                            >
                        </div>

                        <div class="relative">
                            <label class="mb-2 flex items-center gap-2 text-[12px] font-bold uppercase text-[#9c9c9c]">
                                <i data-lucide="map-pin" class="h-[18px] w-[18px] text-[#d0932f]"></i>
                                <span>DISTRICT <span class="text-red-500">*</span></span>
                            </label>
                            <select
                                name="district_name"
                                class="h-[44px] w-full appearance-none rounded-[12px] border border-[#d5d8dd] bg-[#eef0f3] px-5 pr-12 text-[14px] font-semibold text-[#3d3d3d] outline-none"
                            >
                                <option value="">Select District</option>
                                <?php foreach ($districtOptions as $district): ?>
                                    <option value="<?php echo htmlspecialchars($district); ?>" <?php echo (($shipping['district_name'] ?? '') === $district) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($district); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i data-lucide="chevron-down" class="pointer-events-none absolute right-4 top-[47px] h-[18px] w-[18px] -translate-y-1/2 text-[#8d9198]"></i>
                        </div>

                        <div class="relative">
                            <label class="mb-2 flex items-center gap-2 text-[12px] font-bold uppercase text-[#9c9c9c]">
                                <i data-lucide="map-pin" class="h-[18px] w-[18px] text-[#d0932f]"></i>
                                <span>CITY <span class="text-red-500">*</span></span>
                            </label>
                            <select
                                name="city_name"
                                class="h-[44px] w-full appearance-none rounded-[12px] border border-[#d5d8dd] bg-[#eef0f3] px-5 pr-12 text-[14px] font-semibold text-[#3d3d3d] outline-none"
                            >
                                <option value="">Enter City/Village</option>
                                <?php foreach ($cityOptions as $city): ?>
                                    <option value="<?php echo htmlspecialchars($city); ?>" <?php echo (($shipping['city_name'] ?? '') === $city) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($city); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <i data-lucide="chevron-down" class="pointer-events-none absolute right-4 top-[47px] h-[18px] w-[18px] -translate-y-1/2 text-[#8d9198]"></i>
                        </div>

                        <div>
                            <label class="mb-2 flex items-center gap-2 text-[12px] font-bold uppercase text-[#9c9c9c]">
                                <i data-lucide="map-pin" class="h-[18px] w-[18px] text-[#d0932f]"></i>
                                <span>PINCODE <span class="text-red-500">*</span></span>
                            </label>
                            <input
                                type="text"
                                name="pincode"
                                value="<?php echo htmlspecialchars($shipping['pincode'] ?? ''); ?>"
                                placeholder="Enter Your Pincode"
                                class="h-[44px] w-full rounded-[12px] border border-[#d5d8dd] bg-[#eef0f3] px-5 text-[14px] font-semibold text-[#3d3d3d] outline-none"
                            >
                        </div>
                    </div>
                </section>

                <section>
                    <div class="mb-7 flex items-center gap-4">
                        <div class="flex h-[40px] w-[40px] shrink-0 items-center justify-center rounded-[10px] bg-[#f4eee3]">
                            <i data-lucide="badge-check" class="h-[18px] w-[18px] text-[#d09a3d]"></i>
                        </div>
                        <h2 class="whitespace-nowrap text-[20px] font-normal text-[#2d2a25]" style="font-family: Georgia, 'Times New Roman', serif;">
                            CONTACT INFORMATION
                        </h2>
                        <div class="h-[2px] w-full bg-[#c99638]"></div>
                    </div>

                    <div class="grid grid-cols-1 gap-x-4 gap-y-5 md:grid-cols-2">
                        <div>
                            <label class="mb-2 flex items-center gap-2 text-[12px] font-bold uppercase text-[#9c9c9c]">
                                <i data-lucide="mail" class="h-[18px] w-[18px] text-[#d0932f]"></i>
                                <span>EMAIL ID <span class="text-red-500">*</span></span>
                            </label>
                            <input
                                type="email"
                                name="email"
                                value="<?php echo htmlspecialchars($shipping['email'] ?? ''); ?>"
                                placeholder="Enter Your Mail Address"
                                class="h-[44px] w-full rounded-[12px] border border-[#d5d8dd] bg-[#eef0f3] px-5 text-[14px] font-semibold text-[#3d3d3d] outline-none"
                            >
                        </div>

                        <div>
                            <label class="mb-2 flex items-center gap-2 text-[12px] font-bold uppercase text-[#9c9c9c]">
                                <i data-lucide="phone-call" class="h-[18px] w-[18px] text-[#d0932f]"></i>
                                <span>MOBILE NO. <span class="text-red-500">*</span></span>
                            </label>
                            <input
                                type="text"
                                name="mobile_no"
                                value="<?php echo htmlspecialchars($shipping['mobile_no'] ?? ''); ?>"
                                placeholder="Enter Your Phone Number"
                                class="h-[44px] w-full rounded-[12px] border border-[#d5d8dd] bg-[#eef0f3] px-5 text-[14px] font-semibold text-[#3d3d3d] outline-none"
                            >
                        </div>
                    </div>
                </section>

                <div class="flex justify-center pt-2">
                    <button
                        type="submit"
                        class="h-[44px] min-w-[168px] rounded-[12px] bg-[#b9842f] px-8 text-[15px] font-semibold text-white transition hover:bg-[#a17328]"
                    >
                        Update
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();
        
        // Dynamically open the Profile section in sidebar
        document.addEventListener('DOMContentLoaded', function() {
            // Find all details elements in the sidebar
            const detailsElements = document.querySelectorAll('.fixed.bg-\\[\\#103258\\] details');
            
            // Look for the Profile details element
            detailsElements.forEach(function(details) {
                const summary = details.querySelector('summary');
                if (summary && summary.textContent.includes('Profile')) {
                    // Open the Profile section
                    details.setAttribute('open', '');
                    details.open = true;
                }
            });
            
            // Auto-dismiss success/error messages after 5 seconds
            const alertMessages = document.querySelectorAll('[class*="border-green"], [class*="border-red"]');
            alertMessages.forEach(function(message) {
                setTimeout(function() {
                    message.style.transition = 'opacity 0.3s ease';
                    message.style.opacity = '0';
                    setTimeout(function() {
                        message.remove();
                    }, 300);
                }, 5000);
            });
            
            // Form validation on submit
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const nameInput = form.querySelector('input[name="name"]');
                    if (!nameInput || nameInput.value.trim() === '') {
                        e.preventDefault();
                        alert('Please enter your name');
                        return false;
                    }
                });
            }
        });
    </script>
</body>
</html>