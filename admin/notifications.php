<?php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/notifications.php";

requireLogin();
// We don't enforce a specific role here because this file will exist in each role's directory
// The directory itself is protected if they try to access the wrong one, but usually it's fine.

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

$pageTitle = "My Notifications";
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Notifications</h2>
                <?php if (count($notifications) > 0): ?>
                <form method="POST" action="notifications.php">
                    <button type="submit" name="mark_all" class="btn" style="background: var(--secondary);">Mark All as Read</button>
                </form>
                <?php endif; ?>
            </div>
            
            <div class="card" style="background: var(--surface); border-radius: var(--radius); box-shadow: var(--shadow);">
                <?php if (count($notifications) > 0): ?>
                    <ul class="history-list" style="list-style: none; padding: 0; margin: 0;">
                        <?php foreach ($notifications as $n): ?>
                            <li style="padding: 15px; border-bottom: 1px solid var(--border); background-color: <?= $n['is_read'] ? 'transparent' : 'rgba(107, 140, 255, 0.1)' ?>;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <div>
                                        <h4 style="margin: 0 0 5px 0;">
                                            <?php if (!$n['is_read']): ?>
                                                <span style="color: var(--primary);">●</span>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($n['title']) ?>
                                        </h4>
                                        <p style="margin: 0 0 10px 0; color: var(--muted);"><?= htmlspecialchars($n['message']) ?></p>
                                        <div class="history-date"><?= date('M d, Y h:i A', strtotime($n['created_at'])) ?></div>
                                    </div>
                                    <div>
                                        <?php if ($n['ticket_id']): ?>
                                            <form method="POST" action="notifications.php" style="display: inline;">
                                                <input type="hidden" name="mark_read" value="1">
                                                <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                                                <input type="hidden" name="redirect_url" value="ticket-details.php?id=<?= $n['ticket_id'] ?>">
                                                <button type="submit" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem;">View Ticket</button>
                                            </form>
                                        <?php elseif (!$n['is_read']): ?>
                                            <form method="POST" action="notifications.php" style="display: inline;">
                                                <input type="hidden" name="mark_read" value="1">
                                                <input type="hidden" name="notification_id" value="<?= $n['id'] ?>">
                                                <button type="submit" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem;">Mark Read</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div style="padding: 40px; text-align: center;">
                        <h4 class="text-muted">You have no notifications.</h4>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
