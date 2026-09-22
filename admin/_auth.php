<?php
define('WONDER_APP', true);

require_once __DIR__ . '/../api/db.php';
require_once __DIR__ . '/_db_session.php';

session_set_save_handler(new WonderDbSessionHandler(wonder_db()), true);
session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

function admin_config(): array {
    return wonder_config();
}

function admin_require_login(): void {
    if (empty($_SESSION['admin_user'])) {
        header('Location: login.php');
        exit;
    }
}
