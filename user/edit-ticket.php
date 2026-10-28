<?php
// user/edit-ticket.php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/notifications.php";
require_once "../includes/Attachment.php";

requireRole('user');

$user_id = $_SESSION['user_id'];
$user = currentUser();

$error = "";
$success = "";

// Validate ticket ID
$ticket_id = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : (isset($_POST['ticket_id']) ? (int)$_POST['ticket_id'] : 0);

if ($ticket_id <= 0) {
    header("Location: tickets.php");
    exit;
}

// Fetch ticket details securely (must belong to this user)
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
    die("Error fetching ticket details.");
}

$attachmentHandler = new Attachment($pdo);

// Handle attachment deletion
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'delete_attachment') {
    $attachment_id = isset($_POST['attachment_id']) ? (int)$_POST['attachment_id'] : 0;
    if ($ticket['status'] === 'CLOSED') {
        $error = "Cannot delete attachments from a closed ticket.";
    } elseif ($attachment_id > 0) {
        if ($attachmentHandler->deleteAttachment($attachment_id, $user_id)) {
            $pdo->prepare("INSERT INTO ticket_history (ticket_id, user_id, action) VALUES (?, ?, 'User removed an attachment')")->execute([$ticket_id, $user_id]);
            $success = "Attachment removed successfully.";
        } else {
            $error = "Failed to remove attachment.";
        }
    }
}

// Handle ticket update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_ticket'])) {
    if ($ticket['status'] === 'CLOSED') {
        $error = "Closed tickets cannot be modified. Please open a new ticket if you need further help.";
    } else {
        $subject = trim($_POST['subject'] ?? '');
        $category_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
        $priority = strtoupper(trim($_POST['priority'] ?? 'LOW'));
        $description = trim($_POST['description'] ?? '');

        $validPriorities = ['LOW', 'MEDIUM', 'HIGH', 'URGENT'];
        if (!in_array($priority, $validPriorities)) {
            $priority = 'LOW';
        }

        if (empty($subject) || empty($category_id) || empty($description)) {
            $error = "Please fill in all required fields.";
        } else {
            // Verify category
            $catCheck = $pdo->prepare("SELECT id, name FROM categories WHERE id = ?");
            $catCheck->execute([$category_id]);
            $newCategory = $catCheck->fetch();

            if (!$newCategory) {
                $error = "Please select a valid category.";
            } else {
                try {
                    // Track differences for audit log
                    $changes = [];
                    if ($ticket['subject'] !== $subject) {
                        $changes[] = [
                            'action' => 'Subject updated',
                            'old' => $ticket['subject'],
                            'new' => $subject
                        ];
                    }
                    if ((int)$ticket['category_id'] !== $category_id) {
                        $changes[] = [
                            'action' => 'Category changed',
                            'old' => $ticket['category_name'] ?? 'General',
                            'new' => $newCategory['name']
                        ];
                    }
                    if ($ticket['priority'] !== $priority) {
                        $changes[] = [
                            'action' => 'Priority changed',
                            'old' => $ticket['priority'],
                            'new' => $priority
                        ];
                    }
                    if ($ticket['description'] !== $description) {
                        $changes[] = [
                            'action' => 'Description updated',
                            'old' => null,
                            'new' => null
                        ];
                    }

                    // Update ticket in database
                    $update_stmt = $pdo->prepare("
                        UPDATE tickets 
                        SET subject = :subject, 
                            category_id = :category_id, 
                            priority = :priority, 
                            description = :description, 
                            updated_at = CURRENT_TIMESTAMP 
                        WHERE id = :ticket_id AND user_id = :user_id
                    ");
                    $update_stmt->execute([
                        ':subject' => $subject,
                        ':category_id' => $category_id,
                        ':priority' => $priority,
                        ':description' => $description,
                        ':ticket_id' => $ticket_id,
                        ':user_id' => $user_id
                    ]);

                    // Insert audit history
                    if (!empty($changes)) {
                        $hist_stmt = $pdo->prepare("INSERT INTO ticket_history (ticket_id, user_id, action, old_value, new_value) VALUES (?, ?, ?, ?, ?)");
                        foreach ($changes as $c) {
                            $hist_stmt->execute([$ticket_id, $user_id, $c['action'], $c['old'], $c['new']]);
                        }
                    } else {
                        $pdo->prepare("INSERT INTO ticket_history (ticket_id, user_id, action) VALUES (?, ?, 'Ticket updated by user')")->execute([$ticket_id, $user_id]);
                    }

                    // Handle new attachment upload if provided
                    $att_err = "";
                    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] != UPLOAD_ERR_NO_FILE) {
                        $uploadResult = $attachmentHandler->upload($_FILES['attachment'], $ticket_id, $user_id);
                        if (isset($uploadResult['error'])) {
                            $att_err = "&att_err=" . urlencode($uploadResult['error']);
                        } else {
                            $pdo->prepare("INSERT INTO ticket_history (ticket_id, user_id, action) VALUES (?, ?, 'Added attachment during edit')")->execute([$ticket_id, $user_id]);
                        }
                    }

                    // Send notification to assigned staff or admins
                    if (!empty($ticket['assigned_to'])) {
                        createNotification($pdo, $ticket['assigned_to'], "Ticket Updated", "User {$user['name']} updated ticket {$ticket['ticket_number']}.", 'info', $ticket_id);
                    } else {
                        $admins_stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
                        while ($admin = $admins_stmt->fetch()) {
                            createNotification($pdo, $admin['id'], "Ticket Updated", "User {$user['name']} updated unassigned ticket {$ticket['ticket_number']}.", 'info', $ticket_id);
                        }
                    }

                    header("Location: ticket-details.php?id=$ticket_id&msg=updated" . $att_err);
                    exit;
                } catch (PDOException $e) {
                    $error = "An error occurred while updating the ticket.";
                }
            }
        }
    }
}

