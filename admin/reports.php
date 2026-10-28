<?php
// admin/reports.php
require_once "../config/database.php";
require_once "../includes/auth.php";

requireRole('admin');
$user = currentUser();

// Initialize filter variables
$filter_date = $_GET['date'] ?? 'all';
$filter_status = $_GET['status'] ?? '';
$filter_priority = $_GET['priority'] ?? '';
$filter_category = $_GET['category'] ?? '';
$filter_staff = $_GET['staff'] ?? '';

// Build WHERE clauses for tickets
$where_clauses = ["1=1"];
$params = [];

if ($filter_status !== '') {
    $where_clauses[] = "t.status = :status";
    $params[':status'] = $filter_status;
}

if ($filter_priority !== '') {
    $where_clauses[] = "t.priority = :priority";
    $params[':priority'] = $filter_priority;
}

if ($filter_category !== '') {
    $where_clauses[] = "t.category_id = :category";
    $params[':category'] = $filter_category;
}

if ($filter_staff !== '') {
    $where_clauses[] = "t.assigned_to = :staff";
    $params[':staff'] = $filter_staff;
}

if ($filter_date !== 'all') {
    if ($filter_date === 'today') {
        $where_clauses[] = "DATE(t.created_at) = CURDATE()";
    } elseif ($filter_date === '7days') {
        $where_clauses[] = "t.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    } elseif ($filter_date === '30days') {
        $where_clauses[] = "t.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    } elseif ($filter_date === 'this_year') {
        $where_clauses[] = "YEAR(t.created_at) = YEAR(CURDATE())";
    }
}

$where_sql = implode(" AND ", $where_clauses);

