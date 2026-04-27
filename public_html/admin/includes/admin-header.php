<?php
$adminTitle = $adminTitle ?? 'Admin — HabeshaAir';
$me = current_admin();
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= e($adminTitle) ?></title>
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/admin.css">
<link rel="icon" type="image/svg+xml" href="/assets/images/logo.svg">
</head>
<body class="admin-body">

<header class="admin-header">
  <div class="container header-row">
    <a href="/admin/" class="brand">
      <span class="brand-mark"><svg viewBox="0 0 32 32" width="28" height="28"><path d="M16 3 L20 14 L31 16 L20 18 L16 29 L12 18 L1 16 L12 14 Z" fill="currentColor"/></svg></span>
      <span class="brand-name">Admin</span>
    </a>
    <nav class="admin-nav">
      <a href="/admin/" <?= ($activeNav ?? '') === 'dashboard' ? 'class="active"' : '' ?>>Dashboard</a>
      <a href="/admin/requests.php" <?= ($activeNav ?? '') === 'requests' ? 'class="active"' : '' ?>>Requests</a>
      <a href="/admin/messages.php" <?= ($activeNav ?? '') === 'messages' ? 'class="active"' : '' ?>>Messages</a>
      <span class="admin-user"><?= e($me['display_name'] ?? '') ?></span>
      <a href="/admin/logout.php" class="admin-logout">Sign out</a>
    </nav>
  </div>
</header>

<main class="admin-main">
