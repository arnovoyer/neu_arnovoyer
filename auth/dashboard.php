<?php

declare(strict_types=1);

require __DIR__ . '/../lib/auth.php';
auth_require_login();
$user = $_SESSION['auth_user'];
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="robots" content="noindex, nofollow, noarchive">
  <title>Account | Arno Voyer</title>
</head>
<body>
  <p>Eingeloggt als <?php echo htmlspecialchars((string)($user['name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
  <p><a href="/auth/logout.php">Logout</a></p>
</body>
</html>
