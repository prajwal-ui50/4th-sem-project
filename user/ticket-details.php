<?php
// user/ticket-details.php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/notifications.php";
require_once "../includes/Attachment.php";

requireRole('user');

$user_id = $_SESSION['user_id'];
$user = currentUser();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: tickets.php");
    exit;
}

$ticket_id = $_GET['id'];
$error = isset($_GET['att_err']) ? "Attachment warning: " . htmlspecialchars($_GET['att_err']) : "";
$success = "";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'created') {
        $success = "Ticket created successfully!";
    } elseif ($_GET['msg'] === 'updated') {
        $success = "Ticket updated successfully!";
    }
}

// Fetch ticket details securely (must belong to this user)
try {
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name, 
               u1.name as assigned_name 
        FROM tickets t 
        LEFT JOIN categories c ON t.category_id = c.id 
        LEFT JOIN users u1 ON t.assigned_to = u1.id 
        WHERE t.id = :ticket_id AND t.user_id = :user_id
    ");
    $stmt->bindParam(":ticket_id", $ticket_id);
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        header("Location: tickets.php");
        exit;
    }
    
    $ticket = $stmt->fetch();
} catch (PDOException $e) {
    die("Error fetching ticket details.");
}

// Handle reply submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    
    if (!empty($message)) {
        try {
            $msg_stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, message) VALUES (:ticket_id, :sender_id, :message)");
            $msg_stmt->bindParam(":ticket_id", $ticket_id);
            $msg_stmt->bindParam(":sender_id", $user_id);
            $msg_stmt->bindParam(":message", $message);
            
            if ($msg_stmt->execute()) {
                // Update ticket updated_at
                $update_stmt = $pdo->prepare("UPDATE tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = :ticket_id");
                $update_stmt->bindParam(":ticket_id", $ticket_id);
                $update_stmt->execute();
                
                // History
                $hist_stmt = $pdo->prepare("INSERT INTO ticket_history (ticket_id, user_id, action) VALUES (:ticket_id, :user_id, 'User replied')");
                $hist_stmt->bindParam(":ticket_id", $ticket_id);
                $hist_stmt->bindParam(":user_id", $user_id);
                $hist_stmt->execute();
                
                // Notify assigned staff (if any)
                $notified_ids = [];
                if ($ticket['assigned_to']) {
                    createNotification($pdo, $ticket['assigned_to'], "New Reply", "User {$user['name']} replied to Ticket {$ticket['ticket_number']}.", 'info', $ticket_id);
                    $notified_ids[] = (int)$ticket['assigned_to'];
                }
                
                // Always notify all active admins (avoid duplicate if assigned staff is also an admin)
                $admins_stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
                while ($admin = $admins_stmt->fetch()) {
                    if (!in_array((int)$admin['id'], $notified_ids)) {
                        $notif_msg = $ticket['assigned_to'] 
                            ? "User {$user['name']} sent a new message on Ticket {$ticket['ticket_number']}." 
                            : "User {$user['name']} replied to unassigned Ticket {$ticket['ticket_number']}.";
                        createNotification($pdo, $admin['id'], "New Reply on Ticket", $notif_msg, 'info', $ticket_id);
                    }
                }
                
                // Handle Attachment
                if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] != UPLOAD_ERR_NO_FILE) {
                    $attachmentHandler = new Attachment($pdo);
                    $uploadResult = $attachmentHandler->upload($_FILES['attachment'], $ticket_id, $user_id);
                    if (isset($uploadResult['error'])) {
                        $error = "Reply sent, but attachment failed: " . $uploadResult['error'];
                    } else {
                        $success = "Reply and attachment sent successfully.";
                    }
                } else {
                    $success = "Reply sent successfully.";
                }
            } else {
                $error = "Failed to send reply.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred.";
        }
    } else {
        $error = "Message cannot be empty.";
    }
}

