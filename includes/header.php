<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - Retrade' : 'Retrade - Marketplace'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Inter font for Material feel -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/admin.css">
    <link rel="stylesheet" href="assets/css/chat.css">
</head>
<body>
    <div class="layout">
        <?php include __DIR__ . '/sidebar.php'; ?>
        <div class="main">
            <header class="topbar">
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-link text-white d-lg-none p-0 me-1" id="sidebarToggle" aria-label="Toggle sidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="title"><?php echo htmlspecialchars($page_title ?? ''); ?></div>
                </div>
                <div class="actions">
                    <a href="#" class="icon-btn" title="Notifications"><i class="far fa-bell"></i></a>
                    <a href="#" class="icon-btn" title="Messages"><i class="far fa-envelope"></i></a>
                    <?php if (isset($_SESSION['user_name'])): ?>
                        <span class="d-none d-md-inline text-white-50 me-1"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    <?php endif; ?>
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['user_name'] ?? 'User'); ?>&background=3f51b5&color=fff" alt="avatar" class="avatar">
                </div>
            </header>
            <main class="content">
