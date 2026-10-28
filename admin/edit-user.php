<?php
// admin/edit-user.php
require_once "../config/database.php";
require_once "../includes/auth.php";

requireRole('admin');

if (!isset($_GET['id'])) {
    header("Location: users.php");
    exit;
}
$target_id = $_GET['id'];
$error = "";
$success = "";

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$target_id]);
    $uData = $stmt->fetch();
    
    if (!$uData) {
        header("Location: users.php");
        exit;
    }
} catch (PDOException $e) {
    die("Database error.");
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $role = $_POST['role'];
    $status = $_POST['status'];
    
    // Safety check: Don't allow changing one's own role to non-admin or deactivating oneself
    if ($target_id == $_SESSION['user_id'] && ($role != 'admin' || $status != 'active')) {
        $error = "You cannot demote or deactivate your own admin account.";
    } elseif (empty($name)) {
        $error = "Name is required.";
    } else {
        try {
            $upd = $pdo->prepare("UPDATE users SET name = ?, role = ?, status = ? WHERE id = ?");
            if ($upd->execute([$name, $role, $status, $target_id])) {
                $success = "User updated successfully.";
                $uData['name'] = $name;
                $uData['role'] = $role;
                $uData['status'] = $status;
            } else {
                $error = "Update failed.";
            }
        } catch (PDOException $e) {
            $error = "Database error.";
        }
    }
}

// Initials
$u_parts = explode(" ", trim($uData['name'] ?? 'U'));
$u_initials = strtoupper(substr($u_parts[0], 0, 1));
if (count($u_parts) > 1) $u_initials .= strtoupper(substr(end($u_parts), 0, 1));

$pageTitle = "Edit User: " . $uData['name'];
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <div style="max-width: 680px; margin: 0 auto;">
                <div class="page-header">
                    <div>
                        <h2>Edit User Profile</h2>
                        <p class="text-muted mb-0">Modify permissions and access status for this account</p>
                    </div>
                    <a href="users.php" class="btn btn-outline btn-sm">&larr; Back to Directory</a>
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
                
                <!-- User Overview Header -->
                <div class="card" style="margin-bottom: var(--space-lg); padding: var(--space-md) var(--space-lg);">
                    <div style="display: flex; align-items: center; gap: var(--space-md);">
                        <div class="user-avatar" style="width: 52px; height: 52px; font-size: 1.1rem;">
                            <?= htmlspecialchars($u_initials) ?>
                        </div>
                        <div>
                            <h3 style="margin-bottom: 2px; font-size: 1.25rem;"><?= htmlspecialchars($uData['name']) ?></h3>
                            <p class="text-muted" style="margin-bottom: var(--space-xs); font-size: 0.875rem;"><?= htmlspecialchars($uData['email']) ?></p>
                            <span class="badge badge-role-<?= htmlspecialchars(strtolower($uData['role'])) ?>" style="text-transform: uppercase;">
                                <?= htmlspecialchars($uData['role']) ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-body" style="padding: var(--space-xl);">
                        <form action="edit-user.php?id=<?= $target_id ?>" method="POST">
                            <div class="form-group">
                                <label for="name">Full Name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($uData['name']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address <span class="text-muted">(Read-only)</span></label>
                                <input type="email" id="email" class="form-control" value="<?= htmlspecialchars($uData['email']) ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label for="role">Assigned Role</label>
                                <select id="role" name="role" class="form-control">
                                    <option value="user" <?= $uData['role'] == 'user' ? 'selected' : '' ?>>Client User</option>
                                    <option value="staff" <?= $uData['role'] == 'staff' ? 'selected' : '' ?>>Support Staff</option>
                                    <option value="admin" <?= $uData['role'] == 'admin' ? 'selected' : '' ?>>Administrator</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Account Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="active" <?= $uData['status'] == 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="inactive" <?= $uData['status'] == 'inactive' ? 'selected' : '' ?>>Inactive (Blocked)</option>
                                </select>
                            </div>
                            
                            <div style="display: flex; gap: var(--space-md); justify-content: flex-end; margin-top: var(--space-xl);">
                                <a href="users.php" class="btn btn-ghost btn-md">Cancel</a>
                                <button type="submit" class="btn btn-primary btn-md">Update User</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
