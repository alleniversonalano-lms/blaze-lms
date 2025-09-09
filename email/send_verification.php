<?php
// Start session
session_start();

// Include database connection
require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

// Check if the token is passed in the URL
if (isset($_GET['token']) && !empty($_GET['token'])) {
    // Get the token from the URL
    $token = $_GET['token'];

    // Validate the token
    $stmt = $conn->prepare("SELECT users.id, users.email, users.first_name, users.is_verified, verifications.expires_at 
                            FROM users 
                            INNER JOIN verifications ON users.id = verifications.user_id 
                            WHERE verifications.token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Token is valid, fetch the user data
        $stmt->bind_result($user_id, $email, $first_name, $is_verified, $expires_at);
        $stmt->fetch();

        // Check if the user is already verified
        if ($is_verified == 1) {
            $_SESSION['success'] = "Your account is already verified!";
            header("Location: /login-page");
            exit();
        }

        // Check if the token has expired
        if (strtotime($expires_at) < time()) {
            $_SESSION['error'] = "The verification link has expired. Please request a new verification email.";
            header("Location: /signup");
            exit();
        }

        // Update the user's status to verified
        $stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

        // Show success message
        $_SESSION['success'] = "Your account has been successfully verified! You can now log in.";

        // Redirect to the login page
        header("Location: /login-page");
        exit();
    } else {
        // Token is invalid
        $_SESSION['error'] = "Invalid verification request.";
        header("Location: /signup");
        exit();
    }
} else {
    // No token provided
    $_SESSION['error'] = "Invalid verification request.";
    header("Location: /signup");
    exit();
}
