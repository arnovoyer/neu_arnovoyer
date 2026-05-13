<?php

declare(strict_types=1);

require __DIR__ . '/../lib/auth.php';

auth_start_session();
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();
header('Location: /auth/login.php', true, 302);
exit;
