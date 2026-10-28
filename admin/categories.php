<?php
// admin/categories.php
require_once "../config/database.php";
require_once "../includes/auth.php";

requireRole('admin');
$user = currentUser();

$error = "";
$success = "";

// Handle Add/Edit
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO categories (name, description, status) VALUES (?, ?, 'active')");
                if ($stmt->execute([$name, $description])) {
                    $success = "Category added successfully.";
                }
            } catch (PDOException $e) {
                $error = "Failed to add category.";
            }
        }
    } elseif (isset($_POST['edit_category'])) {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $status = $_POST['status'];
        
        if (!empty($name)) {
            try {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, status = ? WHERE id = ?");
                if ($stmt->execute([$name, $description, $status, $id])) {
                    $success = "Category updated successfully.";
                }
            } catch (PDOException $e) {
                $error = "Failed to update category.";
            }
        }
    } elseif (isset($_POST['delete_category'])) {
        $id = $_POST['id'];
        
        try {
            // Check if category is used in tickets
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE category_id = ?");
            $check_stmt->execute([$id]);
            $used_count = $check_stmt->fetchColumn();
            
            if ($used_count > 0) {
                $error = "Cannot delete category because it is used by $used_count ticket(s).";
            } else {
                $del_stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                if ($del_stmt->execute([$id])) {
                    $success = "Category deleted successfully.";
                }
            }
        } catch (PDOException $e) {
            $error = "Failed to delete category.";
        }
    }
}

// Fetch categories
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY created_at DESC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching categories.");
}

$pageTitle = "Manage Categories";
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <div class="page-header">
                <div>
                    <h2>Ticket Categories</h2>
                    <p class="text-muted mb-0">Organize support departments, topics, and routing queues</p>
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
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-lg); align-items: start;">
                <!-- Category Table List -->
                <div class="card">
                    <div class="card-header">
                        <span style="font-weight: 600;">Configured Categories (<?= count($categories) ?>)</span>
                    </div>
                    
                    <?php if (count($categories) > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Status</th>
                                        <th style="text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td>
                                                <strong style="color: var(--text);"><?= htmlspecialchars($cat['name']) ?></strong>
                                            </td>
                                            <td>
                                                <span class="text-muted" style="font-size: 0.875rem;">
                                                    <?= htmlspecialchars($cat['description'] ?? '—') ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($cat['status'] == 'active'): ?>
                                                    <span class="badge badge-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge badge-danger">Inactive</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="text-align: right;">
                                                <div class="btn-group" style="display: inline-flex; gap: 4px;">
                                                    <button class="btn btn-outline btn-sm" onclick="editCategory(<?= $cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name'])) ?>', '<?= htmlspecialchars(addslashes($cat['description'] ?? '')) ?>', '<?= $cat['status'] ?>')">
                                                        Edit
                                                    </button>
                                                    <form action="categories.php" method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                                        <input type="hidden" name="delete_category" value="1">
                                                        <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p class="text-muted">No categories created yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Add / Edit Category Form -->
                <div class="card">
                    <div class="card-header">
                        <span style="font-weight: 600;" id="form-title">Add Category</span>
                    </div>
                    <div class="card-body">
                        <form action="categories.php" method="POST" id="cat-form">
                            <input type="hidden" name="add_category" id="form-action" value="1">
                            <input type="hidden" name="id" id="cat-id" value="">
                            
                            <div class="form-group">
                                <label for="name">Category Name <span class="text-danger">*</span></label>
                                <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Technical Support" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Short Description</label>
                                <textarea id="description" name="description" class="form-control" rows="3" placeholder="Brief explanation of issues covered..."></textarea>
                            </div>
                            
                            <div class="form-group" id="status-group" style="display: none;">
                                <label for="status">Category Status</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="active">Active (Available for new tickets)</option>
                                    <option value="inactive">Inactive (Hidden)</option>
                                </select>
                            </div>
                            
                            <div style="display: flex; gap: var(--space-sm); margin-top: var(--space-lg);">
                                <button type="submit" class="btn btn-primary btn-md" id="btn-submit">Add Category</button>
                                <button type="button" class="btn btn-ghost btn-md" id="btn-cancel" style="display: none;" onclick="resetForm()">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function editCategory(id, name, desc, status) {
    document.getElementById('form-title').innerText = "Edit Category";
    document.getElementById('form-action').name = "edit_category";
    document.getElementById('cat-id').value = id;
    document.getElementById('name').value = name;
    document.getElementById('description').value = desc;
    
    document.getElementById('status-group').style.display = "block";
    document.getElementById('status').value = status;
    
    document.getElementById('btn-submit').innerText = "Update Category";
    document.getElementById('btn-cancel').style.display = "inline-flex";
}

function resetForm() {
    document.getElementById('form-title').innerText = "Add Category";
    document.getElementById('form-action').name = "add_category";
    document.getElementById('cat-form').reset();
    document.getElementById('cat-id').value = "";
    
    document.getElementById('status-group').style.display = "none";
    
    document.getElementById('btn-submit').innerText = "Add Category";
    document.getElementById('btn-cancel').style.display = "none";
}
</script>
<?php require_once "../includes/footer.php"; ?>
