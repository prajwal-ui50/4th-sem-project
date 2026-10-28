<?php
// staff/ticket-details.php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/notifications.php";
require_once "../includes/Attachment.php";

requireRole('staff');

$user_id = $_SESSION['user_id'];
$user = currentUser();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: tickets.php");
    exit;
}

$ticket_id = $_GET['id'];
$error = "";
$success = "";

// Handle updates (Status, Priority)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_ticket'])) {
    $new_status = $_POST['status'];
    $new_priority = $_POST['priority'];
    
    try {
        // Fetch current to record history
        $curr_stmt = $pdo->prepare("SELECT status, priority, user_id FROM tickets WHERE id = :ticket_id AND assigned_to = :user_id");
        $curr_stmt->bindParam(":ticket_id", $ticket_id);
        $curr_stmt->bindParam(":user_id", $user_id);
        $curr_stmt->execute();
        $current = $curr_stmt->fetch();
        
        if ($current) {
            $upd_stmt = $pdo->prepare("UPDATE tickets SET status = :status, priority = :priority, updated_at = CURRENT_TIMESTAMP WHERE id = :ticket_id");
            $upd_stmt->bindParam(":status", $new_status);
            $upd_stmt->bindParam(":priority", $new_priority);
            $upd_stmt->bindParam(":ticket_id", $ticket_id);
            
            if ($upd_stmt->execute()) {
                // Check if status changed
                if ($current['status'] != $new_status) {
                    $h_stmt = $pdo->prepare("INSERT INTO ticket_history (ticket_id, user_id, action, old_value, new_value) VALUES (:ticket_id, :user_id, 'Status changed', :old_val, :new_val)");
                    $h_stmt->bindParam(":ticket_id", $ticket_id);
                    $h_stmt->bindParam(":user_id", $user_id);
                    $h_stmt->bindParam(":old_val", $current['status']);
                    $h_stmt->bindParam(":new_val", $new_status);
                    $h_stmt->execute();
                    
                    createNotification($pdo, $current['user_id'], "Status Updated", "Your ticket status was updated to $new_status.", 'info', $ticket_id);
                }
                
                // Check if priority changed
                if ($current['priority'] != $new_priority) {
                    $h_stmt = $pdo->prepare("INSERT INTO ticket_history (ticket_id, user_id, action, old_value, new_value) VALUES (:ticket_id, :user_id, 'Priority changed', :old_val, :new_val)");
                    $h_stmt->bindParam(":ticket_id", $ticket_id);
                    $h_stmt->bindParam(":user_id", $user_id);
                    $h_stmt->bindParam(":old_val", $current['priority']);
                    $h_stmt->bindParam(":new_val", $new_priority);
                    $h_stmt->execute();
                    
                    createNotification($pdo, $current['user_id'], "Priority Updated", "Your ticket priority was updated to $new_priority.", 'warning', $ticket_id);
                }
                
                $success = "Ticket updated successfully.";
            } else {
                $error = "Failed to update ticket.";
            }
        } else {
            $error = "You are not authorized to update this ticket.";
        }
    } catch (PDOException $e) {
        $error = "An error occurred updating the ticket.";
    }
}

