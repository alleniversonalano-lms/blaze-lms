<?php
// Start session to get user info
session_start();
header('Content-Type: application/json');

// Validate session
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'teacher') {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    // Get and validate JSON data from POST request
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data || !isset($data['questions'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid data format']);
        exit;
    }

    $questions = $data['questions'] ?? [];
    $categories = $data['categories'] ?? [];
    $deleteCategory = $data['deleteCategory'] ?? null;
    
    // Load existing question bank or create new one
    $questionBankPath = __DIR__ . '/question_bank.json';
    $existingData = ['categories' => [], 'questions' => []];
    
    if (file_exists($questionBankPath)) {
        $existingContent = file_get_contents($questionBankPath);
        if ($existingContent) {
            $existingData = json_decode($existingContent, true) ?: ['categories' => [], 'questions' => []];
        }
    }
    
    // Handle category deletion if specified
    if ($deleteCategory !== null) {
        error_log("Deleting category: " . $deleteCategory);
        
        // Remove the category from the categories list
        $categories = array_values(array_filter($existingData['categories'], function($cat) use ($deleteCategory) {
            $keep = isset($cat['id']) && $cat['id'] !== $deleteCategory;
            error_log("Category " . $cat['id'] . ": " . ($keep ? "keeping" : "removing"));
            return $keep;
        }));
        
        // Update questions to set their category to 'uncategorized'
        $questions = array_map(function($question) use ($deleteCategory) {
            if (isset($question['categoryId']) && $question['categoryId'] === $deleteCategory) {
                $question['categoryId'] = 'uncategorized';
                error_log("Updated question " . $question['id'] . " to uncategorized");
            }
            return $question;
        }, $existingData['questions']);
        
        error_log("Categories after deletion: " . json_encode($categories));
        error_log("Questions after update: " . count($questions));
    } else {
        // Merge new data with existing data if not deleting
        if (empty($categories)) {
            $categories = $existingData['categories'];
        } else {
            // Ensure all categories have required fields
            foreach ($categories as &$category) {
                if (!isset($category['id'])) {
                    $category['id'] = 'cat_' . uniqid();
                }
            }
        }
        if (empty($questions)) {
            $questions = $existingData['questions'];
        }
    }
    
    // When deleting category, use the filtered data directly
    if ($deleteCategory !== null) {
        $result = file_put_contents(
            $questionBankPath, 
            json_encode([
                'categories' => $categories,
                'questions' => $questions
            ], JSON_PRETTY_PRINT)
        );
    } else {
        // For other operations, merge categories and questions properly
        
        // First handle categories
        $mergedCategories = $categories;
        foreach ($existingData['categories'] as $existingCat) {
            $found = false;
            foreach ($categories as $newCat) {
                if ($existingCat['id'] === $newCat['id']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $mergedCategories[] = $existingCat;
            }
        }

        // Handle questions
        $mergedQuestions = [];
        
        // Create a map of new questions by ID for quick lookup
        $newQuestionsMap = [];
        foreach ($questions as $question) {
            if (isset($question['id'])) {
                $newQuestionsMap[$question['id']] = $question;
            }
        }
        
        // First add all existing questions, but update them if they exist in new questions
        foreach ($existingData['questions'] as $question) {
            if (isset($question['id'])) {
                if (isset($newQuestionsMap[$question['id']])) {
                    // Use the updated question data
                    $mergedQuestions[$question['id']] = $newQuestionsMap[$question['id']];
                } else {
                    // Keep existing question
                    $mergedQuestions[$question['id']] = $question;
                }
            }
        }
        
        // Add any new questions that weren't in existing data
        foreach ($questions as $question) {
            if (isset($question['id']) && !isset($mergedQuestions[$question['id']])) {
                $mergedQuestions[$question['id']] = $question;
            }
        }

        $result = file_put_contents(
            $questionBankPath, 
            json_encode([
                'categories' => array_values($mergedCategories),
                'questions' => array_values($mergedQuestions)
            ], JSON_PRETTY_PRINT)
        );
    }
    
    if ($result === false) {
        throw new Exception('Failed to write to question bank file');
    }
    
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}