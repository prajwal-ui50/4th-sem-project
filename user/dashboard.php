<?php
// user/dashboard.php
require_once "../config/database.php";
require_once "../includes/auth.php";

requireRole('user');

$user_id = $_SESSION['user_id'];
$user = currentUser();

// Fetch statistics
$stats = [
    'total' => 0,
    'open' => 0,
    'in_progress' => 0,
    'resolved' => 0,
    'closed' => 0
];

try {
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM tickets WHERE user_id = :user_id GROUP BY status");
    $stmt->bindParam(":user_id", $user_id);
    $stmt->execute();
    
    while ($row = $stmt->fetch()) {
        $stats['total'] += $row['count'];
        if (in_array($row['status'], ['NEW', 'OPEN', 'ASSIGNED'])) {
            $stats['open'] += $row['count'];
        } elseif ($row['status'] == 'IN PROGRESS') {
            $stats['in_progress'] += $row['count'];
        } elseif ($row['status'] == 'RESOLVED') {
            $stats['resolved'] += $row['count'];
        } elseif ($row['status'] == 'CLOSED') {
            $stats['closed'] += $row['count'];
        }
    }
    
    // Fetch recent tickets
    $recent_stmt = $pdo->prepare("SELECT * FROM tickets WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 5");
    $recent_stmt->bindParam(":user_id", $user_id);
    $recent_stmt->execute();
    $recent_tickets = $recent_stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Error fetching data.");
}

$pageTitle = "Dashboard";
require_once "../includes/header.php";
?>
<div class="dashboard-wrapper">
    <?php require_once "../includes/sidebar.php"; ?>
    
    <div class="main-content">
        <?php require_once "../includes/navbar.php"; ?>
        
        <div class="dashboard-content">
            <!-- Welcome banner & Quick Action -->
            <div class="page-header" style="margin-bottom: var(--space-xl);">
                <div>
                    <h2>Welcome, <?= htmlspecialchars($user['name']) ?></h2>
                    <p class="text-muted mb-0">Track and manage your customer support requests</p>
                </div>
                <div>
                    <a href="create-ticket.php" class="btn btn-primary btn-md">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                        <span>Create New Ticket</span>
                    </a>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card" style="border-top: 4px solid var(--primary);">
                    <div class="stat-card-icon" style="background: var(--primary-10); color: var(--primary);">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>
                    </div>
                    <h3><?= $stats['total'] ?></h3>
                    <p>Total Tickets</p>
                </div>

                <div class="stat-card" style="border-top: 4px solid var(--warning);">
                    <div class="stat-card-icon" style="background: rgba(233, 196, 106, 0.15); color: #b78103;">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </div>
                    <h3><?= $stats['open'] ?></h3>
                    <p>Open / Pending</p>
                </div>

                <div class="stat-card" style="border-top: 4px solid var(--secondary);">
                    <div class="stat-card-icon" style="background: rgba(114, 9, 183, 0.12); color: var(--secondary);">
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
            </div>
            
            <!-- Recent Tickets Card -->
            <div class="card">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-size: 1.05rem; font-weight: 600;">Recent Tickets</span>
                    <a href="tickets.php" class="btn btn-ghost btn-sm">View All Tickets &rarr;</a>
                </div>
                
                <?php if (count($recent_tickets) > 0): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Ticket #</th>
                                    <th>Subject</th>
                                    <th>Status</th>
                                    <th>Priority</th>
                                    <th>Created</th>
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
                                        <td class="text-muted" style="font-size: 0.875rem;">
                                            <?= date('M d, Y', strtotime($ticket['created_at'])) ?>
                                        </td>
<td style="text-align: right;">
    <div class="btn-group" style="display: inline-flex; flex-direction: column; gap: 4px;">
        <a href="ticket-details.php?id=<?= $ticket['id'] ?>" class="btn btn-outline btn-sm" title="View Ticket Details">
            View
        </a>
        <?php if ($ticket['status'] !== 'CLOSED'): ?>
            <a href="edit-ticket.php?id=<?= $ticket['id'] ?>" class="btn btn-ghost btn-sm" style="border: 1px solid var(--border);" title="Edit Ticket">
                Edit
            </a>
        <?php endif; ?>
        <a href="delete-ticket.php?id=<?= $ticket['id'] ?>" class="btn btn-danger btn-sm" title="Delete Ticket">
            Delete
        </a>
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
                            <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/></svg>
                        </div>
                        <h4>No tickets yet</h4>
                        <p class="text-muted" style="margin-bottom: var(--space-lg);">You haven't submitted any support requests yet.</p>
                        <a href="create-ticket.php" class="btn btn-primary btn-md">Create Your First Ticket</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once "../includes/footer.php"; ?>
