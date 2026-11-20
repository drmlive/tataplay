<?php
header('Content-Type: application/json; charset=utf-8');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'POST required.'));
    exit;
}
$sessionNonce = isset($_SESSION['tp_updater_nonce']) ? $_SESSION['tp_updater_nonce'] : '';
$requestNonce = isset($_POST['nonce']) ? (string)$_POST['nonce'] : '';
if ($sessionNonce === '' || $requestNonce === '' || !hash_equals($sessionNonce, $requestNonce)) {
    http_response_code(403);
    echo json_encode(array('success' => false, 'message' => 'Updater session expired. Refresh the page and try again.'));
    exit;
}
require_once 'updater.php';
$action = isset($_POST['action']) ? $_POST['action'] : 'preview';
if ($action === 'preview') {
    $result = tpPreviewAutoUpdate();
} elseif ($action === 'apply') {
    $result = tpRunAutoUpdate(true, false);
} elseif ($action === 'force') {
    $result = tpRunAutoUpdate(true, true);
} else {
    http_response_code(400);
    echo json_encode(array('success' => false, 'message' => 'Unknown updater action.'));
    exit;
}
$cfg = is_file(tpUpdateConfigPath()) ? tpUpdateReadJson(tpUpdateConfigPath()) : array();
$result['last_update_at'] = isset($cfg['last_update_at']) ? $cfg['last_update_at'] : null;
$result['last_update_sha'] = isset($cfg['last_update_sha']) ? $cfg['last_update_sha'] : null;
$result['last_checked_at'] = isset($cfg['last_checked_at']) ? $cfg['last_checked_at'] : null;
$result['installed_sha'] = isset($cfg['installed_sha']) ? $cfg['installed_sha'] : null;
echo json_encode($result);
