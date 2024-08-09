<?php
error_reporting(0);
ini_set('display_errors', 0);
function fetchData(string $url): ?string {
    return ($data = @file_get_contents($url)) !== false ? trim($data) : null;
}
function fetchMPDManifest(string $url): ?string {
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'X-Forwarded-For: 59.178.72.184'
        ],
    ]);
    $content = curl_exec($curl);
    curl_close($curl);
    return $content !== false ? $content : null;
}
function extractPsshFromManifest(string $content, string $baseUrl): ?array {
    if (($xml = @simplexml_load_string($content)) === false) return null;
    foreach ($xml->Period->AdaptationSet as $set) {
        if ((string)$set['contentType'] === 'audio') {
            foreach ($set->Representation as $rep) {
                $template = $rep->SegmentTemplate ?? null;
                if ($template) {
                    $media = str_replace(['$RepresentationID$', '$Number$'], [(string)$rep['id'], (int)($template['startNumber'] ?? 0) + (int)($template->SegmentTimeline->S['r'] ?? 0)], $template['media']);
                    $url = "$baseUrl/dash/$media";
                    $context = stream_context_create([
                        'http' => ['method' => 'GET', 'header' => 'X-Forwarded-For: 59.178.72.184'],
                    ]);
                    if (($content = @file_get_contents($url, false, $context)) !== false) {
                        $hex = bin2hex($content);
                        $marker = "000000387073736800000000edef8ba979d64acea3c827dcd51d21ed000000";
                        if (($pos = strpos($hex, $marker)) !== false && ($end = strpos($hex, "0000", $pos + strlen($marker))) !== false) {
                            $psshHex = substr($hex, $pos, $end - $pos - 12);
                            $psshHex = str_replace("000000387073736800000000edef8ba979d64acea3c827dcd51d21ed00000018", "000000327073736800000000edef8ba979d64acea3c827dcd51d21ed00000012", $psshHex);
                            $kidHex = substr($psshHex, 68, 32);
                            return [
                                'pssh' => base64_encode(hex2bin($psshHex)),
                                'kid' => substr($kidHex, 0, 8) . "-" . substr($kidHex, 8, 4) . "-" . substr($kidHex, 12, 4) . "-" . substr($kidHex, 16, 4) . "-" . substr($kidHex, 20)
                            ];
                        }
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
$id = $_GET['id'] ?? exit;
$channelInfo = getChannelInfo($id);
$dashUrl = $channelInfo['streamData']['MPD='] ?? exit;
if (strpos($dashUrl, 'https://bpprod') !== 0) {
    header("Location: $dashUrl");
    exit;
}
$manifestContent = fetchMPDManifest($dashUrl) ?? exit;
$baseUrl = dirname($dashUrl);
$widevinePssh = extractPsshFromManifest($manifestContent, $baseUrl);
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
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header('Content-Type: application/dash+xml');
header('Content-Disposition: attachment; filename="script_by_drmlive' . urlencode($id) . '.mpd"');
echo $processedManifest;
?>
