<?php
require_once 'functions.php';

if (!function_exists('tpUpdateConfigPath')) {
    function tpUpdateConfigPath() { return 'data/update_config.json'; }
    function tpUpdateReadJson($path) {
        if (!is_file($path)) return array();
        $data = json_decode(@file_get_contents($path), true);
        return is_array($data) ? $data : array();
    }
    function tpUpdateWriteJson($path, $data) {
        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($json === false || @file_put_contents($path, $json) === false) return false;
        @chmod($path, 0600);
        return true;
    }
    function tpAllowedUpdatePath($path) {
        // Root: ONLY these three files.
        if (in_array($path, array('get-mpd.php', 'index.php', 'playlist.php'), true)) return true;
        // App: everything except updater/control files and all local data.
        if (strpos($path, 'app/') !== 0 || strpos($path, 'app/data/') === 0) return false;
        $base = basename($path);
        if (in_array($base, array('updater.php', 'run_update.php', 'set_auto_update.php'), true)) return false;
        if (preg_match('/^update([._-]|$)/i', $base)) return false;
        return true;
    }
    function tpGitBlobSha($contents) { return sha1('blob ' . strlen($contents) . "\0" . $contents); }
    function tpGitHubRequest($url, $json = false) {
        global $ua;
        if (!function_exists('curl_init')) return array('ok' => false, 'error' => 'PHP cURL extension is required.');
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 25,
            CURLOPT_USERAGENT => !empty($ua) ? $ua : 'TataPlay-Updater',
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0, CURLOPT_ENCODING => '',
            CURLOPT_HTTPHEADER => $json ? array('Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28') : array('Accept: application/vnd.github.raw+json'),
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2
        ));
        $body = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $error = curl_error($ch); curl_close($ch);
        if ($body === false || $code < 200 || $code >= 300) return array('ok' => false, 'error' => $error ?: ('GitHub returned HTTP ' . $code));
        if ($json) { $data = json_decode($body, true); return is_array($data) ? array('ok'=>true,'data'=>$data) : array('ok'=>false,'error'=>'Invalid GitHub JSON.'); }
        return array('ok'=>true,'data'=>$body);
    }
    function tpGetTree($sha) {
        $c = tpGitHubRequest('https://api.github.com/repos/drmlive/tataplay/commits/' . rawurlencode($sha), true);
        if (!$c['ok'] || empty($c['data']['commit']['tree']['sha'])) return array('ok'=>false,'error'=>isset($c['error'])?$c['error']:'Cannot read commit tree.');
        $t = tpGitHubRequest('https://api.github.com/repos/drmlive/tataplay/git/trees/' . rawurlencode($c['data']['commit']['tree']['sha']) . '?recursive=1', true);
        if (!$t['ok'] || !isset($t['data']['tree']) || !empty($t['data']['truncated'])) return array('ok'=>false,'error'=>isset($t['error'])?$t['error']:'Incomplete GitHub tree.');
        $map = array();
        foreach ($t['data']['tree'] as $i) if (isset($i['path'],$i['sha'],$i['type'],$i['mode']) && $i['type']==='blob' && in_array($i['mode'],array('100644','100755'),true)) $map[$i['path']] = array('sha'=>$i['sha'],'size'=>isset($i['size'])?(int)$i['size']:0);
        return array('ok'=>true,'tree'=>$map);
    }
    function tpBuildUpdatePlan() {
        $cfg = tpUpdateReadJson(tpUpdateConfigPath());
        $latest = tpGitHubRequest('https://api.github.com/repos/drmlive/tataplay/commits/main', true);
        if (!$latest['ok'] || empty($latest['data']['sha'])) return array('ok'=>false,'error'=>isset($latest['error'])?$latest['error']:'Cannot get latest commit.');
        $latestSha = $latest['data']['sha'];
        $baseSha = !empty($cfg['installed_sha']) ? $cfg['installed_sha'] : '14c40a45824fe6c64aa810862e5732a02e6820ed';
        $old = tpGetTree($baseSha); $new = tpGetTree($latestSha);
        if (!$old['ok']) return array('ok'=>false,'error'=>'Cannot verify installed baseline: '.$old['error']);
        if (!$new['ok']) return array('ok'=>false,'error'=>'Cannot read latest tree: '.$new['error']);
        $root = dirname(__DIR__); $files = array();
        foreach ($new['tree'] as $path=>$entry) {
            if (!tpAllowedUpdatePath($path) || $entry['size'] > 2097152) continue;
            $oldEntry = isset($old['tree'][$path]) ? $old['tree'][$path] : null;
            $changedUpstream = !$oldEntry || $oldEntry['sha'] !== $entry['sha'];
            $local = $root.'/'.$path; $exists = is_file($local);
            $localSha = $exists ? tpGitBlobSha((string)@file_get_contents($local)) : null;
            $matchesLatest = $exists && hash_equals($entry['sha'],$localSha);
            $matchesOld = $exists && $oldEntry && hash_equals($oldEntry['sha'],$localSha);
            if (!$changedUpstream && $matchesLatest) continue;
            if (!$exists && $oldEntry && !$changedUpstream) $status='missing_locally';
            elseif (!$exists && !$oldEntry) $status='new_file';
            elseif ($matchesLatest) $status='already_current';
            elseif ($matchesOld || (!$oldEntry && !$exists)) $status='upstream_changed';
            else $status='local_modified';
            $files[] = array('path'=>$path,'status'=>$status,'upstream_changed'=>$changedUpstream,'local_exists'=>$exists,'local_matches_latest'=>$matchesLatest,'latest_sha'=>$entry['sha'],'size'=>$entry['size']);
        }
        return array('ok'=>true,'latest_sha'=>$latestSha,'installed_sha'=>$baseSha,'files'=>$files);
    }
    function tpPreviewAutoUpdate() {
        $p=tpBuildUpdatePlan();
        if (!$p['ok']) return array('success'=>false,'message'=>$p['error']);
        $counts=array('upstream_changed'=>0,'local_modified'=>0,'new_file'=>0,'missing_locally'=>0,'already_current'=>0);
        foreach($p['files'] as $f) if(isset($counts[$f['status']])) $counts[$f['status']]++;
        return array('success'=>true,'message'=>count($p['files'])?'Preview ready. Review the files below.':'Already up to date.','latest_sha'=>$p['latest_sha'],'installed_sha'=>$p['installed_sha'],'files'=>$p['files'],'counts'=>$counts);
    }
    function tpApplyUpdateInternal($forceLocal) {
        $p=tpBuildUpdatePlan(); if(!$p['ok']) return array('success'=>false,'message'=>$p['error']);
        $root=dirname(__DIR__); $data='data'; if(!is_dir($data) && !@mkdir($data,0755,true)) return array('success'=>false,'message'=>'Cannot create updater data directory.');
        $pending=array();
        foreach($p['files'] as $f) {
            $apply = $forceLocal ? !$f['local_matches_latest'] : in_array($f['status'],array('upstream_changed','new_file'),true);
            if(!$apply) continue;
            $url='https://raw.githubusercontent.com/drmlive/tataplay/'.rawurlencode($p['latest_sha']).'/'.str_replace('%2F','/',rawurlencode($f['path']));
            $d=tpGitHubRequest($url,false);
            if(!$d['ok']) return array('success'=>false,'message'=>'Download failed for '.$f['path'].': '.$d['error']);
            if(strlen($d['data'])>2097152 || !hash_equals($f['latest_sha'],tpGitBlobSha($d['data']))) return array('success'=>false,'message'=>'Integrity verification failed for '.$f['path']);
            $pending[$f['path']]=$d['data'];
        }
        if(!$pending) {
            $cfg=tpUpdateReadJson(tpUpdateConfigPath()); $cfg['installed_sha']=$p['latest_sha']; $cfg['last_status']='Already up to date. No files needed replacement.'; $cfg['last_checked_at']=gmdate('c'); $cfg['last_check']=time(); tpUpdateWriteJson(tpUpdateConfigPath(),$cfg);
            return array('success'=>true,'message'=>$cfg['last_status'],'updated'=>0,'latest_sha'=>$p['latest_sha']);
        }
        $backup=$data.'/update-backups/'.gmdate('Ymd-His').'-'.substr($p['latest_sha'],0,8);
        if(!is_dir($backup) && !@mkdir($backup,0755,true)) return array('success'=>false,'message'=>'Cannot create backup directory.');
        $applied=array();
        try {
            foreach($pending as $path=>$contents) {
                $target=$root.'/'.$path; if(!is_dir(dirname($target)) && !@mkdir(dirname($target),0755,true)) throw new Exception('Cannot create directory for '.$path);
                if(is_file($target)) { $b=$backup.'/'.$path; if(!is_dir(dirname($b)) && !@mkdir(dirname($b),0755,true)) throw new Exception('Cannot create backup directory for '.$path); if(!@copy($target,$b)) throw new Exception('Cannot back up '.$path); }
                if(@file_put_contents($target,$contents)===false) throw new Exception('Cannot write '.$path);
                @chmod($target,is_file($target)?(fileperms($target)&0777):0644); $applied[]=$path;
            }
        } catch(Exception $e) {
            foreach(array_reverse($applied) as $path) { $target=$root.'/'.$path; $b=$backup.'/'.$path; if(is_file($b)) @copy($b,$target); else @unlink($target); }
            return array('success'=>false,'message'=>'Update rolled back: '.$e->getMessage());
        }
        $cfg=tpUpdateReadJson(tpUpdateConfigPath()); $cfg['installed_sha']=$p['latest_sha']; $cfg['last_checked_at']=gmdate('c'); $cfg['last_check']=time(); $cfg['last_update_at']=gmdate('c'); $cfg['last_update_sha']=$p['latest_sha']; $cfg['last_update_files']=array_values(array_keys($pending));
        $cfg['last_status']=($forceLocal?'Force updated ':'Updated ').count($pending).' file(s) to '.substr($p['latest_sha'],0,12).'.'; tpUpdateWriteJson(tpUpdateConfigPath(),$cfg);
        return array('success'=>true,'message'=>$cfg['last_status'],'updated'=>count($pending),'latest_sha'=>$p['latest_sha'],'files'=>array_values(array_keys($pending)),'backup_dir'=>str_replace($root.'/','',$backup));
    }
    function tpRunAutoUpdate($manual=false,$forceLocal=false) {
        $path=tpUpdateConfigPath(); $cfg=tpUpdateReadJson($path);
        if(!$manual && empty($cfg['consent'])) return array('success'=>false,'enabled'=>false,'message'=>'Automatic updates are disabled.');
        $hour=gmdate('Y-m-d-H');
        if(!$manual && !empty($cfg['last_check_utc_hour']) && hash_equals((string)$cfg['last_check_utc_hour'],$hour)) return array('success'=>true,'enabled'=>true,'message'=>isset($cfg['last_status'])?$cfg['last_status']:'Already checked this UTC hour.');
        $data='data'; if(!is_dir($data) && !@mkdir($data,0755,true)) return array('success'=>false,'message'=>'Cannot create updater data directory.');
        $result=tpApplyUpdateInternal($forceLocal); $cfg=tpUpdateReadJson($path); $cfg['last_check_utc_hour']=$hour; $cfg['last_checked_at']=gmdate('c'); $cfg['last_check']=time(); $cfg['last_status']=$result['message']; tpUpdateWriteJson($path,$cfg);
        $result['enabled']=true; return $result;
    }
}
