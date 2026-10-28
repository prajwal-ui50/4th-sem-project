<?php
// includes/EmailService.php

class EmailService {
    
    /**
     * Sends an HTML email using PHP's built-in mail() function.
     * In a production environment, this could be swapped out for PHPMailer.
     *
     * @param string $to Recipient email address
     * @param string $toName Recipient name
     * @param string $subject Email subject
     * @param string $messageHtml HTML body of the email
     * @return bool True if mail was accepted for delivery, false otherwise
     */
    public static function send($to, $toName, $subject, $messageHtml) {
        // Only send if MAIL_ENABLED is true
        if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
            error_log("Email sending bypassed for $to (MAIL_ENABLED is false). Subject: $subject");
            return true;
        }

        try {
            // Validate email address safely
            if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                error_log("EmailService Error: Invalid recipient address: $to");
                return false;
            }

            $fromEmail = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@example.com';
            $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Ticket System';
            
            // Set Headers for HTML Email
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= "From: $fromName <$fromEmail>" . "\r\n";
            $headers .= "Reply-To: $fromEmail" . "\r\n";
            
            // Create responsive HTML template
            $htmlTemplate = self::getTemplate($subject, $messageHtml);
            
            // Attempt to send email
            $result = mail($to, $subject, $htmlTemplate, $headers);
            
            if (!$result) {
                error_log("EmailService Error: mail() function failed to send email to $to");
            }
            return $result;
            
        } catch (Exception $e) {
            // Prevent email failure from breaking the application
            error_log("EmailService Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Wraps the email content in a professional HTML template.
     * 
     * @param string $subject The email subject
     * @param string $content The main content HTML
     * @return string Full HTML document
     */
    private static function getTemplate($subject, $content) {
        $appName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Ticket System';
        $year = date('Y');
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>" . htmlspecialchars($subject) . "</title>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; color: #333333; }
                .email-container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
                .email-header { background-color: #4361ee; color: #ffffff; padding: 25px 30px; text-align: center; }
                .email-header h2 { margin: 0; font-size: 24px; font-weight: 600; }
                .email-body { padding: 30px; line-height: 1.6; font-size: 16px; }
                .email-footer { background-color: #f8f9fa; padding: 20px; text-align: center; color: #888888; font-size: 13px; border-top: 1px solid #eeeeee; }
                .btn { display: inline-block; padding: 12px 24px; background-color: #4361ee; color: #ffffff; text-decoration: none; border-radius: 4px; font-weight: 500; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='email-header'>
                    <h2>" . htmlspecialchars($appName) . "</h2>
                </div>
                <div class='email-body'>
                    $content
                </div>
                <div class='email-footer'>
                    <p>This is an automated message. Please do not reply directly to this email.</p>
                    <p>&copy; $year " . htmlspecialchars($appName) . ". All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>";
    }
}
?>
