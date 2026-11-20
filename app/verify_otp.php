<?php

require_once 'functions.php';

$mobile = $_POST['mobile'] ?? '';
$otp = $_POST['otp'] ?? '';

$credFile = 'data/guest-device.cred';
if (!file_exists($credFile)) {
    http_response_code(500);
    echo "Missing device credentials.";
    exit;
}

@chmod($credFile, 0600);
$cred = json_decode(file_get_contents($credFile), true);
$deviceId = $cred['deviceId'] ?? '';
$anonymousId = $cred['anonymousId'] ?? '';

if (!$deviceId || !$anonymousId) {
    http_response_code(500);
    echo "Invalid device credentials.";
    exit;
}

if (!preg_match('/^[6-9]\d{9}$/', $mobile) || !preg_match('/^\d{4,6}$/', $otp)) {
    http_response_code(400);
    echo json_encode(["message" => "Invalid input."]);
    exit;
}

$validateUrl = 'https://tb.tapi.videoready.tv/binge-mobile-services/pub/api/v1/user/authentication/validateOTP';
$validateHeaders = [
    "accept: application/json, text/plain, */*",
    "anonymousid: $anonymousId",
    "content-type: application/json",
    "deviceid: $deviceId",
    "origin: https://www.tataplaybinge.com",
    "platform: BINGE_ANYWHERE",
    "referer: https://www.tataplaybinge.com/",
    "user-agent: $ua"
];

$body = json_encode([
    "mobileNumber" => $mobile,
    "otp" => $otp
]);

$ch = curl_init($validateUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => $body,
    CURLOPT_HTTPHEADER => $validateHeaders,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
    CURLOPT_ENCODING => '',
]);
$response = curl_exec($ch);
$data = json_decode($response, true);
if (!isset($data['data']['userAuthenticateToken'])) {
    echo $data['message'] ?? "OTP validation failed";
    exit;
}

$token = $data['data']['userAuthenticateToken'];
$devicetoken = $data['data']['deviceAuthenticateToken'];
$subUrl = 'https://tb.tapi.videoready.tv/binge-mobile-services/api/v4/subscriber/details';
$subHeaders = [
    "accept: application/json, text/plain, */*",
    "anonymousid: $anonymousId",
    "authorization: bearer $token",
    "devicetype: WEB",
    "mobilenumber: $mobile",
    "origin: https://www.tataplaybinge.com",
    "platform: BINGE_ANYWHERE",
    "referer: https://www.tataplaybinge.com/",
    "user-agent: $ua"
];

$ch = curl_init($subUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $subHeaders,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
    CURLOPT_ENCODING => '',
]);
$subResponse = curl_exec($ch);
$subData = json_decode($subResponse, true);
$accountDetails = $subData['data']['accountDetails'][0] ?? [];
$dthStatus = $accountDetails['dthStatus'] ?? '';
$loginUrl = '';
$loginBody = [];

if (empty($dthStatus)) {
    $loginUrl = 'https://tb.tapi.videoready.tv/binge-mobile-services/api/v3/create/new/user';
    $loginBody = json_encode([
        "dthStatus" => "Non DTH User",
        "subscriberId" => $mobile,
        "login" => "OTP",
        "mobileNumber" => $mobile,
        "isPastBingeUser" => false,
        "eulaChecked" => true,
        "packageId" => ""
    ]);
} elseif ($dthStatus === "DTH Without Binge") {
    $loginUrl = 'https://tb.tapi.videoready.tv/binge-mobile-services/api/v3/create/new/user';
    $loginBody = json_encode([
        "dthStatus" => "DTH Without Binge",
        "subscriberId" => $accountDetails['subscriberId'] ?? '',
        "login" => "OTP",
        "mobileNumber" => $mobile,
        "baId" => null,
        "isPastBingeUser" => false,
        "eulaChecked" => true,
        "packageId" => "",
        "referenceId" => null
    ]);
} else {
    $loginUrl = 'https://tb.tapi.videoready.tv/binge-mobile-services/api/v3/update/exist/user';
    $loginBody = json_encode([
        "dthStatus" => $dthStatus,
        "subscriberId" => $accountDetails['subscriberId'] ?? '',
        "bingeSubscriberId" => $accountDetails['bingeSubscriberId'] ?? '',
        "baId" => $accountDetails['baId'] ?? '',
        "login" => "OTP",
        "mobileNumber" => $mobile,
        "payment_return_url" => "https://www.tataplaybinge.com/subscription-transaction/status",
        "eulaChecked" => true,
        "packageId" => ""
    ]);
}
$loginHeaders = [
    "accept: application/json, text/plain, */*",
    "anonymousid: $anonymousId",
    "authorization: bearer $token",
    "content-type: application/json",
    "device: WEB",
    "deviceid: $deviceId",
    "devicename: Web",
    "devicetoken: $devicetoken",
    "origin: https://www.tataplaybinge.com",
    "platform: WEB",
    "referer: https://www.tataplaybinge.com/",
    "user-agent: $ua"
];

$ch = curl_init($loginUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => $loginBody,
    CURLOPT_HTTPHEADER => $loginHeaders,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0,
    CURLOPT_ENCODING => '',
]);
$loginResponse = curl_exec($ch);
$loginData = json_decode($loginResponse, true);

if (is_array($loginData) && isset($loginData['data']['userAuthenticateToken'])) {
    $dataDir = 'data';
    if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);
    $loginFile = $dataDir . '/login.json';
    if (file_put_contents($loginFile, $loginResponse) === false) {
        http_response_code(500);
        echo 'Failed to save login data.';
        exit;
    }
    @chmod($loginFile, 0600);

    // Consent is installation-wide and is saved only after successful login.
    $configPath = $dataDir . '/update_config.json';
    $config = is_file($configPath) ? json_decode(file_get_contents($configPath), true) : array();
    if (!is_array($config)) $config = array();
    $config['consent'] = isset($_POST['auto_update']) && $_POST['auto_update'] === '1';
    $config['consent_updated_at'] = date('c');
    if (empty($config['installed_sha'])) $config['installed_sha'] = '14c40a45824fe6c64aa810862e5732a02e6820ed';
    $tmpConfig = $configPath . '.tmp';
    if (file_put_contents($tmpConfig, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false) {
        @rename($tmpConfig, $configPath);
        @chmod($configPath, 0600);
    }
}

echo isset($loginData['message']) ? $loginData['message'] : 'Login failed.';
