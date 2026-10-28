<?php
// user/create-ticket.php
require_once "../config/database.php";
require_once "../includes/auth.php";
require_once "../includes/notifications.php";
require_once "../includes/Attachment.php";

requireRole('user');

$user_id = $_SESSION['user_id'];
$user = currentUser();

$error = "";
$success = "";
$subject = "";
$description = "";
$category_id = "";
$priority = "LOW";

// Fetch active categories for the dropdown
try {
    $cat_stmt = $pdo->prepare("SELECT id, name, description FROM categories WHERE status = 'active'");
    $cat_stmt->execute();
    $categories = $cat_stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching categories.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $subject = trim($_POST['subject'] ?? '');
    $category_id = $_POST['category_id'] ?? '';
    $priority = $_POST['priority'] ?? 'LOW';
    $description = trim($_POST['description'] ?? '');
    
    if (empty($subject) || empty($category_id) || empty($description)) {
        $error = "Please fill in all required fields.";
    } else {
        try {
            // Generate ticket number (e.g., TKT-YYYY-0000X)
            $year = date("Y");
            $prefix = "TKT-" . $year . "-";
            
            // Find highest existing ticket number for current year
            $stmt = $pdo->prepare("SELECT ticket_number FROM tickets WHERE ticket_number LIKE :prefix ORDER BY ticket_number DESC LIMIT 1");
            $stmt->execute([':prefix' => $prefix . '%']);
            $last_ticket = $stmt->fetch();
            
            $next_num = 1;
            if ($last_ticket && !empty($last_ticket['ticket_number'])) {
                $parts = explode('-', $last_ticket['ticket_number']);
                $last_num = intval(end($parts));
                $next_num = $last_num + 1;
            }
            
            $ticket_number = $prefix . str_pad($next_num, 5, "0", STR_PAD_LEFT);
            
            // Ensure uniqueness in case of race condition or gaps
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE ticket_number = :ticket_number");
            $check_stmt->execute([':ticket_number' => $ticket_number]);
            while ($check_stmt->fetchColumn() > 0) {
                $next_num++;
                $ticket_number = $prefix . str_pad($next_num, 5, "0", STR_PAD_LEFT);
                $check_stmt->execute([':ticket_number' => $ticket_number]);
            }
            
            // Insert ticket
            $insert_stmt = $pdo->prepare("INSERT INTO tickets (ticket_number, user_id, category_id, subject, description, priority, status) VALUES (:ticket_number, :user_id, :category_id, :subject, :description, :priority, 'NEW')");
            $insert_stmt->bindParam(":ticket_number", $ticket_number);
            $insert_stmt->bindParam(":user_id", $user_id);
            $insert_stmt->bindParam(":category_id", $category_id);
            $insert_stmt->bindParam(":subject", $subject);
            $insert_stmt->bindParam(":description", $description);
            $insert_stmt->bindParam(":priority", $priority);
            
            if ($insert_stmt->execute()) {
                $ticket_id = $pdo->lastInsertId();
                
                // Record history
                $hist_stmt = $pdo->prepare("INSERT INTO ticket_history (ticket_id, user_id, action, new_value) VALUES (:ticket_id, :user_id, 'Created Ticket', 'NEW')");
                $hist_stmt->bindParam(":ticket_id", $ticket_id);
                $hist_stmt->bindParam(":user_id", $user_id);
                $hist_stmt->execute();
                
                // Handle Attachment
                if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] != UPLOAD_ERR_NO_FILE) {
                    $attachmentHandler = new Attachment($pdo);
                    $uploadResult = $attachmentHandler->upload($_FILES['attachment'], $ticket_id, $user_id);
                    if (isset($uploadResult['error'])) {
                        header("Location: ticket-details.php?id=$ticket_id&msg=created&att_err=" . urlencode($uploadResult['error']));
                        exit;
                    }
                }
                
                // Notify admins
                $admins_stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
                while ($admin = $admins_stmt->fetch()) {
                    createNotification($pdo, $admin['id'], "New Ticket Created", "Ticket $ticket_number was created by {$user['name']}.", 'info', $ticket_id);
                }
                
                // Redirect to ticket details
                header("Location: ticket-details.php?id=$ticket_id&msg=created");
                exit;
            } else {
                $error = "Failed to create ticket. Please try again.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred while creating the ticket.";
        }
    }
}

