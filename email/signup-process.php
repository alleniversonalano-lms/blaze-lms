<?php
// Start session
session_start();

// Include database connection
require $_SERVER["DOCUMENT_ROOT"] . '/connect/db.php';

// Include PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

// Define the sendEmail function
function sendEmail($toEmail, $toName, $subject, $body)
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.hostinger.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'no-reply@cloudkyle.com';
        $mail->Password = 'Devops@2025';
        $mail->SMTPSecure = 'ssl';
        $mail->Port = 465;

        $mail->setFrom('no-reply@cloudkyle.com', 'BLAZE');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = strip_tags($body); // Plain text version

        if ($mail->send()) {
            return true;
        } else {
            return false;
        }
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo); // Log error
        return false;
    }
}

// Check if form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Get and sanitize input
    $first_name = trim($_POST["first_name"]);
    $last_name = trim($_POST["last_name"]);
    $username = trim($_POST["username"]);
    $email = trim($_POST["email"]);
    $password = trim($_POST["password"]);
    $confirm_password = trim($_POST["confirm_password"]);
    $role = $_POST["role"];

    // Validate inputs
    if (empty($first_name) || empty($last_name) || empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['error'] = "Please fill in all fields.";
        header("Location: signup");
        exit();
    }

    if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
        header("Location: signup");
        exit();
    }

    // Check if username or email already exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $_SESSION['error'] = "Username or email already exists.";
        header("Location: signup");
        exit();
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Prepare the query to insert the new user
    $stmt = $conn->prepare("INSERT INTO users (first_name, last_name, username, email, password, roletype) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $first_name, $last_name, $username, $email, $hashed_password, $role);

    // Execute the query and check for success
    if ($stmt->execute()) {
        // Get the user_id of the newly created user
        $user_id = $stmt->insert_id;

        // Generate a unique verification token
        $verification_token = bin2hex(random_bytes(16));

        // Insert verification details into the verify table
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $stmt = $conn->prepare("INSERT INTO verifications (user_id, token, expires_at) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $user_id, $verification_token, $expires_at);

        // Execute the query to insert verification data
        if ($stmt->execute()) {
            // Generate the base URL dynamically
            $base_url = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];

            // Construct the verification link
            $verification_link = $base_url . '/send_verification.php?token=' . $verification_token;

            // Prepare the email message
            $message = "
                <p>Hi <b>$first_name</b>,</p>
                <p>Thank you for signing up! Please click the link below to verify your account:</p>
                <p><a href='$verification_link'>Verify My Account</a></p>
                <p>This link will expire in 1 hour.</p>
                <p>Regards, <br> Blaze Team</p>
            ";


            // Call the sendEmail function
            if (sendEmail($email, $first_name, $subject, $message)) {
                $_SESSION['success'] = "Account created successfully. A verification email has been sent.";
            } else {
                $_SESSION['error'] = "There was an error sending the verification email.";
            }
        } else {
            $_SESSION['error'] = "Error saving verification data.";
        }

        header("Location: login-page");
        exit();
    } else {
        $_SESSION['error'] = "There was an error creating your account. Please try again.";
        header("Location: signup");
        exit();
    }
} else {
    // If not a POST request, redirect to signup page
    header("Location: signup");
    exit();
}
