<?php
// admin/users.php
require_once "../config/database.php";
require_once "../includes/auth.php";

requireRole('admin');

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$role = isset($_GET['role']) ? $_GET['role'] : '';

$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (name LIKE :search OR email LIKE :search)";
    $params[':search'] = "%$search%";
}
if (!empty($role)) {
    $query .= " AND role = :role";
    $params[':role'] = $role;
}
$query .= " ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => &$val) {
        $stmt->bindParam($key, $val);
    }
    $stmt->execute();
    $usersList = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching users.");
}

$pageTitle = "Manage Users";
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <div class="page-header">
                <div>
                    <h2>User Directory</h2>
                    <p class="text-muted mb-0">Manage system users, support staff accounts, and administrator permissions</p>
                </div>
                <a href="add-user.php" class="btn btn-primary btn-md">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    <span>Add New User</span>
                </a>
            </div>
            
            <!-- Filters -->
            <form class="filter-section" method="GET" action="users.php">
                <input type="text" name="search" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>" style="flex: 2; min-width: 200px;">
                <select name="role" style="flex: 1; min-width: 140px;">
                    <option value="">All Roles</option>
                    <option value="user" <?= $role == 'user' ? 'selected' : '' ?>>User / Client</option>
                    <option value="staff" <?= $role == 'staff' ? 'selected' : '' ?>>Staff Agent</option>
                    <option value="admin" <?= $role == 'admin' ? 'selected' : '' ?>>Administrator</option>
                </select>
<div class="btn-group" style="display: flex; gap: var(--space-xs); flex-wrap: wrap;">
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <?php if (!empty($search) || !empty($role)): ?>
        <a href="users.php" class="btn btn-ghost btn-sm">Reset</a>
    <?php endif; ?>
</div>
            </form>
            
            <!-- Users Table Card -->
            <div class="card">
                <?php if (count($usersList) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email Address</th>
                                    <th>Phone</th>
                                    <th>System Role</th>
                                    <th>Account Status</th>
                                    <th>Joined</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usersList as $u): 
                                    $u_parts = explode(" ", trim($u['name'] ?? 'U'));
                                    $u_initials = strtoupper(substr($u_parts[0], 0, 1));
                                    if (count($u_parts) > 1) $u_initials .= strtoupper(substr(end($u_parts), 0, 1));
                                ?>
                                    <tr>
                                        <td>
                                            <div style="display: flex; align-items: center; gap: var(--space-sm);">
                                                <div class="user-avatar" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                                    <?= htmlspecialchars($u_initials) ?>
                                                </div>
                                                <strong style="color: var(--text);"><?= htmlspecialchars($u['name']) ?></strong>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars($u['email']) ?></td>
                                        <td class="text-muted"><?= htmlspecialchars($u['phone'] ?? '—') ?></td>
                                        <td>
                                            <span class="badge badge-role-<?= htmlspecialchars(strtolower($u['role'])) ?>" style="text-transform: uppercase;">
                                                <?= htmlspecialchars($u['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($u['status'] == 'active'): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-muted" style="font-size: 0.8125rem;">
                                            <?= date('M d, Y', strtotime($u['created_at'])) ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="edit-user.php?id=<?= $u['id'] ?>" class="btn btn-outline btn-sm">Edit User</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div style="width: 64px; height: 64px; border-radius: var(--radius-full); background: var(--surface-secondary); display: flex; align-items: center; justify-content: center; margin: 0 auto var(--space-md); color: var(--muted);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                        </div>
                        <h4>No users found</h4>
                        <p class="text-muted">No users matched your query. Try adjusting filters or create a new user.</p>
                        <a href="add-user.php" class="btn btn-primary btn-sm">Add New User</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
