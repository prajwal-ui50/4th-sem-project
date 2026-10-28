<?php
// admin/dashboard.php
require_once "../config/database.php";
require_once "../includes/auth.php";

requireRole('admin');

$user = currentUser();

$stats = [
    'users' => 0,
    'staff' => 0,
    'total_tickets' => 0,
    'new' => 0,
    'open' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'closed' => 0,
    'high_priority' => 0
];

try {
    // User stats
    $u_stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
    while ($row = $u_stmt->fetch()) {
        if ($row['role'] == 'user') $stats['users'] += $row['count'];
        if ($row['role'] == 'staff') $stats['staff'] += $row['count'];
    }
    
    // Ticket stats
    $t_stmt = $pdo->query("SELECT status, priority, COUNT(*) as count FROM tickets GROUP BY status, priority");
    while ($row = $t_stmt->fetch()) {
        $stats['total_tickets'] += $row['count'];
        
        $status = $row['status'];
        if ($status == 'NEW') $stats['new'] += $row['count'];
        if ($status == 'OPEN') $stats['open'] += $row['count'];
        if ($status == 'IN PROGRESS') $stats['in_progress'] += $row['count'];
        if ($status == 'RESOLVED') $stats['resolved'] += $row['count'];
        if ($status == 'CLOSED') $stats['closed'] += $row['count'];
        
        if (in_array($row['priority'], ['HIGH', 'URGENT']) && !in_array($status, ['RESOLVED', 'CLOSED'])) {
            $stats['high_priority'] += $row['count'];
        }
    }
    
    // Recent Tickets
    $recent_stmt = $pdo->query("SELECT t.*, u.name as client_name, s.name as assigned_name FROM tickets t LEFT JOIN users u ON t.user_id = u.id LEFT JOIN users s ON t.assigned_to = s.id ORDER BY t.created_at DESC LIMIT 5");
    $recent_tickets = $recent_stmt->fetchAll();
    
    // Recent Activities (History)
    $act_stmt = $pdo->query("SELECT h.*, t.ticket_number, u.name as user_name FROM ticket_history h JOIN tickets t ON h.ticket_id = t.id JOIN users u ON h.user_id = u.id ORDER BY h.created_at DESC LIMIT 5");
    $recent_activities = $act_stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Error fetching data.");
}

$pageTitle = "Admin Dashboard";
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <div class="page-header">
                <div>
                    <h2>System Administration Overview</h2>
                    <p class="text-muted mb-0">System metrics, recent support activity, and global platform status</p>
                </div>
                <div style="display: flex; gap: var(--space-sm);">
                    <a href="reports.php" class="btn btn-outline btn-sm">View Reports</a>
                    <a href="users.php" class="btn btn-primary btn-sm">Manage Users</a>
                </div>
            </div>
            
            <!-- 6 Stats Cards Grid -->
            <div class="stats-grid">
                <div class="stat-card" style="border-top: 4px solid var(--primary);">
                    <div class="stat-card-icon" style="background: var(--primary-10); color: var(--primary);">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/></svg>
                    </div>
                    <h3><?= $stats['total_tickets'] ?></h3>
                    <p>Total Tickets</p>
                </div>

                <div class="stat-card" style="border-top: 4px solid var(--success);">
                    <div class="stat-card-icon" style="background: rgba(42, 157, 143, 0.12); color: var(--success);">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                    </div>
                    <h3><?= $stats['new'] + $stats['open'] ?></h3>
                    <p>New / Open</p>
                </div>

                <div class="stat-card" style="border-top: 4px solid var(--warning);">
                    <div class="stat-card-icon" style="background: rgba(233, 196, 106, 0.15); color: #b78103;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/></svg>
                    </div>
                    <h3><?= $stats['in_progress'] ?></h3>
                    <p>In Progress</p>
                </div>

                <div class="stat-card" style="border-top: 4px solid var(--danger);">
                    <div class="stat-card-icon" style="background: rgba(230, 57, 70, 0.12); color: var(--danger);">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"/></svg>
                    </div>
                    <h3 style="color: var(--danger);"><?= $stats['high_priority'] ?></h3>
                    <p>Urgent / High Priority</p>
                </div>

                <div class="stat-card" style="border-top: 4px solid var(--muted);">
                    <div class="stat-card-icon" style="background: var(--surface-secondary); color: var(--text-secondary);">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
                    </div>
                    <h3><?= $stats['users'] ?></h3>
                    <p>Registered Clients</p>
                </div>

                <div class="stat-card" style="border-top: 4px solid var(--secondary);">
                    <div class="stat-card-icon" style="background: rgba(114, 9, 183, 0.12); color: var(--secondary);">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </div>
                    <h3><?= $stats['staff'] ?></h3>
                    <p>Support Agents</p>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--space-lg);">
                <!-- Recent Tickets -->
                <div class="card">
                    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 600;">Recent Tickets</span>
                        <a href="tickets.php" class="btn btn-ghost btn-sm">All Tickets &rarr;</a>
                    </div>
                    
                    <?php if (count($recent_tickets) > 0): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Ticket #</th>
                                        <th>Subject</th>
                                        <th>Client</th>
                                        <th>Assigned</th>
                                        <th>Status</th>
                                        <th style="text-align: right;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_tickets as $ticket): ?>
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
                                                <?= $ticket['assigned_name'] ? htmlspecialchars($ticket['assigned_name']) : '<span class="text-muted">Unassigned</span>' ?>
                                            </td>
                                            <td>
                                                <?php 
                                                    $statusClass = strtolower(str_replace(' ', '', $ticket['status']));
                                                    echo "<span class='badge badge-{$statusClass}'>" . htmlspecialchars($ticket['status']) . "</span>";
                                                ?>
                                            </td>
<td style="text-align: right;">
    <div class="btn-group" style="display: inline-flex; flex-direction: column; gap: 4px;">
        <a href="ticket-details.php?id=<?= $ticket['id'] ?>" class="btn btn-outline btn-sm">Manage</a>
    </div>
</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <p class="text-muted">No recent tickets logged yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Activities -->
                <div class="card">
                    <div class="card-header">
                        <span style="font-weight: 600;">Recent Audit Trail</span>
                    </div>
                    <div class="card-body">
                        <?php if (count($recent_activities) > 0): ?>
                            <ul class="history-list">
                                <?php foreach ($recent_activities as $act): ?>
                                    <li>
                                        <div style="margin-bottom: 3px;">
                                            <strong><?= htmlspecialchars($act['user_name']) ?></strong>: 
                                            <?= htmlspecialchars($act['action']) ?>
                                            on <a href="ticket-details.php?id=<?= $act['ticket_id'] ?>" style="font-weight: 600;"><?= htmlspecialchars($act['ticket_number']) ?></a>
                                        </div>
                                        <div class="history-date"><?= date('M d, Y h:i A', strtotime($act['created_at'])) ?></div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="text-muted" style="text-align: center; padding: var(--space-md) 0;">No recent activities logged.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
