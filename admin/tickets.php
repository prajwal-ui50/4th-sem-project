<?php
// admin/tickets.php
require_once "../config/database.php";
require_once "../includes/auth.php";

requireRole('admin');

$user = currentUser();

// Filtering and Searching
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$priority = isset($_GET['priority']) ? $_GET['priority'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$assigned = isset($_GET['assigned']) ? $_GET['assigned'] : '';

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Fetch categories and staff for filters
try {
    $cat_stmt = $pdo->query("SELECT id, name FROM categories");
    $categories = $cat_stmt->fetchAll();
    
    $staff_stmt = $pdo->query("SELECT id, name FROM users WHERE role IN ('staff', 'admin') AND status = 'active'");
    $staff_members = $staff_stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching filters.");
}

// Build query dynamically
$query = "SELECT t.*, c.name as category_name, u.name as client_name, s.name as assigned_name 
          FROM tickets t 
          LEFT JOIN categories c ON t.category_id = c.id 
          LEFT JOIN users u ON t.user_id = u.id 
          LEFT JOIN users s ON t.assigned_to = s.id 
          WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (t.ticket_number LIKE :search OR t.subject LIKE :search OR u.name LIKE :search)";
    $params[':search'] = "%$search%";
}

if (!empty($status)) {
    $query .= " AND t.status = :status";
    $params[':status'] = $status;
}

if (!empty($priority)) {
    $query .= " AND t.priority = :priority";
    $params[':priority'] = $priority;
}

if (!empty($category)) {
    $query .= " AND t.category_id = :category";
    $params[':category'] = $category;
}

if ($assigned === 'unassigned') {
    $query .= " AND t.assigned_to IS NULL";
} elseif (!empty($assigned)) {
    $query .= " AND t.assigned_to = :assigned";
    $params[':assigned'] = $assigned;
}

$query .= " ORDER BY t.created_at DESC";

// Count total records for pagination
$countQuery = str_replace("t.*, c.name as category_name, u.name as client_name, s.name as assigned_name", "COUNT(t.id)", $query);
try {
    $countStmt = $pdo->prepare($countQuery);
    foreach ($params as $key => &$val) {
        $countStmt->bindParam($key, $val);
    }
    $countStmt->execute();
    $total_records = $countStmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
} catch (PDOException $e) {
    die("Error fetching ticket count.");
}

$query .= " LIMIT :limit OFFSET :offset";

try {
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => &$val) {
        $stmt->bindParam($key, $val);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $tickets = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching tickets.");
}

