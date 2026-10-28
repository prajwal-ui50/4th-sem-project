<?php
// login.php
require_once "config/database.php";
require_once "includes/auth.php";

// Redirect if already logged in
if (isLoggedIn()) {
    $role = $_SESSION['role'];
    header("Location: $role/dashboard.php");
    exit;
}

$error = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, password, role, status FROM users WHERE email = :email");
            $stmt->bindParam(":email", $email);
            $stmt->execute();

            if ($stmt->rowCount() == 1) {
                $user = $stmt->fetch();
                
                if ($user['status'] !== 'active') {
                    $error = "Your account is inactive. Please contact support.";
                } else if (password_verify($password, $user['password'])) {
                    // Prevent session fixation
                    session_regenerate_id(true);

                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];

                    // Redirect to corresponding dashboard
                    header("Location: " . $user['role'] . "/dashboard.php");
                    exit;
                } else {
                    $error = "Invalid email or password.";
                }
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "An error occurred during login. Please try again later.";
        }
    }
}

$pageTitle = "Login";
require_once "includes/header.php";
?>
<div style="position: fixed; top: 20px; right: 20px; z-index: 1000;">
    <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Switch theme" aria-label="Switch theme" type="button">
        <span id="themeIcon" style="display:inline-flex;align-items:center;justify-content:center;transition:transform 0.3s ease,opacity 0.25s ease;"></span>
    </button>
</div>

<div class="auth-container">
    <div class="auth-card">
        <div style="text-align: center;">
            <div class="auth-badge">
                <span class="auth-badge-dot"></span>
                <span>Support Desk</span>
            </div>
        </div>

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

        <div class="auth-header">
            <h2>Welcome Back</h2>
            <p class="auth-subtitle">Sign in to manage your tickets and support requests</p>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <form action="login.php" method="POST" autocomplete="on">
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-icon-group">
                    <span class="input-icon-left">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                    </span>
                    <input type="email" id="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" placeholder="name@example.com" required autofocus autocomplete="email">
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <div class="input-icon-group">
                    <span class="input-icon-left">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
                    <button type="button" class="password-toggle-btn" aria-label="Toggle password visibility"></button>
                </div>
            </div>
            
            <button type="submit" class="btn btn-primary btn-block btn-lg" style="margin-top: var(--space-lg);">
                <span>Sign In</span>
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
            </button>
        </form>
        
        <div class="auth-footer">
            <p>Don't have an account? <a href="register.php">Create an account &rarr;</a></p>
        </div>

        <div class="auth-security-notice">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>End-to-end encrypted session</span>
        </div>
    </div>
</div>
<?php require_once "includes/footer.php"; ?>
