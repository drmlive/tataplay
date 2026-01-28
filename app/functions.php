<?php

$id = $_GET['id'] ?? null;
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$loginFilePath = 'app/data/login.json';
$cachePath = 'app/data/cache_urls.json';
$origin_json = 'app/origin.json';
$aesKey = 'aesEncryptionKey';
$ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36';
$content_api = 'https://tb.tapi.videoready.tv/content-detail/api/partner/cdn/player/details/chotiluli/' . $id;
$origin_api = 'https://tp.drmlive-01.workers.dev/origin';
$stb_only = 'https://tp.drmlive-01.workers.dev/stb_only';

function extractPsshFromManifest(string $content, string $baseUrl, string $userAgent, bool $useInitialization = false): ?array {
    if (($xml = @simplexml_load_string($content)) === false) {return null;}
    if ($result = extractpsshFromMpd($xml)) {return $result;}
    foreach ($xml->Period->AdaptationSet as $set) {
        if ((string)$set['contentType'] === 'audio') {
            foreach ($set->Representation as $rep) {
                $template = $rep->SegmentTemplate ?? null;
                if ($template) {
                    $url = rtrim($baseUrl, '/') . '/' . ltrim((string)$xml->Period->BaseURL, '/');
                    if ($useInitialization) $initUrl = $url . str_replace('$RepresentationID$', (string)$rep['id'], (string)$template['initialization']);
                    else $initUrl = $url . str_replace(['$RepresentationID$', '$Number$'], [(string)$rep['id'], (int)($template['startNumber'] ?? 0) + (int)($template->SegmentTimeline->S['r'] ?? 0)], (string)$template['media']);
                    $context = stream_context_create(['http' => ['method' => 'GET', 'header' => 'User-Agent: ' . $userAgent . "\r\n" . 'Referer: https://watch.tataplay.com/' . "\r\n" . 'Origin: https://watch.tataplay.com']]);
                    if (($content = @file_get_contents($initUrl, false, $context)) !== false) return extractpsshfromhex(bin2hex($content));
                }
            }
        }
    }
    return null;
}

function extractpsshFromMpd(SimpleXMLElement $xml): ?array{
    $namespaces = $xml->getNamespaces(true);
    $mpdNs  = $namespaces['']     ?? 'urn:mpeg:dash:schema:mpd:2011';
    $cencNs = $namespaces['cenc'] ?? 'urn:mpeg:cenc:2013';
    $xml->registerXPathNamespace('mpd',  $mpdNs);
    $xml->registerXPathNamespace('cenc', $cencNs);
    $kidNodes = $xml->xpath('//mpd:ContentProtection[@cenc:default_KID]');
    if (!$kidNodes) return null;
    $wv = $xml->xpath('//mpd:ContentProtection[contains(translate(@schemeIdUri,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"edef8ba9")]/cenc:pssh');
    $wvPssh = $wv ? trim((string)$wv[0]) : null;
    $pr = $xml->xpath('//mpd:ContentProtection[contains(translate(@schemeIdUri,"ABCDEFGHIJKLMNOPQRSTUVWXYZ","abcdefghijklmnopqrstuvwxyz"),"9a04f079")]/cenc:pssh');
    $prPssh = $pr ? trim((string)$pr[0]) : null;
    if (!$wvPssh && !$prPssh) {return null;}
    return finalizeKidAndPssh(['wv_pssh' => $wvPssh, 'pr_pssh' => $prPssh]);
}

function extractpsshfromhex(string $hexContent): ?array{
    $WV_SYSTEM_ID = 'edef8ba979d64acea3c827dcd51d21ed';
    $PR_SYSTEM_ID = '9a04f07998404286ab92e65be0885f95';
    $psshBoxes = [];
    $offset = 0;

    while (($offset = strpos($hexContent, '70737368', $offset)) !== false) {
        $size = hexdec(substr($hexContent, $offset - 8, 8));
        $psshHex = substr($hexContent, $offset - 8, $size * 2);
        $systemId = substr($psshHex, 24, 32);
        $psshBoxes[$systemId] = $psshHex;
        $offset += 8;
    }

    if (!isset($psshBoxes[$WV_SYSTEM_ID]) && !isset($psshBoxes[$PR_SYSTEM_ID])) {return null;}
    return finalizeKidAndPssh([
        'wv_pssh' => isset($psshBoxes[$WV_SYSTEM_ID]) ? base64_encode(hex2bin($psshBoxes[$WV_SYSTEM_ID])) : null,
        'pr_pssh' => isset($psshBoxes[$PR_SYSTEM_ID]) ? base64_encode(hex2bin($psshBoxes[$PR_SYSTEM_ID])) : null,
    ]);
}

function finalizeKidAndPssh(array $data): array{
    $CACHE_FILE = 'app/data/cache_kid.json';
    $CACHE_TTL  = 600;
    $now = time();
    $cache = file_exists($CACHE_FILE) ? json_decode(file_get_contents($CACHE_FILE), true) ?? [] : [];
    foreach ($cache as $k => $v) {if ($now - $v['timestamp'] > $CACHE_TTL) unset($cache[$k]);}
    $result = ['pssh' => null,'pr_pssh' => null,'kid' => null,];
    if (!empty($data['wv_pssh'])) {$psshB64 = $data['wv_pssh'];$psshHex = bin2hex(base64_decode($psshB64));
        if (!isset($cache[$psshHex])) {$resp = json_decode(file_get_contents("https://tp.secure-kid.workers.dev/", false, stream_context_create(['http' => ['method' => 'POST','header' => "Content-Type: application/json\r\n",'content' => json_encode(['pssh' => $psshHex]),]])), true);
            $cache[$psshHex] = ['kid' => $resp['encryptedKID'],'timestamp' => $now];}
        $kidHex = $cache[$psshHex]['kid'];
        $result['pssh'] = $psshB64;
        $result['kid'] =substr($kidHex, 0, 8) . '-' . substr($kidHex, 8, 4) . '-' . substr($kidHex, 12, 4) . '-' . substr($kidHex, 16, 4) . '-' . substr($kidHex, 20);}
    if (!empty($data['pr_pssh'])) {$result['pr_pssh'] = $data['pr_pssh'];}
    @mkdir(dirname($CACHE_FILE), 0777, true);
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

