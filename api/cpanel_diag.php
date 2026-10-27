<?php
header('Content-Type: application/json');

$nodePath = '/opt/alt/alt-nodejs22/root/usr/bin/node';
$npmPath = '/opt/alt/alt-nodejs22/root/usr/bin/npm';

$info = [
    'whoami' => @exec('whoami 2>&1'),
    'pwd' => @exec('pwd 2>&1'),
    'node_path' => $nodePath,
    'node_executable' => is_executable($nodePath),
    'node_version' => @exec($nodePath . ' -v 2>&1'),
    'npm_path' => $npmPath,
    'npm_executable' => is_executable($npmPath),
    'npm_version' => is_executable($npmPath) ? @exec($nodePath . ' ' . $npmPath . ' -v 2>&1') : @exec('which npm 2>&1'),
    'chromium_check' => @exec('which chromium-browser 2>&1') ?: @exec('which chromium 2>&1') ?: @exec('which google-chrome 2>&1'),
    'bridge_dir' => realpath(__DIR__ . '/../bridge'),
    'bridge_node_modules' => is_dir(__DIR__ . '/../bridge/node_modules'),
    'data_dir_writable' => is_writable(__DIR__ . '/../data'),
    'open_ports_check' => @exec('netstat -tlpn 2>&1') ?: @exec('ss -tlpn 2>&1') ?: 'unavailable'
];

echo json_encode($info, JSON_PRETTY_PRINT);
