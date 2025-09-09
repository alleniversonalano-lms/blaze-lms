<?php
require $_SERVER["DOCUMENT_ROOT"] . '/connect/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/Exception.php';
require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/PHPMailer.php';
require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/SMTP.php';

function sendNotificationEmail($toEmail, $toName, $subject, $content, $courseCode, $courseTitle) {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@blaze-lms.com';
        $mail->Password = 'Blazelms@2025';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;

        $mail->setFrom('no-reply@blaze-lms.com', 'BLAZE');
        $mail->addAddress($toEmail, $toName);
        $mail->isHTML(true);
        $mail->Subject = $subject;

        $mail->Body = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title>' . $subject . '</title>
        </head>
        <body style="margin:0;padding:0;background-color:#f4f4f4;font-family:\'Open Sans\', sans-serif;">
            <div style="max-width:600px;margin:40px auto;background-color:#ffffff;padding:40px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.05);">
                <div style="text-align:center;">
                    <img src="https://d93359b3-cf53-478b-b860-2da398f4b24b.b-cdn.net/e/a9ff5014-70cc-435c-aeaf-60985fd1785b/50e04efd-d9eb-425e-a95e-baa9793ef022.png" alt="BLAZE LMS Logo" style="width:60px;" />
                    <h2 style="color:#333333;margin-top:16px;">' . $courseCode . ': ' . $courseTitle . '</h2>
                </div>
                <div style="margin-top:30px;color:#444444;font-size:16px;line-height:1.6;">
                    <p>Hi ' . $toName . ',</p>
                    ' . $content . '
                </div>
                <div style="margin-top:40px;text-align:center;font-size:14px;color:#888888;">
                    BLAZE LMS — BatStateU Learning and Academic Zone for Excellence
                </div>
            </div>
        </body>
        </html>';

        $mail->AltBody = strip_tags($content);
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email error: " . $mail->ErrorInfo);
        return false;
    }
}

function notifyEnrolledUsers($courseId, $subject, $content, $courseCode, $courseTitle) {
    global $conn;
    
    if (empty($courseId)) {
        error_log("Error: courseId is empty in notifyEnrolledUsers");
        return false;
    }
    
    // Get all enrolled users in the course
    $stmt = $conn->prepare("
        SELECT u.id, u.email_address, u.first_name, u.last_name 
        FROM users u 
        JOIN course_enrollments ce ON u.id = ce.student_id 
        WHERE ce.course_id = ?
    ");
    
    if (!$stmt) {
        error_log("Failed to prepare statement: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $courseId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $success = true;
    
    while ($user = $result->fetch_assoc()) {
        $userName = $user['first_name'] . ' ' . $user['last_name'];
        if (!sendNotificationEmail($user['email_address'], $userName, $subject, $content, $courseCode, $courseTitle)) {
            error_log("Failed to send notification email to: " . $user['email_address']);
            $success = false;
        }
    }
    
    return $success;
}

function notifyFromCron($courseId, $subject, $content, $courseCode, $courseTitle, $logFunction = null) {
    global $conn;
    
    // Default log function if none provided
    if (!$logFunction) {
        $logFunction = function($message, $type = 'INFO') {
            error_log("CRON: $type - $message");
        };
    }
    
    $logFunction("Starting cron notification for course $courseCode", 'INFO');
    
    if (empty($courseId)) {
        $logFunction("Error: courseId is empty", 'ERROR');
        return false;
    }
    
    // Get all enrolled users in the course
    $stmt = $conn->prepare("
        SELECT u.id, u.email_address, u.first_name, u.last_name 
        FROM users u 
        JOIN course_enrollments ce ON u.id = ce.student_id 
        WHERE ce.course_id = ?
    ");
    
    if (!$stmt) {
        $logFunction("Failed to prepare statement: " . $conn->error, 'ERROR');
        return false;
    }
    
    try {
        $stmt->bind_param("i", $courseId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $success = true;
        $sent_count = 0;
        $failed_count = 0;
        
        while ($user = $result->fetch_assoc()) {
            $logFunction("Attempting to send email to: {$user['email_address']}", 'DEBUG');
            
            try {
                if (sendNotificationEmail(
                    $user['email_address'],
                    $user['first_name'] . ' ' . $user['last_name'],
                    $subject,
                    $content,
                    $courseCode,
                    $courseTitle
                )) {
                    $sent_count++;
                    $logFunction("Successfully sent email to: {$user['email_address']}", 'SUCCESS');
                } else {
                    $failed_count++;
                    $success = false;
                    $logFunction("Failed to send email to: {$user['email_address']}", 'WARNING');
                }
            } catch (Exception $e) {
                $failed_count++;
                $success = false;
                $logFunction("Exception sending email to {$user['email_address']}: " . $e->getMessage(), 'ERROR');
            }
        }
        
        $logFunction("Notification summary - Sent: $sent_count, Failed: $failed_count", 'INFO');
        return $success;
        
    } catch (Exception $e) {
        $logFunction("Database error: " . $e->getMessage(), 'ERROR');
        return false;
    } finally {
        $stmt->close();
    }
}
?>
