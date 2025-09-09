<?php
session_start();
require $_SERVER["DOCUMENT_ROOT"] . '/connect/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/Exception.php';
require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/PHPMailer.php';
require $_SERVER["DOCUMENT_ROOT"] . '/PHPMailer-master/src/SMTP.php';

function sendEmail($toEmail, $toName, $subject, $verification_link)
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

        $mail->Body = '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1" />
            <title>Email Verification</title>
        </head>
        <body style="margin:0;padding:0;background-color:#f4f4f4;font-family:\'Open Sans\', sans-serif;">
            <div style="max-width:600px;margin:40px auto;background-color:#ffffff;padding:40px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.05);">
                <div style="text-align:center;">
                    <img src="https://d93359b3-cf53-478b-b860-2da398f4b24b.b-cdn.net/e/a9ff5014-70cc-435c-aeaf-60985fd1785b/50e04efd-d9eb-425e-a95e-baa9793ef022.png" alt="BLAZE LMS Logo" style="width:60px;" />
                    <h2 style="color:#333333;margin-top:16px;">Confirm your account</h2>
                </div>
                <div style="margin-top:30px;color:#444444;font-size:16px;line-height:1.6;">
                    <p>Hi there,</p>
                    <p>Please confirm your email address by clicking the button below. This link will expire in 3 minutes.</p>
                </div>
                <div style="margin-top:30px;text-align:center;">
                    <a href="' . $verification_link . '" target="_blank"
                        style="background-color:#0666EB;color:#ffffff;padding:14px 24px;text-decoration:none;border-radius:30px;font-weight:bold;font-size:16px;display:inline-block;">
                        Confirm Email
                    </a>
                </div>
                <div style="margin-top:40px;text-align:center;font-size:14px;color:#888888;">
                    BLAZE LMS — BatStateU Learning and Academic Zone for Excellence
                </div>
            </div>
        </body>
        </html>';

        $mail->AltBody = "Please verify your account using this link: $verification_link";
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
    $username = trim($_POST["username"]);
    $password = $_POST["password"];

    if (empty($username) || empty($password)) {
        header("Location: login?error=Username and password are required");
        exit;
    }

    $stmt = $conn->prepare("SELECT id, first_name, last_name, username, email_address, password, role, is_verified, profile_pic FROM users WHERE BINARY username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        header("Location: login?error=User not found");
        exit;
    }

    $user = $result->fetch_assoc();

    if (!password_verify($password, $user['password'])) {
        header("Location: login?error=Invalid password");
        exit;
    }

    // Not verified
    if (!$user['is_verified']) {
        date_default_timezone_set('Asia/Manila');

        $checkToken = $conn->prepare("SELECT token, expires_at FROM user_verifications WHERE user_id = ?");
        $checkToken->bind_param("i", $user['id']);
        $checkToken->execute();
        $tokenResult = $checkToken->get_result();

        $regenerate = true;

        if ($tokenResult->num_rows > 0) {
            $tokenData = $tokenResult->fetch_assoc();
            if (strtotime($tokenData['expires_at']) > time()) {
                $regenerate = false;
            } else {
                // Expired — delete
                $del = $conn->prepare("DELETE FROM user_verifications WHERE user_id = ?");
                $del->bind_param("i", $user['id']);
                if (!$del->execute()) {
                    error_log("Failed to delete expired token for user_id=" . $user['id']);
                }
            }
        }

        if ($regenerate) {
            $token = generateToken();
            $expiresAt = date("Y-m-d H:i:s", strtotime("+3 minutes"));

            $insert = $conn->prepare("INSERT INTO user_verifications (user_id, token, expires_at) VALUES (?, ?, ?)");
            $insert->bind_param("iss", $user['id'], $token, $expiresAt);
            if (!$insert->execute()) {
                error_log("Failed to insert new token for user_id=" . $user['id'] . ", Error: " . $conn->error);
                header("Location: login?error=Unable to create new verification token.");
                exit;
            }

            $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $verification_link = $scheme . "://{$_SERVER['HTTP_HOST']}/verify?token=" . $token;

            if (!sendEmail($user['email_address'], "{$user['first_name']} {$user['last_name']}", "Confirm Your BLAZE Account", $verification_link)) {
                error_log("Failed to send email to " . $user['email_address']);
                header("Location: login?error=Unable to send verification email.");
                exit;
            }
        }

        header("Location: login?error=Your account is not verified. Please check your email.");
        exit;
    }


    // Verified — Login success
    $_SESSION["user_id"] = $user["id"];
    $_SESSION["username"] = $user["username"];
    $_SESSION["role"] = $user["role"];
    $_SESSION["first_name"] = $user["first_name"];
    $_SESSION["last_name"] = $user["last_name"];
    $_SESSION["email_address"] = $user["email_address"];
    $_SESSION["profile_pic"] = $user["profile_pic"];

    $redirect = $user["role"] === "teacher" ? "/access-teacher/dashboard" : "/access-student/dashboard";
    header("Location: $redirect");
    exit;
}

header("Location: login");
exit;
