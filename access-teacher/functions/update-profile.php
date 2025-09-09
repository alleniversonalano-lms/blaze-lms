<?php
session_start();
require $_SERVER['DOCUMENT_ROOT'] . '/connect/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: /login?error=Access+denied");
    exit;
}

$user_id = $_SESSION['user_id'];
$current_password = $_POST['current_password'] ?? '';
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';
$new_username = trim($_POST['username'] ?? '');
$new_email = trim($_POST['email'] ?? '');
$new_first_name = trim($_POST['first_name'] ?? '');
$new_last_name = trim($_POST['last_name'] ?? '');

// Validate username format
if (!preg_match('/^[a-zA-Z0-9._]+$/', $new_username)) {
    header("Location: ../edit-profile-page?error=Username+must+be+alphanumeric+and+may+include+.+or+_+only");
    exit;
}

// Fetch current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user || !password_verify($current_password, $user['password'])) {
    header("Location: ../edit-profile-page?error=Incorrect+current+password");
    exit;
}

// Check if username is taken (excluding current user)
if ($new_username !== $user['username']) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->bind_param("si", $new_username, $user_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        header("Location: ../edit-profile-page?error=Username+already+taken");
        exit;
    }
}

// Check if email is taken (excluding current user)
if ($new_email !== $user['email_address']) {
    $stmt = $conn->prepare("SELECT id FROM users WHERE email_address = ? AND id != ?");
    $stmt->bind_param("si", $new_email, $user_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        header("Location: ../edit-profile-page?error=Email+already+in+use");
        exit;
    }
}

// Optional password update
$password_clause = "";
$password_param = [];
if (!empty($new_password)) {
    if ($new_password !== $confirm_password) {
        header("Location: ../edit-profile-page?error=Passwords+do+not+match");
        exit;
    }
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $password_clause = ", password = ?";
    $password_param[] = $hashed_password;
}

// Handle profile picture upload
$profile_pic_filename = $user['profile_pic'];
if (!empty($_FILES['profile_pic']['name'])) {
    $target_dir = $_SERVER['DOCUMENT_ROOT'] . "/uploads/profile_pics/";
    $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    if (!in_array($ext, $allowed)) {
        header("Location: ../edit-profile-page?error=Invalid+image+format");
        exit;
    }

    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0755, true);
    }

    $new_filename = "user_" . $user_id . "_" . time() . "." . $ext;
    $target_file = $target_dir . $new_filename;

    if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
        // Delete old image if not default
        if (!empty($user['profile_pic']) && $user['profile_pic'] !== 'default.png') {
            $old_file = $target_dir . $user['profile_pic'];
            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }
        $profile_pic_filename = $new_filename;
    } else {
        header("Location: ../edit-profile-page?error=Failed+to+upload+image");
        exit;
    }
}

// Update user
$query = "UPDATE users SET username = ?, email_address = ?, first_name = ?, last_name = ?, profile_pic = ?" . $password_clause . " WHERE id = ?";
$params = [$new_username, $new_email, $new_first_name, $new_last_name, $profile_pic_filename];
if (!empty($password_clause)) {
    $params[] = $password_param[0];
}
$params[] = $user_id;

$types = str_repeat("s", count($params) - 1) . "i";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();

// Refresh session
$_SESSION['username'] = $new_username;
$_SESSION['email_address'] = $new_email;
$_SESSION['first_name'] = $new_first_name;
$_SESSION['last_name'] = $new_last_name;
$_SESSION['profile_pic'] = $profile_pic_filename;

header("Location: ../edit-profile-page?error=Profile+updated+successfully&v=" . time());
exit;
