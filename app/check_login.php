<?php
header('Content-Type: application/json; charset=utf-8');
$loginExists = is_file('data/login.json');
$updateResult = array('enabled' => false, 'message' => 'Automatic updates are disabled.');
$config = is_file('data/update_config.json') ? json_decode(file_get_contents('data/update_config.json'), true) : array();
if (!is_array($config)) $config = array();
echo json_encode(array(
    'exists' => $loginExists,
    'auto_update' => !empty($config['consent']),
    'update_status' => isset($config['last_status']) ? $config['last_status'] : $updateResult['message'],
    'installed_sha' => isset($config['installed_sha']) ? $config['installed_sha'] : null,
    'last_update_at' => isset($config['last_update_at']) ? $config['last_update_at'] : null,
    'last_update_sha' => isset($config['last_update_sha']) ? $config['last_update_sha'] : null,
    'last_checked_at' => isset($config['last_checked_at']) ? $config['last_checked_at'] : null
));
