<?php
// admin/index.php
session_start();

// Simple authentication (in a real application, use a more secure approach)
$admin_username = "change";
$admin_password = "change123"; // Change this to a secure password

// Check if user is already logged in
$is_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Process login form
if (isset($_POST['login'])) {
    if ($_POST['username'] === $admin_username && $_POST['password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        $is_logged_in = true;
    } else {
        $error_message = "Invalid username or password";
    }
}

// Process logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Define contestant config file
$contestant_config_file = "../contestant_config.php";

// Create contestant config if it doesn't exist
if (!file_exists($contestant_config_file)) {
    $default_contestants = [
        'main_contestant' => [
            'name' => 'FASHION BRAND',
            'votes' => 118,
            'position' => '#3'
        ],
        'contestants' => [
            [
                'name' => 'Jane Kendra',
                'votes' => 750,
                'position' => '#1',
                'status' => 'Leading'
            ],
            [
                'name' => 'Mary Biernacki',
                'votes' => 375,
                'position' => '#2',
                'status' => '2nd Place'
            ]
        ]
    ];
    
    file_put_contents($contestant_config_file, '<?php return ' . var_export($default_contestants, true) . ';');
}

// Load contestant data
$contestants_data = include($contestant_config_file);

// Process contestant name update
$contestant_update_message = '';
if ($is_logged_in && isset($_POST['update_contestant'])) {
    $new_name = htmlspecialchars(trim($_POST['contestant_name']));
    $new_votes = intval($_POST['contestant_votes']);
    $new_position = htmlspecialchars(trim($_POST['contestant_position']));
    
    if (!empty($new_name)) {
        // Update main contestant data
        $contestants_data['main_contestant']['name'] = $new_name;
        $contestants_data['main_contestant']['votes'] = $new_votes;
        $contestants_data['main_contestant']['position'] = $new_position;
        
        // Save the updated data
        file_put_contents($contestant_config_file, '<?php return ' . var_export($contestants_data, true) . ';');
        $contestant_update_message = "Contestant information updated successfully.";
    } else {
        $contestant_update_message = "Contestant name cannot be empty.";
    }
}

// Process additional contestants update
if ($is_logged_in && isset($_POST['update_other_contestants'])) {
    $updated_contestants = [];
    
    for ($i = 0; $i < count($_POST['names']); $i++) {
        $updated_contestants[] = [
            'name' => htmlspecialchars(trim($_POST['names'][$i])),
            'votes' => intval($_POST['votes'][$i]),
            'position' => htmlspecialchars(trim($_POST['positions'][$i])),
            'status' => htmlspecialchars(trim($_POST['statuses'][$i]))
        ];
    }
    
    // Update the contestants array
    $contestants_data['contestants'] = $updated_contestants;
    
    // Save the updated data
    file_put_contents($contestant_config_file, '<?php return ' . var_export($contestants_data, true) . ';');
    $contestant_update_message = "All contestants updated successfully.";
}

// Image upload handling
$upload_message = '';
if ($is_logged_in && isset($_POST['upload_image'])) {
    if (isset($_FILES["image_file"]) && $_FILES["image_file"]["error"] == 0) {
        // Check if file is an image
        $check = getimagesize($_FILES["image_file"]["tmp_name"]);
        if ($check === false) {
            $upload_message = "File is not an image.";
        } else {
            // Check file size (limit to 5MB)
            if ($_FILES["image_file"]["size"] > 5000000) {
                $upload_message = "Sorry, your file is too large.";
            } else {
                // Check file format
                $imageFileType = strtolower(pathinfo($_FILES["image_file"]["name"], PATHINFO_EXTENSION));
                if ($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif") {
                    $upload_message = "Sorry, only JPG, JPEG, PNG & GIF files are allowed.";
                } else {
                    // Upload the image to ImgBB
                    $imageUrl = uploadToImgBB($_FILES["image_file"]["tmp_name"]);
                    
                    if ($imageUrl) {
                        // Update configuration file
                        $config_file = "../image_config.php";
                        $config = [
                            'main_image' => $imageUrl,
                            'last_updated' => date('Y-m-d H:i:s')
                        ];
                        
                        file_put_contents($config_file, '<?php return ' . var_export($config, true) . ';');
                        $upload_message = "The image has been uploaded and set as the main image.";
                    } else {
                        $upload_message = "Sorry, there was an error uploading your file to ImgBB.";
                    }
                }
            }
        }
    } else {
        $upload_message = "Please select a file to upload.";
    }
}

// Function to upload image to ImgBB
function uploadToImgBB($imagePath) {
    // Define your ImgBB API key
    $apiKey = '42ffc74047dfb55d8ac748321d7d6d6f'; // Replace with your actual ImgBB API key
    
    // Convert image to base64
    $imageData = base64_encode(file_get_contents($imagePath));
    
    // Set up the API request
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.imgbb.com/1/upload?key=' . $apiKey);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, [
        'image' => $imageData
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Execute the request
    $response = curl_exec($ch);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    // Parse the response to get the direct image URL
    $data = json_decode($response, true);
    
    if ($data && isset($data['data']['url'])) {
        return $data['data']['url'];
    } elseif ($data && isset($data['data']['display_url'])) {
        return $data['data']['display_url'];
    } else {
        // If upload fails, return a placeholder
        return "https://via.placeholder.com/800x600?text=Upload+Failed";
    }
}

// Load current image configuration
$config_file = "../image_config.php";
if (file_exists($config_file)) {
    $config = include($config_file);
    $current_image = $config['main_image'];
    $last_updated = $config['last_updated'];
} else {
    $current_image = "https://via.placeholder.com/800x600?text=Default+Image";
    $last_updated = "Never";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Instagram Voting System</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@100;200;300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-poppins">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Admin Dashboard</h1>
            
            <?php if (!$is_logged_in): ?>
                <!-- Login Form -->
                <div class="max-w-md mx-auto">
                    <h2 class="text-xl font-semibold mb-4">Login</h2>
                    
                    <?php if (isset($error_message)): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4" role="alert">
                            <p><?php echo $error_message; ?></p>
                        </div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <div class="mb-4">
                            <label for="username" class="block text-gray-700 mb-2">Username</label>
                            <input type="text" name="username" id="username" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="mb-6">
                            <label for="password" class="block text-gray-700 mb-2">Password</label>
                            <input type="password" name="password" id="password" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <button type="submit" name="login"
                                    class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:ring-2 focus:ring-blue-500">
                                Log In
                            </button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <!-- Admin Content -->
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-xl font-semibold">Welcome, Admin!</h2>
                    <a href="?logout=1" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-4 rounded">
                        Log Out
                    </a>
                </div>
                
                <!-- Admin Tabs -->
                <div class="mb-6">
                    <ul class="flex flex-wrap border-b border-gray-200">
                        <li class="mr-2">
                            <a href="#" onclick="showTab('image-tab')" class="tab-btn inline-block p-4 rounded-t-lg border-b-2 border-blue-500" id="image-tab-btn">
                                Image Management
                            </a>
                        </li>
                        <li class="mr-2">
                            <a href="#" onclick="showTab('contestant-tab')" class="tab-btn inline-block p-4 rounded-t-lg border-b-2 border-transparent hover:border-gray-300" id="contestant-tab-btn">
                                Contestant Management
                            </a>
                        </li>
                    </ul>
                </div>
                
                <!-- Image Management Section -->
                <div id="image-tab" class="tab-content">
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold mb-4">Image Management (ImgBB)</h3>
                        
                        <div class="bg-gray-50 p-4 rounded-lg mb-6">
                            <h4 class="font-medium mb-2">Current Main Image:</h4>
                            <div class="flex flex-col md:flex-row md:items-center">
                                <div class="w-40 h-40 bg-gray-200 rounded overflow-hidden mr-4 mb-4 md:mb-0">
                                    <img src="<?php echo $current_image; ?>" alt="Current Main Image" class="w-full h-full object-cover">
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 mb-2">Image URL:</p>
                                    <div class="flex items-center mb-2">
                                        <input type="text" value="<?php echo $current_image; ?>" id="imageUrl" readonly
                                               class="flex-1 px-3 py-2 border border-gray-300 rounded-md focus:outline-none text-sm">
                                        <button onclick="copyToClipboard('imageUrl')" 
                                                class="ml-2 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-2 px-4 rounded">
                                            Copy
                                        </button>
                                    </div>
                                    <p class="text-sm text-gray-600">Last updated: <?php echo $last_updated; ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($upload_message)): ?>
                            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                                <p><?php echo $upload_message; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="" enctype="multipart/form-data" class="bg-white p-4 rounded-lg border border-gray-200">
                            <div class="mb-4">
                                <label for="image_file" class="block text-gray-700 mb-2">Upload New Image to ImgBB</label>
                                <input type="file" name="image_file" id="image_file" required accept="image/*"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                <p class="text-sm text-gray-500 mt-1">Accepted formats: JPG, JPEG, PNG, GIF (Max: 5MB)</p>
                                <p class="text-sm text-gray-500">Image will be uploaded to ImgBB and converted to a link.</p>
                            </div>
                            
                            <button type="submit" name="upload_image"
                                    class="bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-4 rounded">
                                Upload & Set as Main Image
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Contestant Management Section -->
                <div id="contestant-tab" class="tab-content hidden">
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold mb-4">Contestant Management</h3>
                        
                        <?php if (!empty($contestant_update_message)): ?>
                            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                                <p><?php echo $contestant_update_message; ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Main Contestant Form -->
                        <form method="post" action="" class="bg-white p-4 rounded-lg border border-gray-200 mb-6">
                            <h4 class="font-medium mb-4">Main Contestant (Your Profile)</h4>
                            
                            <div class="mb-4">
                                <label for="contestant_name" class="block text-gray-700 mb-2">Name</label>
                                <input type="text" name="contestant_name" id="contestant_name" required
                                       value="<?php echo htmlspecialchars($contestants_data['main_contestant']['name']); ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="contestant_votes" class="block text-gray-700 mb-2">Votes</label>
                                    <input type="number" name="contestant_votes" id="contestant_votes" required
                                           value="<?php echo $contestants_data['main_contestant']['votes']; ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                                
                                <div>
                                    <label for="contestant_position" class="block text-gray-700 mb-2">Position</label>
                                    <input type="text" name="contestant_position" id="contestant_position" required
                                           value="<?php echo htmlspecialchars($contestants_data['main_contestant']['position']); ?>"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                </div>
                            </div>
                            
                            <button type="submit" name="update_contestant"
                                    class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                                Update Main Contestant
                            </button>
                        </form>
                        
                        <!-- Other Contestants Form -->
                        <form method="post" action="" class="bg-white p-4 rounded-lg border border-gray-200">
                            <h4 class="font-medium mb-4">Other Contestants</h4>
                            
                            <div id="contestants-container">
                                <?php foreach ($contestants_data['contestants'] as $index => $contestant): ?>
                                <div class="contestant-entry border-b border-gray-200 pb-4 mb-4">
                                    <div class="mb-4">
                                        <label class="block text-gray-700 mb-2">Name</label>
                                        <input type="text" name="names[]" required
                                               value="<?php echo htmlspecialchars($contestant['name']); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                    </div>
                                    
                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-gray-700 mb-2">Votes</label>
                                            <input type="number" name="votes[]" required
                                                   value="<?php echo $contestant['votes']; ?>"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-gray-700 mb-2">Position</label>
                                            <input type="text" name="positions[]" required
                                                   value="<?php echo htmlspecialchars($contestant['position']); ?>"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                        </div>
                                        
                                        <div>
                                            <label class="block text-gray-700 mb-2">Status</label>
                                            <input type="text" name="statuses[]" required
                                                   value="<?php echo htmlspecialchars($contestant['status']); ?>"
                                                   class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="flex justify-between mt-4">
                                <button type="button" id="add-contestant-btn"
                                        class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded">
                                    Add Contestant
                                </button>
                                
                                <button type="submit" name="update_other_contestants"
                                        class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded">
                                    Update All Contestants
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="mt-8">
                    <a href="../index.php" target="_blank" class="text-blue-500 hover:text-blue-700 font-medium">
                        View Live Site →
                    </a>
                </div>
                
                <script>
                    // Tab switching functionality
                    function showTab(tabId) {
                        // Hide all tab contents
                        document.querySelectorAll('.tab-content').forEach(tab => {
                            tab.classList.add('hidden');
                        });
                        
                        // Show the selected tab content
                        document.getElementById(tabId).classList.remove('hidden');
                        
                        // Update tab buttons
                        document.querySelectorAll('.tab-btn').forEach(btn => {
                            btn.classList.remove('border-blue-500');
                            btn.classList.add('border-transparent', 'hover:border-gray-300');
                        });
                        
                        document.getElementById(tabId + '-btn').classList.remove('border-transparent', 'hover:border-gray-300');
                        document.getElementById(tabId + '-btn').classList.add('border-blue-500');
                    }
                    
                    // Add new contestant functionality
                    document.getElementById('add-contestant-btn').addEventListener('click', function() {
                        const container = document.getElementById('contestants-container');
                        const newEntry = document.createElement('div');
                        newEntry.className = 'contestant-entry border-b border-gray-200 pb-4 mb-4';
                        newEntry.innerHTML = `
                            <div class="mb-4">
                                <label class="block text-gray-700 mb-2">Name</label>
                                <input type="text" name="names[]" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md">
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-gray-700 mb-2">Votes</label>
                                    <input type="number" name="votes[]" required value="0"
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 mb-2">Position</label>
                                    <input type="text" name="positions[]" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                </div>
                                
                                <div>
                                    <label class="block text-gray-700 mb-2">Status</label>
                                    <input type="text" name="statuses[]" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-md">
                                </div>
                            </div>
                        `;
                        container.appendChild(newEntry);
                    });
                    
                    // Copy to clipboard function
                    function copyToClipboard(elementId) {
                        const copyText = document.getElementById(elementId);
                        copyText.select();
                        copyText.setSelectionRange(0, 99999); // For mobile devices
                        document.execCommand("copy");
                        
                        // Alert the copied text
                        const copyBtn = event.target;
                        const originalText = copyBtn.innerText;
                        copyBtn.innerText = "Copied!";
                        setTimeout(() => {
                            copyBtn.innerText = originalText;
                        }, 2000);
                    }
                </script>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>p