<?php
require $_SERVER["DOCUMENT_ROOT"] . '/connect/db.php';

// Check if token exists and is valid
if (!isset($_GET['token'])) {
    header("Location: login?error=" . urlencode("Invalid password reset link"));
    exit();
}

$token = $_GET['token'];
$stmt = $conn->prepare("SELECT email, expiry FROM password_resets WHERE token = ? AND used = 0");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: login?error=" . urlencode("Invalid or expired reset link"));
    exit();
}

$row = $result->fetch_assoc();
if (strtotime($row['expiry']) < time()) {
    header("Location: login?error=" . urlencode("Reset link has expired"));
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" href="img/blaze-logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Reset Password - BLAZE</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body,
        html {
            height: 100%;
            font-family: Arial, sans-serif;
        }

        .landing-page {
            background: url('img/hero-background-2.png') no-repeat center center/cover;
            background-attachment: fixed;
            min-height: 100vh;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: brightness(0.7);
            padding: 40px 16px;
            box-sizing: border-box;
        }

        .reset-password-card {
            background: linear-gradient(to bottom right,
                    rgba(183, 28, 28, 0.85),
                    rgba(211, 47, 47, 0.75));
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            color: white;
            width: 100%;
            max-width: 400px;
            text-align: left;
            animation: fadeIn 1s ease-out;
        }

        .reset-password-card h2 {
            text-align: center;
            margin-bottom: 25px;
            font-size: 2rem;
            font-weight: bold;
        }

        .reset-password-card label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
        }

        .reset-password-card input[type="password"] {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: none;
            margin-bottom: 20px;
            font-size: 1rem;
        }

        .password-container {
            position: relative;
            margin-bottom: 20px;
        }

        .password-container input[type="password"],
        .password-container input[type="text"] {
            margin-bottom: 0;
            padding-right: 40px;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.3s ease;
        }

        .password-toggle:hover {
            opacity: 1;
        }

        .password-toggle i {
            font-size: 18px;
            color: #666;
        }

        .reset-password-card button {
            width: 100%;
            background: linear-gradient(to right, #d32f2f, #b71c1c);
            color: white;
            padding: 12px;
            border: none;
            border-radius: 25px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .reset-password-card button:hover {
            background: linear-gradient(to right, #b71c1c, #d32f2f);
            transform: scale(1.03);
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        #snackbar {
            visibility: hidden;
            min-width: 280px;
            max-width: 90%;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 8px;
            padding: 14px 20px;
            position: fixed;
            z-index: 9999;
            right: 30px;
            bottom: 30px;
            font-size: 0.95rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            transition: all 0.5s ease;
            opacity: 0;
            transform: translateY(20px);
        }

        #snackbar.show {
            visibility: visible;
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>

<body>
    <div class="landing-page">
        <div id="snackbar">This is a sample snackbar message.</div>

        <form class="reset-password-card" action="reset-password" method="POST">
            <h2>Reset Password</h2>

            <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">

            <label for="password">New Password</label>
            <div class="password-container">
                <input type="password" id="password" name="password" required />
                <span class="password-toggle" onclick="togglePassword('password')">
                    <i class="fas fa-eye-slash" id="password-toggle-icon"></i>
                </span>
            </div>

            <label for="confirm_password">Confirm Password</label>
            <div class="password-container">
                <input type="password" id="confirm_password" name="confirm_password" required />
                <span class="password-toggle" onclick="togglePassword('confirm_password')">
                    <i class="fas fa-eye-slash" id="confirm-password-toggle-icon"></i>
                </span>
            </div>

            <button type="submit">Reset Password</button>
        </form>
    </div>

    <script>
        function togglePassword(inputId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(inputId + '-toggle-icon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            }
        }

        function showSnackbar(message = 'This is a sample snackbar message.', duration = 3000) {
            const snackbar = document.getElementById('snackbar');
            snackbar.textContent = message;
            snackbar.classList.add('show');

            setTimeout(() => {
                snackbar.classList.remove('show');
            }, duration);
        }

        document.addEventListener("DOMContentLoaded", function() {
            const params = new URLSearchParams(window.location.search);
            if (params.has("error")) {
                const error = decodeURIComponent(params.get("error"));
                showSnackbar(error);

                const url = new URL(window.location);
                url.searchParams.delete('error');
                window.history.replaceState({}, document.title, url.pathname);
            }
        });
    </script>
</body>

</html>
