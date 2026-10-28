<?php
// staff/dashboard.php
require_once "../config/database.php";
require_once "../includes/auth.php";

requireRole('staff');

$user_id = $_SESSION['user_id'];
$user = currentUser();

$stats = [
    'assigned' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'high_priority' => 0
];

try {
    $stmt = $pdo->prepare("SELECT status, priority, COUNT(*) as count FROM tickets WHERE assigned_to = :user_id GROUP BY status, priority");
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        if (in_array($row['status'], ['ASSIGNED', 'OPEN'])) {
            $stats['assigned'] += $row['count'];
        } elseif ($row['status'] == 'IN PROGRESS') {
            $stats['in_progress'] += $row['count'];
        } elseif ($row['status'] == 'RESOLVED') {
            $stats['resolved'] += $row['count'];
        }
        
        if (in_array($row['priority'], ['HIGH', 'URGENT']) && !in_array($row['status'], ['RESOLVED', 'CLOSED'])) {
            $stats['high_priority'] += $row['count'];
        }
    }
    
    $recent_stmt = $pdo->prepare("SELECT t.*, u.name as creator_name FROM tickets t JOIN users u ON t.user_id = u.id WHERE t.assigned_to = :user_id AND t.status != 'CLOSED' ORDER BY t.updated_at DESC LIMIT 5");
    $recent_stmt->bindParam(":user_id", $user_id);
    $recent_stmt->execute();
    $recent_tickets = $recent_stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Error fetching data.");
}

$pageTitle = "Staff Dashboard";
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <div class="page-header">
                <div>
                    <h2>Support Staff Console</h2>
                    <p class="text-muted mb-0">Overview of your ticket queue and pending customer resolutions</p>
                </div>
                <a href="tickets.php" class="btn btn-primary btn-md">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/></svg>
                    <span>View Ticket Queue</span>
                </a>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card" style="border-top: 4px solid var(--primary);">
                    <div class="stat-card-icon" style="background: var(--primary-10); color: var(--primary);">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/></svg>
                    </div>
                    <h3><?= $stats['assigned'] ?></h3>
                    <p>New / Assigned</p>
                </div>

                <div class="stat-card" style="border-top: 4px solid var(--warning);">
                    <div class="stat-card-icon" style="background: rgba(233, 196, 106, 0.15); color: #b78103;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
                    </div>
                    <h3><?= $stats['in_progress'] ?></h3>
                    <p>In Progress</p>
                </div>

                <div class="stat-card" style="border-top: 4px solid var(--success);">
                    <div class="stat-card-icon" style="background: rgba(42, 157, 143, 0.12); color: var(--success);">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <h3><?= $stats['resolved'] ?></h3>
                    <p>Resolved</p>
                </div>

                <div class="stat-card" style="border-top: 4px solid var(--danger);">
                    <div class="stat-card-icon" style="background: rgba(230, 57, 70, 0.12); color: var(--danger);">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="7.86 2 16.14 2 22 7.86 22 16.14 16.14 22 7.86 22 2 16.14 2 7.86 7.86 2"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    </div>
                    <h3 style="color: var(--danger);"><?= $stats['high_priority'] ?></h3>
                    <p>High Priority (Active)</p>
                </div>
            </div>
            
            <!-- Active Assigned Tickets Card -->
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 600;">Active Assigned Tickets</span>
                    <a href="tickets.php" class="btn btn-ghost btn-sm">Manage All &rarr;</a>
                </div>
                
                <?php if (count($recent_tickets) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Ticket #</th>
                                    <th>Subject</th>
                                    <th>Client</th>
                                    <th>Status</th>
                                    <th>Priority</th>
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
                                            <span style="color: var(--text-secondary); font-size: 0.9rem;"><?= htmlspecialchars($ticket['creator_name']) ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                                $statusClass = strtolower(str_replace(' ', '', $ticket['status']));
                                                echo "<span class='badge badge-{$statusClass}'>" . htmlspecialchars($ticket['status']) . "</span>";
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $priorityClass = strtolower($ticket['priority']);
                                                echo "<span class='badge badge-{$priorityClass}'>" . htmlspecialchars($ticket['priority']) . "</span>";
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
                        <div style="width: 64px; height: 64px; border-radius: var(--radius-full); background: var(--surface-secondary); display: flex; align-items: center; justify-content: center; margin: 0 auto var(--space-md); color: var(--muted);">
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        </div>
                        <h4>No active tickets assigned</h4>
                        <p class="text-muted">You have no active pending tickets in your queue. Check the unassigned queue or check back later.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
