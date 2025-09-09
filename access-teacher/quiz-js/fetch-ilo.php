<?php
session_start();
include($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

header('Content-Type: application/json'); // Set header before any output

$course_id = $_GET['course_id'] ?? 0;
$ilos = [];

if ($course_id) {
    $stmt = $conn->prepare("SELECT id, ilo_number FROM course_ilos WHERE course_id = ?");
    $stmt->bind_param("i", $course_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $ilos[] = [
            'id' => $row['id'],
            'name' => $row['ilo_number'] ?? ('ILO ' . $row['id'])
        ];
    }
    $stmt->close();
}

echo json_encode($ilos);