// Handle reply submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['message'])) {
    $message = trim($_POST['message']);
    $is_internal = isset($_POST['is_internal']) ? 1 : 0;
    
    if (!empty($message)) {
        try {
            $msg_stmt = $pdo->prepare("INSERT INTO ticket_messages (ticket_id, sender_id, message, is_internal) VALUES (:ticket_id, :sender_id, :message, :is_internal)");
            $msg_stmt->bindParam(":ticket_id", $ticket_id);
            $msg_stmt->bindParam(":sender_id", $user_id);
            $msg_stmt->bindParam(":message", $message);
            $msg_stmt->bindParam(":is_internal", $is_internal);
            
            if ($msg_stmt->execute()) {
                // Fetch creator id
                $owner_stmt = $pdo->prepare("SELECT user_id FROM tickets WHERE id = :ticket_id");
                $owner_stmt->bindParam(":ticket_id", $ticket_id);
                $owner_stmt->execute();
                $owner_id = $owner_stmt->fetchColumn();

                if (!$is_internal) {
                    $update_stmt = $pdo->prepare("UPDATE tickets SET updated_at = CURRENT_TIMESTAMP, status = IF(status IN ('OPEN', 'ASSIGNED', 'NEW'), 'IN PROGRESS', status) WHERE id = :ticket_id");
                    $update_stmt->bindParam(":ticket_id", $ticket_id);
                    $update_stmt->execute();
                    
                    if ($owner_id) {
                        createNotification($pdo, $owner_id, "New Reply", "Staff {$user['name']} replied to your ticket.", 'info', $ticket_id);
                    }
                } else {
                    $update_stmt = $pdo->prepare("UPDATE tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = :ticket_id");
                    $update_stmt->bindParam(":ticket_id", $ticket_id);
                    $update_stmt->execute();
                }
                
                // History
                $hist_action = $is_internal ? 'Added internal note' : 'Staff replied';
                $hist_stmt = $pdo->prepare("INSERT INTO ticket_history (ticket_id, user_id, action) VALUES (:ticket_id, :user_id, :action)");
                $hist_stmt->bindParam(":ticket_id", $ticket_id);
                $hist_stmt->bindParam(":user_id", $user_id);
                $hist_stmt->bindParam(":action", $hist_action);
                $hist_stmt->execute();
                
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
            $error = "An error occurred sending the message.";
        }
    } else {
        $error = "Message cannot be empty.";
    }
}

