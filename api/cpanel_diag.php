<?php
header('Content-Type: application/json');

$nodePath = '/opt/alt/alt-nodejs22/root/usr/bin/node';
$npmPath = '/opt/alt/alt-nodejs22/root/usr/bin/npm';
$bridgeDir = realpath(__DIR__ . '/../bridge');

$test = [
    'bridgeDir' => $bridgeDir,
    'bridgeDir_writable' => is_writable($bridgeDir),
    'node_modules_exists' => is_dir($bridgeDir . '/node_modules'),
    'npm_exec_test' => shell_exec("cd " . escapeshellarg($bridgeDir) . " && " . escapeshellarg($npmPath) . " install qrcode --no-audit 2>&1")
];

echo json_encode($test, JSON_PRETTY_PRINT);
