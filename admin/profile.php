<?php
// user/profile.php
require_once "../config/database.php";
require_once "../includes/auth.php";

requireRole('admin');

$user_id = $_SESSION['user_id'];
$user = currentUser();

$error = "";
$success = "";

// Fetch full user data
try {
    $stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = :id");
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

$pageTitle = "My Profile";
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <h2 style="margin-bottom: 20px;">My Profile</h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <!-- Profile Form -->
                <div class="card" style="flex: 1; min-width: 300px; background: var(--surface); padding: 25px; border-radius: var(--radius); box-shadow: var(--shadow);">
                    <h3 style="margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">Update Information</h3>
                    
                    <form action="profile.php" method="POST">
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="form-group">
                            <label for="email">Email Address (Cannot be changed)</label>
                            <input type="email" id="email" class="form-control" value="<?= htmlspecialchars($userData['email']) ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($userData['name']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($userData['phone']) ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
                
                <!-- Password Form -->
                <div class="card" style="flex: 1; min-width: 300px; background: var(--surface); padding: 25px; border-radius: var(--radius); box-shadow: var(--shadow);">
                    <h3 style="margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">Change Password</h3>
                    
                    <form action="profile.php" method="POST">
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="form-group">
                            <label for="current_password">Current Password <span class="text-danger">*</span></label>
                            <input type="password" id="current_password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password <span class="text-danger">*</span></label>
                            <input type="password" id="new_password" name="new_password" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password <span class="text-danger">*</span></label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="background-color: var(--secondary);">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