// Fetch messages
try {
    $msg_stmt = $pdo->prepare("
        SELECT m.*, u.name as sender_name, u.role as sender_role 
        FROM ticket_messages m 
        JOIN users u ON m.sender_id = u.id 
        WHERE m.ticket_id = :ticket_id 
        ORDER BY m.created_at ASC
    ");
    $msg_stmt->bindParam(":ticket_id", $ticket_id);
    $msg_stmt->execute();
    $messages = $msg_stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching messages.");
}

// Fetch history
try {
    $hist_stmt = $pdo->prepare("
        SELECT h.*, u.name as user_name 
        FROM ticket_history h 
        JOIN users u ON h.user_id = u.id 
        WHERE h.ticket_id = :ticket_id 
        ORDER BY h.created_at DESC
    ");
    $hist_stmt->bindParam(":ticket_id", $ticket_id);
    $hist_stmt->execute();
    $history = $hist_stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching history.");
}

// Fetch attachments
$attachmentHandler = new Attachment($pdo);
$attachments = $attachmentHandler->getAttachments($ticket_id);

$pageTitle = "Ticket " . $ticket['ticket_number'];
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <div class="page-header">
                <div>
                    <a href="tickets.php" class="btn btn-ghost btn-sm" style="margin-bottom: var(--space-xs);">&larr; Back to My Tickets</a>
                    <h2><?= htmlspecialchars($ticket['subject']) ?></h2>
                    <p class="text-muted mb-0">Ticket ID: <strong><?= htmlspecialchars($ticket['ticket_number']) ?></strong></p>
                </div>
<div class="btn-group" style="display: flex; flex-wrap: wrap; gap: var(--space-sm); align-items: center; width: 100%;">
    <?php 
        $statusClass = strtolower(str_replace(' ', '', $ticket['status']));
        echo "<span class='badge badge-{$statusClass}' style='font-size: 0.875rem; padding: 6px 14px; text-transform: uppercase;'>" . htmlspecialchars($ticket['status']) . "</span>";
    ?>
    <?php if ($ticket['status'] !== 'CLOSED'): ?>
        <a href="edit-ticket.php?id=<?= $ticket['id'] ?>" class="btn btn-outline btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            <span>Edit Ticket</span>
        </a>
    <?php endif; ?>
    <a href="delete-ticket.php?id=<?= $ticket['id'] ?>" class="btn btn-danger btn-sm">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
        <span>Delete</span>
    </a>
</div>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="alert">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
            <?php endif; ?>
            
            <div class="ticket-layout">
                <!-- Main Conversation Column -->
                <div class="ticket-main">
                    <div class="card" style="margin-bottom: var(--space-lg);">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 600;">Issue Description</span>
                            <span class="text-muted" style="font-size: 0.8125rem;"><?= date('M d, Y h:i A', strtotime($ticket['created_at'])) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="ticket-description-block" style="background: var(--surface-secondary); padding: var(--space-md); border-radius: var(--radius-md); border: 1px solid var(--border);">
                                <p style="white-space: pre-wrap; margin: 0; line-height: 1.7;"><?= htmlspecialchars($ticket['description']) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Conversation Thread -->
                    <div class="card">
                        <div class="card-header">
                            <span style="font-weight: 600;">Conversation Thread (<?= count($messages) ?>)</span>
                        </div>
                        <div class="card-body">
                            <?php if (count($messages) == 0): ?>
                                <p class="text-muted" style="text-align: center; padding: var(--space-lg) 0;">No replies yet. Our support staff will respond shortly.</p>
                            <?php else: ?>
                                <div class="conversation">
                                    <?php foreach ($messages as $msg): 
                                        $isSelf = ($msg['sender_id'] == $user_id);
                                    ?>
                                        <div class="message <?= $isSelf ? 'message-self' : '' ?>" style="border-left: 4px solid <?= $isSelf ? 'var(--primary)' : 'var(--secondary)' ?>;">
                                            <div class="message-header">
                                                <div style="display: flex; align-items: center; gap: var(--space-sm);">
                                                    <strong><?= htmlspecialchars($msg['sender_name']) ?></strong>
                                                    <span class="badge badge-role-<?= htmlspecialchars(strtolower($msg['sender_role'])) ?>" style="font-size: 0.7rem; text-transform: uppercase;">
                                                        <?= htmlspecialchars($msg['sender_role']) ?>
                                                    </span>
                                                </div>
                                                <span><?= date('M d, Y h:i A', strtotime($msg['created_at'])) ?></span>
                                            </div>
                                            <div class="message-body"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Reply Form -->
                            <?php if ($ticket['status'] != 'CLOSED'): ?>
                                <div style="margin-top: var(--space-xl); border-top: 1px solid var(--border); padding-top: var(--space-lg);">
                                    <h4 style="margin-bottom: var(--space-md);">Add a Reply</h4>
                                    <form action="ticket-details.php?id=<?= $ticket['id'] ?>" method="POST" enctype="multipart/form-data">
                                        <div class="form-group">
                                            <textarea name="message" class="form-control" rows="4" required placeholder="Type your reply message here..."></textarea>
                                        </div>
                                        
                                        <!-- Attachment Zone in Reply -->
                                        <div class="form-group">
                                            <label>Attach File <span class="text-muted">(Optional — Max 5MB)</span></label>
                                            <div class="dropzone" style="padding: var(--space-md);">
                                                <input type="file" name="attachment" class="dropzone-input" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                                                <div class="dropzone-text" style="font-size: 0.875rem;">
                                                    <strong>Click or drag a file</strong> to attach
                                                </div>
                                                <div class="dropzone-preview">
                                                    <span class="dropzone-preview-name"></span>
                                                    <button type="button" class="dropzone-preview-remove">Remove</button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary btn-md">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                            <span>Send Reply</span>
                                        </button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning" style="margin-top: var(--space-lg);">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    <span>This ticket has been marked as <strong>Closed</strong>. Further replies are disabled.</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Ticket Metadata Sidebar -->
                <div class="ticket-sidebar">
                    <div class="card" style="margin-bottom: var(--space-lg);">
                        <div class="card-header">
                            <span style="font-weight: 600;">Ticket Information</span>
                        </div>
                        <div class="card-body">
                            <div class="ticket-info-item">
                                <div class="label">Category</div>
                                <div class="value">
                                    <span class="badge" style="background: var(--surface-secondary); color: var(--text-secondary); border: 1px solid var(--border);">
                                        <?= htmlspecialchars($ticket['category_name'] ?? 'General') ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="ticket-info-item">
                                <div class="label">Priority</div>
                                <div class="value">
                                    <?php 
                                        $priorityClass = strtolower($ticket['priority']);
                                        echo "<span class='badge badge-{$priorityClass}'>" . htmlspecialchars($ticket['priority']) . "</span>";
                                    ?>
                                </div>
                            </div>
                            
                            <div class="ticket-info-item">
                                <div class="label">Assigned Staff</div>
                                <div class="value">
                                    <?= $ticket['assigned_name'] ? htmlspecialchars($ticket['assigned_name']) : '<span class="text-muted">Unassigned</span>' ?>
                                </div>
                            </div>
                            
                            <div class="ticket-info-item">
                                <div class="label">Created Date</div>
                                <div class="value" style="font-size: 0.875rem;">
                                    <?= date('M d, Y h:i A', strtotime($ticket['created_at'])) ?>
                                </div>
                            </div>
                            
                            <div class="ticket-info-item" style="margin-bottom: 0;">
                                <div class="label">Last Updated</div>
                                <div class="value" style="font-size: 0.875rem;">
                                    <?= date('M d, Y h:i A', strtotime($ticket['updated_at'])) ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attachments Card -->
                    <?php if (count($attachments) > 0): ?>
                        <div class="card" style="margin-bottom: var(--space-lg);">
                            <div class="card-header">
                                <span style="font-weight: 600;">Attachments (<?= count($attachments) ?>)</span>
                            </div>
                            <div class="card-body">
                                <ul style="list-style: none; padding: 0; margin: 0;">
                                    <?php foreach ($attachments as $att): ?>
                                        <li style="padding: var(--space-sm) 0; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; gap: var(--space-sm);">
                                            <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                <a href="../download.php?id=<?= $att['id'] ?>" target="_blank" style="font-weight: 500;" title="<?= htmlspecialchars($att['original_name']) ?>">
                                                    <?= htmlspecialchars($att['original_name']) ?>
                                                </a>
                                                <div class="text-muted" style="font-size: 0.75rem;">
                                                    <?= number_format($att['file_size'] / 1024, 1) ?> KB
                                                </div>
                                            </div>
                                            <a href="../download.php?id=<?= $att['id'] ?>" class="btn btn-outline btn-sm" title="Download">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Ticket History Audit Trail -->
                    <div class="card">
                        <div class="card-header">
                            <span style="font-weight: 600;">History Timeline</span>
                        </div>
                        <div class="card-body">
                            <ul class="history-list">
                                <?php foreach ($history as $h): ?>
                                    <li>
                                        <div><strong><?= htmlspecialchars($h['user_name']) ?></strong>: <?= htmlspecialchars($h['action']) ?> <?= $h['new_value'] ? '('.htmlspecialchars($h['new_value']).')' : '' ?></div>
                                        <div class="history-date"><?= date('M d, Y h:i A', strtotime($h['created_at'])) ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
