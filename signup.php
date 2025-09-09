<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" href="img/blaze-logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Sign Up - BLAZE</title>
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
            /* gives breathing space */
            box-sizing: border-box;
        }

        .signup-card {
            background: linear-gradient(to bottom right,
                    rgba(183, 28, 28, 0.85),
                    rgba(211, 47, 47, 0.75));
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
            color: white;
            width: 100%;
            max-width: 450px;
            text-align: left;
            animation: fadeIn 1s ease-out;
        }

        .signup-card h2 {
            text-align: center;
            margin-bottom: 25px;
            font-size: 2rem;
            font-weight: bold;
        }

        .signup-card label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            font-size: small;
        }

        .signup-card input[type="text"],
        .signup-card input[type="email"],
        .signup-card input[type="password"] {
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
            display: block;
            width: 100%;
        }

        .password-container input[type="password"],
        .password-container input[type="text"] {
            margin-bottom: 0;
            padding-right: 40px;
            width: 100%;
            display: block;
        }

        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.3s ease;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle:hover {
            opacity: 1;
        }

        .password-toggle i {
            font-size: 16px;
            color: #666;
            line-height: 1;
        }

        .signup-card .radio-group,
        .signup-card .checkbox-group {
            margin-bottom: 20px;
        }

        .signup-card .radio-group label,
        .signup-card .checkbox-group label {
            font-weight: normal;
        }

        .signup-card button {
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

        .signup-card button:hover {
            background: linear-gradient(to right, #b71c1c, #d32f2f);
            transform: scale(1.03);
        }

        .signup-card .signin-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.95rem;
        }

        .signup-card .signin-link a {
            color: yellow;
            text-decoration: none;
            font-weight: bold;
        }

        .signup-card .signin-link a:hover {
            text-decoration: underline;
        }

        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(0, 0, 0, 0.4);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            font-weight: bold;
            font-size: 0.95rem;
            backdrop-filter: blur(2px);
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: yellow;
            transform: scale(1.05);
            text-decoration: none;
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

        .form-row {
            display: flex;
            gap: 16px;
        }

        .form-col {
            flex: 1;
        }

        .form-col label,
        .form-col input {
            width: 100%;
            display: block;
        }

        .signup-card input[type="text"],
        .signup-card input[type="email"] {
            margin-bottom: 20px;
        }

        @media (max-width: 600px) {
            .form-row {
                flex-direction: column;
            }
        }

        .radio-group {
            margin: 20px 0;
        }

        .radio-options {
            display: flex;
            gap: 40px;
            margin-top: 8px;
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
        }

        .checkbox-group a {
            color: yellow;
            text-decoration: underline;
        }

        .checkbox-group label {
            font-size: 0.95rem;
        }

        .termscondition {
            text-decoration: none !important;
            display: inline-block;
            transition: all 0.3s ease;
        }

        .termscondition:hover {
            transform: scale(1.05);

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
            /* Position it from the right */
            bottom: 30px;
            /* Keep it at the bottom */
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

        <!-- Back Button -->
        <a href="index" class="back-btn">← Back</a>

        <!-- Snackbar Container -->
        <div id="snackbar">This is a sample snackbar message.</div>

        <!-- Signup Form -->
        <form class="signup-card" action="process_signup" method="POST">
            <h2>Create an Account</h2>

            <!-- First Name and Last Name -->
            <div class="form-row">
                <div class="form-col">
                    <label for="fname">First Name</label>
                    <input type="text" id="fname" name="fname" placeholder="Enter First Name" style="font-size: small;" maxlength="25" required>
                </div>
                <div class="form-col">
                    <label for="lname">Last Name</label>
                    <input type="text" id="lname" name="lname" placeholder="Enter Last Name" style="font-size: small;" maxlength="25" required>
                </div>
            </div>

            <!-- Username and Email -->
            <div class="form-row">
                <div class="form-col">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" pattern="^[a-zA-Z0-9_.]+$"
                        title="Alphanumeric only. You can use _ and . (no spaces)" placeholder="Enter Username" style="font-size: small;" maxlength="25" required>
                </div>
                <div class="form-col">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter Email" style="font-size: small;" maxlength="60" required>
                </div>
            </div>

            <!-- Password and Confirm -->
            <label for="password">Password</label>
            <div class="password-container">
                <input type="password" id="password" name="password" 
                       placeholder="Enter Password" style="font-size: small;" 
                       maxlength="30" minlength="8" required>
                <span class="password-toggle" onclick="togglePassword(this)">
                    <i class="fas fa-eye-slash"></i>
                </span>
            </div>
            
            <label for="confirm_password">Confirm Password</label>
            <div class="password-container">
                <input type="password" id="confirm_password" name="confirm_password" 
                       placeholder="Enter Confirm Password" style="font-size: small;" 
                       maxlength="30" minlength="8" required>
                <span class="password-toggle" onclick="togglePassword(this)">
                    <i class="fas fa-eye-slash"></i>
                </span>
            </div>

            <!-- Role -->
            <div class="radio-group">
                <label>Role:</label>
                <div class="radio-options">
                    <div class="radio-option">
                        <input type="radio" id="student" name="role" value="student" required>
                        <label for="student">Student</label>
                    </div>
                    <div class="radio-option">
                        <input type="radio" id="teacher" name="role" value="teacher" required>
                        <label for="teacher">Teacher</label>
                    </div>
                </div>
            </div>

            <!-- Terms -->
            <div class="checkbox-group">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">I agree to the <a href="terms-condition" class="termscondition" target="_blank">terms and conditions</a></label>
            </div>

            <!-- Submit -->
            <button type="submit">Sign Up</button>

            <!-- Already have an account -->
            <div class="signin-link">
                Already have an account? <a href="login">Sign in here</a>
            </div>
        </form>
    </div>

    <script>
        function togglePassword(toggleBtn) {
            const passwordInput = toggleBtn.previousElementSibling; // the input before the icon
            const icon = toggleBtn.querySelector("i");
        
            if (passwordInput.type === "password") {
                passwordInput.type = "text";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            } else {
                passwordInput.type = "password";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
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

        // Trigger snackbar if error param exists
        document.addEventListener("DOMContentLoaded", function() {
            const params = new URLSearchParams(window.location.search);
            if (params.has("error")) {
                const error = decodeURIComponent(params.get("error"));
                showSnackbar(error);

                // Remove error from URL after displaying
                if (history.replaceState) {
                    const cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                    history.replaceState(null, "", cleanUrl);
                }
            }
        });
    </script>

</body>

</html>