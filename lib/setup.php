<?php
// lib/setup.php
function generate_admin_password() {
    global $config;
    require_once __DIR__ . '/db.php';

    $password = bin2hex(random_bytes(16));
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $db = DB::init($config);
    DB::execute('UPDATE settings SET value = ? WHERE key = ?', [$hash, 'admin_password_hash']);

    // Write to setup.txt
    file_put_contents(__DIR__ . '/../setup.txt', "Admin password: {$password}\n");

    return $password;
}

function get_setup_password() {
    $file = __DIR__ . '/../setup.txt';
    if (file_exists($file)) {
        $content = file_get_contents($file);
        if (preg_match('/Admin password: (.+)/', $content, $matches)) {
            return $matches[1];
        }
    }
    return null;
}