// Fetch ticket details securely (must belong to this staff)
try {
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name, 
               u1.name as client_name, u1.email as client_email 
        FROM tickets t 
        LEFT JOIN categories c ON t.category_id = c.id 
        LEFT JOIN users u1 ON t.user_id = u1.id 
        WHERE t.id = :ticket_id AND t.assigned_to = :user_id
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

$pageTitle = "Manage Ticket " . $ticket['ticket_number'];
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <div class="page-header">
                <div>
                    <a href="tickets.php" class="btn btn-ghost btn-sm" style="margin-bottom: var(--space-xs);">&larr; Back to Assigned Tickets</a>
                    <h2><?= htmlspecialchars($ticket['subject']) ?></h2>
                    <p class="text-muted mb-0">Ticket: <strong><?= htmlspecialchars($ticket['ticket_number']) ?></strong> &bull; Client: <strong><?= htmlspecialchars($ticket['client_name']) ?></strong> (<?= htmlspecialchars($ticket['client_email']) ?>)</p>
                </div>
                <div style="display: flex; gap: var(--space-sm); align-items: center;">
                    <?php 
                        $statusClass = strtolower(str_replace(' ', '', $ticket['status']));
                        echo "<span class='badge badge-{$statusClass}' style='font-size: 0.875rem; padding: 6px 14px; text-transform: uppercase;'>" . htmlspecialchars($ticket['status']) . "</span>";
                    ?>
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
                            <span style="font-weight: 600;">Original Issue</span>
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
                                <p class="text-muted" style="text-align: center; padding: var(--space-lg) 0;">No messages yet. Reply to the client below.</p>
                            <?php else: ?>
                                <div class="conversation">
                                    <?php foreach ($messages as $msg): 
                                        $isSelf = ($msg['sender_id'] == $user_id);
                                        $isInternal = !empty($msg['is_internal']);
                                    ?>
                                        <div class="message <?= $isSelf ? 'message-self' : '' ?>" style="border-left: 4px solid <?= $isInternal ? 'var(--danger)' : ($isSelf ? 'var(--primary)' : 'var(--warning)') ?>; <?= $isInternal ? 'background-color: rgba(230, 57, 70, 0.05);' : '' ?>">
                                            <div class="message-header">
                                                <div style="display: flex; align-items: center; gap: var(--space-sm); flex-wrap: wrap;">
                                                    <strong><?= htmlspecialchars($msg['sender_name']) ?></strong>
                                                    <span class="badge badge-role-<?= htmlspecialchars(strtolower($msg['sender_role'])) ?>" style="font-size: 0.7rem; text-transform: uppercase;">
                                                        <?= htmlspecialchars($msg['sender_role']) ?>
                                                    </span>
                                                    <?php if ($isInternal): ?>
                                                        <span class="badge badge-danger" style="font-size: 0.7rem;">Internal Note</span>
                                                    <?php endif; ?>
                                                </div>
                                                <span><?= date('M d, Y h:i A', strtotime($msg['created_at'])) ?></span>
                                            </div>
                                            <div class="message-body"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Reply Form -->
                            <div style="margin-top: var(--space-xl); border-top: 1px solid var(--border); padding-top: var(--space-lg);">
                                <h4 style="margin-bottom: var(--space-md);">Send Reply / Internal Note</h4>
                                <form action="ticket-details.php?id=<?= $ticket['id'] ?>" method="POST" enctype="multipart/form-data">
                                    <div class="form-group">
                                        <textarea name="message" class="form-control" rows="4" required placeholder="Type reply to client, or check 'Internal Note' to keep hidden from client..."></textarea>
                                    </div>
                                    
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

                                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--space-md); margin-top: var(--space-md);">
                                        <div class="form-check">
                                            <input type="checkbox" name="is_internal" id="is_internal" class="form-check-input">
                                            <label for="is_internal" class="form-check-label" style="font-weight: 600; color: var(--danger);">Internal Note (Hidden from client)</label>
                                        </div>
                                        
                                        <button type="submit" class="btn btn-primary btn-md">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                            <span>Post Message</span>
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Sidebar Column (Manage Ticket & Info) -->
                <div class="ticket-sidebar">
                    <!-- Manage Status & Priority Card -->
                    <div class="card" style="margin-bottom: var(--space-lg);">
                        <div class="card-header">
                            <span style="font-weight: 600;">Manage Ticket Status</span>
                        </div>
                        <div class="card-body">
                            <form action="ticket-details.php?id=<?= $ticket['id'] ?>" method="POST">
                                <input type="hidden" name="update_ticket" value="1">
                                
                                <div class="form-group">
                                    <label for="status">Ticket Status</label>
                                    <select name="status" id="status" class="form-control">
                                        <option value="OPEN" <?= $ticket['status'] == 'OPEN' ? 'selected' : '' ?>>Open</option>
                                        <option value="ASSIGNED" <?= $ticket['status'] == 'ASSIGNED' ? 'selected' : '' ?>>Assigned</option>
                                        <option value="IN PROGRESS" <?= $ticket['status'] == 'IN PROGRESS' ? 'selected' : '' ?>>In Progress</option>
                                        <option value="RESOLVED" <?= $ticket['status'] == 'RESOLVED' ? 'selected' : '' ?>>Resolved</option>
                                        <option value="CLOSED" <?= $ticket['status'] == 'CLOSED' ? 'selected' : '' ?>>Closed</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="priority">Ticket Priority</label>
                                    <select name="priority" id="priority" class="form-control">
                                        <option value="LOW" <?= $ticket['priority'] == 'LOW' ? 'selected' : '' ?>>Low</option>
                                        <option value="MEDIUM" <?= $ticket['priority'] == 'MEDIUM' ? 'selected' : '' ?>>Medium</option>
                                        <option value="HIGH" <?= $ticket['priority'] == 'HIGH' ? 'selected' : '' ?>>High</option>
                                        <option value="URGENT" <?= $ticket['priority'] == 'URGENT' ? 'selected' : '' ?>>Urgent</option>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-primary btn-block btn-md">Update Ticket</button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Metadata Card -->
                    <div class="card" style="margin-bottom: var(--space-lg);">
                        <div class="card-header">
                            <span style="font-weight: 600;">Ticket Details</span>
                        </div>
                        <div class="card-body">
                            <div class="ticket-info-item">
                                <div class="label">Client</div>
                                <div class="value"><?= htmlspecialchars($ticket['client_name']) ?></div>
                                <div class="text-muted" style="font-size: 0.8125rem;"><?= htmlspecialchars($ticket['client_email']) ?></div>
                            </div>
                            
                            <div class="ticket-info-item">
                                <div class="label">Category</div>
                                <div class="value">
                                    <span class="badge" style="background: var(--surface-secondary); color: var(--text-secondary); border: 1px solid var(--border);">
                                        <?= htmlspecialchars($ticket['category_name'] ?? 'General') ?>
                                    </span>
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
                    
                    <!-- Attachments -->
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
                    
                    <!-- History Timeline -->
                    <div class="card">
                        <div class="card-header">
                            <span style="font-weight: 600;">Ticket History</span>
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
