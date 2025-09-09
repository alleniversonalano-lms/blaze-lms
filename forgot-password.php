<?php
require $_SERVER["DOCUMENT_ROOT"] . '/connect/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/Exception.php';
require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/PHPMailer.php';
require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/SMTP.php';

function sendEmail($toEmail, $toName, $subject, $reset_link) {
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
            <title>Password Reset</title>
        </head>
        <body style="margin:0;padding:0;background-color:#f4f4f4;font-family:\'Open Sans\', sans-serif;">
            <div style="max-width:600px;margin:40px auto;background-color:#ffffff;padding:40px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.05);">
                <div style="text-align:center;">
                    <img src="https://d93359b3-cf53-478b-b860-2da398f4b24b.b-cdn.net/e/a9ff5014-70cc-435c-aeaf-60985fd1785b/50e04efd-d9eb-425e-a95e-baa9793ef022.png" alt="BLAZE LMS Logo" style="width:60px;" />
                    <h2 style="color:#333333;margin-top:16px;">Reset Your Password</h2>
                </div>
                <div style="margin-top:30px;color:#444444;font-size:16px;line-height:1.6;">
                    <p>Hi there,</p>
                    <p>We received a request to reset your password. Click the button below to create a new password. This link will expire in 1 hour.</p>
                </div>
                <div style="margin-top:30px;text-align:center;">
                    <a href="' . $reset_link . '" target="_blank"
                        style="background-color:#0666EB;color:#ffffff;padding:14px 24px;text-decoration:none;border-radius:30px;font-weight:bold;font-size:16px;display:inline-block;">
                        Reset Password
                    </a>
                </div>
                <div style="margin-top:40px;text-align:center;font-size:14px;color:#888888;">
                    BLAZE LMS — BatStateU Learning and Academic Zone for Excellence
                </div>
            </div>
        </body>
        </html>';

        $mail->AltBody = "Reset your password using this link: $reset_link";
        return $mail->send();
    } catch (Exception $e) {
        error_log("Email error: " . $mail->ErrorInfo);
        return false;
    }
}

function generateToken($length = 64) {
    return bin2hex(random_bytes($length / 2));
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $_POST['email'];
    
    // Check if email exists in database
    $stmt = $conn->prepare("SELECT id, first_name, last_name FROM users WHERE email_address = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $userData = $result->fetch_assoc();
        $userName = $userData['first_name'] . ' ' . $userData['last_name'];
        
        // Generate unique token
        $token = generateToken();
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store token in database
        $stmt = $conn->prepare("INSERT INTO password_resets (email, token, expiry) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $email, $token, $expiry);
        
        if ($stmt->execute()) {
            $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $resetLink = $scheme . "://{$_SERVER['HTTP_HOST']}/reset-password-page?token=" . $token;
            
            if (sendEmail($email, $userName, "Reset Your BLAZE Password", $resetLink)) {
                header("Location: login?error=" . urlencode("Password reset link has been sent to your email"));
                exit();
            } else {
                // Delete the token if email fails
                $stmt = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                $stmt->bind_param("s", $token);
                $stmt->execute();
                
                header("Location: forgot-password-page?error=" . urlencode("Failed to send reset email. Please try again."));
                exit();
            }
        } else {
            header("Location: forgot-password-page?error=" . urlencode("Database error. Please try again."));
            exit();
        }
    } else {
        header("Location: forgot-password-page?error=" . urlencode("Email address not found"));
        exit();
    }
}
?>
