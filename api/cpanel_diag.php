<?php
header('Content-Type: application/json');

$results = [
    'php_version' => PHP_VERSION,
    'uname' => php_uname(),
    'disabled_functions' => ini_get('disable_functions'),
    'can_exec' => function_exists('exec'),
    'can_shell_exec' => function_exists('shell_exec'),
    'node_path' => null,
    'node_version' => null,
    'npm_version' => null,
    'home_dir' => getenv('HOME') ?: ($_SERVER['HOME'] ?? null),
    'cloudlinux' => file_exists('/usr/share/cagefs') || file_exists('/etc/cloudlinux-release'),
    'cpanel_nodejs' => file_exists('/usr/local/cpanel/3rdparty/bin/node') || is_dir('/opt/alt/alt-nodejs20') || is_dir('/opt/alt/alt-nodejs18') || is_dir('/opt/alt/alt-nodejs22')
];

if (function_exists('exec')) {
    $node = @exec('which node 2>&1');
    $results['node_path'] = $node;
    if ($node && str_starts_with($node, '/')) {
        $results['node_version'] = @exec($node . ' -v 2>&1');
    } else {
        // Check common cPanel / CloudLinux alt-nodejs paths
        foreach (['/opt/alt/alt-nodejs22/root/usr/bin/node', '/opt/alt/alt-nodejs20/root/usr/bin/node', '/opt/alt/alt-nodejs18/root/usr/bin/node', '/usr/local/cpanel/3rdparty/bin/node', '/usr/bin/node'] as $path) {
            if (is_executable($path)) {
                $results['node_path'] = $path;
                $results['node_version'] = @exec($path . ' -v 2>&1');
                break;
            }
        }
    }
}

echo json_encode($results, JSON_PRETTY_PRINT);
