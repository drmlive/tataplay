<?php
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(array('success' => false, 'message' => 'POST required.'));
    exit;
}
$loginPath = 'data/login.json';
if (!is_file($loginPath)) {
    http_response_code(401);
    echo json_encode(array('success' => false, 'message' => 'Login required.'));
    exit;
}
$value = isset($_POST['enabled']) && $_POST['enabled'] === '1';
$configPath = 'data/update_config.json';
$config = is_file($configPath) ? json_decode(file_get_contents($configPath), true) : array();
if (!is_array($config)) $config = array();
$config['consent'] = $value;
$config['consent_updated_at'] = date('c');
if (!isset($config['installed_sha'])) $config['installed_sha'] = '14c40a45824fe6c64aa810862e5732a02e6820ed';
$dir = dirname($configPath);
if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Cannot create configuration directory.'));
    exit;
}
$tmp = $configPath . '.tmp';
$json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if ($json === false || file_put_contents($tmp, $json, LOCK_EX) === false || !rename($tmp, $configPath)) {
    @unlink($tmp);
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => 'Could not save update preference.'));
    exit;
}
@chmod($configPath, 0600);
echo json_encode(array('success' => true, 'enabled' => $value, 'message' => $value ? 'Automatic updates enabled.' : 'Automatic updates disabled.'));