// Reload fresh ticket record after potential modifications
try {
    $stmt = $pdo->prepare("
        SELECT t.*, c.name as category_name 
        FROM tickets t 
        LEFT JOIN categories c ON t.category_id = c.id 
        WHERE t.id = :ticket_id AND t.user_id = :user_id
    ");
    $stmt->execute([':ticket_id' => $ticket_id, ':user_id' => $user_id]);
    $ticket = $stmt->fetch();
} catch (PDOException $e) {
    die("Error refreshing ticket details.");
}

// Fetch categories for dropdown
try {
    $cat_stmt = $pdo->prepare("SELECT id, name, description FROM categories WHERE status = 'active' ORDER BY name ASC");
    $cat_stmt->execute();
    $categories = $cat_stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching categories.");
}

// Fetch existing attachments
$attachments = $attachmentHandler->getAttachments($ticket_id);

$pageTitle = "Edit Ticket " . $ticket['ticket_number'];
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <div style="max-width: 860px; margin: 0 auto;">
                <div class="page-header" style="margin-bottom: var(--space-lg);">
                    <div>
                        <div style="display: flex; align-items: center; gap: var(--space-sm); margin-bottom: var(--space-xs);">
                            <a href="ticket-details.php?id=<?= $ticket['id'] ?>" class="btn btn-ghost btn-sm">&larr; Back to Ticket Details</a>
                        </div>
                        <h2>Edit Ticket <span style="color: var(--primary);"><?= htmlspecialchars($ticket['ticket_number']) ?></span></h2>
                        <p class="text-muted mb-0">Modify your ticket details, update category, or attach additional files</p>
                    </div>
                    <div style="display: flex; gap: var(--space-sm); align-items: center;">
                        <?php 
                            $statusClass = strtolower(str_replace(' ', '', $ticket['status']));
                            echo "<span class='badge badge-{$statusClass}' style='font-size: 0.875rem; padding: 6px 14px; text-transform: uppercase;'>" . htmlspecialchars($ticket['status']) . "</span>";
                        ?>
                    </div>
                </div>

                <?php if ($ticket['status'] === 'CLOSED'): ?>
                    <div class="alert alert-warning" role="alert" style="margin-bottom: var(--space-lg);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <div>
                            <strong>Ticket is Closed</strong>
                            <p style="margin: 0; font-size: 0.875rem;">This ticket is closed and cannot be edited. If you have a new issue, please create a new ticket.</p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert" style="margin-bottom: var(--space-lg);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success" role="alert" style="margin-bottom: var(--space-lg);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        <span><?= htmlspecialchars($success) ?></span>
                    </div>
                <?php endif; ?>

                <div class="card" style="box-shadow: var(--shadow-md); border-radius: var(--radius-lg);">
                    <div class="card-body" style="padding: var(--space-xl);">
                        <form action="edit-ticket.php?id=<?= $ticket['id'] ?>" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                            <input type="hidden" name="update_ticket" value="1">

                            <!-- Subject Field -->
                            <div class="form-group">
                                <label for="subject" style="font-weight: 600;">Ticket Subject <span class="text-danger">*</span></label>
                                <input type="text" id="subject" name="subject" class="form-control" 
                                       value="<?= htmlspecialchars($ticket['subject']) ?>" required 
                                       placeholder="Brief summary of the issue"
                                       <?= $ticket['status'] === 'CLOSED' ? 'disabled' : '' ?>>
                            </div>
                            
                            <!-- Category Field -->
                            <div class="form-group">
                                <label for="category_id" style="font-weight: 600;">Category <span class="text-danger">*</span></label>
                                <select id="category_id" name="category_id" class="form-control" required <?= $ticket['status'] === 'CLOSED' ? 'disabled' : '' ?>>
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $ticket['category_id'] == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?><?= !empty($cat['description']) ? ' — ' . htmlspecialchars($cat['description']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Priority Level Picker -->
                            <div class="form-group">
                                <label style="font-weight: 600;">Priority Level <span class="text-danger">*</span></label>
                                <div class="priority-picker">
                                    <label class="priority-pill pill-low <?= $ticket['priority'] == 'LOW' ? 'active' : '' ?>">
                                        <input type="radio" name="priority" value="LOW" <?= $ticket['priority'] == 'LOW' ? 'checked' : '' ?> <?= $ticket['status'] === 'CLOSED' ? 'disabled' : '' ?>>
                                        <span class="priority-dot"></span>
                                        <span>Low</span>
                                    </label>
                                    
                                    <label class="priority-pill pill-medium <?= $ticket['priority'] == 'MEDIUM' ? 'active' : '' ?>">
                                        <input type="radio" name="priority" value="MEDIUM" <?= $ticket['priority'] == 'MEDIUM' ? 'checked' : '' ?> <?= $ticket['status'] === 'CLOSED' ? 'disabled' : '' ?>>
                                        <span class="priority-dot"></span>
                                        <span>Medium</span>
                                    </label>
                                    
                                    <label class="priority-pill pill-high <?= $ticket['priority'] == 'HIGH' ? 'active' : '' ?>">
                                        <input type="radio" name="priority" value="HIGH" <?= $ticket['priority'] == 'HIGH' ? 'checked' : '' ?> <?= $ticket['status'] === 'CLOSED' ? 'disabled' : '' ?>>
                                        <span class="priority-dot"></span>
                                        <span>High</span>
                                    </label>
                                    
                                    <label class="priority-pill pill-urgent <?= $ticket['priority'] == 'URGENT' ? 'active' : '' ?>">
                                        <input type="radio" name="priority" value="URGENT" <?= $ticket['priority'] == 'URGENT' ? 'checked' : '' ?> <?= $ticket['status'] === 'CLOSED' ? 'disabled' : '' ?>>
                                        <span class="priority-dot"></span>
                                        <span>Urgent</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Description Field -->
                            <div class="form-group">
                                <label for="description" style="font-weight: 600;">Detailed Description <span class="text-danger">*</span></label>
                                <textarea id="description" name="description" class="form-control" rows="7" required 
                                          placeholder="Describe your issue or update..."
                                          <?= $ticket['status'] === 'CLOSED' ? 'disabled' : '' ?>><?= htmlspecialchars($ticket['description']) ?></textarea>
                            </div>

                            <!-- Existing Attachments Section -->
                            <?php if (count($attachments) > 0): ?>
                                <div class="form-group" style="margin-top: var(--space-lg);">
                                    <label style="font-weight: 600;">Current Attachments (<?= count($attachments) ?>)</label>
                                    <div style="background: var(--surface-secondary); border: 1px solid var(--border); border-radius: var(--radius-md); padding: var(--space-sm) var(--space-md);">
                                        <ul style="list-style: none; padding: 0; margin: 0;">
                                            <?php foreach ($attachments as $att): ?>
                                                <li style="padding: var(--space-xs) 0; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); gap: var(--space-sm);">
                                                    <div style="display: flex; align-items: center; gap: var(--space-sm); overflow: hidden;">
                                                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: var(--muted); flex-shrink: 0;"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                                                        <span style="font-weight: 500; font-size: 0.875rem; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;">
                                                            <?= htmlspecialchars($att['original_name']) ?>
                                                        </span>
                                                        <span class="text-muted" style="font-size: 0.75rem;">(<?= number_format($att['file_size'] / 1024, 1) ?> KB)</span>
                                                    </div>
                                                    <div style="display: flex; align-items: center; gap: var(--space-xs); flex-shrink: 0;">
                                                        <a href="../download.php?id=<?= $att['id'] ?>" target="_blank" class="btn btn-outline btn-sm" style="padding: 4px 8px; font-size: 0.75rem;" title="Download file">
                                                            Download
                                                        </a>
                                                        <?php if ($ticket['status'] !== 'CLOSED'): ?>
                                                            <button type="submit" form="del_att_form_<?= $att['id'] ?>" class="btn btn-danger btn-sm" style="padding: 4px 8px; font-size: 0.75rem;" onclick="return confirm('Are you sure you want to remove this attachment?');" title="Remove attachment">
                                                                Remove
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Upload Additional Attachment -->
                            <?php if ($ticket['status'] !== 'CLOSED'): ?>
                                <div class="form-group" style="margin-top: var(--space-md);">
                                    <label style="font-weight: 600;">Add Another Attachment <span class="text-muted">(Optional — Max 5MB)</span></label>
                                    <div class="dropzone" id="ticketDropzone">
                                        <input type="file" name="attachment" id="attachment" class="dropzone-input" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt">
                                        <div class="dropzone-icon">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                        </div>
                                        <div class="dropzone-text">
                                            <strong>Click to browse</strong> or drag & drop file here
                                        </div>
                                        <div class="dropzone-hint">
                                            Supported: JPG, PNG, GIF, WEBP, PDF, DOC, DOCX, XLS, XLSX, TXT (up to 5MB)
                                        </div>
                                        <div class="dropzone-preview">
                                            <span class="dropzone-preview-name"></span>
                                            <button type="button" class="dropzone-preview-remove">Remove</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
<!-- Form Actions -->
<div class="btn-group" style="display: flex; flex-wrap: wrap; gap: var(--space-sm); justify-content: space-between; align-items: center; margin-top: var(--space-xl); padding-top: var(--space-md); border-top: 1px solid var(--border); width: 100%;">
    <a href="delete-ticket.php?id=<?= $ticket['id'] ?>" class="btn btn-danger btn-sm" style="display: inline-flex; align-items: center; gap: var(--space-xs);">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
        <span>Delete Ticket</span>
    </a>

    <div style="display: flex; flex-wrap: wrap; gap: var(--space-sm);">
        <a href="ticket-details.php?id=<?= $ticket['id'] ?>" class="btn btn-ghost btn-md">Cancel</a>
        <?php if ($ticket['status'] !== 'CLOSED'): ?>
            <button type="submit" class="btn btn-primary btn-md">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                <span>Save Changes</span>
            </button>
        <?php endif; ?>
    </div>
</div>
                        </form>

                        <!-- Hidden forms for attachment deletion -->
                        <?php foreach ($attachments as $att): ?>
                            <form id="del_att_form_<?= $att['id'] ?>" action="edit-ticket.php?id=<?= $ticket['id'] ?>" method="POST" style="display:none;">
                                <input type="hidden" name="action" value="delete_attachment">
                                <input type="hidden" name="attachment_id" value="<?= $att['id'] ?>">
                                <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
