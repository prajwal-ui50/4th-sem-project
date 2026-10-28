<?php
// user/notifications.php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/notifications.php";

requireRole('user');

$user_id = $_SESSION['user_id'];

// Handle mark as read actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['mark_all'])) {
        markAllNotificationsAsRead($pdo, $user_id);
        header("Location: notifications.php");
        exit;
    } elseif (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
        $notif_id = (int)$_POST['notification_id'];
        markNotificationAsRead($pdo, $notif_id, $user_id);
        
        if (!empty($_POST['redirect_url'])) {
            header("Location: " . $_POST['redirect_url']);
            exit;
        } else {
            header("Location: notifications.php");
            exit;
        }
    }
}

$notifications = getUserNotifications($pdo, $user_id, 100);

$pageTitle = "Notifications";
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <div class="page-header">
                <div>
                    <h2>Notifications</h2>
                    <p class="text-muted mb-0">Updates on your tickets and support conversations</p>
                </div>
                <?php if (count($notifications) > 0): ?>
                    <form method="POST" action="notifications.php">
                        <button type="submit" name="mark_all" class="btn btn-outline btn-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                            <span>Mark All as Read</span>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <?php if (count($notifications) > 0): ?>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <?php foreach ($notifications as $n): ?>
                            <li class="notification-item <?= !$n['is_read'] ? 'notification-item-unread' : '' ?>" style="display: flex; justify-content: space-between; align-items: center; gap: var(--space-md); flex-wrap: wrap;">
                                <div style="display: flex; align-items: flex-start; gap: var(--space-md);">
                                    <div style="margin-top: 4px;">
                                        <?php if (!$n['is_read']): ?>
                                            <span style="display: inline-block; width: 10px; height: 10px; border-radius: var(--radius-full); background: var(--primary);"></span>
                                        <?php else: ?>
                                            <span style="display: inline-block; width: 10px; height: 10px; border-radius: var(--radius-full); background: var(--border);"></span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h4 style="margin: 0 0 var(--space-xs) 0; font-size: 1rem;">
                                            <?= htmlspecialchars($n['title']) ?>
                                        </h4>
                                        <p style="margin: 0 0 var(--space-xs) 0; color: var(--text-secondary); font-size: 0.9rem;">
                                            <?= htmlspecialchars($n['message']) ?>
                                        </p>
                                        <div class="text-muted" style="font-size: 0.75rem;">
                                            <?= date('M d, Y h:i A', strtotime($n['created_at'])) ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <?php if ($n['ticket_id']): ?>
                                        <form method="POST" action="notifications.php" style="display: inline;">
                                            <input type="hidden" name="mark_read" value="1">
                                            <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                                            <input type="hidden" name="redirect_url" value="ticket-details.php?id=<?= $n['ticket_id'] ?>">
                                            <button type="submit" class="btn btn-outline btn-sm">View Ticket &rarr;</button>
                                        </form>
                                    <?php elseif (!$n['is_read']): ?>
                                        <form method="POST" action="notifications.php" style="display: inline;">
                                            <input type="hidden" name="mark_read" value="1">
                                            <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm">Mark Read</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <div style="width: 64px; height: 64px; border-radius: var(--radius-full); background: var(--surface-secondary); display: flex; align-items: center; justify-content: center; margin: 0 auto var(--space-md); color: var(--muted);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        </div>
                        <h4>No notifications yet</h4>
                        <p class="text-muted">You're all caught up! When updates occur on your tickets, they will appear here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
