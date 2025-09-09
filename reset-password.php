<?php
require $_SERVER["DOCUMENT_ROOT"] . '/connect/db.php';


if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($password !== $confirm_password) {
        header("Location: reset-password?token=$token&error=" . urlencode("Passwords do not match"));
        exit();
    }
    
    // Verify token and check expiry
    $stmt = $conn->prepare("SELECT email, expiry FROM password_resets WHERE token = ? AND used = 0");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $email = $row['email'];
        $expiry = strtotime($row['expiry']);
        
        if (time() > $expiry) {
            header("Location: forgot-password-page?error=" . urlencode("Password reset link has expired"));
            exit();
        }
        
        // Update password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email_address = ?");
        $stmt->bind_param("ss", $hashed_password, $email);
        
        if ($stmt->execute()) {
            // Mark token as used
            $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            
            header("Location: login?error=" . urlencode("Password has been successfully reset"));
            exit();
        } else {
            header("Location: reset-password?token=$token&error=" . urlencode("Error updating password"));
            exit();
        }
    } else {
        header("Location: forgot-password-page?error=" . urlencode("Invalid or expired reset link"));
        exit();
    }
}
?>
