<?php

require_once 'functions.php';

$mobile = $_POST['mobile'] ?? '';
if (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
    http_response_code(400);
    echo "Invalid mobile number.";
    exit;
}

$credDir = 'data';
$credFile = "$credDir/guest-device.cred";
function generateNumericUuid(): string {
    return strval(mt_rand(100, 999)) . strval(time()) . strval(mt_rand(10, 99));
}

if (!file_exists($credDir)) {if (!mkdir($credDir, 0777, true)) {http_response_code(500); echo 'Failed to create directory. Please do chmod 777 to "app" folder.'; exit;}}
if (!file_exists($credFile)) {
    $deviceId = generateNumericUuid();
    $ch = curl_init('https://tb.tapi.videoready.tv/binge-mobile-services/pub/api/v1/user/guest/register');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
        CURLOPT_ENCODING => '',
    ]);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'accept: application/json, text/plain, */*',
        'authorization: bearer undefined',
        'content-length: 0',
        'referer: https://www.tataplaybinge.com/',
        "deviceid: $deviceId",
        'origin: https://www.tataplaybinge.com',
        'user-agent: ' . $ua,
        'Expect:'
    ]);

    $guestResponse = curl_exec($ch);
    $guestData = json_decode($guestResponse, true);
    $anonymousId = $guestData['data']['anonymousId'] ?? '';

    if ($anonymousId) {
        if (file_put_contents($credFile, json_encode([
            'deviceId' => $deviceId,
            'anonymousId' => $anonymousId
        ], JSON_PRETTY_PRINT)) === false) {
            http_response_code(500);
            echo 'Failed to save device credentials.';
            exit;
        }
        @chmod($credFile, 0600);
    } else {
        http_response_code(500);
        echo "Failed to register device.";
        exit;
    }
}

if (is_file($credFile)) @chmod($credFile, 0600);
$cred = json_decode(@file_get_contents($credFile), true);
$deviceId = $cred['deviceId'] ?? '';
$anonymousId = $cred['anonymousId'] ?? '';

if (!$deviceId || !$anonymousId) {
    http_response_code(500);
    echo "Invalid device credentials.";
    exit;
}

$url = 'https://tb.tapi.videoready.tv/binge-mobile-services/pub/api/v1/user/authentication/generateOTP';
$headers = [
    "accept: application/json, text/plain, */*",
    "anonymousid: $anonymousId",
    "content-length: 0",
    "deviceid: $deviceId",
    "mobilenumber: $mobile",
    "newotpflow: 4DOTP",
    "origin: https://www.tataplaybinge.com",
    "platform: BINGE_ANYWHERE",
    "referer: https://www.tataplaybinge.com/",
    "user-agent: $api_ua"
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
    CURLOPT_ENCODING => '',
]);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$data = json_decode($response, true);
echo $data['message'] ?? "OTP send status unknown";
