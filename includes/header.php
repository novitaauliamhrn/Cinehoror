<?php
// includes/header.php
// Panggil setelah session_start() dan setelah $page_title diset
$page_title = $page_title ?? 'CineHoror';
$user       = currentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — CineHoror</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <link rel="stylesheet" href="<?= isset($base_path) ? $base_path : '' ?>assets/style.css">
</head>
<body>

<header>
    <a href="<?= isset($base_path) ? $base_path : '' ?>index.php" class="logo">
        CineHoror
        <span>BIOSKOP ONLINE</span>
    </a>
    <div class="header-right">
        <div class="wa-badge"><i class="fab fa-whatsapp"></i> WhatsApp Integrated</div>
        <?php if ($user): ?>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="<?= isset($base_path) ? $base_path : '' ?>admin/" class="nav-link admin-link">
                    <i class="fas fa-cog"></i> Admin Panel
                </a>
            <?php endif; ?>
            <div class="user-info">
                <i class="fas fa-user-circle"></i>
                <span><?= htmlspecialchars($user['name']) ?></span>
                <a href="<?= isset($base_path) ? $base_path : '' ?>index.php?logout=1" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        <?php endif; ?>
    </div>
</header>