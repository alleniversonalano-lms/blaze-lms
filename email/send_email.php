<?php
date_default_timezone_set('Asia/Manila'); // Change to your correct timezone
session_start();
include $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';
require $_SERVER['DOCUMENT_ROOT'] . '/send_email'; // Use reusable email function

if (!isset($_GET['token']) || empty($_GET['token'])) {
    $_SESSION['error'] = "Invalid request!";
    header("Location: /login-page");
    exit();
}

$token = $_GET['token'];

// Validate token
$stmt = $conn->prepare("SELECT users.user_id, users.email, users.first_name, users.is_verified, verify.expires_at 
                        FROM users 
                        INNER JOIN verify ON users.user_id = verify.user_id 
                        WHERE verify.token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($user_id, $email, $first_name, $is_verified, $expires_at);
    $stmt->fetch();

    if ($is_verified == 1) {
        $_SESSION['success'] = "Your account is already verified!";
        header("Location: /login-page");
        exit();
    }

    // Prevent resending if the token has expired
    if (strtotime($expires_at) < time()) {
        $_SESSION['error'] = "The verification link has expired. Please request a new verification email.";
        header("Location: /login-page");
        exit();
    }

    // ✅ Prevent resending if an email was sent within the last 5 minutes
    $stmt = $conn->prepare("SELECT time_stamp FROM verifications WHERE user_id = ? ORDER BY time_stamp DESC LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($last_sent);
    $stmt->fetch();

    if ($last_sent && (strtotime($last_sent) > strtotime('-5 minutes'))) {
        $_SESSION['error'] = "A verification email was already sent recently. Please wait a few minutes before requesting again.";
        header("Location: /login-page");
        exit();
    }

    // Generate a new verification token
    $new_verification_code = bin2hex(random_bytes(16));
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Expiry in 1 hour

    // Update the verification table
    $stmt = $conn->prepare("UPDATE verifications SET token = ?, expires_at = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $new_verification_code, $expires_at, $user_id);
    $stmt->execute();

    // Send verification email
    $subject = "Resend Verification - Activate Your Account";
    $message = "
        <p>Hi <b>$first_name</b>,</p>
        <p>You requested to resend your verification email. Click the link below to verify your account:</p>
        <p><a href='http://blaze-demo.cloudkyle.com/send_verification.php?token=$new_verification_code'>Verify My Account</a></p>
        <p>This link will expire in 1 hour.</p>
        <p>Regards, <br> BLAZE Team</p>
    ";

    if (sendEmail($email, $first_name, $subject, $message)) {
        $_SESSION['success'] = "A new verification email has been sent.";
    } else {
        $_SESSION['error'] = "Failed to send the verification email. Try again later.";
    }
} else {
    $_SESSION['error'] = "Invalid verification request!";
}

header("Location: /login-page");
exit();
