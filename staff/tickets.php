<?php
// staff/tickets.php
require_once "../config/database.php";
require_once "../includes/auth.php";

requireRole('staff');

$user_id = $_SESSION['user_id'];
$user = currentUser();

// Filtering and Searching
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$priority = isset($_GET['priority']) ? $_GET['priority'] : '';
$category = isset($_GET['category']) ? $_GET['category'] : '';

// Pagination
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Fetch categories for filter dropdown
try {
    $cat_stmt = $pdo->prepare("SELECT id, name FROM categories");
    $cat_stmt->execute();
    $categories = $cat_stmt->fetchAll();
} catch (PDOException $e) {
    die("Error fetching categories.");
}

// Build query dynamically - Only tickets assigned to this staff member
$query = "SELECT t.*, c.name as category_name, u.name as client_name 
          FROM tickets t 
          LEFT JOIN categories c ON t.category_id = c.id 
          LEFT JOIN users u ON t.user_id = u.id 
          WHERE t.assigned_to = :user_id";
$params = [':user_id' => $user_id];

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

$query .= " ORDER BY t.updated_at DESC";

// Count total records for pagination
$countQuery = str_replace("t.*, c.name as category_name, u.name as client_name", "COUNT(t.id)", $query);
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

$pageTitle = "Assigned Tickets";
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <div class="page-header">
                <div>
                    <h2>Assigned Tickets</h2>
                    <p class="text-muted mb-0">Manage customer tickets assigned directly to your queue</p>
                </div>
            </div>
            
            <!-- Filters -->
            <form class="filter-section" method="GET" action="tickets.php">
                <input type="text" name="search" placeholder="Search ticket #, subject or client..." value="<?= htmlspecialchars($search) ?>" style="flex: 2; min-width: 200px;">
                
                <select name="status" style="flex: 1; min-width: 140px;">
                    <option value="">All Statuses</option>
                    <option value="OPEN" <?= $status == 'OPEN' ? 'selected' : '' ?>>Open</option>
                    <option value="ASSIGNED" <?= $status == 'ASSIGNED' ? 'selected' : '' ?>>Assigned</option>
                    <option value="IN PROGRESS" <?= $status == 'IN PROGRESS' ? 'selected' : '' ?>>In Progress</option>
                    <option value="RESOLVED" <?= $status == 'RESOLVED' ? 'selected' : '' ?>>Resolved</option>
                    <option value="CLOSED" <?= $status == 'CLOSED' ? 'selected' : '' ?>>Closed</option>
                    <option value="REOPENED" <?= $status == 'REOPENED' ? 'selected' : '' ?>>Reopened</option>
                </select>
                
                <select name="priority" style="flex: 1; min-width: 130px;">
                    <option value="">All Priorities</option>
                    <option value="LOW" <?= $priority == 'LOW' ? 'selected' : '' ?>>Low</option>
                    <option value="MEDIUM" <?= $priority == 'MEDIUM' ? 'selected' : '' ?>>Medium</option>
                    <option value="HIGH" <?= $priority == 'HIGH' ? 'selected' : '' ?>>High</option>
                    <option value="URGENT" <?= $priority == 'URGENT' ? 'selected' : '' ?>>Urgent</option>
                </select>
                
                <select name="category" style="flex: 1; min-width: 140px;">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>" <?= $category == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                
<div class="btn-group" style="display: flex; gap: var(--space-xs); flex-wrap: wrap;">
    <button type="submit" class="btn btn-primary btn-sm">Filter</button>
    <?php if (!empty($search) || !empty($status) || !empty($priority) || !empty($category)): ?>
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
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Last Updated</th>
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
                                            <span style="color: var(--text-secondary);"><?= htmlspecialchars($ticket['client_name']) ?></span>
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
                                        <td class="text-muted" style="font-size: 0.875rem;">
                                            <?= date('M d, Y h:i A', strtotime($ticket['updated_at'])) ?>
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
                        <p class="text-muted">You have no assigned tickets matching these search filters.</p>
                        <a href="tickets.php" class="btn btn-outline btn-sm">Clear Filters</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
