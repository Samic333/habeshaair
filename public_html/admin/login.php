<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';

if (current_admin()) {
    redirect('/admin/');
}

$error = flash_get('login_error');
$next  = (string)($_GET['next'] ?? '/admin/');
if (!str_starts_with($next, '/admin')) $next = '/admin/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    if (!rate_check('admin_login', (int)cfg('security.rate_login_max', 5), (int)cfg('security.rate_login_window', 900))) {
        flash_set('login_error', 'Too many attempts. Try again in 15 minutes.');
        redirect('/admin/login.php');
    }

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT id, password_hash, is_active FROM admin_users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $row = $stmt->fetch();

    if (!$row || (int)$row['is_active'] !== 1 || !password_verify($password, (string)$row['password_hash'])) {
        // Constant-ish delay to mitigate user enumeration
        usleep(random_int(150_000, 350_000));
        flash_set('login_error', 'Invalid username or password.');
        redirect('/admin/login.php' . ($next !== '/admin/' ? '?next=' . urlencode($next) : ''));
    }

    admin_login((int)$row['id']);
    redirect($next);
}

$adminTitle = 'Admin sign in — HabeshAir';
$activeNav  = '';
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($adminTitle) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body admin-login-body">
<main class="admin-login-wrap">
  <div class="admin-login-card">
    <h1 style="margin-bottom:.25em">Admin sign in</h1>
    <p style="color:var(--gray-600); margin:0 0 1.5rem">HabeshAir operations console</p>

    <?php if ($error): ?>
      <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="post" action="/admin/login.php<?= $next !== '/admin/' ? '?next=' . urlencode($next) : '' ?>" novalidate>
      <?= csrf_field() ?>
      <div class="form-grid">
        <div class="form-group">
          <label for="username">Username</label>
          <input id="username" type="text" name="username" autocomplete="username" required>
        </div>
        <div class="form-group">
          <label for="password">Password</label>
          <input id="password" type="password" name="password" autocomplete="current-password" required>
        </div>
        <button type="submit" class="btn btn-navy btn-block btn-lg">Sign in</button>
      </div>
    </form>

    <p style="margin-top:1.5rem; font-size:.85rem; color:var(--gray-600)">
      <a href="/">← Back to site</a>
    </p>
  </div>
</main>
</body>
</html>
