<?php
// includes/Attachment.php
require_once __DIR__ . "/../config/database.php";

class Attachment {
    private $pdo;
    private $uploadDir = __DIR__ . "/../uploads/";
    private $maxSize = 5242880; // 5MB

    public function __construct($pdo) {
        $this->pdo = $pdo;
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
            // Create .htaccess to prevent direct access/execution
            file_put_contents($this->uploadDir . '.htaccess', "Order Deny,Allow\nDeny from all");
        }
    }

    public function upload($file, $ticket_id, $user_id) {
        if (!isset($file['error']) || is_array($file['error'])) {
            return ['error' => 'Invalid parameters.'];
        }

        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                return ['error' => 'No file sent.'];
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return ['error' => 'Exceeded filesize limit.'];
            default:
                return ['error' => 'Unknown errors.'];
        }

        if ($file['size'] > $this->maxSize) {
            return ['error' => 'Exceeded filesize limit (5MB max).'];
        }

        $original_name = basename($file['name']);
        $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

        $allowedExtensions = [
            'jpg' => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png' => ['image/png'],
            'gif' => ['image/gif'],
            'webp' => ['image/webp'],
            'pdf' => ['application/pdf'],
            'doc' => ['application/msword'],
            'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
            'xls' => ['application/vnd.ms-excel'],
            'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
            'txt' => ['text/plain']
        ];

        if (!array_key_exists($ext, $allowedExtensions)) {
            return ['error' => 'Invalid file format (unsupported extension: ' . htmlspecialchars($ext) . ').'];
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        
        if (!in_array($mime, $allowedExtensions[$ext])) {
            return ['error' => 'Invalid file format (MIME type mismatch).'];
        }

        $safe_name = preg_replace("/[^a-zA-Z0-9.-]/", "_", $original_name);
        // Add random hash to prevent guessing/overwriting
        $filename = sprintf('%s_%s.%s',
            pathinfo($safe_name, PATHINFO_FILENAME),
            bin2hex(random_bytes(8)),
            $ext
        );
        
        $filepath = $this->uploadDir . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return ['error' => 'Failed to move uploaded file.'];
        }

        // Save to DB
        try {
            $stmt = $this->pdo->prepare("INSERT INTO ticket_attachments (ticket_id, user_id, file_name, original_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$ticket_id, $user_id, $filename, $original_name, 'uploads/' . $filename, $mime, $file['size']])) {
                return ['success' => true, 'attachment_id' => $this->pdo->lastInsertId()];
            } else {
                // Rollback file if db insert fails
                unlink($filepath);
                return ['error' => 'Database error.'];
            }
        } catch (PDOException $e) {
            unlink($filepath);
            return ['error' => 'DB Error: ' . $e->getMessage()];
        }
    }

    public function getAttachments($ticket_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM ticket_attachments WHERE ticket_id = ? ORDER BY created_at ASC");
        $stmt->execute([$ticket_id]);
        return $stmt->fetchAll();
    }
    
    public function getAttachmentById($attachment_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM ticket_attachments WHERE id = ?");
        $stmt->execute([$attachment_id]);
        return $stmt->fetch();
    }

    public function deleteByTicket($ticket_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT file_path, file_name FROM ticket_attachments WHERE ticket_id = ?");
            $stmt->execute([$ticket_id]);
            $attachments = $stmt->fetchAll();
            foreach ($attachments as $att) {
                $filepath = $this->uploadDir . $att['file_name'];
                if (file_exists($filepath)) {
                    @unlink($filepath);
                }
            }
            $del_stmt = $this->pdo->prepare("DELETE FROM ticket_attachments WHERE ticket_id = ?");
            $del_stmt->execute([$ticket_id]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function deleteAttachment($attachment_id, $user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM ticket_attachments WHERE id = ? AND user_id = ?");
            $stmt->execute([$attachment_id, $user_id]);
            $att = $stmt->fetch();
            if ($att) {
                $filepath = $this->uploadDir . $att['file_name'];
                if (file_exists($filepath)) {
                    @unlink($filepath);
                }
                $del = $this->pdo->prepare("DELETE FROM ticket_attachments WHERE id = ?");
                $del->execute([$attachment_id]);
                return true;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }
}
?>
