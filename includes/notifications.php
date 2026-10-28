<?php
// includes/notifications.php
require_once __DIR__ . '/EmailService.php';

/**
 * Creates a notification for a user.
 * 
 * @param PDO $pdo Database connection
 * @param int $userId ID of the user to notify
 * @param string $title Notification title
 * @param string $message Notification content
 * @param string $type Notification type (info, success, warning, danger)
 * @param int|null $ticketId Associated ticket ID (optional)
 * @return bool True on success, false on failure
 */
function createNotification($pdo, $userId, $title, $message, $type = 'info', $ticketId = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, ticket_id, title, message, type) VALUES (?, ?, ?, ?, ?)");
        $result = $stmt->execute([$userId, $ticketId, $title, $message, $type]);
        
        if ($result) {
            // Attempt to send an email notification as well
            try {
                $userStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                $userStmt->execute([$userId]);
                $user = $userStmt->fetch();
                
                if ($user) {
                    $emailHtml = "<p>Hello " . htmlspecialchars($user['name']) . ",</p>";
                    $emailHtml .= "<p>" . nl2br(htmlspecialchars($message)) . "</p>";
                    
                    if ($ticketId) {
                        $emailHtml .= "<p><strong>Note:</strong> You can log in to your dashboard to view the full details of this ticket.</p>";
                    }
                    
                    EmailService::send($user['email'], $user['name'], $title, $emailHtml);
                }
            } catch (Exception $e) {
                error_log("Failed to send email notification: " . $e->getMessage());
            }
        }
        
        return $result;
    } catch (PDOException $e) {
        // Log error silently, do not break application flow
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Gets the number of unread notifications for a user.
 * 
 * @param PDO $pdo Database connection
 * @param int $userId ID of the user
 * @return int Number of unread notifications
 */
function getUnreadNotificationCount($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

/**
 * Retrieves notifications for a user.
 * 
 * @param PDO $pdo Database connection
 * @param int $userId ID of the user
 * @param int $limit Max number of notifications to retrieve
 * @return array List of notifications
 */
function getUserNotifications($pdo, $userId, $limit = 50) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
        $stmt->bindParam(1, $userId, PDO::PARAM_INT);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Marks a specific notification as read.
 * 
 * @param PDO $pdo Database connection
 * @param int $notificationId ID of the notification
 * @param int $userId ID of the user (for security validation)
 * @return bool True on success
 */
function markNotificationAsRead($pdo, $notificationId, $userId) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE id = ? AND user_id = ?");
        return $stmt->execute([$notificationId, $userId]);
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Marks all notifications for a user as read.
 * 
 * @param PDO $pdo Database connection
 * @param int $userId ID of the user
 * @return bool True on success
 */
function markAllNotificationsAsRead($pdo, $userId) {
    try {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = TRUE WHERE user_id = ? AND is_read = FALSE");
        return $stmt->execute([$userId]);
    } catch (PDOException $e) {
        return false;
    }
}
?>
