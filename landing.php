<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
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
            /* ✅ Makes background fixed */
            min-height: 100vh;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            text-align: center;
            backdrop-filter: brightness(0.7);
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

        /* ✅ Fade-in on load for each element */
        .logo,
        h2,
        p,
        .btn {
            opacity: 0;
            animation: fadeIn 1s ease-out forwards;
        }

        .logo {
            width: 190px;
            height: auto;
            margin-bottom: 20px;
            animation-delay: 0.2s;
        }

        h2 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            font-weight: bold;
            text-shadow: 2px 2px 6px rgba(0, 0, 0, 0.6);
            animation-delay: 0.4s;
        }

        p {
            font-size: 1.2rem;
            margin-bottom: 30px;
            text-shadow: 1px 1px 4px rgba(0, 0, 0, 0.5);
            animation-delay: 0.6s;
        }

        .btn {
            background: linear-gradient(to right, #d32f2f, #b71c1c);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 30px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            animation-delay: 0.8s;
        }

        .btn:hover {
            background: linear-gradient(to right, #b71c1c, #d32f2f);
            transform: scale(1.05);
            box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
        }

    </style>
</head>

<body>

    <div class="landing-page">
        <img src="img/blaze-logo.png" alt="Logo" class="logo" />
        <h2>Welcome to BLAZE</h2>
        <p>BatStateU Learning and Academic Zone for Excellence</p>
        <a href="index" class="btn">Get Started</a>
    </div>

</body>

</html>