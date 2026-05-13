<?php

declare(strict_types=1);

function auth_start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function auth_data_path(string $file): string
{
    return __DIR__ . '/../data/' . $file;
}

function auth_load_json(string $file): array
{
    $path = auth_data_path($file);
    if (!file_exists($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false || trim($raw) === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function auth_save_json(string $file, array $data): bool
{
    $path = auth_data_path($file);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }

    return file_put_contents($path, $json . PHP_EOL, LOCK_EX) !== false;
}

function auth_get_users(): array
{
    return auth_load_json('users.json');
}

function auth_save_users(array $users): bool
{
    return auth_save_json('users.json', $users);
}

function auth_find_user_by_email(string $email): ?array
{
    $needle = strtolower(trim($email));
    foreach (auth_get_users() as $user) {
        if (strtolower((string)($user['email'] ?? '')) === $needle) {
            return $user;
        }
    }

    return null;
}

function auth_upsert_user(array $user): bool
{
    $users = auth_get_users();
    $updated = false;

    foreach ($users as $index => $existing) {
        if (($existing['email'] ?? '') === ($user['email'] ?? '')) {
            $users[$index] = array_merge($existing, $user);
            $updated = true;
            break;
        }
    }

    if (!$updated) {
        $users[] = $user;
    }

    return auth_save_users($users);
}

function auth_require_login(): void
{
    auth_start_session();
    if (empty($_SESSION['auth_user'])) {
        header('Location: /auth/login.php', true, 302);
        exit;
    }
}

function auth_is_admin(): bool
{
    auth_start_session();
    return !empty($_SESSION['auth_admin']);
}

function auth_require_admin(): void
{
    auth_start_session();
    if (!auth_is_admin()) {
        header('Location: /auth/admin-login.php', true, 302);
        exit;
    }
}

function auth_csrf_token(): string
{
    auth_start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
    }

    return (string) $_SESSION['csrf_token'];
}

function auth_verify_csrf(?string $token): bool
{
    auth_start_session();
    return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals((string) $_SESSION['csrf_token'], $token);
}
