<?php
// user/delete-ticket.php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/Attachment.php";
require_once "../includes/notifications.php";

requireRole('user');

$user_id = $_SESSION['user_id'];
$user = currentUser();

$ticket_id = isset($_POST['ticket_id']) && is_numeric($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : (isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : 0);

if ($ticket_id <= 0) {
    header("Location: tickets.php");
    exit;
}

// Fetch ticket to ensure it belongs to the current user
try {
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name 
        FROM tickets t 
        LEFT JOIN categories c ON t.category_id = c.id 
        WHERE t.id = :ticket_id AND t.user_id = :user_id
    ");
    $stmt->execute([':ticket_id' => $ticket_id, ':user_id' => $user_id]);
    $ticket = $stmt->fetch();

    if (!$ticket) {
        header("Location: tickets.php?err=" . urlencode("Ticket not found or access denied."));
        exit;
    }
} catch (PDOException $e) {
    die("Database error occurred while fetching ticket.");
}

// Handle deletion upon POST confirmation
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['confirm_delete'])) {
    try {
        $ticket_number = $ticket['ticket_number'];
        $assigned_to = $ticket['assigned_to'];

        // 1. Delete associated attachments on disk and DB
        $attachmentHandler = new Attachment($pdo);
        $attachmentHandler->deleteByTicket($ticket['id']);

        // 2. Delete the ticket from DB (ON DELETE CASCADE handles messages, history, and notifications)
        $delStmt = $pdo->prepare("DELETE FROM tickets WHERE id = :ticket_id AND user_id = :user_id");
        $delStmt->execute([':ticket_id' => $ticket['id'], ':user_id' => $user_id]);

        // 3. Notify assigned staff or admins that ticket was deleted
        if (!empty($assigned_to)) {
            createNotification($pdo, $assigned_to, "Ticket Deleted", "User {$user['name']} has deleted ticket {$ticket_number}.", 'warning');
        } else {
            $admins_stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
            while ($admin = $admins_stmt->fetch()) {
                createNotification($pdo, $admin['id'], "Ticket Deleted", "User {$user['name']} has deleted ticket {$ticket_number}.", 'warning');
            }
        }

        // Redirect to tickets list with success message
        header("Location: tickets.php?msg=deleted&ticket_num=" . urlencode($ticket_number));
        exit;
    } catch (PDOException $e) {
        $error = "Failed to delete ticket. Please try again.";
    }
}

// Fetch attachment count for display
$attachmentHandler = new Attachment($pdo);
$attachments = $attachmentHandler->getAttachments($ticket['id']);

$pageTitle = "Delete Ticket " . $ticket['ticket_number'];
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <div style="max-width: 650px; margin: var(--space-xl) auto;">
                <div class="card" style="border: 1px solid rgba(230, 57, 70, 0.3); box-shadow: var(--shadow-lg); border-radius: var(--radius-lg); overflow: hidden;">
                    <!-- Warning Header -->
                    <div style="background: rgba(230, 57, 70, 0.08); padding: var(--space-xl) var(--space-xl) var(--space-lg); text-align: center; border-bottom: 1px solid rgba(230, 57, 70, 0.15);">
                        <div style="width: 60px; height: 60px; border-radius: var(--radius-full); background: rgba(230, 57, 70, 0.15); color: var(--danger); display: flex; align-items: center; justify-content: center; margin: 0 auto var(--space-md);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        </div>
                        <h3 style="color: var(--danger); margin-bottom: var(--space-xs);">Delete Ticket Confirmation</h3>
                        <p class="text-muted" style="margin-bottom: 0;">Are you sure you want to permanently delete this support ticket?</p>
                    </div>

                    <div class="card-body" style="padding: var(--space-xl);">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert" style="margin-bottom: var(--space-lg);">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                <span><?= htmlspecialchars($error) ?></span>
                            </div>
                        <?php endif; ?>

                        <!-- Ticket Details Preview -->
                        <div style="background: var(--surface-secondary); border: 1px solid var(--border); border-radius: var(--radius-md); padding: var(--space-md); margin-bottom: var(--space-lg);">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-sm); padding-bottom: var(--space-xs); border-bottom: 1px solid var(--border);">
                                <span style="font-weight: 700; color: var(--primary);"><?= htmlspecialchars($ticket['ticket_number']) ?></span>
                                <?php 
                                    $statusClass = strtolower(str_replace(' ', '', $ticket['status']));
                                    echo "<span class='badge badge-{$statusClass}'>" . htmlspecialchars($ticket['status']) . "</span>";
                                ?>
                            </div>
                            
                            <h4 style="margin-bottom: var(--space-xs); font-size: 1.1rem;"><?= htmlspecialchars($ticket['subject']) ?></h4>
                            
                            <p class="text-muted" style="font-size: 0.875rem; margin-bottom: var(--space-sm); line-height: 1.5;">
                                <?= htmlspecialchars(mb_strimwidth($ticket['description'], 0, 180, "...")) ?>
                            </p>

                            <div style="display: flex; flex-wrap: wrap; gap: var(--space-md); font-size: 0.8125rem; color: var(--text-secondary); margin-top: var(--space-sm);">
                                <div><strong>Category:</strong> <?= htmlspecialchars($ticket['category_name'] ?? 'General') ?></div>
                                <div><strong>Priority:</strong> <?= htmlspecialchars($ticket['priority']) ?></div>
                                <div><strong>Attachments:</strong> <?= count($attachments) ?> file(s)</div>
                                <div><strong>Created:</strong> <?= date('M d, Y', strtotime($ticket['created_at'])) ?></div>
                            </div>
                        </div>

                        <!-- Consequence Warning -->
                        <div style="background: rgba(230, 57, 70, 0.05); border-left: 3px solid var(--danger); padding: var(--space-sm) var(--space-md); border-radius: 0 var(--radius-sm) var(--radius-sm) 0; margin-bottom: var(--space-xl); font-size: 0.875rem; color: var(--text);">
                            <strong>Warning:</strong> Deleting this ticket will permanently remove all associated conversation messages, uploaded attachments, and timeline audit logs. <em>This action cannot be undone.</em>
                        </div>

                        <!-- Confirmation Form -->
                        <form action="delete-ticket.php?id=<?= $ticket['id'] ?>" method="POST">
                            <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                            <input type="hidden" name="confirm_delete" value="1">

                            <div style="display: flex; gap: var(--space-md); justify-content: flex-end;">
                                <a href="ticket-details.php?id=<?= $ticket['id'] ?>" class="btn btn-ghost btn-md">
                                    Cancel & Keep Ticket
                                </a>
                                <button type="submit" class="btn btn-danger btn-md">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
                                    <span>Yes, Delete Permanently</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
