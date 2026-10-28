<?php
require_once __DIR__ . '/includes/auth.php';

/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['franchise_id'])) {
    header("Location: index.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| GET SESSION DATA
|--------------------------------------------------------------------------
*/
$franchise_id   = $_SESSION['franchise_id'];
$franchise_type = $_SESSION['franchise_type'] ?? 'state';
$franchise_code = $_SESSION['franchise_code'] ?? '';
$franchise_name = $_SESSION['franchise_name'] ?? '';

$success = "";
$error   = "";

/*
|--------------------------------------------------------------------------
| FIX FRANCHISE TYPE
|--------------------------------------------------------------------------
|
| Now automatically save:
| ✔ state franchise
| ✔ city franchise
| ✔ mobile franchise
|
*/

/*
|--------------------------------------------------------------------------
| SEND MESSAGE
|--------------------------------------------------------------------------
*/
if(isset($_POST['send_message'])){

    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);

    if($subject == "" || $message == ""){

        $error = "Please fill all fields.";

    } else {

        /*
        |--------------------------------------------------------------------------
        | INSERT MESSAGE
        |--------------------------------------------------------------------------
        */
        $stmt = $conn->prepare("
            INSERT INTO franchise_messages
            (
                franchise_id,
                folder,
                from_name,
                to_name,
                subject,
                message_details,
                status
            )
            VALUES(?,?,?,?,?,?,?)
        ");

        if($stmt){

            $folder = "outbox";
            $to_name = "Admin";
            $status  = "Pending";

            $stmt->bind_param(
                "issssss",
                $franchise_id,
                $folder,
                $franchise_name,
                $to_name,
                $subject,
                $message,
                $status
            );

            if($stmt->execute()){

                $success = "Message Sent Successfully";

            } else {

                $error = "Insert Failed : " . $stmt->error;
            }

        } else {

            $error = "SQL Error : " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Compose Message</title>

    <!-- TAILWIND -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- LUCIDE -->
    <script src="https://unpkg.com/lucide@latest"></script>

</head>

<body class="bg-[#f3f3f3] font-sans overflow-x-hidden">

<!-- SIDEBAR -->
<?php include __DIR__ . '/includes/sidebar.php'; ?>

<!-- MAIN -->
<div class="ml-64 min-h-screen bg-[#f3f3f3]">

    <!-- NAVBAR -->
    <?php include __DIR__ . '/includes/navbar.php'; ?>

    <!-- CONTENT -->
    <div class="px-6 py-6">

        <!-- PAGE TITLE -->
        <div class="flex items-center gap-4">

            <div class="w-[38px] h-[38px] rounded-[10px] bg-[#f7efe2] flex items-center justify-center">

                <i data-lucide="mail-plus"
                   class="w-[18px] h-[18px] text-[#C89741]"></i>

            </div>

            <h2 class="text-[24px] text-[#40352a]"
                style="font-family: Georgia, 'Times New Roman', serif;">

                Compose Message

            </h2>

            <div class="flex-1 h-[2px] bg-[#d1a14b]"></div>

        </div>

        <!-- FORM CARD -->
        <div class="mt-8 bg-white rounded-2xl border border-[#e5e5e5] shadow-sm p-8 max-w-4xl">

            <!-- SUCCESS -->
            <?php if($success != ""): ?>

                <div class="bg-green-100 text-green-700 px-5 py-4 rounded-xl mb-6">

                    <?php echo $success; ?>

                </div>

            <?php endif; ?>

            <!-- ERROR -->
            <?php if($error != ""): ?>

                <div class="bg-red-100 text-red-700 px-5 py-4 rounded-xl mb-6">

                    <?php echo $error; ?>

                </div>

            <?php endif; ?>

            <!-- FRANCHISE INFO -->
            <div class="grid grid-cols-3 gap-5 mb-6">

                <!-- TYPE -->
                <div>

                    <label class="block mb-2 text-[14px] font-semibold text-[#444]">

                        Franchise Type

                    </label>

                    <input type="text"
                           value="<?php echo ucfirst($franchise_type); ?>"
                           readonly
                           class="w-full border border-[#ddd] rounded-xl px-4 py-3 bg-[#f8f8f8]">

                </div>

                <!-- CODE -->
                <div>

                    <label class="block mb-2 text-[14px] font-semibold text-[#444]">

                        Franchise Code

                    </label>

                    <input type="text"
                           value="<?php echo $franchise_code; ?>"
                           readonly
                           class="w-full border border-[#ddd] rounded-xl px-4 py-3 bg-[#f8f8f8]">

                </div>

                <!-- NAME -->
                <div>

                    <label class="block mb-2 text-[14px] font-semibold text-[#444]">

                        Franchise Name

                    </label>

                    <input type="text"
                           value="<?php echo $franchise_name; ?>"
                           readonly
                           class="w-full border border-[#ddd] rounded-xl px-4 py-3 bg-[#f8f8f8]">

                </div>

            </div>

            <!-- FORM -->
            <form method="POST">

                <!-- SUBJECT -->
                <div class="mb-5">

                    <label class="block mb-2 text-[14px] font-semibold text-[#444]">

                        Subject

                    </label>

                    <input type="text"
                           name="subject"
                           placeholder="Enter Subject"
                           required
                           class="w-full border border-[#ddd] rounded-xl px-4 py-3 outline-none focus:border-[#C89741]">

                </div>

                <!-- MESSAGE -->
                <div class="mb-5">

                    <label class="block mb-2 text-[14px] font-semibold text-[#444]">

                        Message

                    </label>

                    <textarea
                        name="message"
                        placeholder="Write your message here..."
                        required
                        class="w-full border border-[#ddd] rounded-xl px-4 py-3 h-[200px] resize-none outline-none focus:border-[#C89741]"></textarea>

                </div>

                <!-- BUTTON -->
                <div>

                    <button
                        type="submit"
                        name="send_message"
                        class="bg-[#C89741] hover:bg-[#b68430] transition text-white px-8 py-3 rounded-xl font-semibold">

                        Send Message

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<script>
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
</script>

</body>
</html>