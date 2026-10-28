<?php
// user/profile.php
require_once "../config/database.php";
require_once "../includes/auth.php";

requireRole('user');

$user_id = $_SESSION['user_id'];
$user = currentUser();

$error = "";
$success = "";

// Fetch full user data
try {
    $stmt = $pdo->prepare("SELECT name, email, phone, role, created_at FROM users WHERE id = :id");
    $stmt->bindParam(":id", $user_id);
    $stmt->execute();
    $userData = $stmt->fetch();
} catch (PDOException $e) {
    die("Error fetching profile.");
}

// Handle Profile Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $phone = trim($_POST['phone']);
    
    if (empty($name)) {
        $error = "Name cannot be empty.";
    } else {
        try {
            $update_stmt = $pdo->prepare("UPDATE users SET name = :name, phone = :phone WHERE id = :id");
            $update_stmt->bindParam(":name", $name);
            $update_stmt->bindParam(":phone", $phone);
            $update_stmt->bindParam(":id", $user_id);
            
            if ($update_stmt->execute()) {
                $_SESSION['user_name'] = $name; // Update session
                $success = "Profile updated successfully.";
                $userData['name'] = $name;
                $userData['phone'] = $phone;
            } else {
                $error = "Failed to update profile.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred.";
        }
    }
}

// Handle Password Change
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all password fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters.";
    } else {
        try {
            // Verify current password
            $pass_stmt = $pdo->prepare("SELECT password FROM users WHERE id = :id");
            $pass_stmt->bindParam(":id", $user_id);
            $pass_stmt->execute();
            $hash = $pass_stmt->fetchColumn();
            
            if (password_verify($current_password, $hash)) {
                $new_hash = password_hash($new_password, PASSWORD_BCRYPT);
                
                $update_pass = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
                $update_pass->bindParam(":password", $new_hash);
                $update_pass->bindParam(":id", $user_id);
                
                if ($update_pass->execute()) {
                    $success = "Password changed successfully.";
                } else {
                    $error = "Failed to change password.";
                }
            } else {
                $error = "Current password is incorrect.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred.";
        }
    }
}

// Generate initials
$name_parts = explode(" ", trim($userData['name'] ?? 'User'));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if (count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}

$pageTitle = "My Profile";
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <div class="page-header">
                <div>
                    <h2>My Profile</h2>
                    <p class="text-muted mb-0">Manage your account information and security preferences</p>
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
            
            <!-- User Overview Banner -->
            <div class="card" style="margin-bottom: var(--space-lg); padding: var(--space-lg);">
                <div style="display: flex; align-items: center; gap: var(--space-lg); flex-wrap: wrap;">
                    <div class="user-avatar" style="width: 72px; height: 72px; font-size: 1.5rem;">
                        <?= htmlspecialchars($initials) ?>
                    </div>
                    <div>
                        <h3 style="margin-bottom: var(--space-xs);"><?= htmlspecialchars($userData['name']) ?></h3>
                        <p class="text-muted" style="margin-bottom: var(--space-xs);"><?= htmlspecialchars($userData['email']) ?></p>
                        <div style="display: flex; gap: var(--space-sm); align-items: center;">
                            <span class="badge badge-role-<?= htmlspecialchars(strtolower($userData['role'])) ?>" style="text-transform: uppercase;">
                                <?= htmlspecialchars($userData['role']) ?>
                            </span>
                            <span class="text-muted" style="font-size: 0.8125rem;">
                                Member since <?= date('M Y', strtotime($userData['created_at'])) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="profile-grid">
                <!-- Personal Info Form -->
                <div class="card">
                    <div class="card-header">
                        <span style="font-weight: 600;">Personal Information</span>
                    </div>
                    <div class="card-body">
                        <form action="profile.php" method="POST">
                            <input type="hidden" name="update_profile" value="1">
                            
                            <div class="form-group">
                                <label for="email">Email Address <span class="text-muted">(Read-only)</span></label>
                                <input type="email" id="email" class="form-control" value="<?= htmlspecialchars($userData['email']) ?>" disabled>
                            </div>
                            
                            <div class="form-group">
                                <label for="name">Full Name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($userData['name']) ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($userData['phone']) ?>" placeholder="+1 234 567 8900">
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-md" style="margin-top: var(--space-md);">Save Changes</button>
                        </form>
                    </div>
                </div>
                
                <!-- Change Password Form -->
                <div class="card">
                    <div class="card-header">
                        <span style="font-weight: 600;">Security & Password</span>
                    </div>
                    <div class="card-body">
                        <form action="profile.php" method="POST">
                            <input type="hidden" name="change_password" value="1">
                            
                            <div class="form-group">
                                <label for="current_password">Current Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" id="current_password" name="current_password" class="form-control" required placeholder="Enter current password">
                                    <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility"></button>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" id="new_password" name="new_password" class="form-control" required placeholder="Create new password" data-strength-target="profilePasswordStrength">
                                    <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility"></button>
                                </div>
                                <div class="password-strength" id="profilePasswordStrength">
                                    <div class="password-strength-bar"></div>
                                    <div class="password-strength-text"></div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="Re-enter new password">
                                    <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility"></button>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-secondary btn-md" style="margin-top: var(--space-md);">Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
