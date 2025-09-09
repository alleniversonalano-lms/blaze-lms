<?php
// --- BACKEND SECTION: Only runs when AJAX is called ---
if (isset($_GET['get_server_time'])) {
    date_default_timezone_set('UTC'); // Hostinger's time is in UTC
    echo json_encode([
        'utc' => date('Y-m-d H:i:s'),
        'ph' => date('Y-m-d H:i:s', strtotime('+8 hours'))
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Deployment in Progress</title>
  <style>
    * {
      margin: 0; padding: 0; box-sizing: border-box;
    }
    body {
      font-family: Arial, sans-serif;
      display: flex;
      flex-direction: column;
      align-items: center;
      min-height: 100vh;
      background-color: #f4f4f9;
      text-align: center;
      padding-bottom: 100px;
    }
    .circle {
      border: 8px solid #f3f3f3;
      border-top: 8px solid #3498db;
      border-radius: 50%;
      width: 60px;
      height: 60px;
      animation: spin 2s linear infinite;
      margin-top: 80px;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
    .loading-text {
      font-size: 18px;
      color: #333;
      margin-top: 20px;
      min-height: 48px;
      transition: opacity 1s ease;
    }
    .fade-out {
      opacity: 0;
    }
    .blink {
      animation: blink-animation 1s steps(2, start) infinite;
      color: #e74c3c;
      font-weight: bold;
    }
    @keyframes blink-animation {
      to { visibility: hidden; }
    }
    .progress-bar-container {
      width: 80%;
      height: 15px;
      background-color: #ddd;
      margin-top: 20px;
      border-radius: 10px;
    }
    .progress-bar {
      height: 100%;
      width: 0;
      background-color: #3498db;
      border-radius: 10px;
      transition: width 0.5s ease;
    }
    .server-time {
      font-size: 14px;
      color: #777;
      margin-top: 12px;
    }

    /* Game Styles */
    #mini-game {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: #2c3e50;
      color: #fff;
      padding: 10px 20px;
      font-size: 14px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      height: 80px;
      z-index: 10;
    }
    #dot {
      position: absolute;
      width: 30px;
      height: 30px;
      background-color: #e74c3c;
      border-radius: 50%;
      cursor: pointer;
      z-index: 20;
      transition: top 0.3s ease, left 0.3s ease;
    }
    #score {
      font-weight: bold;
    }
  </style>
</head>
<body>

  <div class="circle"></div>

  <div id="loading-text" class="loading-text">
    Deployment is currently on-going. We're working hard to bring the latest features and improvements. Please be patient, as it will be completed as soon as possible.
  </div>

  <div class="progress-bar-container">
    <div id="progress-bar" class="progress-bar"></div>
  </div>

  <div id="server-time" class="server-time">
    Checking server time...
  </div>

  <!-- Beep sound when message changes -->
  <audio id="beep-sound" preload="auto">
    <source src="https://actions.google.com/sounds/v1/alarms/beep_short.ogg" type="audio/ogg">
  </audio>

  <!-- Background music -->
  <audio id="waiting-music" loop>
    <source src="https://www.bensound.com/bensound-music/bensound-slowmotion.mp3" type="audio/mpeg">
  </audio>

  <!-- Mini Game -->
  <div id="dot"></div>
  <div id="mini-game">
    <div>🎮 Mini Game: Catch the Dot!</div>
    <div>Score: <span id="score">0</span></div>
  </div>

  <script>
    const progressBar = document.getElementById('progress-bar');
    const loadingText = document.getElementById('loading-text');
    const beepSound = document.getElementById('beep-sound');
    const serverTime = document.getElementById('server-time');
    const waitingMusic = document.getElementById('waiting-music');

    const messages = [
      {
        text: "Deployment is currently on-going. We're working hard to bring the latest features and improvements. Please be patient, as it will be completed as soon as possible.",
        blink: false
      },
      {
        text: "Files are too big, it will take some time to update. Hang tight!",
        blink: true
      }
    ];

    let msgIndex = 0;

    setInterval(() => {
      loadingText.classList.add('fade-out');
      setTimeout(() => {
        msgIndex = (msgIndex + 1) % messages.length;
        loadingText.textContent = messages[msgIndex].text;
        loadingText.classList.toggle('blink', messages[msgIndex].blink);
        beepSound.play().catch(() => {});
        loadingText.classList.remove('fade-out');
      }, 1000);
    }, 6000);

    let progress = 0;
    const maxProgress = 20;
    const progressInterval = setInterval(() => {
      if (progress < maxProgress) {
        progress += 1;
        progressBar.style.width = progress + '%';
      } else {
        clearInterval(progressInterval);
      }
    }, 200);

    function fetchServerTime() {
      fetch('?get_server_time=1')
        .then(res => res.json())
        .then(data => {
          serverTime.textContent = `Server Time (UTC): ${data.utc} | Philippine Time: ${data.ph}`;
        })
        .catch(() => {
          serverTime.textContent = "Unable to fetch server time.";
        });
    }

    fetchServerTime();
    setInterval(fetchServerTime, 10000);

    setTimeout(() => {
      waitingMusic.play().catch(err => {
        console.warn("Autoplay blocked by browser:", err);
      });
    }, 3000);

    document.addEventListener('click', () => {
      waitingMusic.play().catch(() => {});
    }, { once: true });

    // Mini Game Logic
    const dot = document.getElementById('dot');
    const scoreDisplay = document.getElementById('score');
    let score = 0;

    function moveDot() {
      const maxX = window.innerWidth - 40;
      const maxY = window.innerHeight - 140;
      const x = Math.random() * maxX;
      const y = Math.random() * maxY;
      dot.style.left = `${x}px`;
      dot.style.top = `${y}px`;
    }

    dot.addEventListener('click', () => {
      score++;
      scoreDisplay.textContent = score;
      moveDot();
    });

    setInterval(moveDot, 1500); // Move every 1.5s
    moveDot(); // Initial position
  </script>

</body>
</html>
