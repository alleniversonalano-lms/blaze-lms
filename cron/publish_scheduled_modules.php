<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone to Manila
date_default_timezone_set('Asia/Manila');

// Get the document root path for Hostinger
$doc_root = realpath(dirname(dirname(__FILE__)));
if ($doc_root === false) {
    die("Could not resolve document root path");
}
define('DOC_ROOT', $doc_root);
writeLog("Document root path: " . DOC_ROOT);

// Custom error logging with directory creation
function writeLog($message, $type = 'INFO') {
    $logDir = DOC_ROOT . '/logs/cron';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/module_publisher_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $message = "[$timestamp] [$type] $message" . PHP_EOL;
    
    // Only echo if running from CLI
    if (php_sapi_name() === 'cli') {
        echo $message;
    }
    
    // Write to log file
    file_put_contents($logFile, $message, FILE_APPEND);
}

try {
    // Include database connection
    require_once(DOC_ROOT . '/connect/db.php');
    
    writeLog("Starting module publisher");
    writeLog("PHP Version: " . PHP_VERSION);
    writeLog("Script Path: " . __FILE__);
    
    // Get current time using Manila timezone
    $timeZone = new DateTimeZone('Asia/Manila');
    $now = new DateTime('now', $timeZone);
    $currentTime = $now->format('Y-m-d H:i:s');
    writeLog("Current Server Time: $currentTime");

    try {
        // First, get the modules that need to be published
        $select_query = "SELECT id, module_number, title, scheduled_at 
                        FROM modules 
                        WHERE scheduled_at <= ? 
                        AND is_published = 0 
                        AND notification_sent = 0
                        AND scheduled_at IS NOT NULL 
                        AND scheduled_at != ''
                        AND TRIM(scheduled_at) != '0000-00-00 00:00:00'";

        writeLog("Executing query to find modules to publish");
        $select_stmt = $conn->prepare($select_query);
        if (!$select_stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $select_stmt->bind_param("s", $currentTime);
        $select_stmt->execute();
        $result = $select_stmt->get_result();

        $modules_to_publish = [];
        while ($row = $result->fetch_assoc()) {
            $modules_to_publish[] = $row;
            writeLog("Found module to publish: {$row['module_number']} - {$row['title']} (Scheduled: {$row['scheduled_at']})");
        }

        writeLog("Found " . count($modules_to_publish) . " modules to publish");

        if (!empty($modules_to_publish)) {
            // Update the modules
            $module_ids = array_column($modules_to_publish, 'id');
            $update_query = "UPDATE modules 
                           SET is_published = 1, notification_sent = 1 
                           WHERE id IN (" . implode(',', $module_ids) . ")";
            
            writeLog("Executing update query");
            if ($conn->query($update_query)) {
                writeLog("Successfully published " . count($modules_to_publish) . " modules", 'SUCCESS');
                
                // Get module details for email notifications
                $module_details_query = "
                    SELECT m.id, m.module_number, m.title, m.description, m.course_id,
                           c.course_code, c.course_title,
                           u.first_name, u.last_name 
                    FROM modules m
                    JOIN courses c ON m.course_id = c.id
                    JOIN users u ON m.created_by = u.id
                    WHERE m.id IN (" . implode(',', $module_ids) . ")";
                
                writeLog("Executing module details query: " . $module_details_query);
                $details_result = $conn->query($module_details_query);
                
                if (!$details_result) {
                    throw new Exception("Failed to get module details: " . $conn->error);
                }
                
                $notification_file = DOC_ROOT . '/email/notification_sender.php';
                writeLog("Loading notification sender from: " . $notification_file);
                
                if (!file_exists($notification_file)) {
                    writeLog("Notification sender file not found at: " . $notification_file, 'ERROR');
                    throw new Exception("Notification sender file not found");
                }
                
                try {
                    require_once($notification_file);
                    writeLog("Successfully loaded notification sender", 'INFO');
                    
                    // Verify the required functions exist
                    if (!function_exists('notifyEnrolledUsers')) {
                        throw new Exception("notifyEnrolledUsers function not found");
                    }
                    if (!function_exists('sendNotificationEmail')) {
                        throw new Exception("sendNotificationEmail function not found");
                    }
                    writeLog("Required notification functions verified", 'INFO');
                } catch (Exception $e) {
                    writeLog("Error loading notification sender: " . $e->getMessage(), 'ERROR');
                    throw $e;
                }
                
                if ($details_result->num_rows === 0) {
                    writeLog("No module details found for IDs: " . implode(',', $module_ids), 'WARNING');
                }
                
                while ($module = $details_result->fetch_assoc()) {
                    writeLog(sprintf("Processing notifications for Module %s: %s (Course: %s)", 
                        $module['module_number'],
                        $module['title'],
                        $module['course_code'] ?? 'Unknown'
                    ), 'INFO');
                    
                    // Send email notification for each published module
                    $emailSubject = "New Module Published in {$module['course_code']}";
                    $emailContent = "
                        <p>{$module['first_name']} {$module['last_name']} has published a new module:</p>
                        <div style='margin: 20px 0; padding: 15px; background: #f5f5f5; border-radius: 5px;'>
                            <h3 style='margin-top: 0;'>Module {$module['module_number']}: {$module['title']}</h3>
                            <p>" . nl2br(htmlspecialchars($module['description'])) . "</p>
                        </div>
                        <p>Log in to BLAZE to view the complete module content.</p>
                    ";
                    
                    try {
                        // Debug module data
                        writeLog("Module data: " . json_encode([
                            'module_id' => $module['id'],
                            'course_id' => $module['course_id'],
                            'course_code' => $module['course_code'],
                            'course_title' => $module['course_title'],
                            'teacher' => $module['first_name'] . ' ' . $module['last_name']
                        ], JSON_PRETTY_PRINT), 'DEBUG');
                        
                        // Get enrolled users count first
                        $enrolled_query = "
                            SELECT COUNT(*) as count 
                            FROM course_enrollments 
                            WHERE course_id = ?";
                        $enrolled_stmt = $conn->prepare($enrolled_query);
                        
                        if (!isset($module['course_id'])) {
                            writeLog("course_id is missing from module data!", 'ERROR');
                            throw new Exception("course_id is missing");
                        }
                        
                        writeLog("Checking enrollments for course_id: " . $module['course_id'], 'DEBUG');
                        $enrolled_stmt->bind_param("i", $module['course_id']);
                        $enrolled_stmt->execute();
                        $enrolled_result = $enrolled_stmt->get_result();
                        $enrolled_count = $enrolled_result->fetch_assoc()['count'];
                        
                        writeLog("Found {$enrolled_count} enrolled users for course {$module['course_code']}", 'INFO');
                        
                        if ($enrolled_count > 0) {
                            writeLog("Attempting to send notifications with data:", 'DEBUG');
                            writeLog("course_id: " . $module['course_id'], 'DEBUG');
                            writeLog("emailSubject: " . $emailSubject, 'DEBUG');
                            writeLog("course_code: " . $module['course_code'], 'DEBUG');
                            writeLog("course_title: " . $module['course_title'], 'DEBUG');
                            
                            try {
                                $success = notifyFromCron(
                                    $module['course_id'], 
                                    $emailSubject, 
                                    $emailContent, 
                                    $module['course_code'], 
                                    $module['course_title'],
                                    'writeLog' // Pass our logging function
                                );
                                
                                if ($success) {
                                    writeLog("Email notifications completed for Module {$module['module_number']}", 'SUCCESS');
                                    // Update notification status separately in case the first update didn't complete
                                    $notify_update = $conn->prepare("UPDATE modules SET notification_sent = 1 WHERE id = ?");
                                    $notify_update->bind_param("i", $module['id']);
                                    $notify_update->execute();
                                    $notify_update->close();
                                } else {
                                    writeLog("Some notifications failed for Module {$module['module_number']} - check detailed logs above", 'WARNING');
                                    // If notifications fail, we'll leave notification_sent as 0 to retry next time
                                }
                            } catch (Exception $e) {
                                writeLog("Exception in notifyEnrolledUsers: " . $e->getMessage(), 'ERROR');
                                writeLog("Parameters passed: " . json_encode([
                                    'course_id' => $module['course_id'],
                                    'subject' => $emailSubject,
                                    'course_code' => $module['course_code'],
                                    'course_title' => $module['course_title']
                                ]), 'DEBUG');
                                throw $e;
                            }
                        } else {
                            writeLog("No enrolled users found for course {$module['course_code']}, skipping notifications", 'INFO');
                        }
                        
                    } catch (Exception $e) {
                        writeLog("Failed to send email notifications for Module {$module['module_number']}: " . $e->getMessage(), 'WARNING');
                        writeLog("Stack trace: " . $e->getTraceAsString(), 'DEBUG');
                        // Continue with other modules even if email fails for one
                        continue;
                    }
                }
            } else {
                throw new Exception("Update failed: " . $conn->error);
            }
        } else {
            writeLog("No modules found to publish at this time");
        }

        // Close statements
        $select_stmt->close();
        
    } catch (Exception $e) {
        writeLog("Error while publishing modules: " . $e->getMessage(), 'ERROR');
    }

    // Close database connection
    $conn->close();
    writeLog("=== Module Publisher Finished ===");

} catch (Exception $e) {
    writeLog("Fatal error: " . $e->getMessage(), 'FATAL');
}
