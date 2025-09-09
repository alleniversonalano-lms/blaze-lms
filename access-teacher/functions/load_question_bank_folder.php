<?php
header('Content-Type: application/json');
require_once($_SERVER['DOCUMENT_ROOT'] . '/connect/db.php');

// Validate course_id
$course_id = isset($_GET['course_id']) ? intval($_GET['course_id']) : 0;
if ($course_id <= 0) {
    echo json_encode(['error' => 'Invalid course_id']);
    exit;
}

// Fetch folders for the given course_id
$folders = [];
$folder_stmt = $conn->prepare("SELECT id, name, parent_id FROM question_folders WHERE course_id = ? ORDER BY name ASC");
$folder_stmt->bind_param("i", $course_id);
$folder_stmt->execute();
$folder_result = $folder_stmt->get_result();

while ($row = $folder_result->fetch_assoc()) {
    $folders[$row['id']] = $row + ['subfolders' => []];
}

// Nest folders
$rootFolders = [];
foreach ($folders as $id => &$folder) {
    if ($folder['parent_id'] && isset($folders[$folder['parent_id']])) {
        $folders[$folder['parent_id']]['subfolders'][] = &$folder;
    } else {
        $rootFolders[] = &$folder;
    }
}
unset($folder); // prevent reference issues

echo json_encode($rootFolders);
