<?php
// admin/add-user.php
require_once "../config/database.php";
require_once "../includes/auth.php";

requireRole('admin');

$error = "";
$success = "";
$name = "";
$email = "";
$phone = "";
$role = "user";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    if (empty($name) || empty($email) || empty($password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = "An account with this email already exists.";
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $insert = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, ?)");
                if ($insert->execute([$name, $email, $phone, $hash, $role])) {
                    $success = "User account created successfully.";
                    $name = $email = $phone = "";
                    $role = "user";
                } else {
                    $error = "Failed to create user account.";
                }
            }
        } catch (PDOException $e) {
            $error = "Database error occurred.";
        }
    }
}

$pageTitle = "Add User";
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
                        <h2>Create New User</h2>
                        <p class="text-muted mb-0">Register a new client, staff agent, or administrator</p>
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
                        <span><?= htmlspecialchars($success) ?> <a href="users.php" style="font-weight: 600; text-decoration: underline;">View User List</a></span>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body" style="padding: var(--space-xl);">
                        <form action="add-user.php" method="POST">
                            <div class="form-group">
                                <label for="name">Full Name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" placeholder="Full Name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address <span class="text-danger">*</span></label>
                                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" placeholder="name@example.com" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number <span class="text-muted">(Optional)</span></label>
                                <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($phone) ?>" placeholder="+1 234 567 8900">
                            </div>
                            
                            <div class="form-group">
                                <label for="password">Initial Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" id="password" name="password" class="form-control" required placeholder="Minimum 6 characters" data-strength-target="addUserStrength">
                                    <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility"></button>
                                </div>
                                <div class="password-strength" id="addUserStrength">
                                    <div class="password-strength-bar"></div>
                                    <div class="password-strength-text"></div>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="role">System Role <span class="text-danger">*</span></label>
                                <select id="role" name="role" class="form-control">
                                    <option value="user" <?= $role == 'user' ? 'selected' : '' ?>>Client User (Submits and views own tickets)</option>
                                    <option value="staff" <?= $role == 'staff' ? 'selected' : '' ?>>Support Staff (Answers and manages assigned tickets)</option>
                                    <option value="admin" <?= $role == 'admin' ? 'selected' : '' ?>>Administrator (Full system permissions)</option>
                                </select>
                            </div>
                            
                            <div style="display: flex; gap: var(--space-md); justify-content: flex-end; margin-top: var(--space-xl);">
                                <a href="users.php" class="btn btn-ghost btn-md">Cancel</a>
                                <button type="submit" class="btn btn-primary btn-md">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                                    <span>Create User</span>
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
