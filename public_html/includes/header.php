<?php
/**
 * header.php — site chrome up to <body>; called by every public page.
 * Each page sets $page = ['title' => ..., 'description' => ..., ...] before include.
 */
require_once __DIR__ . '/schema-org.php';

$title       = $page['title']       ?? cfg('app.company') . ' — Premium Air Charter Africa & Beyond';
$description = $page['description'] ?? 'HabeshAir coordinates premium VIP, cargo, humanitarian, and emergency charter flights across Africa, the Middle East, and beyond. Response within 60 minutes.';
$canonical   = $page['canonical']   ?? url($_SERVER['REQUEST_URI'] ?? '/');
$ogImage     = $page['og_image']    ?? url('/assets/images/og-default.jpg');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0a1628">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">

<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<meta property="og:image" content="<?= e($ogImage) ?>">
<meta property="og:site_name" content="<?= e(cfg('app.company')) ?>">
<meta name="twitter:card" content="summary_large_image">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="icon" type="image/svg+xml" href="/assets/images/logo.svg">
<link rel="alternate icon" href="/favicon.ico">

<?php render_schema_org(); ?>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header" id="top">
  <div class="container header-row">
    <a href="/" class="brand" aria-label="HabeshAir home">
      <span class="brand-mark" aria-hidden="true">
        <svg viewBox="0 0 32 32" width="32" height="32"><path d="M16 3 L20 14 L31 16 L20 18 L16 29 L12 18 L1 16 L12 14 Z" fill="currentColor"/></svg>
      </span>
      <span class="brand-name">Habesh<span class="brand-accent">Air</span></span>
    </a>

    <button class="nav-toggle" aria-controls="primary-nav" aria-expanded="false" aria-label="Toggle navigation">
      <span></span><span></span><span></span>
    </button>

    <nav id="primary-nav" class="primary-nav" aria-label="Primary">
      <ul>
        <li><a href="/services.php"<?= active_nav('services') ?>>Services</a></li>
        <li><a href="/how-it-works.php"<?= active_nav('how') ?>>How it works</a></li>
        <li><a href="/about.php"<?= active_nav('about') ?>>About</a></li>
        <li><a href="/faq.php"<?= active_nav('faq') ?>>FAQ</a></li>
        <li><a href="/contact.php"<?= active_nav('contact') ?>>Contact</a></li>
      </ul>
      <a href="/request.php" class="btn btn-gold nav-cta">Request a Charter</a>
    </nav>
  </div>
</header>

<main id="main">
