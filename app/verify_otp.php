<?php

$mobile = $_POST['mobile'] ?? '';
$otp = $_POST['otp'] ?? '';

$credFile = 'data/guest-device.cred';
if (!file_exists($credFile)) {
    http_response_code(500);
    echo "Missing device credentials.";
    exit;
}

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
    "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36"
];

$body = json_encode([
    "mobileNumber" => $mobile,
    "otp" => $otp
]);

$ch = curl_init($validateUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
curl_setopt($ch, CURLOPT_HTTPHEADER, $validateHeaders);
$response = curl_exec($ch);
curl_close($ch);
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
    "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36"
];

$ch = curl_init($subUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $subHeaders);
$subResponse = curl_exec($ch);
curl_close($ch);
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
    "user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36"
];

$ch = curl_init($loginUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $loginBody);
curl_setopt($ch, CURLOPT_HTTPHEADER, $loginHeaders);
$loginResponse = curl_exec($ch);
curl_close($ch);
$loginData = json_decode($loginResponse, true);
file_put_contents("data/login.json",$loginResponse);
echo $loginData['message'];