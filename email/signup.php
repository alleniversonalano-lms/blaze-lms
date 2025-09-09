<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Sign Up | BLAZE</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body,
        html {
            margin: 0;
            padding: 0;
            height: 100%;
            font-family: 'Segoe UI', sans-serif;
            background: url('img/hero-background-2.png') no-repeat center center/cover;
        }

        .fade-in {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 1.2s ease forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .signup-card {
            background: linear-gradient(135deg, rgba(255, 0, 0, 0.75), rgba(139, 0, 0, 0.75));
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.6);
            color: white;
            width: 100%;
            max-width: 400px;
            margin: 20px;
            transition: all 0.3s ease;
        }

        .form-control {
            border-radius: 30px;
            padding: 0.6rem 1rem;
        }

        .signup-btn {
            background: linear-gradient(135deg, #ff3c3c, #8b0000);
            color: white;
            border: none;
            border-radius: 30px;
            font-weight: bold;
            padding: 0.6rem 1.5rem;
            transition: background 0.3s ease, transform 0.2s ease;
            font-size: 0.9rem;
        }

        .signup-btn:hover {
            background: linear-gradient(135deg, #e60000, #5c0000);
            transform: scale(1.05);
        }

        .yellow-link {
            color: yellow;
            text-decoration: none;
            font-weight: 500;
        }

        .yellow-link:hover {
            text-decoration: underline;
        }

        .toggle-btn {
            border-radius: 50px;
            padding: 0.4rem 1.5rem;
            background: linear-gradient(135deg, rgba(255, 0, 0, 0.75), rgba(139, 0, 0, 0.75));
            color: white;
            font-weight: 600;
            transition: background 0.3s ease;
            font-size: 0.85rem;
        }

        .toggle-btn:hover {
            background: linear-gradient(135deg, #e60000, #5c0000);
        }

        /* Adjusting layout for small devices */
        @media (max-width: 576px) {
            .signup-card {
                padding: 1.5rem;
                width: 90%;
            }

            .signup-card h2 {
                font-size: 1.5rem;
            }

            .form-label,
            .toggle-btn,
            .signup-btn {
                font-size: 0.85rem;
            }

            .form-control {
                padding: 0.5rem 1rem;
            }
        }

        /* Styling for the toggle switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            border-radius: 50%;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
        }

        input:checked+.slider {
            background-color: #ff3c3c;
        }

        input:checked+.slider:before {
            transform: translateX(26px);
        }

        /* Styling for the role selection */
        .role-selection {
            display: flex;
            justify-content: left;
            align-items: center;
            margin-bottom: 1rem;
        }

        .role-selection input {
            margin-right: 0.5rem;
        }
    </style>
</head>

<body class="d-flex justify-content-center align-items-center vh-100">
    <a href="home" class="btn btn-warning text-danger position-absolute m-3 d-flex align-items-center gap-2" style="top: 0; left: 0; border-radius: 50px; font-weight: 600;">
        <i class="bi bi-arrow-left-circle-fill"></i> Back
    </a>

    <div class="signup-card text-center fade-in">
        <h2 class="fw-bold mb-4">Create an Account</h2>
        <form action="signup-process" method="POST" id="signup-form">

            <!-- Name Fields - Two Column Layout -->
            <div class="row mb-3 text-start">
                <div class="col-md-6">
                    <label for="first-name" class="form-label text-white" style="font-size: 0.875rem;">First Name</label>
                    <input type="text" name="first_name" id="first-name" class="form-control" placeholder="Enter your First Name" required style="font-size: 0.75rem;">
                </div>
                <div class="col-md-6">
                    <label for="last-name" class="form-label text-white" style="font-size: 0.875rem;">Last Name</label>
                    <input type="text" name="last_name" id="last-name" class="form-control" placeholder="Enter your Last Name" required style="font-size: 0.75rem;">
                </div>
            </div>

            <!-- Email, Username - Three Column Layout -->
            <div class="row mb-3 text-start">
                <div class="col-md-6">
                    <label for="username" class="form-label text-white" style="font-size: 0.875rem;">Username</label>
                    <input type="text" name="username" id="username" class="form-control" placeholder="Choose a Username" required style="font-size: 0.75rem;">
                </div>

                <div class="col-md-6">
                    <label for="email" class="form-label text-white" style="font-size: 0.875rem;">Email Address</label>
                    <input type="email" name="email" id="email" class="form-control" placeholder="Enter your Email" required style="font-size: 0.75rem;">
                </div>
            </div>

            <div class="mb-3 text-start">
                <label for="password" class="form-label fw-semibold text-white">Password</label>
                <input type="password" name="password" id="password" class="form-control" placeholder="Enter your Password" required>
            </div>
            <!-- Confirm Password -->
            <div class="mb-3 text-start">
                <label for="confirm-password" class="form-label fw-semibold text-white">Confirm Password</label>
                <input type="password" name="confirm_password" id="confirm-password" class="form-control" placeholder="Confirm your Password" required>
            </div>

            <!-- Role Selection Radio Buttons -->
            <div class="role-selection">
                <label class="text-white me-2">Choose Role:</label>
                <div>
                    <input type="radio" id="role-student" name="role" value="student" checked>
                    <label for="role-student" class="text-white">Student</label>
                </div>
                <div class="ms-3">
                    <input type="radio" id="role-teacher" name="role" value="teacher">
                    <label for="role-teacher" class="text-white">Teacher</label>
                </div>
            </div>

            <!-- Terms and Conditions -->
            <div class="mb-3 form-check text-start">
                <input type="checkbox" class="form-check-input" id="terms" required>
                <label class="form-check-label text-white" for="terms">
                    I agree to the <a href="terms-and-conditions" class="yellow-link" target="_blank">terms and conditions</a>
                </label>
            </div>

            <!-- Sign Up Button -->
            <button type="submit" class="btn signup-btn w-100">Sign Up</button>
        </form>

        <div class="mt-3">
            <p class="mb-0">Already have an Account? <a href="login-page" class="yellow-link">Sign in here</a></p>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast-container position-fixed top-0 end-0 p-3" id="toast-container">
        <div class="toast" id="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">BLAZE</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body" id="toast-message">
                Signup Successful!
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleRole() {
            const role = document.getElementById("role-toggle").checked ? "teacher" : "student";
            document.getElementById("role").value = role;
            document.getElementById("role-label").textContent = role.charAt(0).toUpperCase() + role.slice(1);
        }

        // Show toast after signup (This can be triggered after form submission)
        function showToast(message) {
            const toastMessage = document.getElementById("toast-message");
            toastMessage.textContent = message;
            const toast = new bootstrap.Toast(document.getElementById("toast"));
            toast.show();
        }

        // Form validation before submission
        document.querySelector("form").onsubmit = function(event) {
            const password = document.getElementById("password").value;
            const confirmPassword = document.getElementById("confirm-password").value;
            if (password !== confirmPassword) {
                event.preventDefault();
                alert("Passwords do not match!");
            } else {
                // Assuming signup is successful, you can call showToast()
                showToast("Signup Successful!");
            }
        };
    </script>
</body>

</html>