$pageTitle = "All Tickets";
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <div class="page-header">
                <div>
                    <h2>All Support Tickets</h2>
                    <p class="text-muted mb-0">System-wide ticket management, dispatching, and status monitoring</p>
                </div>
            </div>
            
            <!-- Filters -->
            <form class="filter-section" method="GET" action="tickets.php">
                <input type="text" name="search" placeholder="Search ticket #, subject or user..." value="<?= htmlspecialchars($search) ?>" style="flex: 2; min-width: 200px;">
                
                <select name="status" style="flex: 1; min-width: 130px;">
                    <option value="">All Statuses</option>
                    <option value="NEW" <?= $status == 'NEW' ? 'selected' : '' ?>>New</option>
                    <option value="OPEN" <?= $status == 'OPEN' ? 'selected' : '' ?>>Open</option>
                    <option value="ASSIGNED" <?= $status == 'ASSIGNED' ? 'selected' : '' ?>>Assigned</option>
                    <option value="IN PROGRESS" <?= $status == 'IN PROGRESS' ? 'selected' : '' ?>>In Progress</option>
                    <option value="RESOLVED" <?= $status == 'RESOLVED' ? 'selected' : '' ?>>Resolved</option>
                    <option value="CLOSED" <?= $status == 'CLOSED' ? 'selected' : '' ?>>Closed</option>
                </select>
                
                <select name="priority" style="flex: 1; min-width: 120px;">
                    <option value="">All Priorities</option>
                    <option value="LOW" <?= $priority == 'LOW' ? 'selected' : '' ?>>Low</option>
                    <option value="MEDIUM" <?= $priority == 'MEDIUM' ? 'selected' : '' ?>>Medium</option>
                    <option value="HIGH" <?= $priority == 'HIGH' ? 'selected' : '' ?>>High</option>
                    <option value="URGENT" <?= $priority == 'URGENT' ? 'selected' : '' ?>>Urgent</option>
                </select>
                
                <select name="category" style="flex: 1; min-width: 130px;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                
                <select name="assigned" style="flex: 1; min-width: 140px;">
                    <option value="">All Assignees</option>
                    <option value="unassigned" <?= $assigned === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
                    <?php foreach ($staff_members as $sm): ?>
                        <option value="<?= $sm['id'] ?>" <?= $assigned == $sm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($sm['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                
<div class="btn-group" style="display: flex; gap: var(--space-xs); flex-wrap: wrap;">
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <?php if (!empty($search) || !empty($status) || !empty($priority) || !empty($category) || !empty($assigned)): ?>
        <a href="tickets.php" class="btn btn-ghost btn-sm">Reset</a>
    <?php endif; ?>
</div>
            </form>
            
            <!-- Table Card -->
            <div class="card">
                <?php if (count($tickets) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Ticket #</th>
                                    <th>Subject</th>
                                    <th>Client</th>
                                    <th>Category</th>
                                    <th>Assigned To</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th style="text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tickets as $ticket): ?>
                                    <tr>
                                        <td>
                                            <a href="ticket-details.php?id=<?= $ticket['id'] ?>" style="font-weight: 600;">
                                                <?= htmlspecialchars($ticket['ticket_number']) ?>
                                            </a>
                                        </td>
                                        <td>
                                            <span style="font-weight: 500; color: var(--text);"><?= htmlspecialchars($ticket['subject']) ?></span>
                                        </td>
                                        <td>
                                            <span style="color: var(--text-secondary); font-size: 0.9rem;"><?= htmlspecialchars($ticket['client_name']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge" style="background: var(--surface-secondary); color: var(--text-secondary); border: 1px solid var(--border);">
                                                <?= htmlspecialchars($ticket['category_name'] ?? 'General') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($ticket['assigned_name']): ?>
                                                <span class="badge badge-role-staff"><?= htmlspecialchars($ticket['assigned_name']) ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-urgent" style="font-size: 0.7rem;">Unassigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $priorityClass = strtolower($ticket['priority']);
                                                echo "<span class='badge badge-{$priorityClass}'>" . htmlspecialchars($ticket['priority']) . "</span>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $statusClass = strtolower(str_replace(' ', '', $ticket['status']));
                                                echo "<span class='badge badge-{$statusClass}'>" . htmlspecialchars($ticket['status']) . "</span>";
                                            ?>
                                        </td>
                                        <td class="text-muted" style="font-size: 0.8125rem;">
                                            <?= date('M d, Y', strtotime($ticket['created_at'])) ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <a href="ticket-details.php?id=<?= $ticket['id'] ?>" class="btn btn-outline btn-sm">Manage</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination Controls -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <div>
                                Showing <strong><?= $offset + 1 ?></strong> to <strong><?= min($offset + $limit, $total_records) ?></strong> of <strong><?= $total_records ?></strong> tickets
                            </div>
                            <div class="pagination-controls">
                                <?php 
                                $queryString = $_GET;
                                if ($page > 1) {
                                    $queryString['page'] = $page - 1;
                                    $prevUrl = '?' . http_build_query($queryString);
                                    echo "<a href='$prevUrl' class='btn btn-outline btn-sm'>&larr; Previous</a>";
                                }
                                if ($page < $total_pages) {
                                    $queryString['page'] = $page + 1;
                                    $nextUrl = '?' . http_build_query($queryString);
                                    echo "<a href='$nextUrl' class='btn btn-outline btn-sm'>Next &rarr;</a>";
                                }
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <div style="width: 64px; height: 64px; border-radius: var(--radius-full); background: var(--surface-secondary); display: flex; align-items: center; justify-content: center; margin: 0 auto var(--space-md); color: var(--muted);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </div>
                        <h4>No tickets found</h4>
                        <p class="text-muted">No tickets match the selected filters.</p>
                        <a href="tickets.php" class="btn btn-outline btn-sm">Clear Filters</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
