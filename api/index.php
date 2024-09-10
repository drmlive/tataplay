<?php
error_reporting(0);
ini_set('display_errors', 0);
$userAgent = $_SERVER['HTTP_USER_AGENT'];
$beginTimestamp = isset($_GET['utc']) ? intval($_GET['utc']) : null;
$endTimestamp = isset($_GET['lutc']) ? intval($_GET['lutc']) : null;
$begin = $beginTimestamp ? date('Ymd\THis', $beginTimestamp) : 'unknown';
$end = $endTimestamp ? date('Ymd\THis', $endTimestamp) : 'unknown';
$id = $_GET['id'] ?? exit;
$channelInfo = getChannelInfo($id);
$dashUrl = $channelInfo['streamData']['MPD='] ?? exit;
if (strpos($dashUrl, 'https://bpprod') !== 0) {
    header("Location: $dashUrl");
    exit;
}
if ($beginTimestamp) {
    $dashUrl = str_replace('master', 'manifest', $dashUrl);
    $dashUrl .= "?begin=$begin&end=$end";
}
$manifestContent = fetchMPDManifest($dashUrl, $userAgent) ?? exit;
$baseUrl = dirname($dashUrl);
$widevinePssh = extractPsshFromManifest($manifestContent, $baseUrl, $userAgent, $beginTimestamp);
    $processedManifest = str_replace('dash/', "$baseUrl/dash/", $manifestContent);
    if ($widevinePssh) {
    $processedManifest = str_replace(
      '<ContentProtection value="cenc" schemeIdUri="urn:mpeg:dash:mp4protection:2011"/>',
      '<!-- Common Encryption -->
      <ContentProtection schemeIdUri="urn:mpeg:dash:mp4protection:2011" value="cenc" cenc:default_KID="' . $widevinePssh['kid'] . '">
      </ContentProtection>',
      $processedManifest
    );
    $processedManifest = str_replace(
      '<ContentProtection schemeIdUri="urn:uuid:9a04f079-9840-4286-ab92-e65be0885f95" value="PlayReady"/>
      <ContentProtection schemeIdUri="urn:uuid:edef8ba9-79d6-4ace-a3c8-27dcd51d21ed" value="Widevine"/>',
      '<!-- Widevine -->
      <ContentProtection schemeIdUri="urn:uuid:EDEF8BA9-79D6-4ACE-A3C8-27DCD51D21ED">
        <cenc:pssh>' . $widevinePssh['pssh'] . '</cenc:pssh>
      </ContentProtection>',
      $processedManifest
    );
}
if (in_array($id, ['244', '599'])) {
    $processedManifest = str_replace(
        'minBandwidth="226400" maxBandwidth="3187600" maxWidth="1920" maxHeight="1080"',
        'minBandwidth="226400" maxBandwidth="2452400" maxWidth="1280" maxHeight="720"',
        $processedManifest
    );
    $processedManifest = preg_replace('/<Representation id="video=3187600" bandwidth="3187600".*?<\/Representation>/s', '', $processedManifest);
}

header('Content-Security-Policy: default-src \'self\';');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/dash+xml');
header("Cache-Control: max-age=20, public");
header('Content-Disposition: attachment; filename="script_by_drmlive' . urlencode($id) . '.mpd"');
echo $processedManifest;
function fetchMPDManifest(string $url, string $userAgent): ?string {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => 'User-Agent: ' . $userAgent,
        ]
    ]);
    $content = @file_get_contents($url, false, $context);
    return $content !== false ? $content : null;
}
function extractKid($hexContent) {
    $psshMarker = "70737368";
    $psshOffset = strpos($hexContent, $psshMarker);
    
    if ($psshOffset !== false) {
        $headerSizeHex = substr($hexContent, $psshOffset - 8, 8);
        $headerSize = hexdec($headerSizeHex);
        $psshHex = substr($hexContent, $psshOffset - 8, $headerSize * 2);
        $kidHex = substr($psshHex, 68, 32);
        $newPsshHex = "000000327073736800000000edef8ba979d64acea3c827dcd51d21ed000000121210" . $kidHex;
        $pssh = base64_encode(hex2bin($newPsshHex));
        $kid = substr($kidHex, 0, 8) . "-" . substr($kidHex, 8, 4) . "-" . substr($kidHex, 12, 4) . "-" . substr($kidHex, 16, 4) . "-" . substr($kidHex, 20);
        
        return ['pssh' => $pssh, 'kid' => $kid];
    }
    
    return null;
}
function extractPsshFromManifest(string $content, string $baseUrl, string $userAgent, ?int $beginTimestamp): ?array {
    if (($xml = @simplexml_load_string($content)) === false) return null;
    foreach ($xml->Period->AdaptationSet as $set) {
        if ((string)$set['contentType'] === 'audio') {
            foreach ($set->Representation as $rep) {
                $template = $rep->SegmentTemplate ?? null;
                if ($template) {
                    $startNumber = $beginTimestamp ? (int)($template['startNumber'] ?? 0) : (int)($template['startNumber'] ?? 0) + (int)($template->SegmentTimeline->S['r'] ?? 0);
                    $media = str_replace(['$RepresentationID$', '$Number$'], [(string)$rep['id'], $startNumber], $template['media']);
                    $url = "$baseUrl/dash/$media";
                    $context = stream_context_create([
                        'http' => ['method' => 'GET', 'header' => 'User-Agent: ' . $userAgent],
                    ]);
                    if (($content = @file_get_contents($url, false, $context)) !== false) {
                        $hexContent = bin2hex($content);
                        return extractKid($hexContent);
                    }
                }
            }
        }
    }
    return null;
}
function getChannelInfo(string $id): array {
    $json = @file_get_contents('https://raw.githubusercontent.com/ttoor5/tataplay_urls/main/origin.json');
    $channels = $json !== false ? json_decode($json, true) : null;
    if ($channels === null) {
        exit;
    }
    foreach ($channels as $channel) {
        if ($channel['id'] == $id) return $channel;
    }
    exit;
}
?>
