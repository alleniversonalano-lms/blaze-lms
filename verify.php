<?php
session_start();
require $_SERVER["DOCUMENT_ROOT"] . '/connect/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/Exception.php';
require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/PHPMailer.php';
require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/SMTP.php';

function generateToken($length = 64)
{
    return bin2hex(random_bytes($length / 2));
}

function sendVerificationEmail($email, $name, $token)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@blaze-lms.com';
        $mail->Password = '*****';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;

        $mail->setFrom('no-reply@blaze-lms.com', 'BLAZE');
        $mail->addAddress($email, $name);
        $mail->isHTML(true);
        $mail->Subject = "New Verification Link - BLAZE";

        $link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}/verify?token=$token";

        $mail->Body = '
        <html>
        <body>
            <h2>Verify your account</h2>
            <p>Your previous verification link has expired. Click below to verify again:</p>
            <a href="' . $link . '">Verify My Account</a><br>
            <p>This link will expire in 1 hour.</p>
        </body>
        </html>';
        $mail->AltBody = "Verify here: $link";

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email error: " . $mail->ErrorInfo);
        return false;
    }
}

if (!isset($_GET['token']) || empty($_GET['token'])) {
    header("Location: login?error=Invalid or missing verification token.");
    exit;
}

$token = $_GET['token'];
date_default_timezone_set("Asia/Manila");

// ✅ Check if token is valid and not expired
$stmt = $conn->prepare("SELECT user_id FROM user_verifications WHERE token = ? AND expires_at > NOW() LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    $row = $result->fetch_assoc();
    $user_id = $row['user_id'];

    $update = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
    $update->bind_param("i", $user_id);
    $update->execute();

    $delete = $conn->prepare("DELETE FROM user_verifications WHERE token = ?");
    $delete->bind_param("s", $token);
    $delete->execute();

    header("Location: login?error=Your email has been successfully verified.");
    exit;
}

// ❌ If not valid, check if token exists but is expired
$stmt = $conn->prepare("SELECT uv.user_id, u.email_address, u.first_name, u.last_name 
    FROM user_verifications uv 
    JOIN users u ON uv.user_id = u.id 
    WHERE uv.token = ? AND uv.expires_at <= NOW() LIMIT 1");
$stmt->bind_param("s", $token);
$stmt->execute();
$expiredResult = $stmt->get_result();

if ($expiredResult->num_rows === 1) {
    $row = $expiredResult->fetch_assoc();
    $user_id = $row['user_id'];
    $email = $row['email_address'];
    $name = $row['first_name'] . ' ' . $row['last_name'];

    // Delete expired token
    $del = $conn->prepare("DELETE FROM user_verifications WHERE user_id = ?");
    $del->bind_param("i", $user_id);
    $del->execute();

    // Generate and insert new token
    $newToken = generateToken();
    $expiresAt = date("Y-m-d H:i:s", strtotime("+60 minutes"));

    $insert = $conn->prepare("INSERT INTO user_verifications (user_id, token, expires_at) VALUES (?, ?, ?)");
    $insert->bind_param("iss", $user_id, $newToken, $expiresAt);

    if (!$insert->execute()) {
        error_log("DB Insert failed: " . $conn->error);
        header("Location: login?error=Unable to generate a new token. Please try again.");
        exit;
    }

    if (!sendVerificationEmail($email, $name, $newToken)) {
        header("Location: login?error=Failed to send new verification email. Please try again later.");
        exit;
    }

    header("Location: login?error=Your token expired. A new verification link has been sent.");
    exit;
}

// ❌ Token is invalid and does not exist
header("Location: login?error=Invalid or expired verification token.");
exit;

