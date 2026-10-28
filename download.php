<?php
// download.php
require_once "config/database.php";
require_once "includes/auth.php";

$user = currentUser();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("Invalid request.");
}

$attachment_id = $_GET['id'];

try {
    $stmt = $pdo->prepare("SELECT * FROM ticket_attachments WHERE id = ?");
    $stmt->execute([$attachment_id]);
    $attachment = $stmt->fetch();
    
    if (!$attachment) {
        die("Attachment not found.");
    }
    
    $ticket_id = $attachment['ticket_id'];
    
    // Authorization Check
    $authorized = false;
    if ($user['role'] === 'admin') {
        $authorized = true;
    } else if ($user['role'] === 'staff') {
        // Staff can download if they are assigned to the ticket
        $t_stmt = $pdo->prepare("SELECT id FROM tickets WHERE id = ? AND assigned_to = ?");
        $t_stmt->execute([$ticket_id, $user['id']]);
        if ($t_stmt->rowCount() > 0) $authorized = true;
    } else {
        // User can download if they own the ticket
        $t_stmt = $pdo->prepare("SELECT id FROM tickets WHERE id = ? AND user_id = ?");
        $t_stmt->execute([$ticket_id, $user['id']]);
        if ($t_stmt->rowCount() > 0) $authorized = true;
    }
    
    if (!$authorized) {
        die("You are not authorized to access this file.");
    }
    
    $filepath = __DIR__ . "/uploads/" . $attachment['file_name'];
    
    if (!file_exists($filepath)) {
        die("File not found on server.");
    }
    
    // Send headers for download
    header('Content-Description: File Transfer');
    header('Content-Type: ' . $attachment['file_type']);
    header('Content-Disposition: attachment; filename="' . basename($attachment['original_name']) . '"');
    header('Expires: 0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');
    header('Content-Length: ' . filesize($filepath));
    
    // Clear output buffer and send file
    flush();
    readfile($filepath);
    exit;
    
} catch (PDOException $e) {
    die("Database error.");
}
?>
