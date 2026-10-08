<?php
// Shared top of the auth pages. Set before requiring:
//   $authTitle (string), $authAlt = ['label' => 'Create account', 'href' => 'register.php']
$authAlt = $authAlt ?? ['label' => 'Sign in', 'href' => 'login.php'];
$hasSeal = is_file(__DIR__ . '/../assets/img/omsc-seal.png');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= escape($authTitle) ?> | <?= escape($site['site_name']) ?> Portal | Occidental Mindoro State College</title>
<?php require __DIR__ . '/styles.php'; ?>
<link rel="stylesheet" href="<?= escape(url('assets/css/auth.css')) ?>">
</head>
<body class="el-body">
<a class="el-skip" href="#main-content">Skip to content</a>

<header class="el-header">
  <div class="el-wrap">
    <a class="el-brand" href="<?= escape(url('/')) ?>">
      <?php if ($hasSeal): ?><img src="<?= escape(url('assets/img/omsc-seal.png')) ?>" alt="" width="48" height="48"><?php endif; ?>
      <img src="<?= escape(url('assets/img/elia-logo.png')) ?>" alt="ELIA logo" width="48" height="48">
      <span class="el-brand-text"><strong><?= escape($site['site_name']) ?> Portal</strong><span>Occidental Mindoro State College</span></span>
    </a>
    <nav class="el-nav" aria-label="Main navigation">
      <a class="el-home" href="<?= escape(url('/')) ?>">Home</a>
      <a class="el-btn el-btn-navy" href="<?= escape(url($authAlt['href'])) ?>"><?= escape($authAlt['label']) ?></a>
    </nav>
  </div>
</header>

<main id="main-content" tabindex="-1" class="au-stage"></main></main>