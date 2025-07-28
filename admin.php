<?php
session_start();

// Function to sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(trim($data));
}

// Basic authentication
$adminUsername = '@admin'; // Change this to your desired username
$adminPassword = '@password'; // Change this to your desired password

if (isset($_POST['username']) && isset($_POST['password'])) {
    if ($_POST['username'] === $adminUsername && $_POST['password'] === $adminPassword) {
        $_SESSION['authenticated'] = true;
    } else {
        $errorMessage = "Invalid username or password.";
    }
}

if (!isset($_SESSION['authenticated'])) {
    // Show login form for admin
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Login</title>
        <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    </head>
    <body class="bg-gray-100 flex items-center justify-center h-screen">
        <div class="bg-white p-6 rounded shadow-md w-96">
            <h1 class="text-2xl font-bold mb-4">Admin Login</h1>
            <form method="POST">
                <label for="username" class="block text-sm font-medium text-gray-700">Username:</label>
                <input type="text" id="username" name="username" required class="mt-1 block w-full border border-gray-300 rounded-md p-2">
                <br>
                <label for="password" class="block text-sm font-medium text-gray-700 mt-4">Password:</label>
                <input type="password" id="password" name="password" required class="mt-1 block w-full border border-gray-300 rounded-md p-2">
                <br>
                <input type="submit" value="Login" class="mt-4 w-full bg-blue-500 text-white rounded-md p-2 hover:bg-blue-600">
            </form>
            <?php if (isset($errorMessage)) echo "<p class='text-red-500 mt-2'>$errorMessage</p>"; ?>
        </div>
    </body>
    </html>
    <?php
    exit; // Stop further execution
}

// Load the current Telegram chat ID from the text file
$chatId = file_get_contents('telegram_chat_id.txt');
$message = '';

// Handle chat ID update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chat_id'])) {
    $newChatId = sanitizeInput($_POST['chat_id']);
    if (!empty($newChatId) && preg_match('/^\d+$/', $newChatId)) {
        // Update the chat ID in the text file
        file_put_contents('telegram_chat_id.txt', $newChatId);
        $message = "Chat ID updated successfully.";
    } else {
        $message = "Invalid Chat ID format. It should be a number.";
    }
}

// Handle time update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['time'])) {
    $newTime = sanitizeInput($_POST['time']);
    if (!empty($newTime)) {
        // Update the time in the text file
        file_put_contents('time.txt', $newTime);
        $message = "Time updated successfully.";
    } else {
        $message = "Invalid Time format.";
    }
}

// Load the current time from the text file
$currentTime = file_get_contents('time.txt');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="bg-white p-6 rounded shadow-md w-96">
        <h1 class="text-2xl font-bold mb-4">Admin Panel</h1>
        
        <form method="POST">
            <label for="chat_id" class="block text-sm font-medium text-gray-700">Telegram Chat ID:</label>
            <input type="text" id="chat_id" name="chat_id" value="<?php echo htmlspecialchars(            $chatId); ?>" required class="mt-1 block w-full border border-gray-300 rounded-md p-2">
            <input type="submit" value="Save Chat ID" class="mt-4 w-full bg-blue-500 text-white rounded-md p-2 hover:bg-blue-600">
        </form>
        <?php if ($message) echo "<p class='text-green-500 mt-2'>$message</p>"; ?>

        <form method="POST" class="mt-4">
            <label for="time" class="block text-sm font-medium text-gray-700">Set Time (YYYY-MM-DDTHH:MM:SS):</label>
            <input type="datetime-local" id="time" name="time" value="<?php echo htmlspecialchars($currentTime); ?>" required class="mt-1 block w-full border border-gray-300 rounded-md p-2">
            <input type="submit" value="Save Time" class="mt-4 w-full bg-blue-500 text-white rounded-md p-2 hover:bg-blue-600">
        </form>

        <form method="POST" action="logout.php" class="mt-4">
            <input type="submit" value="Logout" class="w-full bg-red-500 text-white rounded-md p-2 hover:bg-red-600">
        </form>
    </div>
</body>
</html>