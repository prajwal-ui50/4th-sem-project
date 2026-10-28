<?php
// register.php
require_once "config/database.php";
require_once "includes/auth.php";

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['role'];
    header("Location: $role/dashboard.php");
    exit;
}

$error = "";
$success = "";
$name = "";
$email = "";
$phone = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "Please fill in all required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            // Check for duplicate email
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->bindParam(":email", $email);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $error = "An account with this email already exists.";
            } else {
                // Insert new user
                $hashed_password = password_hash($password, PASSWORD_BCRYPT);
                $role = 'user'; // Default role, prevents privilege escalation
                
                $insert_stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (:name, :email, :phone, :password, :role)");
                $insert_stmt->bindParam(":name", $name);
                $insert_stmt->bindParam(":email", $email);
                $insert_stmt->bindParam(":phone", $phone);
                $insert_stmt->bindParam(":password", $hashed_password);
                $insert_stmt->bindParam(":role", $role);
                
                if ($insert_stmt->execute()) {
                    $success = "Registration successful! You can now log in.";
                    // Clear form values on success
                    $name = $email = $phone = "";
                } else {
                    $error = "Something went wrong during registration. Please try again.";
                }
            }
        } catch (PDOException $e) {
            $error = "An error occurred. Please try again later.";
        }
    }
}

$pageTitle = "Register";
require_once "includes/header.php";
?>
<div style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
    <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Switch theme" aria-label="Switch theme" type="button">
        <span id="themeIcon" style="display:inline-flex;align-items:center;justify-content:center;transition:transform 0.3s ease,opacity 0.25s ease;"></span>
    </button>
</div>

<div class="auth-container">
    <div class="auth-card" style="max-width: 460px;">
        <div class="auth-logo">
            <div class="auth-logo-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>
                    <path d="M13 5v2"/>
                    <path d="M13 17v2"/>
                    <path d="M13 11v2"/>
                </svg>
            </div>
        </div>

        <h2>Create an Account</h2>
        <p class="auth-subtitle">Register to submit tickets and track resolutions</p>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="alert alert-success" role="alert">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <span><?= htmlspecialchars($success) ?> <a href="login.php" style="font-weight: 600; text-decoration: underline;">Click here to sign in</a></span>
            </div>
        <?php endif; ?>

        <form action="register.php" method="POST" autocomplete="on">
            <div class="form-group">
                <label for="name">Full Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" class="form-control" value="<?= htmlspecialchars($name) ?>" placeholder="Jane Doe" required autofocus autocomplete="name">
            </div>
            
            <div class="form-group">
                <label for="email">Email Address <span class="text-danger">*</span></label>
                <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" placeholder="jane@example.com" required autocomplete="email">
            </div>
            
            <div class="form-group">
                <label for="phone">Phone Number <span class="text-muted">(Optional)</span></label>
                <input type="tel" id="phone" name="phone" class="form-control" value="<?= htmlspecialchars($phone) ?>" placeholder="+1 234 567 8900" autocomplete="tel">
            </div>
            
            <div class="form-group">
                <label for="password">Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="password" id="password" name="password" class="form-control" placeholder="Create a strong password" required autocomplete="new-password" data-strength-target="passwordStrength">
                    <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility"></button>
                </div>
                <!-- Password strength meter -->
                <div class="password-strength" id="passwordStrength">
                    <div class="password-strength-bar"></div>
                    <div class="password-strength-text"></div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm Password <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-enter your password" required autocomplete="new-password">
                    <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility"></button>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: var(--space-md);">Create Account</button>
        </form>
        
        <div class="auth-footer">
            <p>Already have an account? <a href="login.php">Sign in here</a></p>
        </div>
    </div>
</div>
<?php require_once "includes/footer.php"; ?>
