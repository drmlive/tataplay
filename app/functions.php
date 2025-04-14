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
    $psshMarker = "70737368";
    $psshOffsets = [];
    $offset = 0;
    while (($offset = strpos($hexContent, $psshMarker, $offset)) !== false) {$psshOffsets[] = $offset;$offset += 8;}
    if (count($psshOffsets) < 2) {"Error: Less than two PSSH found.\n";return null;}
    $wvPsshOffset = $psshOffsets[0];
    $wvHeaderSizeHex = substr($hexContent, $wvPsshOffset - 8, 8);
    $wvHeaderSize = hexdec($wvHeaderSizeHex);
    $wvPsshHex = substr($hexContent, $wvPsshOffset - 8, $wvHeaderSize * 2);
    $wvKidHex = substr($wvPsshHex, 68, 32);
    $newWvPsshHex = "000000327073736800000000edef8ba979d64acea3c827dcd51d21ed000000121210" . $wvKidHex;
    $wvPsshBase64 = base64_encode(hex2bin($newWvPsshHex));
    $wvKid = substr($wvKidHex, 0, 8) . "-" . substr($wvKidHex, 8, 4) . "-" . substr($wvKidHex, 12, 4) . "-" . substr($wvKidHex, 16, 4) . "-" . substr($wvKidHex, 20);
    $prPsshOffset = $psshOffsets[1];
    $prHeaderSizeHex = substr($hexContent, $prPsshOffset - 8, 8);
    $prHeaderSize = hexdec($prHeaderSizeHex);
    $prPsshHex = substr($hexContent, $prPsshOffset - 8, $prHeaderSize * 2);
    $prPsshBase64 = base64_encode(hex2bin($prPsshHex));
    return ['pssh' => $wvPsshBase64, 'kid' => $wvKid, 'pr_pssh' => $prPsshBase64];
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
