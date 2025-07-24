<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Check if user is logged in and is admin
require_login();
require_admin();

// Set JSON content type
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['course_id']) || !isset($input['order']) || !is_array($input['order'])) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid input data']);
    exit;
}

$course_id = filter_var($input['course_id'], FILTER_VALIDATE_INT);

if (!$course_id) {
    http_response_code(400); // Bad Request
    echo json_encode(['success' => false, 'message' => 'Invalid course ID']);
    exit;
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Prepare the update statement
    $stmt = $conn->prepare("UPDATE lessons SET sort_order = ? WHERE id = ? AND course_id = ?");
    
    // Update each lesson's sort order
    foreach ($input['order'] as $item) {
        if (!isset($item['id']) || !isset($item['order'])) {
            continue;
        }
        
        $lesson_id = filter_var($item['id'], FILTER_VALIDATE_INT);
        $sort_order = filter_var($item['order'], FILTER_VALIDATE_INT);
        
        if ($lesson_id && $sort_order !== false) {
            $stmt->bind_param('iii', $sort_order, $lesson_id, $course_id);
            $stmt->execute();
        }
    }
    
    // Log the activity
    log_activity($_SESSION['user_id'], 'lessons_reordered', "Course ID: $course_id - Lessons reordered");
    
    // Commit transaction
    $conn->commit();
    
    // Return success response
    echo json_encode([
        'success' => true, 
        'message' => 'Lesson order updated successfully'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if (isset($conn)) {
        $conn->rollback();
    }
    
    // Log the error
    error_log('Error reordering lessons: ' . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
