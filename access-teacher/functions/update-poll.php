<?php
session_start();
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

$poll_id = (int)($_POST['poll_id'] ?? 0);
$question = trim($_POST['question'] ?? '');
$options = $_POST['options'] ?? [];

if ($poll_id > 0 && !empty($question)) {
    // Update question
    $stmt = $conn->prepare("UPDATE polls SET question = ? WHERE id = ?");
    $stmt->bind_param("si", $question, $poll_id);
    $stmt->execute();

    // Fetch current option IDs
    $existingOptions = [];
    $result = $conn->prepare("SELECT id FROM poll_options WHERE poll_id = ? ORDER BY id ASC");
    $result->bind_param("i", $poll_id);
    $result->execute();
    $res = $result->get_result();
    while ($row = $res->fetch_assoc()) {
        $existingOptions[] = $row['id'];
    }

    foreach ($options as $index => $option_text) {
        $option_text = trim($option_text);
        if (empty($option_text)) continue;

        if (isset($existingOptions[$index])) {
            // Update existing option
            $opt_id = $existingOptions[$index];
            $update_opt = $conn->prepare("UPDATE poll_options SET option_text = ? WHERE id = ?");
            $update_opt->bind_param("si", $option_text, $opt_id);
            $update_opt->execute();
        } else {
            // Add new option
            $insert_opt = $conn->prepare("INSERT INTO poll_options (poll_id, option_text) VALUES (?, ?)");
            $insert_opt->bind_param("is", $poll_id, $option_text);
            $insert_opt->execute();
        }
    }

    header("Location: ../announcements?error=Poll+updated");
    exit;
}

header("Location: ../announcements?error=Invalid+data");
exit;
