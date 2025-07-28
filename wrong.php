<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wrong Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
    <style>
        body {
            background-color: #f9fafb; /* Tailwind's gray-100 */
        }
        .shake {
            animation: shake 0.5s ease-in-out infinite;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            50% { transform: translateX(5px); }
            75% { transform: translateX(-5px); }
        }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="max-w-md w-full bg-white shadow-lg rounded-lg p-8">
        <div class="text-center mb-6">
            <img src="https://i.postimg.cc/wMv7DgxH/12065738771352376078-Arnoud999-Right-or-wrong-5-svg-thumb.png" alt="Wrong Password Illustration" class="mx-auto mb-4 w-32 shake">
            <h1 class="text-2xl font-semibold text-white-800">Oops! Wrong Password</h1>
            <p class="text-gray-600 mt-2">The password you entered is incorrect. Please try again.</p>
        </div>
        <div class="text-center">
            <a href="login.php" class="inline-block px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200">
                <i class="fas fa-arrow-left"></i> TRY AGAIN
            </a>
        </div>
    </div>
</body>
</html>