$pageTitle = "Create Ticket";
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <div style="max-width: 820px; margin: 0 auto;">
                <div class="page-header">
                    <div>
                        <h2>Submit a Support Ticket</h2>
                        <p class="text-muted mb-0">Describe your issue in detail and our support staff will assist you</p>
                    </div>
                    <a href="tickets.php" class="btn btn-outline btn-sm">&larr; Back to My Tickets</a>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger" role="alert">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span><?= htmlspecialchars($error) ?></span>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body" style="padding: var(--space-xl);">
                        <form action="create-ticket.php" method="POST" enctype="multipart/form-data">
                            <!-- Subject -->
                            <div class="form-group">
                                <label for="subject">Ticket Subject <span class="text-danger">*</span></label>
                                <input type="text" id="subject" name="subject" class="form-control" value="<?= htmlspecialchars($subject) ?>" required placeholder="Brief summary of the issue (e.g., Cannot access billing portal)">
                            </div>
                            
                            <!-- Category -->
                            <div class="form-group">
                                <label for="category_id">Category <span class="text-danger">*</span></label>
                                <select id="category_id" name="category_id" class="form-control" required>
                                    <option value="">-- Select Relevant Category --</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['id'] ?>" <?= $category_id == $cat['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cat['name']) ?><?= !empty($cat['description']) ? ' — ' . htmlspecialchars($cat['description']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Priority Selector with Visual Pills -->
                            <div class="form-group">
                                <label>Priority Level <span class="text-danger">*</span></label>
                                <div class="priority-picker">
                                    <label class="priority-pill pill-low <?= $priority == 'LOW' ? 'active' : '' ?>">
                                        <input type="radio" name="priority" value="LOW" <?= $priority == 'LOW' ? 'checked' : '' ?>>
                                        <span class="priority-dot"></span>
                                        <span>Low</span>
                                    </label>
                                    
                                    <label class="priority-pill pill-medium <?= $priority == 'MEDIUM' ? 'active' : '' ?>">
                                        <input type="radio" name="priority" value="MEDIUM" <?= $priority == 'MEDIUM' ? 'checked' : '' ?>>
                                        <span class="priority-dot"></span>
                                        <span>Medium</span>
                                    </label>
                                    
                                    <label class="priority-pill pill-high <?= $priority == 'HIGH' ? 'active' : '' ?>">
                                        <input type="radio" name="priority" value="HIGH" <?= $priority == 'HIGH' ? 'checked' : '' ?>>
                                        <span class="priority-dot"></span>
                                        <span>High</span>
                                    </label>
                                    
                                    <label class="priority-pill pill-urgent <?= $priority == 'URGENT' ? 'active' : '' ?>">
                                        <input type="radio" name="priority" value="URGENT" <?= $priority == 'URGENT' ? 'checked' : '' ?>>
                                        <span class="priority-dot"></span>
                                        <span>Urgent</span>
                                    </label>
                                </div>
                            </div>
                            
                            <!-- Detailed Description -->
                            <div class="form-group">
                                <label for="description">Detailed Description <span class="text-danger">*</span></label>
                                <textarea id="description" name="description" class="form-control" rows="6" required placeholder="Describe what happened, error messages, and steps to reproduce..."><?= htmlspecialchars($description) ?></textarea>
                            </div>
                            
                            <!-- Drag & Drop File Upload -->
                            <div class="form-group">
                                <label>Attachment <span class="text-muted">(Optional — Max 5MB)</span></label>
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
                            
<div class="btn-group" style="display: flex; flex-wrap: wrap; gap: var(--space-md); justify-content: flex-end; margin-top: var(--space-xl); width: 100%;">
    <a href="tickets.php" class="btn btn-ghost btn-md">Cancel</a>
    <button type="submit" class="btn btn-primary btn-md">
        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        <span>Submit Ticket</span>
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
