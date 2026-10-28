<?php
require_once __DIR__ . '/config/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection is not working. Please check config/db.php");
}

$success = "";
$error = "";
$generatedMemberId = "";

$full_name = "";
$dob = "";
$gender = "";
$email = "";
$mobile = "";
$password = "";
$address = "";
$pincode = "";
$state = "";
$city = "";
$district = "";

function generateMemberId(mysqli $conn): string {
    $query = "SELECT id FROM signup_users ORDER BY id DESC LIMIT 1";
    $result = $conn->query($query);

    if ($result && $row = $result->fetch_assoc()) {
        $nextId = (int)$row['id'] + 1;
    } else {
        $nextId = 1;
    }

    return 'fresh' . str_pad((string)$nextId, 4, '0', STR_PAD_LEFT);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $full_name = trim($_POST['full_name'] ?? '');
    $dob = trim($_POST['dob'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $terms = isset($_POST['terms']) ? 1 : 0;
    $age_confirm = isset($_POST['age_confirm']) ? 1 : 0;

    if (
        $full_name === "" || $dob === "" || $gender === "" || $email === "" ||
        $mobile === "" || $password === "" || $address === "" || $pincode === "" ||
        $state === "" || $city === "" || $district === ""
    ) {
        $error = "Please fill all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $error = "Mobile number must be 10 digits.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (!$terms || !$age_confirm) {
        $error = "Please accept both checkboxes.";
    } else {
        $checkStmt = $conn->prepare("SELECT id FROM signup_users WHERE email = ? OR mobile = ?");

        if (!$checkStmt) {
            $error = "Database prepare failed: " . $conn->error;
        } else {
            $checkStmt->bind_param("ss", $email, $mobile);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                $error = "Email or mobile already registered.";
            } else {
                $generatedMemberId = generateMemberId($conn);
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("INSERT INTO signup_users 
                    (member_id, full_name, dob, gender, email, mobile, password, address, pincode, state, city, district, terms_accepted, age_confirmed) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                if (!$stmt) {
                    $error = "Database prepare failed: " . $conn->error;
                } else {
                    $stmt->bind_param(
                        "ssssssssssssii",
                        $generatedMemberId,
                        $full_name,
                        $dob,
                        $gender,
                        $email,
                        $mobile,
                        $hashedPassword,
                        $address,
                        $pincode,
                        $state,
                        $city,
                        $district,
                        $terms,
                        $age_confirm
                    );

                    if ($stmt->execute()) {
                        $success = "Sign up successful. Your Member ID is: " . $generatedMemberId;

                        $full_name = "";
                        $dob = "";
                        $gender = "";
                        $email = "";
                        $mobile = "";
                        $password = "";
                        $address = "";
                        $pincode = "";
                        $state = "";
                        $city = "";
                        $district = "";
                    } else {
                        $error = "Something went wrong. Please try again.";
                    }

                    $stmt->close();
                }
            }

            $checkStmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Freshiya Sign Up</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#efeff1] min-h-screen font-sans text-[#2b2b2b]">

    <div class="w-full flex justify-center px-4 py-8 md:py-10">
        <div class="w-full max-w-[700px]">

            <div class="flex flex-col items-center">
                <img src="assets/freshiya_logo.png" alt="Freshiya Logo" class="w-[50px] md:w-[80px] object-contain">
                <h1 class="mt-4 text-[24px] md:text-[26px] font-bold text-black leading-none">Sign Up</h1>
            </div>

            <?php if (!empty($success)): ?>
                <div class="mt-6 bg-green-100 border border-green-300 text-green-700 px-4 py-3 rounded-xl text-center">
                    <?php echo htmlspecialchars($success); ?>
                    <div class="mt-2">
                        <a href="login.php" class="text-blue-600 font-semibold">Go to Sign In</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="mt-6 bg-red-100 border border-red-300 text-red-700 px-4 py-3 rounded-xl text-center">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST" class="mt-8">

                <div class="bg-[#f7f7f8] rounded-[22px] px-8 md:px-10 py-7 shadow-[0_0_0_1px_rgba(0,0,0,0.02)]">
                    <h2 class="text-[#c7922f] text-[18px] md:text-[20px] font-semibold mb-7">
                        Personal Details
                    </h2>

                    <div class="space-y-5">
                        <div>
                            <label class="block text-[15px] md:text-[16px] font-medium text-[#3b3b3b] mb-3">Full Name</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($full_name); ?>"
                                class="w-full bg-transparent border-0 border-b border-[#cfcfcf] focus:outline-none focus:ring-0 focus:border-[#c7922f] pb-3 text-[15px]">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-5">
                            <div>
                                <label class="block text-[15px] md:text-[16px] font-medium text-[#3b3b3b] mb-3">DOB</label>
                                <input type="date" name="dob" value="<?php echo htmlspecialchars($dob); ?>"
                                    class="w-full bg-transparent border-0 border-b border-[#cfcfcf] focus:outline-none focus:ring-0 focus:border-[#c7922f] pb-3 text-[15px]">
                            </div>

                            <div>
                                <label class="block text-[15px] md:text-[16px] font-medium text-[#3b3b3b] mb-3">Gender</label>
                                <select name="gender"
                                    class="w-full bg-transparent border-0 border-b border-[#cfcfcf] focus:outline-none focus:ring-0 focus:border-[#c7922f] pb-3 text-[15px]">
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php if ($gender === "Male") echo "selected"; ?>>Male</option>
                                    <option value="Female" <?php if ($gender === "Female") echo "selected"; ?>>Female</option>
                                    <option value="Other" <?php if ($gender === "Other") echo "selected"; ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-5">
                            <div>
                                <label class="block text-[15px] md:text-[16px] font-medium text-[#3b3b3b] mb-3">Email Address</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($email); ?>"
                                    class="w-full bg-transparent border-0 border-b border-[#cfcfcf] focus:outline-none focus:ring-0 focus:border-[#c7922f] pb-3 text-[15px]">
                            </div>

                            <div>
                                <label class="block text-[15px] md:text-[16px] font-medium text-[#3b3b3b] mb-3">Mobile No.</label>
                                <input type="text" name="mobile" maxlength="10" value="<?php echo htmlspecialchars($mobile); ?>"
                                    class="w-full bg-transparent border-0 border-b border-[#cfcfcf] focus:outline-none focus:ring-0 focus:border-[#c7922f] pb-3 text-[15px]">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[15px] md:text-[16px] font-medium text-[#3b3b3b] mb-3">Password</label>
                            <input type="password" name="password"
                                class="w-full bg-transparent border-0 border-b border-[#cfcfcf] focus:outline-none focus:ring-0 focus:border-[#c7922f] pb-3 text-[15px]">
                        </div>
                    </div>
                </div>

                <div class="bg-[#f7f7f8] rounded-[22px] px-8 md:px-10 py-7 mt-5 shadow-[0_0_0_1px_rgba(0,0,0,0.02)]">
                    <h2 class="text-[#c7922f] text-[18px] md:text-[20px] font-semibold mb-7">
                        Address Details
                    </h2>

                    <div class="space-y-5">
                        <div>
                            <label class="block text-[15px] md:text-[16px] font-medium text-[#3b3b3b] mb-3">Address</label>
                            <input type="text" name="address" value="<?php echo htmlspecialchars($address); ?>"
                                class="w-full bg-transparent border-0 border-b border-[#cfcfcf] focus:outline-none focus:ring-0 focus:border-[#c7922f] pb-3 text-[15px]">
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-5">
                            <div>
                                <label class="block text-[15px] md:text-[16px] font-medium text-[#3b3b3b] mb-3">Pincode</label>
                                <input type="text" name="pincode" value="<?php echo htmlspecialchars($pincode); ?>"
                                    class="w-full bg-transparent border-0 border-b border-[#cfcfcf] focus:outline-none focus:ring-0 focus:border-[#c7922f] pb-3 text-[15px]">
                            </div>

                            <div>
                                <label class="block text-[15px] md:text-[16px] font-medium text-[#3b3b3b] mb-3">State</label>
                                <input type="text" name="state" value="<?php echo htmlspecialchars($state); ?>"
                                    class="w-full bg-transparent border-0 border-b border-[#cfcfcf] focus:outline-none focus:ring-0 focus:border-[#c7922f] pb-3 text-[15px]">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-5 gap-y-5">
                            <div>
                                <label class="block text-[15px] md:text-[16px] font-medium text-[#3b3b3b] mb-3">City</label>
                                <input type="text" name="city" value="<?php echo htmlspecialchars($city); ?>"
                                    class="w-full bg-transparent border-0 border-b border-[#cfcfcf] focus:outline-none focus:ring-0 focus:border-[#c7922f] pb-3 text-[15px]">
                            </div>

                            <div>
                                <label class="block text-[15px] md:text-[16px] font-medium text-[#3b3b3b] mb-3">District</label>
                                <input type="text" name="district" value="<?php echo htmlspecialchars($district); ?>"
                                    class="w-full bg-transparent border-0 border-b border-[#cfcfcf] focus:outline-none focus:ring-0 focus:border-[#c7922f] pb-3 text-[15px]">
                            </div>
                        </div>

                        <div class="pt-2 space-y-4">
                            <label class="flex items-start gap-4 cursor-pointer">
                                <input type="checkbox" name="terms"
                                    class="mt-0.5 h-5 w-5 rounded-full border border-[#c7922f] text-[#c7922f] focus:ring-[#c7922f]">
                                <span class="text-[#c7922f] text-[15px] md:text-[16px] leading-[1.5] font-medium">
                                    I have read & agree to the Terms and Conditions*
                                </span>
                            </label>

                            <label class="flex items-start gap-4 cursor-pointer">
                                <input type="checkbox" name="age_confirm"
                                    class="mt-0.5 h-5 w-5 rounded-full border border-[#c7922f] text-[#c7922f] focus:ring-[#c7922f]">
                                <span class="text-[#c7922f] text-[15px] md:text-[16px] leading-[1.5] font-medium">
                                    I am atleast 18 years old (21 years in case of domicile being Maharashtra) and citizen of India
                                </span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex justify-center mt-8">
                    <button type="submit"
                        class="w-full max-w-[290px] h-[42px] rounded-[10px] bg-[#c7922f] text-white text-[18px] font-semibold hover:opacity-95 transition">
                        Submit
                    </button>
                </div>

            </form>
        </div>
    </div>

</body>
</html>