<?php
// user/tickets.php
require_once "../config/database.php";
require_once "../includes/auth.php";

requireRole('user');

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

// Build query dynamically
$query = "SELECT t.*, c.name as category_name 
          FROM tickets t 
          LEFT JOIN categories c ON t.category_id = c.id 
          WHERE t.user_id = :user_id";
$params = [':user_id' => $user_id];

if (!empty($search)) {
    $query .= " AND (t.ticket_number LIKE :search OR t.subject LIKE :search)";
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

$query .= " ORDER BY t.created_at DESC";

// Count total records for pagination
$countQuery = str_replace("t.*, c.name as category_name", "COUNT(t.id)", $query);
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

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';
$successAlert = '';
$errorAlert = '';

if ($msg === 'deleted') {
    $tNum = isset($_GET['ticket_num']) ? htmlspecialchars($_GET['ticket_num']) : 'Ticket';
    $successAlert = "Ticket $tNum was successfully deleted.";
} elseif ($msg === 'updated') {
    $successAlert = "Ticket updated successfully.";
}

if (!empty($err)) {
    $errorAlert = htmlspecialchars($err);
}

$pageTitle = "My Tickets";
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <div class="page-header">
                <div>
                    <h2>My Tickets</h2>
                    <p class="text-muted mb-0">Browse, filter, and track all your support requests</p>
                </div>
                <a href="create-ticket.php" class="btn btn-primary btn-md">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                    <span>Create Ticket</span>
                </a>
            </div>

            <?php if (!empty($successAlert)): ?>
                <div class="alert alert-success" role="alert" style="margin-bottom: var(--space-lg);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span><?= $successAlert ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($errorAlert)): ?>
                <div class="alert alert-danger" role="alert" style="margin-bottom: var(--space-lg);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span><?= $errorAlert ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Filters Section -->
            <form class="filter-section" method="GET" action="tickets.php">
                <input type="text" name="search" placeholder="Search ticket # or subject..." value="<?= htmlspecialchars($search) ?>" style="flex: 2; min-width: 200px;">
                
                <select name="status" style="flex: 1; min-width: 140px;">
                    <option value="">All Statuses</option>
                    <option value="NEW" <?= $status == 'NEW' ? 'selected' : '' ?>>New</option>
                    <option value="OPEN" <?= $status == 'OPEN' ? 'selected' : '' ?>>Open</option>
                    <option value="ASSIGNED" <?= $status == 'ASSIGNED' ? 'selected' : '' ?>>Assigned</option>
                    <option value="IN PROGRESS" <?= $status == 'IN PROGRESS' ? 'selected' : '' ?>>In Progress</option>
                    <option value="RESOLVED" <?= $status == 'RESOLVED' ? 'selected' : '' ?>>Resolved</option>
                    <option value="CLOSED" <?= $status == 'CLOSED' ? 'selected' : '' ?>>Closed</option>
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
            
            <!-- Tickets Table Card -->
            <div class="card">
                <?php if (count($tickets) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Ticket #</th>
                                    <th>Subject</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Created Date</th>
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
                                            <span class="badge" style="background: var(--surface-secondary); color: var(--text-secondary); border: 1px solid var(--border);">
                                                <?= htmlspecialchars($ticket['category_name'] ?? 'General') ?>
                                            </span>
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
                                            <?= date('M d, Y h:i A', strtotime($ticket['created_at'])) ?>
                                        </td>
<td style="text-align: right;">
    <div class="btn-group" style="display: inline-flex; flex-direction: column; gap: 4px;">
        <a href="ticket-details.php?id=<?= $ticket['id'] ?>" class="btn btn-outline btn-sm" title="View Ticket Details">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
            <span>View</span>
        </a>
        <?php if ($ticket['status'] !== 'CLOSED'): ?>
            <a href="edit-ticket.php?id=<?= $ticket['id'] ?>" class="btn btn-ghost btn-sm" style="border: 1px solid var(--border);" title="Edit Ticket">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                <span>Edit</span>
            </a>
        <?php endif; ?>
        <a href="delete-ticket.php?id=<?= $ticket['id'] ?>" class="btn btn-danger btn-sm" title="Delete Ticket">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
            <span>Delete</span>
        </a>
    </div>
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
                        <p class="text-muted" style="margin-bottom: var(--space-lg);">No tickets matched your current search filters.</p>
                        <a href="tickets.php" class="btn btn-outline btn-sm">Clear Filters</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
