<?php

include 'app/functions.php';
if (!$id) {http_response_code(400);echo 'Missing content ID.';exit;}
if (!file_exists($loginFilePath)) {http_response_code(401);echo 'Login required.';exit;}
$loginData = json_decode(file_get_contents($loginFilePath), true);
if (!isset($loginData['data']['subscriberId']) || !isset($loginData['data']['userAuthenticateToken'])) {http_response_code(403);echo 'Invalid login data.';exit;}
$subscriberId = $loginData['data']['subscriberId'];
$userToken = $loginData['data']['userAuthenticateToken'];
$cacheData = file_exists($cachePath) ? json_decode(file_get_contents($cachePath), true) : [];
$useCache = false;
if (isset($cacheData[$id])) {
    $cachedUrl = $cacheData[$id]['url'];
    parse_str(parse_url($cachedUrl, PHP_URL_QUERY), $queryParams);
    $exp = isset($queryParams['hdntl']) ? null : ($queryParams['exp'] ?? null);
    if (isset($queryParams['hdntl'])) {parse_str(str_replace('~', '&', $queryParams['hdntl']), $hdntlParams);$exp = $hdntlParams['exp'] ?? null;}
    if ($exp && is_numeric($exp) && time() < (int)$exp) {$mpdurl = $cachedUrl;$useCache = true;}
}

if (!$useCache) {
    $headers = ['Authorization: Bearer ' . $userToken,'subscriberId: ' . $subscriberId,];
    $options = ['http' => ['method' => 'GET','header' => implode("\r\n", $headers),],];
    $context = stream_context_create($options);
    $response = @file_get_contents($content_api, false, $context);
    if ($response === false) { http_response_code(500); echo 'Failed to fetch content data.'; exit; }
    $responseData = json_decode($response, true);
    if (!isset($responseData['data']['dashPlayreadyPlayUrl'])) { http_response_code(404); echo 'dashPlayreadyPlayUrl not found.'; exit;}
    $encrypteddashUrl = $responseData['data']['dashPlayreadyPlayUrl'];
    $decryptedUrl = decryptUrl($encrypteddashUrl, $aesKey);
    $decryptedUrl = str_replace('bpaicatchupta', 'bpaita', $decryptedUrl);
    if (strpos($decryptedUrl, 'bpaita') === false) {header("Location: $decryptedUrl");exit;}
    $getheaders = get_headers($decryptedUrl, 1, stream_context_create(['http' => ['method' => 'GET','header' => "User-Agent: $ua\r\n",'follow_location' => 0,'ignore_errors' => true]]));
    if (!$getheaders || !isset($getheaders['Location'])) {header("Location: $decryptedUrl", true, 302);exit;}
    $location = is_array($getheaders['Location']) ? end($getheaders['Location']) : $getheaders['Location'];
    $mpdurl = strpos($location, '&') !== false ? substr($location, 0, strpos($location, '&')) : $location;
    $cacheData[$id] = ['url' => $mpdurl, 'updated_at' => time()];
    file_put_contents($cachePath, json_encode($cacheData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

$mpdContext = stream_context_create(['http' => ['method' => 'GET','header' => "User-Agent: $ua\r\nReferer: https://watch.tataplay.com/\r\nOrigin: https://watch.tataplay.com\r\n",'ignore_errors' => true]]);
$mpdContent = @file_get_contents($mpdurl, false, $mpdContext);
if ($mpdContent === false) {http_response_code(500);echo 'Failed to fetch MPD content.';exit;}
$baseUrl = dirname($mpdurl);
$GetPssh = extractPsshFromManifest($mpdContent, $baseUrl, $ua);
    $processedManifest = str_replace('dash/', "$baseUrl/dash/", $mpdContent);
    if ($GetPssh) {
    $processedManifest = str_replace('mp4protection:2011', 'mp4protection:2011" cenc:default_KID="' . $GetPssh['kid'], $processedManifest);
    $processedManifest = str_replace('" value="PlayReady"/>', '"><cenc:pssh>' . $GetPssh['pr_pssh'] . '</cenc:pssh></ContentProtection>', $processedManifest);
    $processedManifest = str_replace('" value="Widevine"/>', '"><cenc:pssh>' . $GetPssh['pssh'] . '</cenc:pssh></ContentProtection>', $processedManifest);
}


header('Content-Security-Policy: default-src \'self\';');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/dash+xml');
header('Content-Disposition: attachment; filename="tp' . urlencode($id) . '.mpd"');
echo $processedManifest;
