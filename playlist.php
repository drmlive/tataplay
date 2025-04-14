<?php

include 'app/functions.php';
if (!file_exists($loginFilePath)) {http_response_code(401); echo 'Login required.'; exit;}
header('Content-Type: audio/x-mpegurl');
header('Content-Disposition: attachment; filename="playlist.m3u"');
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $origin_api);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);
$data = json_decode($response, true);
$channels = $data['data']['list'] ?? [];
if (!is_array($channels)) {http_response_code(500);echo "# Error: Invalid or missing 'list' in response\n";exit;}
$skip_ids_json = @file_get_contents($stb_only);
$skip_ids = json_decode($skip_ids_json, true);
if (!is_array($skip_ids)) $skip_ids = [];
if (stripos($userAgent, 'tivimate') !== false) {$liveheaders = '| X-Forwarded-For=59.178.74.184 | Origin=https://watch.tataplay.com | Referer=https://watch.tataplay.com/';} elseif  (stripos($userAgent, 'SparkleTV') !== false) {$liveheaders = '|X-Forwarded-For=59.178.74.184|Origin=https://watch.tataplay.com|Referer=https://watch.tataplay.com/';} else {$liveheaders = '|X-Forwarded-For=59.178.74.184&Origin=https://watch.tataplay.com&Referer=https://watch.tataplay.com/';}
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$port = $_SERVER['SERVER_PORT'];
$host_with_port = $host;
if (($protocol === 'http' && $port !== '80') || ($protocol === 'https' && $port !== '443')) {$host_with_port = $_SERVER['SERVER_NAME'] . ':' . $port;}
$request_uri = $_SERVER['REQUEST_URI'];
$path = dirname($request_uri);
$base_url = "{$protocol}://{$host_with_port}{$path}";
$is_apache = isApacheCompatible();
if ($is_apache) {$htaccess_path = '.htaccess';
    $stream_path = file_exists($htaccess_path) ? "manifest.mpd" : "get-mpd.php";} else {
    $stream_path = "get-mpd.php";
}

foreach ($channels as $channel) {
    $channel_id = $channel['id'];
    if (in_array($channel_id, $skip_ids, true)) {continue;}
    if (isset($channel['provider']) && $channel['provider'] === 'DistroTV') {continue;}
    $channel_name = $channel['title'];
    $channel_logo = $channel['transparentImageUrl'];
    $channel_genre = $channel['genres'][0] ?? 'General';
    if (in_array('HD', $channel['genres'])) {$channel_genre .= ", HD";}

    $license_url = "https://tp.drmlive-01.workers.dev?id={$channel_id}";
    $channel_live = "{$base_url}/{$stream_path}?id={$channel_id}{$liveheaders}";

    echo "#EXTINF:-1 tvg-id=\"ts{$channel_id}\" tvg-logo=\"{$channel_logo}\" group-title=\"{$channel_genre}\",{$channel_name}\n";
    echo "#KODIPROP:inputstream.adaptive.license_type=clearkey\n";
    echo "#KODIPROP:inputstream.adaptive.license_key={$license_url}\n";
    echo "#KODIPROP:inputstream.adaptive.manifest_type=mpd\n";
    echo "#EXTVLCOPT:http-user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.0.0 Safari/537.36\n";
    echo "{$channel_live}\n\n";
}
