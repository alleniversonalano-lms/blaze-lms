<?php
session_start();
require $_SERVER["DOCUMENT_ROOT"] . '/connect/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/Exception.php';
require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/PHPMailer.php';
require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/SMTP.php';

function sendEmail($toEmail, $toName, $subject, $body)
{
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
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body);

        return $mail->send();
    } catch (Exception $e) {
        error_log("Email error: " . $mail->ErrorInfo);
        return false;
    }
}

function generateToken($length = 64)
{
    return bin2hex(random_bytes($length / 2));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $first_name = trim($_POST["fname"]);
    $last_name = trim($_POST["lname"]);
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = $_POST["password"];
    $confirm_password = $_POST["confirm_password"];
    $role = $_POST["role"];

    if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password) || empty($role)) {
        header("Location: signup?error=" . urlencode("All fields are required."));
        exit;
    }

    if ($password !== $confirm_password) {
        header("Location: signup?error=" . urlencode("Passwords do not match."));
        exit;
    }

    $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email_address = ?");
    $checkStmt->bind_param("ss", $username, $email);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        header("Location: signup?error=" . urlencode("Username or email already exists."));
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, username, email_address, password, role, is_verified) VALUES (?, ?, ?, ?, ?, ?, 0)");
    $stmt->bind_param("ssssss", $first_name, $last_name, $username, $email, $hashed_password, $role);

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $token = generateToken();
        date_default_timezone_set('Asia/Manila');

        $expires_at = date("Y-m-d H:i:s", strtotime("+60 minutes"));

        $vstmt = $conn->prepare("INSERT INTO user_verifications (user_id, token, expires_at) VALUES (?, ?, ?)");
        $vstmt->bind_param("iss", $user_id, $token, $expires_at);
        $vstmt->execute();

        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $base_url = $scheme . '://' . $_SERVER['HTTP_HOST'];
        $verification_link = $base_url . "/verify?token=" . urlencode($token);

        // HTML email with card style
        $emailBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Email Verification</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f4f4;font-family:'Open Sans', sans-serif;">
  <div style="max-width:600px;margin:40px auto;background-color:#ffffff;padding:40px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.05);">
    <div style="text-align:center;">
      <img src="https://d93359b3-cf53-478b-b860-2da398f4b24b.b-cdn.net/e/a9ff5014-70cc-435c-aeaf-60985fd1785b/50e04efd-d9eb-425e-a95e-baa9793ef022.png" alt="BLAZE LMS Logo" style="width:60px;" />
      <h2 style="color:#333333;margin-top:16px;">Confirm your account</h2>
    </div>
    <div style="margin-top:30px;color:#444444;font-size:16px;line-height:1.6;">
      <p>Hi <strong>$first_name</strong>,</p>
      <p>Please confirm your email address by clicking the button below. This link will expire in 1 Hour.</p>
    </div>
    <div style="margin-top:30px;text-align:center;">
      <a href="$verification_link" target="_blank"
         style="background-color:#0666EB;color:#ffffff;padding:14px 24px;text-decoration:none;border-radius:30px;font-weight:bold;font-size:16px;display:inline-block;">
        Confirm Email
      </a>
    </div>
    <div style="margin-top:40px;text-align:center;font-size:14px;color:#888888;">
      BLAZE LMS — BatStateU Learning and Academic Zone for Excellence
    </div>
  </div>
</body>
</html>
HTML;

        if (sendEmail($email, "$first_name $last_name", "Confirm Your BLAZE Account", $emailBody)) {
            $_SESSION['success'] = "Registration successful! Please check your email to verify your account.";
            header("Location: login?signup=success");
            exit;
        } else {
            $_SESSION['error'] = "Failed to send verification email.";
            header("Location: signup");
            exit;
        }
    } else {
        $_SESSION['error'] = "Registration failed. Please try again.";
        header("Location: signup");
        exit;
    }
} else {
    header("Location: signup");
    exit;
}
