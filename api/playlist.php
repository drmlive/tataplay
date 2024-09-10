<?php
header("Cache-Control: max-age=84000, public");
header('Content-Type: audio/x-mpegurl');
header('Content-Disposition: attachment; filename="playlist.m3u"');
function getAllChannelInfo(): array {
    $json = @file_get_contents('https://raw.githubusercontent.com/ttoor5/tataplay_urls/main/origin.json');
    if ($json === false) {
        header("HTTP/1.1 500 Internal Server Error");
        exit;
    }
    $channels = json_decode($json, true);
    if ($channels === null) {
        header("HTTP/1.1 500 Internal Server Error");
        exit;
    }
    return $channels;
}
$channels = getAllChannelInfo();
$serverAddress = $_SERVER['HTTP_HOST'] ?? 'default.server.address';
$serverPort = $_SERVER['SERVER_PORT'] ?? '80';
$serverScheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$dirPath = dirname($requestUri);
$portPart = ($serverPort != '80' && $serverPort != '443') ? ":$serverPort" : '';
$m3u8PlaylistFile = "#EXTM3U x-tvg-url=\"https://www.tsepg.cf/epg.xml.gz\"\n";
foreach ($channels as $channel) {
    $id = $channel['id'];
    $dashUrl = $channel['streamData']['MPD='] ?? null;
    if ($dashUrl === null) {
        continue;
    }
    $extension = pathinfo(parse_url($dashUrl, PHP_URL_PATH), PATHINFO_EXTENSION);
    $playlistUrl = "https://$serverAddress/{$id}.$extension|X-Forwarded-For=59.178.72.184";
    $m3u8PlaylistFile .= "#EXTINF:-1 tvg-id=\"{$id}\" tvg-country=\"IN\" catchup-days=\"7\" tvg-logo=\"https://mediaready.videoready.tv/tatasky-epg/image/fetch/f_auto,fl_lossy,q_auto,h_250,w_250/{$channel['channel_logo']}\" group-title=\"{$channel['channel_genre'][0]}\",{$channel['channel_name']}\n";
    $m3u8PlaylistFile .= "#KODIPROP:inputstream.adaptive.license_type=clearkey\n";
    $m3u8PlaylistFile .= "#KODIPROP:inputstream.adaptive.license_key=https://tpck.drmlive-01.workers.dev/?id={$id}\n";
    $m3u8PlaylistFile .= "#EXTVLCOPT:http-user-agent=third-party\n";
    $m3u8PlaylistFile .= "$playlistUrl\n\n";
}
$additionalEntries = <<<EOT
#EXTINF:-1 tvg-logo="https://mediaready.videoready.tv/tatasky-epg/image/fetch/f_auto,fl_lossy,q_auto,h_250,w_250/https://ltsk-cdn.s3.eu-west-1.amazonaws.com/jumpstart/Temp_Live/cdn/HLS/Channel/imageContent-11073-j95e7nyo-v1/imageContent-11073-j95e7nyo-m1.png" group-title="zee",Zee TV HD
#EXTVLCOPT:http-user-agent=Mozilla/5.0
https://la.drmlive.au/tp/zee.php?id=tvhd
#EXTINF:-1 tvg-logo="https://mediaready.videoready.tv/tatasky-epg/image/fetch/f_auto,fl_lossy,q_auto,h_250,w_250/https://ltsk-cdn.s3.eu-west-1.amazonaws.com/jumpstart/Temp_Live/cdn/HLS/Channel/imageContent-117-j5fl7440-v1/imageContent-117-j5fl7440-m1.png" group-title="zee",&tv HD
#EXTVLCOPT:http-user-agent=Mozilla/5.0
https://la.drmlive.au/tp/zee.php?id=andtvhd
#EXTINF:-1 tvg-logo="https://mediaready.videoready.tv/tatasky-epg/image/fetch/f_auto,fl_lossy,q_auto,h_250,w_250/https://ltsk-cdn.s3.eu-west-1.amazonaws.com/jumpstart/Temp_Live/cdn/HLS/Channel/XPOHD_Thumbnail-v1/XPOHD_Thumbnail.png" group-title="zee",&Xplor HD
#EXTVLCOPT:http-user-agent=Mozilla/5.0
https://la.drmlive.au/tp/zee.php?id=andxplorehd
#EXTINF:-1 tvg-logo="https://mediaready.videoready.tv/tatasky-epg/image/fetch/f_auto,fl_lossy,q_auto,h_250,w_250/https://ltsk-cdn.s3.eu-west-1.amazonaws.com/jumpstart/Temp_Live/cdn/HLS/Channel/imageContent-11173-j9hth720-v1/imageContent-11173-j9hth720-m1.png" group-title="zee",&pictures HD
#EXTVLCOPT:http-user-agent=Mozilla/5.0
https://la.drmlive.au/tp/zee.php?id=andpictureshd
#EXTINF:-1 tvg-logo="https://mediaready.videoready.tv/tatasky-epg/image/fetch/f_auto,fl_lossy,q_auto,h_250,w_250/https://ltsk-cdn.s3.eu-west-1.amazonaws.com/jumpstart/Temp_Live/cdn/HLS/Channel/imageContent-11915-j9l5clzs-v1/imageContent-11915-j9l5clzs-m1.png" group-title="zee",Zee Cinema HD
#EXTVLCOPT:http-user-agent=Mozilla/5.0
https://la.drmlive.au/tp/zee.php?id=cinemahd
#EXTINF:-1 tvg-logo="https://upload.wikimedia.org/wikipedia/commons/1/12/%26flix_logo.png" group-title="Movies",&flix HD
#EXTVLCOPT:http-user-agent=Mozilla/5.0
https://la.drmlive.au/tp/zee.php?id=andflixhd
#EXTINF:-1 tvg-logo="https://upload.wikimedia.org/wikipedia/en/0/0b/Zee_Zest_logo.jpeg" group-title="zee",Zee Zest HD
#EXTVLCOPT:http-user-agent=Mozilla/5.0
https://la.drmlive.au/tp/zee.php?id=zesthd
#EXTINF:-1 tvg-logo="https://mediaready.videoready.tv/tatasky-epg/image/fetch/f_auto,fl_lossy,q_auto,h_250,w_250/https://ltsk-cdn.s3.eu-west-1.amazonaws.com/jumpstart/Temp_Live/cdn/HLS/Channel/imageContent-11266-j9j2spmg-v1/imageContent-11266-j9j2spmg-m1.png" group-title="zee",Big Magic
#EXTVLCOPT:http-user-agent=Mozilla/5.0
https://la.drmlive.au/tp/zee.php?id=bigmagic
#EXTINF:-1 tvg-logo="https://upload.wikimedia.org/wikipedia/commons/thumb/7/77/%26priv%C3%A9_HD.svg/2880px-%26priv%C3%A9_HD.svg.png" group-title="zee",&prive HD
#EXTVLCOPT:http-user-agent=Mozilla/5.0
https://la.drmlive.au/tp/zee.php?id=privehd
#EXTINF:-1 tvg-logo="https://upload.wikimedia.org/wikipedia/en/a/a4/Zee_Action_2023_logo.png" group-title="zee",Zee Action
#EXTVLCOPT:http-user-agent=Mozilla/5.0
https://la.drmlive.au/tp/zee.php?id=action
#EXTINF:-1 tvg-logo="https://mediaready.videoready.tv/tatasky-epg/image/fetch/f_auto,fl_lossy,q_auto,h_250,w_250/https://ltsk-cdn.s3.eu-west-1.amazonaws.com/jumpstart/Temp_Live/cdn/HLS/Channel/imageContent-31233-jli1wlvc-v1/imageContent-31233-jli1wlvc-m1.png" group-title="zee",Zee Bollywood
#EXTVLCOPT:http-user-agent=Mozilla/5.0
https://la.drmlive.au/tp/zee.php?id=bollywood
#EXTINF:-1 tvg-logo="https://mediaready.videoready.tv/tatasky-epg/image/fetch/f_auto,fl_lossy,q_auto,h_250,w_250/https://ltsk-cdn.s3.eu-west-1.amazonaws.com/jumpstart/Temp_Live/cdn/HLS/Channel/imageContent-11090-j95hdh6o-v1/imageContent-11090-j95hdh6o-m1.png" group-title="zee",Zee Anmol Cinema
#EXTVLCOPT:http-user-agent=Mozilla/5.0
https://la.drmlive.au/tp/zee.php?id=anmolcinema
#EXTINF:-1 tvg-logo="https://upload.wikimedia.org/wikipedia/en/1/14/Zee_Caf%C3%A9_2011_logo.png" group-title="zee",Zee Cafe HD
#EXTVLCOPT:http-user-agent=Mozilla/5.0
https://la.drmlive.au/tp/zee.php?id=cafehd
#EXTINF:-1 tvg-logo="https://mediaready.videoready.tv/tatasky-epg/image/fetch/f_auto,fl_lossy,q_auto,h_250,w_250/https://ltsk-cdn.s3.eu-west-1.amazonaws.com/jumpstart/Temp_Live/cdn/HLS/Channel/imageContent-11969-j9luigc0-v2/imageContent-11969-j9luigc0-m2.png" group-title="zee",Zee Anmol
#EXTVLCOPT:http-user-agent=Mozilla/5.0
https://la.drmlive.au/tp/zee.php?id=anmol
#EXTINF:-1 tvg-logo="https://mediaready.videoready.tv/tatasky-epg/image/fetch/f_auto,fl_lossy,q_auto,h_250,w_250/https://ltsk-cdn.s3.eu-west-1.amazonaws.com/jumpstart/Temp_Live/cdn/HLS/Channel/imageContent-49009-k5g6nid4-v1/imageContent-49009-k5g6nid4-m1.png" group-title="zee",Zee Punjabi
#EXTVLCOPT:http-user-agent=Mozilla/5.0
https://la.drmlive.au/tp/zee.php?id=punjabi

