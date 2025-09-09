<?php
function logUserHistory($action, $section) {
    if (!isset($_SESSION['user_history'])) {
        $_SESSION['user_history'] = [];
    }

    $_SESSION['user_history'][] = [
        'action' => $action,
        'section' => $section,
        'timestamp' => time()
    ];

    // Optional: Keep only the last 20 entries
    if (count($_SESSION['user_history']) > 20) {
        array_shift($_SESSION['user_history']);
    }
}
