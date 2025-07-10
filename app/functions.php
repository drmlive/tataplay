<?php

$id = $_GET['id'] ?? null;
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$loginFilePath = 'app/data/login.json';
$cachePath = 'app/data/cache_urls.json';
$origin_json = 'app/origin.json';
$aesKey = 'aesEncryptionKey';
$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36';
$content_api = 'https://tb.tapi.videoready.tv/content-detail/api/partner/cdn/player/details/chotiluli/' . $id;
$origin_api = 'https://tp.drmlive-01.workers.dev/origin';
$stb_only = 'https://tp.drmlive-01.workers.dev/stb_only';

function extractPsshFromManifest(string $content, string $baseUrl, string $userAgent): ?array {
    if (($xml = @simplexml_load_string($content)) === false) return null;
    foreach ($xml->Period->AdaptationSet as $set) {
        if ((string)$set['contentType'] === 'audio') {
            foreach ($set->Representation as $rep) {
                $template = $rep->SegmentTemplate ?? null;
                if ($template) {
                    $media = str_replace(['$RepresentationID$', '$Number$'], [(string)$rep['id'], (int)($template['startNumber'] ?? 0) + (int)($template->SegmentTimeline->S['r'] ?? 0)], $template['media']);
                    $url = "$baseUrl/dash/$media";
                    $context = stream_context_create(['http' => ['method' => 'GET','header' => 'User-Agent: ' . $userAgent . "\r\n" .'Referer: https://watch.tataplay.com/' . "\r\n" .'Origin: https://watch.tataplay.com',]]);
                    if (($content = @file_get_contents($url, false, $context)) !== false) {
                        $hexContent = bin2hex($content);
                        return extractKidandpssh($hexContent);
                    }
                }
            }
        }
    }
    return null;
}

function extractKidandpssh($hexContent) {
    $WV_SYSTEM_ID = 'edef8ba979d64acea3c827dcd51d21ed';
    $PR_SYSTEM_ID = '9a04f07998404286ab92e65be0885f95';
    $CACHE_FILE = 'app/data/cache_kid.json';
    $CACHE_TTL = 10 * 60;
    $cache = [];
    if (file_exists($CACHE_FILE)) {$cache = json_decode(file_get_contents($CACHE_FILE), true) ?: [];}
    $now = time();
    foreach ($cache as $key => $entry) {if ($now - $entry['timestamp'] > $CACHE_TTL) {unset($cache[$key]);}}
    $psshBoxes = [];
    $offset = 0;
    while (($offset = strpos($hexContent, '70737368', $offset)) !== false) {
        $headerSize = hexdec(substr($hexContent, $offset - 8, 8));
        $psshHex = substr($hexContent, $offset - 8, $headerSize * 2);
        $systemId = substr($psshHex, 24, 32);
        $psshBoxes[$systemId] = $psshHex;
        $offset += 8;
    }

    $hasWV = isset($psshBoxes[$WV_SYSTEM_ID]);
    $hasPR = isset($psshBoxes[$PR_SYSTEM_ID]);
    if (!$hasWV && !$hasPR) {return null;}
    $result = ['pssh' => null,'kid' => null,'pr_pssh' => null,];
    if ($hasWV) {
        $wvPsshHex = $psshBoxes[$WV_SYSTEM_ID];
        if (isset($cache[$wvPsshHex])) {
            $wvKidHex = $cache[$wvPsshHex]['kid'];} else {$wvKidHex = json_decode(file_get_contents("https://tp.secure-kid.workers.dev/", false, stream_context_create(["http"=>["method"=>"POST","header"=>"Content-Type: application/json\r\n","content"=>json_encode(["pssh"=>$wvPsshHex])]])), true)['encryptedKID'];
            $cache[$wvPsshHex] = ['kid' => $wvKidHex,'timestamp' => $now];}
        $result['pssh'] = base64_encode(hex2bin($wvPsshHex));
        $result['kid'] = substr($wvKidHex, 0, 8) . '-' . substr($wvKidHex, 8, 4) . '-' . substr($wvKidHex, 12, 4) . '-' . substr($wvKidHex, 16, 4) . '-' . substr($wvKidHex, 20);
    }
    if ($hasPR) {$result['pr_pssh'] = base64_encode(hex2bin($psshBoxes[$PR_SYSTEM_ID]));}
    file_put_contents($CACHE_FILE, json_encode($cache, JSON_PRETTY_PRINT));
    return $result;
}

function isApacheCompatible(): bool {
    $software = $_SERVER['SERVER_SOFTWARE'] ?? '';
    return stripos($software, 'Apache') !== false || stripos($software, 'LiteSpeed') !== false;
}

function decryptUrl($encryptedUrl, $aesKey) {
    $cleanEncrypted = preg_replace('/#.*$/', '', $encryptedUrl);
    $decoded = base64_decode($cleanEncrypted);
    $decryptedUrl = openssl_decrypt($decoded, 'aes-128-ecb', $aesKey, OPENSSL_RAW_DATA);
    if ($decryptedUrl === false) {return false;}
    return $decryptedUrl;
}

?>
