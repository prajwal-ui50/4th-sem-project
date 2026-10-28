<?php
// includes/sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$role = isset($_SESSION['role']) ? $_SESSION['role'] : 'user';
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo-icon">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
                <path d="M13 5v2"/>
                <path d="M13 17v2"/>
                <path d="M13 11v2"/>
            </svg>
        </div>
        <span class="sidebar-title">Ticket Flow</span>
        <button class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Collapse sidebar" aria-label="Collapse sidebar" type="button">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="15 18 9 12 15 6"/>
            </svg>
        </button>
    </div>

    <ul class="sidebar-menu">
        <?php if ($role === 'user'): ?>
            <li>
                <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>" title="Dashboard">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="create-ticket.php" class="<?= $current_page == 'create-ticket.php' ? 'active' : '' ?>" title="Create Ticket">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    <span class="menu-text">Create Ticket</span>
                </a>
            </li>
            <li>
                <a href="tickets.php" class="<?= $current_page == 'tickets.php' || $current_page == 'ticket-details.php' ? 'active' : '' ?>" title="My Tickets">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/></svg>
                    <span class="menu-text">My Tickets</span>
                </a>
            </li>
            <li>
                <a href="profile.php" class="<?= $current_page == 'profile.php' ? 'active' : '' ?>" title="Profile">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span class="menu-text">Profile</span>
                </a>
            </li>
        <?php elseif ($role === 'staff'): ?>
            <li>
                <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>" title="Dashboard">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="tickets.php" class="<?= $current_page == 'tickets.php' || $current_page == 'ticket-details.php' ? 'active' : '' ?>" title="Assigned Tickets">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/></svg>
                    <span class="menu-text">Assigned Tickets</span>
                </a>
            </li>
            <li>
                <a href="profile.php" class="<?= $current_page == 'profile.php' ? 'active' : '' ?>" title="Profile">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span class="menu-text">Profile</span>
                </a>
            </li>
        <?php elseif ($role === 'admin'): ?>
            <li>
                <a href="dashboard.php" class="<?= $current_page == 'dashboard.php' ? 'active' : '' ?>" title="Dashboard">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="tickets.php" class="<?= $current_page == 'tickets.php' || $current_page == 'ticket-details.php' ? 'active' : '' ?>" title="All Tickets">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/></svg>
                    <span class="menu-text">All Tickets</span>
                </a>
            </li>
            <li>
                <a href="users.php" class="<?= $current_page == 'users.php' || $current_page == 'add-user.php' || $current_page == 'edit-user.php' ? 'active' : '' ?>" title="Manage Users">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span class="menu-text">Manage Users</span>
                </a>
            </li>
            <li>
                <a href="categories.php" class="<?= $current_page == 'categories.php' ? 'active' : '' ?>" title="Categories">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                    <span class="menu-text">Categories</span>
                </a>
            </li>
            <li>
                <a href="reports.php" class="<?= $current_page == 'reports.php' ? 'active' : '' ?>" title="Reports">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                    <span class="menu-text">Reports</span>
                </a>
            </li>
            <li>
                <a href="profile.php" class="<?= $current_page == 'profile.php' ? 'active' : '' ?>" title="Profile">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    <span class="menu-text">Profile</span>
                </a>
            </li>
        <?php endif; ?>
        <li>
            <a href="../logout.php" class="logout-link" title="Logout">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                <span class="menu-text">Logout</span>
            </a>
        </li>
    </ul>
</aside>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