#EXTINF:-1 tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/Sony_HD.png" group-title="Sony Liv",SONY HD
https://la.drmlive.au/tp/sliv.php?id=sony
#EXTINF:-1 tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/Sony_SAB_HD.png" group-title="Sony Liv",SONY SAB HD
https://la.drmlive.au/tp/sliv.php?id=sab
#EXTINF:-1 tvg-logo="https://i.postimg.cc/ZqnmcXdx/Sony-KAL.png" group-title="Sony Liv",SONY KAL
https://spt-sonykal-1-us.lg.wurl.tv/playlist.m3u8
#EXTINF:-1 tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/Sony_Pal.png" group-title="Sony Liv",SONY PAL
https://la.drmlive.au/tp/sliv.php?id=pal
#EXTINF:-1 tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/Sony_Wah.png" group-title="Sony Liv",SONY WAH
https://la.drmlive.au/tp/sliv.php?id=wah
#EXTINF:-1 tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/SET_MAX.png" group-title="Sony Liv",SONY MAX
https://la.drmlive.au/tp/sliv.php?id=max
#EXTINF:-1 tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/Sony_Max_HD.png" group-title="Sony Liv",SONY MAX HD
https://la.drmlive.au/tp/sliv.php?id=maxhd
#EXTINF:-1 tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/Sony_MAX2.png" group-title="Sony Liv",SONY MAX2
https://la.drmlive.au/tp/sliv.php?id=max2
#EXTINF:-1 tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/Ten_HD.png" group-title="Sony Liv",SONY TEN 1 HD
https://la.drmlive.au/tp/sliv.php?id=ten1hd
#EXTINF:-1 tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/Ten_1.png" group-title="Sony Liv",SONY TEN 1
https://la.drmlive.au/tp/sliv.php?id=ten1
#EXTINF:-1 tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/Ten2_HD.png" group-title="Sony Liv",SONY TEN 2 HD
https://la.drmlive.au/tp/sliv.php?id=ten2hd
#EXTINF:-1 tvg-logo="https://jiotv.catchup.cdn.jio.com/dare_images/images/Ten_2.png" group-title="Sony Liv",SONY TEN 2
https://la.drmlive.au/tp/sliv.php?id=ten2
#EXTINF:-1 tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/Ten3_HD.png" group-title="Sony Liv",SONY TEN 3 HD
https://la.drmlive.au/tp/sliv.php?id=ten3hd
#EXTINF:-1 tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/Ten_3.png" group-title="Sony Liv",SONY TEN 3
https://la.drmlive.au/tp/sliv.php?id=ten3
#EXTINF:-1 tvg-logo="https://www.sonypicturesnetworks.com/images/logos/SONY_SportsTen4_HD_Logo_CLR.png" group-title="Sony Liv",SONY TEN 4 HD
https://la.drmlive.au/tp/sliv.php?id=ten4hd
#EXTINF:-1 tvg-logo="https://www.sonypicturesnetworks.com/images/logos/SONY_SportsTen4_SD_Logo_CLR.png" group-title="Sony Liv",SONY TEN 4 
https://la.drmlive.au/tp/sliv.php?id=ten4
#EXTINF:-1 tvg-logo="https://www.sonypicturesnetworks.com/images/logos/SONY_SportsTen5_HD_Logo_CLR.png" group-title="Sony Liv",SONY TEN 5 HD
https://la.drmlive.au/tp/sliv.php?id=ten5hd
#EXTINF:-1 tvg-logo="https://www.sonypicturesnetworks.com/images/logos/SONY_SportsTen5_SD_Logo_CLR.png" group-title="Sony Liv",SONY TEN 5 
https://la.drmlive.au/tp/sliv.php?id=ten5
#EXTINF:-1 tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/Sony_BBC_Earth_HD.png" group-title="Sony Liv",SONY BBC EARTH
https://la.drmlive.au/tp/sliv.php?id=bbc
#EXTINF:-1 tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/Sony_Yay_Hindi.png" group-title="Sony Liv",SONY YAY
https://la.drmlive.au/tp/sliv.php?id=yay
#EXTINF:-1 tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/Sony_Pix_HD.png" group-title="Sony Liv",SONY PIX HD
https://la.drmlive.au/tp/sliv.php?id=pix
#EXTINF:-1 tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/Sony_Marathi_SD.png" group-title="Sony Liv",SONY MARATHI
https://la.drmlive.au/tp/sliv.php?id=marathi
#EXTINF:-1 tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/Sony_aath.png" group-title="Sony Liv",SONY AATH 
https://la.drmlive.au/tp/sliv.php?id=aath

