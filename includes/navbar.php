<?php
// includes/navbar.php
require_once __DIR__ . "/notifications.php";
$user = currentUser();
$unread_count = 0;
if ($user) {
    $unread_count = getUnreadNotificationCount($pdo, $user['id']);
}

// Generate user initials for avatar
$name_parts = explode(" ", trim($user['name'] ?? 'User'));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if (count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}
$currentTitle = isset($pageTitle) ? htmlspecialchars($pageTitle) : "Dashboard";
?>
<header class="topbar">
    <div class="topbar-left">
        <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Open navigation menu" title="Open navigation menu" type="button">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        <nav aria-label="Breadcrumb">
            <ol class="breadcrumb">
                <li><a href="dashboard.php">Home</a></li>
                <?php if ($currentTitle !== "Dashboard"): ?>
                    <li class="breadcrumb-separator" aria-hidden="true">/</li>
                    <li class="breadcrumb-current" aria-current="page"><?= $currentTitle ?></li>
                <?php endif; ?>
            </ol>
        </nav>
    </div>

    <div class="topbar-right">
        <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Switch theme" aria-label="Switch theme" type="button">
            <span id="themeIcon" style="display:inline-flex;align-items:center;justify-content:center;transition:transform 0.3s ease,opacity 0.25s ease;"></span>
        </button>

        <a href="notifications.php" class="notif-bell-wrap" aria-label="Notifications<?= $unread_count > 0 ? ' (' . $unread_count . ' unread)' : '' ?>">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
            </svg>
            <?php if ($unread_count > 0): ?>
                <span class="notif-count"><?= $unread_count ?></span>
            <?php endif; ?>
        </a>

        <a href="profile.php" class="user-info" style="text-decoration:none;" title="View Profile">
            <div class="user-avatar" aria-hidden="true"><?= htmlspecialchars($initials) ?></div>
            <div class="user-info-text">
                <strong><?= htmlspecialchars($user['name']) ?></strong>
            </div>
            <span class="badge badge-role-<?= htmlspecialchars(strtolower($user['role'])) ?>" style="text-transform: uppercase;">
                <?= htmlspecialchars($user['role']) ?>
            </span>
        </a>
    </div>
</header>
