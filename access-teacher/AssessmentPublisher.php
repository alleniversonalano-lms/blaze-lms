<?php
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set timezone to Manila
date_default_timezone_set('Asia/Manila');

// Get the document root path for Hostinger
define('DOC_ROOT', dirname(dirname(__FILE__))); 

// Custom error logging with directory creation
function writeLog($message, $type = 'INFO') {
    $logDir = DOC_ROOT . '/logs/cron';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/assessment_publisher_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $message = "[$timestamp] [$type] $message" . PHP_EOL;
    
    // Only echo if running from CLI
    if (php_sapi_name() === 'cli') {
        echo $message;
    }
    
    // Write to log file
    file_put_contents($logFile, $message, FILE_APPEND);
}

function checkAndPublishAssessments() {
    try {
        // Get database connection
        require_once(DOC_ROOT . '/connect/db.php');
        
        writeLog("Starting assessment publisher");
        writeLog("PHP Version: " . PHP_VERSION);
        writeLog("Script Path: " . __FILE__);
        
        // Get current time using server default timezone
        $now = new DateTime('now');
        $currentTime = $now->format('Y-m-d H:i:s');
        writeLog("Current Server Time: $currentTime");

        // Get assessments path
        $assessmentsPath = DOC_ROOT . '/assessment-list/quizzes';
        writeLog("Assessments Path: " . $assessmentsPath);

        if (!is_dir($assessmentsPath)) {
            writeLog("Directory not found: " . $assessmentsPath, 'ERROR');
            return;
        }

        $files = glob($assessmentsPath . '/*.json');
        writeLog("Found " . count($files) . " JSON files");

        foreach($files as $file) {
            try {
                writeLog("Processing: " . basename($file));

                if (!is_readable($file)) {
                    writeLog("File not readable: " . basename($file), 'ERROR');
                    continue;
                }

                $content = file_get_contents($file);
                if ($content === false) {
                    writeLog("Could not read file: " . basename($file), 'ERROR');
                    continue; 
                }

                $quizData = json_decode($content, true);
                if (!$quizData) {
                    writeLog("Invalid JSON in file: " . basename($file), 'ERROR');
                    continue;
                }

                // Debug log the quiz data
                writeLog("Quiz Details for " . basename($file) . ":");
                writeLog("- Title: " . ($quizData['title'] ?? 'Not set'));
                writeLog("- Is Published: " . ($quizData['is_published'] ?? 'Not set'));
                writeLog("- Available From Date: " . ($quizData['options']['availableFromDate'] ?? 'Not set'));
                writeLog("- Available From Time: " . ($quizData['options']['availableFromTime'] ?? 'Not set'));

                if (isset($quizData['is_published']) && 
                    (int)$quizData['is_published'] === 0 && 
                    isset($quizData['options']['availableFromDate']) && 
                    isset($quizData['options']['availableFromTime']) &&
                    !empty(trim($quizData['options']['availableFromDate'])) && 
                    !empty(trim($quizData['options']['availableFromTime']))) {
                    
                    // Skip if date/time is not valid
                    try {
                        $testDate = DateTime::createFromFormat('Y-m-d H:i', 
                            $quizData['options']['availableFromDate'] . ' ' . 
                            $quizData['options']['availableFromTime']);
                        if (!$testDate) {
                            writeLog("Invalid date/time format: " . basename($file), 'WARNING');
                            continue;
                        }
                    } catch (Exception $e) {
                        writeLog("Invalid date/time values: " . basename($file), 'WARNING');
                        continue;
                    }
                    
                    $startDateTime = $quizData['options']['availableFromDate'] . ' ' . $quizData['options']['availableFromTime'];
                    // Use Manila timezone
                    $timeZone = new DateTimeZone('Asia/Manila');
                    $startDate = new DateTime($startDateTime, $timeZone);
                    $now = new DateTime('now', $timeZone);

                    writeLog("Schedule comparison for " . basename($file) . ":");
                    writeLog("- Scheduled Time: " . $startDate->format('Y-m-d H:i:s'));
                    writeLog("- Current Time: " . $now->format('Y-m-d H:i:s'));
                    
                    if ($startDate <= $now) {
                        writeLog("Publishing quiz: " . $quizData['title'], 'SUCCESS');
                        $quizData['is_published'] = 1;
                        
                        // Add proper file locking
                        $fp = fopen($file, 'c+');
                        if (flock($fp, LOCK_EX)) {
                            ftruncate($fp, 0);
                            $jsonContent = json_encode($quizData, JSON_PRETTY_PRINT);
                            writeLog("Writing updated content to file", 'INFO');
                            fwrite($fp, $jsonContent);
                            fflush($fp);
                            flock($fp, LOCK_UN);
                            writeLog("Successfully published: " . basename($file), 'SUCCESS');
                        } else {
                            writeLog("Could not lock file: " . basename($file), 'ERROR');
                        }
                        fclose($fp);
                    } else {
                        $interval = $now->diff($startDate);
                        writeLog("Not yet time to publish: " . basename($file));
                        writeLog("Time remaining: " . $interval->format('%d days, %h hours, %i minutes'));
                    }
                }
            } catch (Exception $e) {
                writeLog("Error processing file {$file}: " . $e->getMessage(), 'ERROR');
            }
        }
    } catch (Exception $e) {
        writeLog("Critical error: " . $e->getMessage(), 'CRITICAL');
    }
}

// Run the publisher
try {
    writeLog("=== Starting Assessment Publisher ===");
    checkAndPublishAssessments();
    writeLog("=== Finished Assessment Publisher ===");
} catch (Exception $e) {
    writeLog("Fatal error: " . $e->getMessage(), 'FATAL');
}
