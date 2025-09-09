<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" href="img/blaze-logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>Forgot Password - BLAZE</title>
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

        .forgot-password-card {
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

        .forgot-password-card h2 {
            text-align: center;
            margin-bottom: 25px;
            font-size: 2rem;
            font-weight: bold;
        }

        .forgot-password-card p {
            text-align: center;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }

        .forgot-password-card label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
        }

        .forgot-password-card input[type="email"] {
            width: 100%;
            padding: 10px;
            border-radius: 8px;
            border: none;
            margin-bottom: 20px;
            font-size: 1rem;
        }

        .forgot-password-card button {
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

        .forgot-password-card button:hover {
            background: linear-gradient(to right, #b71c1c, #d32f2f);
            transform: scale(1.03);
        }

        .back-to-login {
            text-align: center;
            margin-top: 20px;
            font-size: 0.95rem;
        }

        .back-to-login a {
            color: yellow;
            text-decoration: none;
            font-weight: bold;
        }

        .back-to-login a:hover {
            text-decoration: underline;
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
        <a href="login" class="back-btn">← Back to Login</a>

        <div id="snackbar">This is a sample snackbar message.</div>

        <form class="forgot-password-card" action="forgot-password" method="POST">
            <h2>Forgot Password</h2>
            <p>Enter your email address and we'll send you a link to reset your password.</p>

            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" required />

            <button type="submit">Send Reset Link</button>

            <div class="back-to-login">
                Remember your password? <a href="login">Login here</a>
            </div>
        </form>
    </div>

    <script>
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