#EXTINF:-1 tvg-id="144" group-title="Entertainment" tvg-language="Hindi" tvg-logo="https://v3img.voot.com/resizeMedium,w_1090,h_613/v3Storage/assets/colors-hindi--16x9-1714557869344.jpg",Colors HD
https://jc.drmlive-01.workers.dev/144.m3u8
#EXTINF:-1 tvg-id="1370" group-title="Entertainment" tvg-language="Hindi" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/collors-rishtey-live-channels-16x9-3-1642676080416-1674198105431-1697532377978.jpg",Rishtey
https://jc.drmlive-01.workers.dev/1370.m3u8
#EXTINF:-1 tvg-id="756" group-title="Entertainment" tvg-language="Bengali" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/colors-bangla-new-16x9-4-1649659533344.jpg",Colors Bengla HD
https://jc.drmlive-01.workers.dev/756.m3u8
#EXTINF:-1 tvg-id="757" group-title="Entertainment" tvg-language="Kannada" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/colors-kannada-16x9-1677754085834.jpg",Colors Kannada HD
https://jc.drmlive-01.workers.dev/757.m3u8
#EXTINF:-1 tvg-id="755" group-title="Entertainment" tvg-language="Marathi" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/colors-marathi-live-channels-16x9-4-6-apr-1649257093359.jpg",Colors Marathi HD
https://jc.drmlive-01.workers.dev/755.m3u8
#EXTINF:-1 tvg-id="196" group-title="Entertainment" tvg-language="Gujarati" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/colors-gujarati-16x9-1713269620328.jpg",Colors Gujarati
https://jc.drmlive-01.workers.dev/196.m3u8
#EXTINF:-1 tvg-id="429" group-title="Entertainment" tvg-language="Tamil" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/ct-1644165913136.jpg",Colors Tamil HD
https://jc.drmlive-01.workers.dev/429.m3u8
#EXTINF:-1 tvg-id="198" group-title="Entertainment" tvg-language="Odia" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/colors-odia-live-channels-16x9-4-1642583679866.jpg",Colors Oriya
https://jc.drmlive-01.workers.dev/198.m3u8
#EXTINF:-1 tvg-id="1157" group-title="Entertainment" tvg-language="English" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/comedy-central-16x9-1624419769894-1663699927285.jpg",Comedy Central HD
https://jc.drmlive-01.workers.dev/1157.m3u8
#EXTINF:-1 tvg-id="1158" group-title="Entertainment" tvg-language="English" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/colors-infinity-live-channels-16x9-1642496946057.jpg",Colors Infinity HD
https://jc.drmlive-01.workers.dev/1158.m3u8
#EXTINF:-1 tvg-id="785" group-title="Entertainment" tvg-language="Kannada" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/colors-super-live-channels-16x9-4-1642744939924.jpg",Colors Super
https://jc.drmlive-01.workers.dev/785.m3u8
#EXTINF:-1 tvg-id="1477" group-title="Movies" tvg-language="Hindi" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/Live-Tv-Channels-colors-cineplex-1607514413063.jpg",Colors Cineplex HD
https://jc.drmlive-01.workers.dev/1477.m3u8
#EXTINF:-1 tvg-id="1450" group-title="Movies" tvg-language="Hindi" tvg-logo="https://v3img.voot.com/resizeMedium,w_960,h_540/v3Storage/assets/colors-cineplex-superhit%2016x9-1648793655358.jpg",Colors Cineplex Superhit
https://jc.drmlive-01.workers.dev/1450.m3u8
#EXTINF:-1 tvg-id="1763" group-title="Movies" tvg-language="Hindi" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/cineplex-1713963820848.jpeg",Colors Cineplex Bollywood
https://jc.drmlive-01.workers.dev/1763.m3u8
#EXTINF:-1 tvg-id="1632" group-title="Movies" tvg-language="Kannada" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/colors-kannada-cinema-16x9-1713963481807.jpg",Colors Kannada Cinema
https://jc.drmlive-01.workers.dev/1632.m3u8
#EXTINF:-1 tvg-id="1145" group-title="Music" tvg-language="Hindi" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/mtv-16x9-1714316345624.jpg",MTV HD
https://jc.drmlive-01.workers.dev/1145.m3u8
#EXTINF:-1 tvg-id="753" group-title="Music" tvg-language="Hindi" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/mtv-beats-live-channels-16x9-1642675874665.jpg",MTV Beats HD
https://jc.drmlive-01.workers.dev/753.m3u8
#EXTINF:-1 tvg-id="544" group-title="Kids" tvg-language="Hindi" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/nick-jr-16x9-2-1626708077243.jpg",Nick Junior
https://jc.drmlive-01.workers.dev/544.m3u8
#EXTINF:-1 tvg-id="1226" group-title="Kids" tvg-language="English" tvg-logo="https://v3img.voot.com/resizeMedium,w_1090,h_613/v3Storage/assets/nick-hd-plus-live-channels-16x9-4-1642585145139.jpg",Nick HD+
https://jc.drmlive-01.workers.dev/1226.m3u8
#EXTINF:-1 tvg-id="1227" group-title="Kids" tvg-language="Hindi" tvg-logo="https://v3img.voot.com/resizeMedium,w_1090,h_613/v3Storage/assets/16x9-1719554242246.jpg",Nick SD
https://jc.drmlive-01.workers.dev/1227.m3u8
#EXTINF:-1 tvg-id="815" group-title="Kids" tvg-language="Hindi" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/sonic-16x9-2-1626707025539.jpg",Sonic
https://jc.drmlive-01.workers.dev/815.m3u8
#EXTINF:-1 tvg-id="122" group-title="Sports" tvg-language="English" tvg-logo="https://v3img.voot.com/resizeMedium,w_450,h_253/v3Storage/assets/sports18_tray-1693930594270.jpg",SPORTS 18 HD
https://jc.drmlive-01.workers.dev/122.m3u8
#EXTINF:-1 tvg-id="1998" group-title="Sports" tvg-language="Hindi" tvg-logo="https://v3img.voot.com/resizeMedium,w_1090,h_613/v3Storage/assets/sports18_khel_tray-1693931658589.jpg",SPORTS 18 Khel
https://jc.drmlive-01.workers.dev/1998.m3u8
#EXTINF:-1 tvg-id="2000" group-title="Cricket" tvg-language="Hindi" tvg-logo="https://v3img.voot.com/resizeMedium,w_1090,h_613/v3Storage/assets/jc_sports_horizontal_tray-1695561700528.jpg",JC Sports
https://jc.drmlive-01.workers.dev/2000.m3u8
#EXTINF:-1 tvg-id="2001" group-title="Sports" tvg-language="English" tvg-logo="https://v3img.voot.com/resizeMedium,w_1090,h_613/v3Storage/assets/sports_zone_cricket_horizontal-1695032322122.jpg",CricStream
https://jc.drmlive-01.workers.dev/2001.m3u8
#EXTINF:-1 tvg-id="190" group-title="Business" tvg-language="Hindi" tvg-logo="https://v3img.voot.com/resizeMedium,w_1090,h_613/v3Storage/assets/cnbc-awaaz-16x9-1702387934761.jpg",CNBC Awaaz
https://jc.drmlive-01.workers.dev/190.m3u8
#EXTINF:-1 tvg-id="490" group-title="Business" tvg-language="Gujarati" tvg-logo="https://v3img.voot.com/resizeMedium,w_1090,h_613/v3Storage/assets/whatsapp16x9-1693491956187.jpg",CNBC Bazaar
https://jc.drmlive-01.workers.dev/490.m3u8
#EXTINF:-1 tvg-id="489" group-title="Business" tvg-language="English" tvg-logo="https://v3img.voot.com/resizeMedium,w_1090,h_613/v3Storage/assets/cnbc18-shereen-bhan-16x9-2-1693479472079.jpg",CNBC Tv 18
https://jc.drmlive-01.workers.dev/489.m3u8
#EXTINF:-1 tvg-id="231" group-title="News" tvg-language="Hindi" tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/IBN_7.png",News18 India
https://jc.drmlive-01.workers.dev/231.m3u8
#EXTINF:-1 tvg-id="492" group-title="News" tvg-language="English" tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/CNN_NEWS_18.png",CNN NEWS 18
https://jc.drmlive-01.workers.dev/492.m3u8
#EXTINF:-1 tvg-id="615" group-title="News" tvg-language="Tamil" tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/News_18_Tamilnadu.png",News18 Tamilnadu
https://jc.drmlive-01.workers.dev/615.m3u8
#EXTINF:-1 tvg-id="232" group-title="News" tvg-language="Marathi" tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/IBN_Lokmat.png",News18 Lokmat
https://jc.drmlive-01.workers.dev/232.m3u8
#EXTINF:-1 tvg-id="717" group-title="News" tvg-language="Bengali" tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/ETV_Bangla_News.png",News18 Bangla News
https://jc.drmlive-01.workers.dev/717.m3u8
#EXTINF:-1 tvg-id="653" group-title="News" tvg-language="Kannada" tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/ETV_Kannada_News.png",News18 Kannada News
https://jc.drmlive-01.workers.dev/653.m3u8
#EXTINF:-1 tvg-id="620" group-title="News" tvg-language="Gujarati" tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/ETV_News_Gujarati.png",News18 Gujarati
https://jc.drmlive-01.workers.dev/620.m3u8
#EXTINF:-1 tvg-id="655" group-title="News" tvg-language="Hindi" tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/ETV_Haryana_and_HP_News.png",News18 Punjab Haryana
https://jc.drmlive-01.workers.dev/655.m3u8
#EXTINF:-1 tvg-id="696" group-title="News" tvg-language="Odia" tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/ETV_News_Oriya.png",News18 Oriya
https://jc.drmlive-01.workers.dev/696.m3u8
#EXTINF:-1 tvg-id="627" group-title="News" tvg-language="Assamese" tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/News_18_Assam.png",News18 Assam
https://jc.drmlive-01.workers.dev/627.m3u8
#EXTINF:-1 tvg-id="965" group-title="News" tvg-language="Malayalam" tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/News_18_Kerala.png",News 18 Kerala
https://jc.drmlive-01.workers.dev/965.m3u8
#EXTINF:-1 tvg-id="531" group-title="News" tvg-language="Hindi" tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/ETV_RAJASTHAN.png",News18 RAJASTHAN
https://jc.drmlive-01.workers.dev/531.m3u8
#EXTINF:-1 tvg-id="693" group-title="News" tvg-language="Bhojpuri" tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/ETV_BIHAR.png",News18 BIHAR
https://jc.drmlive-01.workers.dev/693.m3u8
#EXTINF:-1 tvg-id="529" group-title="News" tvg-language="Hindi" tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/ETV_MP.png",News18 MP
https://jc.drmlive-01.workers.dev/529.m3u8
#EXTINF:-1 tvg-id="530" group-title="News" tvg-language="Hindi" tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/ETV_UP.png",News18 UP
https://jc.drmlive-01.workers.dev/530.m3u8
#EXTINF:-1 tvg-id="694" group-title="News" tvg-language="Urdu" tvg-logo="http://jiotv.catchup.cdn.jio.com/dare_images/images/ETV_Urdu.png",News18 JKLH
https://jc.drmlive-01.workers.dev/694.m3u8
EOT;
echo $m3u8PlaylistFile . $additionalEntries;
?>
