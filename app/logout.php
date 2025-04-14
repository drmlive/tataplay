<?php

$loginFile = 'data/login.json';
$guestCredsFile = 'data/guest-device.cred';
$cacheUrlsFile = 'data/cache_urls.json';
$loginData = json_decode(@file_get_contents($loginFile), true);
if (!$loginData || !isset($loginData['data'])) {echo "Already logged out.";exit;}
$guestCreds = json_decode(@file_get_contents($guestCredsFile), true);
if (!$guestCreds) {echo "Already logged out.";exit;}

$loginInfo = $loginData['data'];
$baId = $loginInfo['baId'];
$subscriberId = $loginInfo['subscriberId'];
$subscriptionStatus = $loginInfo['subscriptionStatus'];
$dthStatus = $loginInfo['dthStatus'];
$userAuthenticateToken = $loginInfo['userAuthenticateToken'];
$deviceAuthenticateToken = $loginInfo['deviceAuthenticateToken'];
$deviceId = $guestCreds['deviceId'];
$url = "https://tb.tapi.videoready.tv/binge-mobile-services/api/v2/logout/$baId";
$headers = [
    'accept: application/json, text/plain, */*',
    'accept-language: en-US,en;q=0.9,da;q=0.8',
    "authorization: $userAuthenticateToken",
    'cache-control: no-cache',
    'content-length: 0',
    "deviceid: $deviceId",
    "devicetoken: $deviceAuthenticateToken",
    "dthstatus: $dthStatus",
    'locale: en',
    'origin: https://www.tataplaybinge.com',
    'platform: WEB',
    'referer: https://www.tataplaybinge.com/',
    'sec-fetch-site: cross-site',
    "subscriberid: $subscriberId",
    "subscriptiontype: $subscriptionStatus",
    'user-agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36',
    'x-authenticated-userid;'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);
echo $data['message'];
$loggedOut = false;

if (isset($data['message']) && $data['message'] === 'You have been successfully logged out.') {
    if (file_exists($loginFile)) {
        unlink($loginFile);
    }
    if (file_exists($guestCredsFile)) {
        unlink($guestCredsFile);
    }
    if (file_exists($cacheUrlsFile)) {
        unlink($cacheUrlsFile);
    }
} else {
    echo "Already logged out.";
}
?>
