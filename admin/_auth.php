<?php
define('WONDER_APP', true);

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function admin_config(): array {
    require __DIR__ . '/../api/db.php';
    return wonder_config();
}

function admin_require_login(): void {
    if (empty($_SESSION['admin_user'])) {
        header('Location: login.php');
        exit;
    }
}
