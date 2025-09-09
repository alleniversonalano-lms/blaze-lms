<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" type="image/png" href="img/blaze-logo.png">

    <title>Welcome to BLAZE</title>
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
            background: url('img/hero-background.png') no-repeat center center/cover;
            background-attachment: fixed;
            height: 100vh;
            width: 100%;
            display: flex;
            flex-direction: column;
            color: white;
            text-align: center;
            position: relative;
            backdrop-filter: brightness(0.7);
        }

        .navbar {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            /* aligns to top of logo */
            padding: 20px 40px;
            position: absolute;
            top: 0;
            left: 0;
            z-index: 2;
            min-height: 120px;
            /* prevent overlap if needed */
        }

        .nav-left {
            position: relative;
        }

        .nav-logo {
            height: 290px;
            width: auto;
            position: absolute;
            top: -110px;
            /* adjust upward */
            left: 0;
        }

        .nav-right {
            display: flex;
            gap: 15px;
            align-items: center;
            padding-top: 10px;
        }

        .nav-btn {
            display: inline-block;
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

        .nav-btn:hover {
            transform: scale(1.05);
            background: rgba(255, 255, 255, 0.2);
            color: yellow;
            transform: scale(1.05);
            text-decoration: none;
        }

        .main-content {
            margin-top: auto;
            margin-bottom: auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 0 20px;
        }

        @keyframes fadeIn {
            0% {
                opacity: 0;
                transform: translateY(20px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade {
            opacity: 0;
            animation: fadeIn 1s ease-out forwards;
        }

        .fade.delay-1 {
            animation-delay: 0.3s;
        }

        .fade.delay-2 {
            animation-delay: 0.5s;
        }

        .fade.delay-3 {
            animation-delay: 0.7s;
        }

        h1 {
            font-size: 2.8rem;
            font-weight: bold;
            margin-bottom: 20px;
            text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.6);
        }

        p {
            font-size: 1.2rem;
            text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.5);
        }
    </style>
</head>

<body>
    <div class="landing-page">
        <!-- Navbar -->
        <div class="navbar">
            <div class="nav-left">
                <a href="landing">
                    <img src="img/left-logo.png" alt="Logo" class="nav-logo" />
                </a>
            </div>

            <div class="nav-right">
                <a href="login.php" class="nav-btn">Login</a>
                <a href="signup.php" class="nav-btn">Sign Up</a>
            </div>

        </div>

        <!-- Centered Main Content -->
        <div class="main-content">
            <h1 class="fade delay-2">EMPOWERING MINDS, IGNITING FUTURES</h1>
            <p class="fade delay-3">BatStateU Learning and Academic Zone for Excellence</p>
        </div>
    </div>
</body>

</html>