// ==========================================
// EXPORT TO CSV
// ==========================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $export_query = "
        SELECT 
            t.id, t.ticket_number, t.subject, u.name as user_name, c.name as category_name,
            t.priority, t.status, s.name as staff_name, t.created_at, t.updated_at, t.resolved_at
        FROM tickets t
        LEFT JOIN users u ON t.user_id = u.id
        LEFT JOIN categories c ON t.category_id = c.id
        LEFT JOIN users s ON t.assigned_to = s.id
        WHERE $where_sql
        ORDER BY t.created_at DESC
    ";
    
    $stmt = $pdo->prepare($export_query);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=tickets_report_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Ticket Number', 'Subject', 'User', 'Category', 'Priority', 'Status', 'Assigned Staff', 'Created At', 'Updated At', 'Resolved At']);
    
    foreach ($results as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

// ==========================================
// FETCH REPORT DATA
// ==========================================

try {
    // Basic stats
    $stats = [
        'users' => 0, 'staff' => 0, 'total' => 0, 'new' => 0, 'open' => 0, 'in_progress' => 0,
        'resolved' => 0, 'closed' => 0, 'high_urgent' => 0, 'resolution_rate' => 0, 'avg_resolution_time' => null
    ];
    
    // User counts
    $u_stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    while ($row = $u_stmt->fetch()) {
        if ($row['role'] == 'user') $stats['users'] = $row['count'];
        if ($row['role'] == 'staff') $stats['staff'] = $row['count'];
    }
    
    // Ticket aggregations with current filters
    $stmt = $pdo->prepare("SELECT status, priority, COUNT(*) as count FROM tickets t WHERE $where_sql GROUP BY status, priority");
    $stmt->execute($params);
    $dist_status = [];
    $dist_priority = [];
    
    while ($row = $stmt->fetch()) {
        $stats['total'] += $row['count'];
        $status = $row['status'];
        $priority = $row['priority'];
        $count = $row['count'];
        
        if ($status == 'NEW') $stats['new'] += $count;
        if ($status == 'OPEN') $stats['open'] += $count;
        if ($status == 'IN PROGRESS') $stats['in_progress'] += $count;
        if ($status == 'RESOLVED') $stats['resolved'] += $count;
        if ($status == 'CLOSED') $stats['closed'] += $count;
        
        if (in_array($priority, ['HIGH', 'URGENT'])) {
            $stats['high_urgent'] += $count;
        }
        
        $dist_status[$status] = ($dist_status[$status] ?? 0) + $count;
        $dist_priority[$priority] = ($dist_priority[$priority] ?? 0) + $count;
    }
    
    // Calculate resolution rate
    if ($stats['total'] > 0) {
        $resolved_total = $stats['resolved'] + $stats['closed'];
        $stats['resolution_rate'] = round(($resolved_total / $stats['total']) * 100, 1);
    }
    
    // Average resolution time for filtered tickets
    $avg_stmt = $pdo->prepare("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours FROM tickets t WHERE $where_sql AND resolved_at IS NOT NULL");
    $avg_stmt->execute($params);
    $avg_result = $avg_stmt->fetch();
    if ($avg_result && $avg_result['avg_hours'] !== null) {
        $stats['avg_resolution_time'] = round($avg_result['avg_hours'], 1);
    }
    
    // Category Distribution
    $cat_stmt = $pdo->prepare("SELECT c.name, COUNT(t.id) as count FROM categories c LEFT JOIN tickets t ON c.id = t.category_id AND $where_sql GROUP BY c.id ORDER BY count DESC");
    $cat_stmt->execute($params);
    $dist_category = $cat_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Staff Workload
    $staff_stmt = $pdo->prepare("
        SELECT u.id, u.name, 
            COUNT(t.id) as total_assigned,
            SUM(CASE WHEN t.status IN ('NEW', 'OPEN', 'ASSIGNED') THEN 1 ELSE 0 END) as open_tickets,
            SUM(CASE WHEN t.status = 'IN PROGRESS' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN t.status = 'RESOLVED' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN t.status = 'CLOSED' THEN 1 ELSE 0 END) as closed
        FROM users u 
        LEFT JOIN tickets t ON u.id = t.assigned_to AND $where_sql
        WHERE u.role = 'staff'
        GROUP BY u.id
        ORDER BY total_assigned DESC
    ");
    $staff_stmt->execute($params);
    $staff_workload = $staff_stmt->fetchAll();
    
    // Form options
    $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
    $staff_members = $pdo->query("SELECT id, name FROM users WHERE role = 'staff' ORDER BY name")->fetchAll();
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$pageTitle = "Reporting & Analytics";
require_once "../includes/header.php";

// Helper function for rendering bar charts safely
function renderBar($label, $value, $max, $colorClass) {
    $percentage = $max > 0 ? ($value / $max) * 100 : 0;
    $percentFmt = round($percentage, 1) . '%';
    return '
    <div style="margin-bottom: 10px;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 4px; font-size: 0.9rem;">
            <span>' . htmlspecialchars($label) . '</span>
            <span class="text-muted">' . $value . ' (' . $percentFmt . ')</span>
        </div>
        <div style="width: 100%; background: var(--surface-secondary); height: 8px; border-radius: 4px; overflow: hidden; border: 1px solid var(--border);">
            <div class="' . $colorClass . '" style="width: ' . $percentage . '%; height: 100%; border-radius: 4px;"></div>
        </div>
    </div>';
}

$max_status = !empty($dist_status) ? max($dist_status) : 0;
$max_priority = !empty($dist_priority) ? max($dist_priority) : 0;
$max_category = !empty($dist_category) ? max($dist_category) : 0;
?>

<style>
    /* Custom bar colors using CSS variables to ensure Dark Mode compatibility */
    .bg-new { background-color: var(--primary); }
    .bg-open { background-color: var(--warning); }
    .bg-progress { background-color: var(--success); }
    .bg-resolved { background-color: var(--secondary); }
    .bg-closed { background-color: var(--muted); }
    
    .bg-low { background-color: var(--primary); }
    .bg-medium { background-color: var(--warning); }
    .bg-high { background-color: var(--danger); }
    .bg-urgent { background-color: #8b0000; }
    
    .bg-category { background-color: var(--primary); }
</style>

<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2>Reporting & Analytics</h2>
                
                <a href="reports.php?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-secondary">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 5px;"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                    Export CSV
                </a>
            </div>
            
            <!-- Filters -->
            <div class="filter-section">
                <form action="reports.php" method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; width: 100%; align-items: center;">
                    <div>
                        <select name="date" class="form-control" style="width: auto;">
                            <option value="all" <?= $filter_date == 'all' ? 'selected' : '' ?>>All Time</option>
                            <option value="today" <?= $filter_date == 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="7days" <?= $filter_date == '7days' ? 'selected' : '' ?>>Last 7 Days</option>
                            <option value="30days" <?= $filter_date == '30days' ? 'selected' : '' ?>>Last 30 Days</option>
                            <option value="this_year" <?= $filter_date == 'this_year' ? 'selected' : '' ?>>This Year</option>
                        </select>
                    </div>
                    <div>
                        <select name="status" class="form-control" style="width: auto;">
                            <option value="">All Statuses</option>
                            <?php foreach(['NEW', 'OPEN', 'ASSIGNED', 'IN PROGRESS', 'RESOLVED', 'CLOSED', 'REOPENED'] as $st): ?>
                                <option value="<?= $st ?>" <?= $filter_status == $st ? 'selected' : '' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <select name="priority" class="form-control" style="width: auto;">
                            <option value="">All Priorities</option>
                            <?php foreach(['LOW', 'MEDIUM', 'HIGH', 'URGENT'] as $pr): ?>
                                <option value="<?= $pr ?>" <?= $filter_priority == $pr ? 'selected' : '' ?>><?= $pr ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <select name="category" class="form-control" style="width: auto;">
                            <option value="">All Categories</option>
                            <?php foreach($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>" <?= $filter_category == $cat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <select name="staff" class="form-control" style="width: auto;">
                            <option value="">All Staff</option>
                            <?php foreach($staff_members as $staff): ?>
                                <option value="<?= $staff['id'] ?>" <?= $filter_staff == $staff['id'] ? 'selected' : '' ?>><?= htmlspecialchars($staff['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
<div class="btn-group" style="display: flex; gap: var(--space-xs); flex-wrap: wrap;">
    <button type="submit" class="btn btn-primary btn-sm">Apply Filters</button>
    <a href="reports.php" class="btn btn-ghost btn-sm">Reset</a>
</div>
                </form>
            </div>
            
            <?php if ($stats['total'] === 0): ?>
                <div class="alert" style="background: var(--surface); text-align: center; padding: 40px; border: 1px solid var(--border);">
                    <h3 style="color: var(--muted); margin-bottom: 10px;">No Data Found</h3>
                    <p class="text-muted">No tickets found for the selected filters.</p>
                </div>
            <?php else: ?>
            
                <!-- Summary Stats -->
                <div class="stats-grid">
                    <div class="stat-card" style="border-top: 4px solid var(--primary);">
                        <h3><?= $stats['total'] ?></h3>
                        <p>Total Tickets</p>
                    </div>
                    <div class="stat-card" style="border-top: 4px solid var(--success);">
                        <h3><?= $stats['resolved'] + $stats['closed'] ?></h3>
                        <p>Resolved / Closed</p>
                    </div>
                    <div class="stat-card" style="border-top: 4px solid var(--warning);">
                        <h3><?= $stats['new'] + $stats['open'] + $stats['in_progress'] ?></h3>
                        <p>Active Tickets</p>
                    </div>
                    <div class="stat-card" style="border-top: 4px solid var(--danger);">
                        <h3 class="text-danger"><?= $stats['high_urgent'] ?></h3>
                        <p>High/Urgent</p>
                    </div>
                    <div class="stat-card" style="border-top: 4px solid var(--secondary);">
                        <h3><?= $stats['resolution_rate'] ?>%</h3>
                        <p>Resolution Rate</p>
                    </div>
                    <div class="stat-card" style="border-top: 4px solid var(--muted);">
                        <h3><?= $stats['avg_resolution_time'] !== null ? $stats['avg_resolution_time'] . 'h' : 'N/A' ?></h3>
                        <p>Avg Resolution Time</p>
                    </div>
                </div>
                
                <!-- Charts Row -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 30px;">
                    <!-- Status Distribution -->
                    <div class="card" style="background: var(--surface); padding: 20px; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border);">
                        <h3 style="margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">Ticket Status</h3>
                        <?= renderBar('NEW', $dist_status['NEW'] ?? 0, $max_status, 'bg-new') ?>
                        <?= renderBar('OPEN', $dist_status['OPEN'] ?? 0, $max_status, 'bg-open') ?>
                        <?= renderBar('IN PROGRESS', $dist_status['IN PROGRESS'] ?? 0, $max_status, 'bg-progress') ?>
                        <?= renderBar('RESOLVED', $dist_status['RESOLVED'] ?? 0, $max_status, 'bg-resolved') ?>
                        <?= renderBar('CLOSED', $dist_status['CLOSED'] ?? 0, $max_status, 'bg-closed') ?>
                    </div>
                    
                    <!-- Priority Distribution -->
                    <div class="card" style="background: var(--surface); padding: 20px; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border);">
                        <h3 style="margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">Ticket Priority</h3>
                        <?= renderBar('LOW', $dist_priority['LOW'] ?? 0, $max_priority, 'bg-low') ?>
                        <?= renderBar('MEDIUM', $dist_priority['MEDIUM'] ?? 0, $max_priority, 'bg-medium') ?>
                        <?= renderBar('HIGH', $dist_priority['HIGH'] ?? 0, $max_priority, 'bg-high') ?>
                        <?= renderBar('URGENT', $dist_priority['URGENT'] ?? 0, $max_priority, 'bg-urgent') ?>
                    </div>
                    
                    <!-- Category Distribution -->
                    <div class="card" style="background: var(--surface); padding: 20px; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border);">
                        <h3 style="margin-bottom: 20px; border-bottom: 1px solid var(--border); padding-bottom: 10px;">Categories</h3>
                        <?php 
                        $cat_count = 0;
                        foreach($dist_category as $name => $count) {
                            if($cat_count++ >= 5) break; // show top 5
                            echo renderBar($name, $count, $max_category, 'bg-category');
                        }
                        if (count($dist_category) > 5) {
                            echo '<p class="text-muted" style="font-size: 0.8rem; text-align: center; margin-top: 10px;">Showing top 5 categories</p>';
                        }
                        ?>
                    </div>
                </div>
                
                <!-- Staff Workload -->
                <div class="card" style="background: var(--surface); padding: 20px; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border);">
                    <h3 style="margin-bottom: 20px;">Staff Workload & Performance</h3>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Staff Member</th>
                                    <th>Total Assigned</th>
                                    <th>Open/Assigned</th>
                                    <th>In Progress</th>
                                    <th>Resolved</th>
                                    <th>Closed</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($staff_workload as $staff): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($staff['name']) ?></strong></td>
                                    <td><?= $staff['total_assigned'] ?></td>
                                    <td><?= $staff['open_tickets'] ?></td>
                                    <td><?= $staff['in_progress'] ?></td>
                                    <td><?= $staff['resolved'] ?></td>
                                    <td><?= $staff['closed'] ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if(empty($staff_workload)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;" class="text-muted">No staff workload data available.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
            <?php endif; ?>
            
            <!-- Global User Stats (Unaffected by filters) -->
            <div style="display: flex; gap: 20px; margin-top: 30px;">
                <div class="card" style="flex: 1; background: var(--surface); padding: 20px; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); display: flex; align-items: center; gap: 20px;">
                    <div style="background: rgba(108, 140, 255, 0.1); color: var(--primary); padding: 15px; border-radius: 50%;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path></svg>
                    </div>
                    <div>
                        <h4 style="margin: 0; color: var(--text-secondary);">Total Registered Users</h4>
                        <h2 style="margin: 5px 0 0; color: var(--text);"><?= $stats['users'] ?></h2>
                    </div>
                </div>
                <div class="card" style="flex: 1; background: var(--surface); padding: 20px; border-radius: var(--radius); box-shadow: var(--shadow); border: 1px solid var(--border); display: flex; align-items: center; gap: 20px;">
                    <div style="background: rgba(42, 157, 143, 0.1); color: var(--success); padding: 15px; border-radius: 50%;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect><line x1="8" y1="21" x2="16" y2="21"></line><line x1="12" y1="17" x2="12" y2="21"></line></svg>
                    </div>
                    <div>
                        <h4 style="margin: 0; color: var(--text-secondary);">Total Support Staff</h4>
                        <h2 style="margin: 5px 0 0; color: var(--text);"><?= $stats['staff'] ?></h2>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
